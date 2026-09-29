<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductServiceRequest;
use App\Http\Requests\UpdateProductServiceRequest;
use App\Models\AuditLog;
use App\Models\ClientPurchaseOrder;
use App\Models\InventoryMovement;
use App\Models\ProductImage;
use App\Models\ProductService;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProductServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ProductService::class);

        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->trim()->toString();
        $status = $request->string('status')->trim()->toString();

        return Inertia::render('product-services/Index', [
            'filters' => [
                'search' => $search,
                'type' => $type === '' ? 'all' : $type,
                'status' => $status === '' ? 'all' : $status,
            ],
            'items' => ProductService::query()
                ->select(['id', 'code', 'type', 'name', 'description', 'unit', 'default_price', 'quantity', 'status', 'public_token', 'is_public', 'created_at'])
                ->with(['primaryImage:id,product_service_id,image_path'])
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                })
                ->when(in_array($type, [ProductService::TYPE_PRODUCT, ProductService::TYPE_SERVICE], true), fn (Builder $query) => $query->where('type', $type))
                ->when(in_array($status, [ProductService::STATUS_ACTIVE, ProductService::STATUS_INACTIVE], true), fn (Builder $query) => $query->where('status', $status))
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (ProductService $productService): array => $this->productServicePayload($productService)),
            'can' => [
                'create' => $request->user()->can('create', ProductService::class),
                'edit' => $request->user()->can('products-services.edit'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        Gate::authorize('create', ProductService::class);

        return Inertia::render('product-services/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductServiceRequest $request, AuditLogger $audit): RedirectResponse
    {
        $storedPaths = [];

        try {
            $productService = DB::transaction(function () use ($request, &$storedPaths): ProductService {
                $productService = ProductService::create($this->productServiceAttributes($request->validated()));
                $storedPaths = $this->storeImages($productService, $request->file('images', []), $request->integer('primary_new_image_index'));

                return $productService;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths);

            throw $exception;
        }

        $audit->record('products-services', 'created', $productService, newValues: $productService->only($this->auditedAttributes()), request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product/service created.')]);

        return to_route('product-services.show', $productService);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, ProductService $productService): Response
    {
        Gate::authorize('view', $productService);
        $productService->load([
            'images',
            'primaryImage',
            'inventoryMovements' => fn ($query) => $query->with('reference:id,client_po_no')->limit(10),
        ]);

        return Inertia::render('product-services/Show', [
            'item' => $this->productServicePayload($productService),
            'activityLogs' => AuditLog::recentFor($productService),
            'can' => [
                'edit' => $request->user()->can('update', $productService),
            ],
        ]);
    }

    public function qrPrint(ProductService $productService): Response
    {
        Gate::authorize('view', $productService);
        abort_unless($this->isPrintableQrProduct($productService), 404);

        return Inertia::render('product-services/QrPrint', [
            'items' => [$this->qrPayload($productService)],
            'title' => $productService->code,
            'layout' => 'single',
        ]);
    }

    public function bulkQrPrint(Request $request): Response
    {
        Gate::authorize('viewAny', ProductService::class);

        $search = $request->string('search')->trim()->toString();
        $rawIds = $request->string('ids')->trim()->toString();
        $ids = collect(explode(',', $rawIds))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $items = ProductService::query()
            ->select(['id', 'code', 'name', 'type', 'status', 'is_public', 'public_token'])
            ->where('type', ProductService::TYPE_PRODUCT)
            ->where('status', ProductService::STATUS_ACTIVE)
            ->where('is_public', true)
            ->whereNotNull('public_token')
            ->when($ids !== [], fn (Builder $query) => $query->whereKey($ids))
            ->when($ids === [] && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn (ProductService $productService): array => $this->qrPayload($productService))
            ->values();

        return Inertia::render('product-services/QrPrint', [
            'items' => $items,
            'title' => 'Bulk QR Print',
            'layout' => 'bulk',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductService $productService): Response
    {
        Gate::authorize('update', $productService);
        $productService->load('images');

        return Inertia::render('product-services/Edit', [
            'item' => $this->productServicePayload($productService),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductServiceRequest $request, ProductService $productService, AuditLogger $audit): RedirectResponse
    {
        $oldValues = $productService->only($this->auditedAttributes());
        $storedPaths = [];
        $deletedPaths = [];

        try {
            DB::transaction(function () use ($request, $productService, &$storedPaths, &$deletedPaths): void {
                $productService->update($this->productServiceAttributes($request->validated()));
                $deletedPaths = $this->syncImages($productService, $request, $storedPaths);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths);

            throw $exception;
        }

        $this->deleteStoredPaths($deletedPaths);
        $audit->recordChanges('products-services', 'updated', $productService, $oldValues, $productService->getChanges(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product/service updated.')]);

        return to_route('product-services.show', $productService);
    }

    public function activate(Request $request, ProductService $productService, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $productService);
        $oldValues = $productService->only(['status']);

        $productService->update(['status' => ProductService::STATUS_ACTIVE]);
        $audit->recordChanges('products-services', 'status_changed', $productService, $oldValues, $productService->getChanges(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product/service activated.')]);

        return back();
    }

    public function deactivate(Request $request, ProductService $productService, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $productService);
        $oldValues = $productService->only(['status']);

        $productService->update(['status' => ProductService::STATUS_INACTIVE]);
        $audit->recordChanges('products-services', 'status_changed', $productService, $oldValues, $productService->getChanges(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product/service deactivated.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function productServicePayload(ProductService $productService): array
    {
        return [
            'id' => $productService->id,
            'code' => $productService->code,
            'type' => $productService->type,
            'name' => $productService->name,
            'description' => $productService->description,
            'unit' => $productService->unit,
            'default_price' => $productService->default_price,
            'quantity' => $productService->quantity,
            'status' => $productService->status,
            'is_public' => $productService->is_public,
            'public_url' => $this->publicProductUrl($productService),
            'created_at' => $productService->created_at?->toDateString(),
            'primary_image' => $productService->relationLoaded('primaryImage') && $productService->primaryImage
                ? $this->imagePayload($productService->primaryImage)
                : null,
            'images' => $productService->relationLoaded('images')
                ? $productService->images->map(fn (ProductImage $image): array => $this->imagePayload($image))->values()
                : [],
            'inventory_movements' => $productService->relationLoaded('inventoryMovements')
                ? $productService->inventoryMovements->map(fn (InventoryMovement $movement): array => [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'quantity_before' => $movement->quantity_before,
                    'quantity_after' => $movement->quantity_after,
                    'reference' => $movement->reference instanceof ClientPurchaseOrder
                        ? $movement->reference->client_po_no
                        : null,
                    'created_at' => $movement->created_at?->toDateString(),
                ])->values()
                : [],
        ];
    }

    private function isPrintableQrProduct(ProductService $productService): bool
    {
        return $productService->type === ProductService::TYPE_PRODUCT
            && $productService->status === ProductService::STATUS_ACTIVE
            && $productService->is_public
            && $productService->public_token !== null;
    }

    private function publicProductUrl(ProductService $productService): ?string
    {
        if (! $this->isPrintableQrProduct($productService)) {
            return null;
        }

        return route('public-products.show', $productService->public_token);
    }

    /**
     * @return array<string, mixed>
     */
    private function qrPayload(ProductService $productService): array
    {
        return [
            'id' => $productService->id,
            'code' => $productService->code,
            'name' => $productService->name,
            'public_url' => route('public-products.show', $productService->public_token),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function productServiceAttributes(array $validated): array
    {
        $type = $validated['type'];

        return [
            'code' => $validated['code'],
            'type' => $type,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'unit' => $validated['unit'],
            'default_price' => $validated['default_price'],
            'quantity' => $type === ProductService::TYPE_PRODUCT ? (int) ($validated['quantity'] ?? 0) : 0,
            'status' => $validated['status'],
            'is_public' => (bool) ($validated['is_public'] ?? false),
        ];
    }

    /**
     * @param  array<int, UploadedFile>  $images
     * @return array<int, string>
     */
    private function storeImages(ProductService $productService, array $images, int $primaryNewImageIndex = 0, int $startSortOrder = 0): array
    {
        $storedPaths = [];

        if (! $productService->isProduct()) {
            return $storedPaths;
        }

        foreach (array_values($images) as $index => $image) {
            $path = $image->store('product-images', 'public');
            throw_if($path === false, new \RuntimeException('Unable to store product image.'));

            $storedPaths[] = $path;
            $productService->images()->create([
                'image_path' => $path,
                'original_filename' => $this->safeOriginalFilename($image),
                'mime_type' => $image->getMimeType() ?: 'application/octet-stream',
                'file_size' => $image->getSize(),
                'sort_order' => $startSortOrder + $index,
                'is_primary' => $primaryNewImageIndex === $index,
            ]);
        }

        $this->ensureSinglePrimaryImage($productService);

        return $storedPaths;
    }

    /**
     * @param  array<int, string>  $storedPaths
     * @return array<int, string>
     */
    private function syncImages(ProductService $productService, Request $request, array &$storedPaths): array
    {
        $productService->load('images');
        $rawKeepIds = $request->input('existing_image_ids', []);
        $keepIds = array_values(array_unique(array_map(
            fn (mixed $imageId): int => (int) $imageId,
            is_array($rawKeepIds) ? $rawKeepIds : [],
        )));
        $deletedPaths = $productService->images
            ->reject(fn (ProductImage $image): bool => in_array($image->id, $keepIds, true))
            ->pluck('image_path')
            ->all();

        $productService->images()
            ->whereNotIn('id', $keepIds)
            ->delete();

        foreach ($keepIds as $sortOrder => $imageId) {
            $productService->images()
                ->whereKey($imageId)
                ->update([
                    'sort_order' => $sortOrder,
                    'is_primary' => $request->integer('primary_existing_image_id') === $imageId,
                ]);
        }

        $newImages = $request->file('images', []);
        $primaryNewImageIndex = $request->integer('primary_new_image_index', -1);
        $storedPaths = $this->storeImages($productService, $newImages, $primaryNewImageIndex, count($keepIds));
        $this->ensureSinglePrimaryImage($productService);

        return $deletedPaths;
    }

    private function ensureSinglePrimaryImage(ProductService $productService): void
    {
        $images = $productService->images()->orderBy('sort_order')->orderBy('id')->get();

        if ($images->isEmpty()) {
            return;
        }

        $primary = $images->firstWhere('is_primary', true) ?? $images->first();

        $productService->images()
            ->whereKeyNot($primary->id)
            ->update(['is_primary' => false]);
        $primary->update(['is_primary' => true]);
    }

    private function safeOriginalFilename(UploadedFile $image): ?string
    {
        $basename = pathinfo($image->getClientOriginalName(), PATHINFO_BASENAME);
        $clean = trim((string) preg_replace('/[^\w.\- ()]/', '_', $basename));

        return $clean === '' ? null : $clean;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteStoredPaths(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function imagePayload(ProductImage $image): array
    {
        return [
            'id' => $image->id,
            'url' => Storage::disk('public')->url($image->image_path),
            'original_filename' => $image->original_filename,
            'sort_order' => $image->sort_order,
            'is_primary' => $image->is_primary,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function auditedAttributes(): array
    {
        return ['code', 'type', 'name', 'description', 'unit', 'default_price', 'quantity', 'status', 'is_public'];
    }
}
