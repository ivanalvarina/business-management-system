<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductServiceRequest;
use App\Http\Requests\UpdateProductServiceRequest;
use App\Models\ProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

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
                ->select(['id', 'code', 'type', 'name', 'unit', 'default_price', 'status', 'created_at'])
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
    public function store(StoreProductServiceRequest $request): RedirectResponse
    {
        $productService = ProductService::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product/service created.')]);

        return to_route('product-services.show', $productService);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, ProductService $productService): Response
    {
        Gate::authorize('view', $productService);

        return Inertia::render('product-services/Show', [
            'item' => $this->productServicePayload($productService),
            'can' => [
                'edit' => $request->user()->can('update', $productService),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductService $productService): Response
    {
        Gate::authorize('update', $productService);

        return Inertia::render('product-services/Edit', [
            'item' => $this->productServicePayload($productService),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductServiceRequest $request, ProductService $productService): RedirectResponse
    {
        $productService->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product/service updated.')]);

        return to_route('product-services.show', $productService);
    }

    public function activate(ProductService $productService): RedirectResponse
    {
        Gate::authorize('update', $productService);

        $productService->update(['status' => ProductService::STATUS_ACTIVE]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product/service activated.')]);

        return back();
    }

    public function deactivate(ProductService $productService): RedirectResponse
    {
        Gate::authorize('update', $productService);

        $productService->update(['status' => ProductService::STATUS_INACTIVE]);

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
            'status' => $productService->status,
            'created_at' => $productService->created_at?->toDateString(),
        ];
    }
}
