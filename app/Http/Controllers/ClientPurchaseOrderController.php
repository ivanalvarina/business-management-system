<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientPurchaseOrderRequest;
use App\Http\Requests\UpdateClientPurchaseOrderRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientPurchaseOrder;
use App\Models\ClientPurchaseOrderItem;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClientPurchaseOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ClientPurchaseOrder::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $companyId = $request->integer('company_id');
        $clientId = $request->integer('client_id');
        $user = $request->user();

        if ($companyId > 0 && ! $this->userCanAccessCompanyId($user, $companyId)) {
            abort(403);
        }

        return Inertia::render('client-pos/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'all' : $status,
                'company_id' => $companyId > 0 ? $companyId : null,
                'client_id' => $clientId > 0 ? $clientId : null,
            ],
            'companies' => $this->companyOptions($user),
            'clients' => $this->clientOptions($user),
            'statuses' => ClientPurchaseOrder::statuses(),
            'clientPurchaseOrders' => ClientPurchaseOrder::query()
                ->select(['id', 'company_id', 'client_id', 'quotation_id', 'client_po_no', 'po_date', 'currency', 'amount', 'status', 'received_at'])
                ->with(['company:id,company_code,company_name', 'client:id,client_code,client_name', 'quotations:id,quotation_no'])
                ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                    'company.users',
                    fn (Builder $query) => $query->whereKey($user->id),
                ))
                ->when($companyId > 0, fn (Builder $query) => $query->where('company_id', $companyId))
                ->when($clientId > 0, fn (Builder $query) => $query->where('client_id', $clientId))
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('client_po_no', 'like', "%{$search}%")
                            ->orWhereHas('client', fn (Builder $query) => $query
                                ->where('client_code', 'like', "%{$search}%")
                                ->orWhere('client_name', 'like', "%{$search}%"))
                            ->orWhereHas('quotations', fn (Builder $query) => $query
                                ->where('quotation_no', 'like', "%{$search}%"));
                    });
                })
                ->when(in_array($status, ClientPurchaseOrder::statuses(), true), fn (Builder $query) => $query->where('status', $status))
                ->latest('po_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (ClientPurchaseOrder $clientPurchaseOrder): array => $this->summaryPayload($clientPurchaseOrder)),
            'can' => [
                'create' => $user->can('create', ClientPurchaseOrder::class),
                'edit' => $user->can('client-pos.edit'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', ClientPurchaseOrder::class);

        return Inertia::render('client-pos/Create', $this->formOptions($request->user()));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClientPurchaseOrderRequest $request, AuditLogger $audit): RedirectResponse
    {
        $clientPurchaseOrder = DB::transaction(function () use ($request): ClientPurchaseOrder {
            $clientPurchaseOrder = ClientPurchaseOrder::create([
                ...$this->attributes($request->validated()),
                'received_by' => $request->user()->id,
                'received_at' => $request->validated('received_at') ?? now(),
            ]);
            $clientPurchaseOrder->quotations()->sync($request->validated('quotation_ids') ?? []);
            $this->syncItems($clientPurchaseOrder, $request->validated('items'));

            return $clientPurchaseOrder->fresh();
        });
        $audit->record('client-pos', 'created', $clientPurchaseOrder, newValues: $clientPurchaseOrder->only($this->auditedAttributes()), request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client purchase order recorded.')]);

        return to_route('client-pos.show', $clientPurchaseOrder);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, ClientPurchaseOrder $clientPurchaseOrder): Response
    {
        Gate::authorize('view', $clientPurchaseOrder);

        $clientPurchaseOrder->load([
            'company:id,company_code,company_name,email,phone,address',
            'client:id,client_code,client_name,email,phone,billing_address,shipping_address',
            'quotations:id,quotation_no,quotation_date,status,total_amount,currency',
            'items.productService.primaryImage:id,product_service_id,image_path',
            'receiver:id,name',
            'documents.uploader:id,name',
        ]);

        return Inertia::render('client-pos/Show', [
            'clientPurchaseOrder' => $this->payload($clientPurchaseOrder),
            'activityLogs' => AuditLog::recentFor($clientPurchaseOrder),
            'can' => [
                'edit' => $request->user()->can('update', $clientPurchaseOrder),
                'delete' => $request->user()->can('delete', $clientPurchaseOrder),
                'process' => $request->user()->can('update', $clientPurchaseOrder) && $clientPurchaseOrder->canTransitionTo(ClientPurchaseOrder::STATUS_PROCESSING),
                'fulfill' => $request->user()->can('fulfill', $clientPurchaseOrder) && $clientPurchaseOrder->canTransitionTo(ClientPurchaseOrder::STATUS_FULFILLED),
                'complete' => $request->user()->can('update', $clientPurchaseOrder) && $clientPurchaseOrder->canTransitionTo(ClientPurchaseOrder::STATUS_COMPLETED),
                'cancel' => $request->user()->can('cancel', $clientPurchaseOrder) && $clientPurchaseOrder->canTransitionTo(ClientPurchaseOrder::STATUS_CANCELLED),
                'uploadDocuments' => $request->user()->can('documents.upload'),
                'downloadDocuments' => $request->user()->can('documents.download'),
                'deleteDocuments' => $request->user()->can('documents.delete'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, ClientPurchaseOrder $clientPurchaseOrder): Response
    {
        Gate::authorize('update', $clientPurchaseOrder);
        $clientPurchaseOrder->load(['items', 'quotations:id,quotation_no']);

        return Inertia::render('client-pos/Edit', [
            'clientPurchaseOrder' => $this->formPayload($clientPurchaseOrder),
            ...$this->formOptions($request->user()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientPurchaseOrderRequest $request, ClientPurchaseOrder $clientPurchaseOrder, AuditLogger $audit): RedirectResponse
    {
        $oldValues = $clientPurchaseOrder->only($this->auditedAttributes());
        DB::transaction(function () use ($request, $clientPurchaseOrder): void {
            $clientPurchaseOrder->update($this->attributes($request->validated()));
            $clientPurchaseOrder->quotations()->sync($request->validated('quotation_ids') ?? []);
            $this->syncItems($clientPurchaseOrder, $request->validated('items'));
        });
        $audit->recordChanges('client-pos', 'updated', $clientPurchaseOrder, $oldValues, $clientPurchaseOrder->getChanges(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client purchase order updated.')]);

        return to_route('client-pos.show', $clientPurchaseOrder);
    }

    public function process(Request $request, ClientPurchaseOrder $clientPurchaseOrder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $clientPurchaseOrder);
        $this->transition($request, $clientPurchaseOrder, ClientPurchaseOrder::STATUS_PROCESSING, $audit, 'processing');

        return back();
    }

    public function fulfill(Request $request, ClientPurchaseOrder $clientPurchaseOrder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('fulfill', $clientPurchaseOrder);
        $oldValues = $clientPurchaseOrder->only(['status', 'fulfilled_at']);

        DB::transaction(function () use ($request, $clientPurchaseOrder): void {
            /** @var ClientPurchaseOrder $lockedClientPurchaseOrder */
            $lockedClientPurchaseOrder = ClientPurchaseOrder::query()
                ->whereKey($clientPurchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedClientPurchaseOrder->canTransitionTo(ClientPurchaseOrder::STATUS_FULFILLED)) {
                throw ValidationException::withMessages(['status' => __('This client purchase order cannot be fulfilled from its current status.')]);
            }

            if ($lockedClientPurchaseOrder->fulfilled_at !== null || $lockedClientPurchaseOrder->inventoryMovements()->where('type', InventoryMovement::TYPE_OUT)->exists()) {
                return;
            }

            $items = $lockedClientPurchaseOrder->items()
                ->with('productService:id,type,name,quantity')
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('A client purchase order must have items before fulfillment.')]);
            }

            $productQuantities = $items
                ->filter(fn (ClientPurchaseOrderItem $item): bool => $item->productService?->type === ProductService::TYPE_PRODUCT)
                ->groupBy('product_service_id')
                ->map(fn ($items): int => (int) ceil($items->sum(fn (ClientPurchaseOrderItem $item): float => (float) $item->quantity)));

            $products = ProductService::query()
                ->whereIn('id', $productQuantities->keys()->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($productQuantities as $productId => $requiredQuantity) {
                $product = $products->get($productId);

                if (! $product || $product->quantity < $requiredQuantity) {
                    throw ValidationException::withMessages([
                        'status' => __('Insufficient stock for :product. Available: :available, Required: :required.', [
                            'product' => $product ? $product->name : __('Unknown product'),
                            'available' => $product ? $product->quantity : 0,
                            'required' => $requiredQuantity,
                        ]),
                    ]);
                }
            }

            foreach ($productQuantities as $productId => $requiredQuantity) {
                /** @var ProductService $product */
                $product = $products->get($productId);
                $before = $product->quantity;
                $after = $before - $requiredQuantity;
                $product->update(['quantity' => $after]);

                InventoryMovement::create([
                    'company_id' => $lockedClientPurchaseOrder->company_id,
                    'product_id' => $product->id,
                    'type' => InventoryMovement::TYPE_OUT,
                    'quantity' => $requiredQuantity,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reference_type' => ClientPurchaseOrder::class,
                    'reference_id' => $lockedClientPurchaseOrder->id,
                    'performed_by' => $request->user()->id,
                    'notes' => "Client PO {$lockedClientPurchaseOrder->client_po_no} fulfilled.",
                ]);
            }

            $lockedClientPurchaseOrder->update([
                'status' => ClientPurchaseOrder::STATUS_FULFILLED,
                'fulfilled_at' => now(),
            ]);
        });

        $clientPurchaseOrder->refresh();
        $audit->recordChanges('client-pos', 'fulfilled', $clientPurchaseOrder, $oldValues, $clientPurchaseOrder->only(['status', 'fulfilled_at']), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client purchase order fulfilled and inventory deducted.')]);

        return back();
    }

    public function complete(Request $request, ClientPurchaseOrder $clientPurchaseOrder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $clientPurchaseOrder);
        $this->transition($request, $clientPurchaseOrder, ClientPurchaseOrder::STATUS_COMPLETED, $audit, 'completed');

        return back();
    }

    public function cancel(Request $request, ClientPurchaseOrder $clientPurchaseOrder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('cancel', $clientPurchaseOrder);
        $this->transition($request, $clientPurchaseOrder, ClientPurchaseOrder::STATUS_CANCELLED, $audit, 'cancelled', ['cancelled_at' => now()]);

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, ClientPurchaseOrder $clientPurchaseOrder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $clientPurchaseOrder);
        $oldValues = $clientPurchaseOrder->only($this->auditedAttributes());

        $clientPurchaseOrder->delete();
        $audit->record('client-pos', 'deleted', $clientPurchaseOrder, oldValues: $oldValues, request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client purchase order deleted.')]);

        return to_route('client-pos.index');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'company_id' => $validated['company_id'],
            'client_id' => $validated['client_id'],
            'quotation_id' => null,
            'client_po_no' => $validated['client_po_no'],
            'po_date' => $validated['po_date'],
            'currency' => strtoupper($validated['currency']),
            'amount' => '0.00',
            'status' => in_array($validated['status'], [ClientPurchaseOrder::STATUS_FULFILLED, ClientPurchaseOrder::STATUS_COMPLETED], true)
                ? ClientPurchaseOrder::STATUS_RECEIVED
                : $validated['status'],
            'received_at' => $validated['received_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        return [
            'companies' => $this->companyOptions($user),
            'clients' => $this->clientOptions($user),
            'quotations' => $this->quotationOptions($user),
            'statuses' => ClientPurchaseOrder::statuses(),
            'productServices' => $this->productServiceOptions(),
        ];
    }

    /**
     * @return array<int, array{id: int, company_code: string, company_name: string}>
     */
    private function companyOptions(User $user): array
    {
        return Company::query()
            ->select(['id', 'company_code', 'company_name'])
            ->active()
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                'users',
                fn (Builder $query) => $query->whereKey($user->id),
            ))
            ->orderBy('company_name')
            ->get()
            ->map(fn (Company $company): array => [
                'id' => (int) $company->id,
                'company_code' => $company->company_code,
                'company_name' => $company->company_name,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function clientOptions(User $user): array
    {
        return Client::query()
            ->select(['id', 'client_code', 'client_name'])
            ->where('clients.status', Client::STATUS_ACTIVE)
            ->with(['companies' => fn ($query) => $query
                ->select(['companies.id'])
                ->where('companies.status', Company::STATUS_ACTIVE)
                ->where('company_client.status', Company::STATUS_ACTIVE)])
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                'companies.users',
                fn (Builder $query) => $query->whereKey($user->id),
            ))
            ->whereHas('companies', fn (Builder $query) => $query
                ->where('companies.status', Company::STATUS_ACTIVE)
                ->where('company_client.status', Company::STATUS_ACTIVE))
            ->orderBy('client_name')
            ->get()
            ->map(fn (Client $client): array => [
                'id' => (int) $client->id,
                'client_code' => $client->client_code,
                'client_name' => $client->client_name,
                'company_ids' => $client->companies->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function quotationOptions(User $user): array
    {
        return Quotation::query()
            ->select(['id', 'quotation_no', 'company_id', 'client_id', 'quotation_date', 'currency', 'total_amount', 'status'])
            ->with('items:id,quotation_id,product_service_id,description,quantity,unit,unit_price,discount,tax,line_total,sort_order')
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                'company.users',
                fn (Builder $query) => $query->whereKey($user->id),
            ))
            ->where('status', Quotation::STATUS_APPROVED)
            ->latest('quotation_date')
            ->latest('id')
            ->get()
            ->map(fn (Quotation $quotation): array => [
                'id' => (int) $quotation->id,
                'quotation_no' => $quotation->quotation_no,
                'company_id' => (int) $quotation->company_id,
                'client_id' => (int) $quotation->client_id,
                'quotation_date' => $quotation->quotation_date->toDateString(),
                'currency' => $quotation->currency,
                'total_amount' => $quotation->total_amount,
                'status' => $quotation->status,
                'items' => $quotation->items->map(fn (QuotationItem $item): array => [
                    'id' => (int) $item->id,
                    'product_service_id' => $item->product_service_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'tax' => $item->tax,
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productServiceOptions(): array
    {
        return ProductService::query()
            ->select(['id', 'code', 'type', 'name', 'unit', 'default_price', 'quantity', 'status'])
            ->with('primaryImage:id,product_service_id,image_path')
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (ProductService $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'type' => $item->type,
                'name' => $item->name,
                'unit' => $item->unit,
                'default_price' => $item->default_price,
                'quantity' => $item->quantity,
                'primary_image' => $item->primaryImage ? [
                    'url' => Storage::disk('public')->url($item->primaryImage->image_path),
                ] : null,
            ])
            ->all();
    }

    private function userCanAccessCompanyId(User $user, int $companyId): bool
    {
        if ($user->hasGlobalCompanyAccess()) {
            return Company::query()->whereKey($companyId)->exists();
        }

        return Company::query()
            ->whereKey($companyId)
            ->whereHas('users', fn (Builder $query) => $query->whereKey($user->id))
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(ClientPurchaseOrder $clientPurchaseOrder): array
    {
        return [
            'id' => $clientPurchaseOrder->id,
            'client_po_no' => $clientPurchaseOrder->client_po_no,
            'po_date' => $clientPurchaseOrder->po_date->toDateString(),
            'currency' => $clientPurchaseOrder->currency,
            'amount' => $clientPurchaseOrder->amount,
            'status' => $clientPurchaseOrder->status,
            'received_at' => $clientPurchaseOrder->received_at?->toDateString(),
            'company' => [
                'id' => $clientPurchaseOrder->company?->id,
                'company_code' => $clientPurchaseOrder->company?->company_code,
                'company_name' => $clientPurchaseOrder->company?->company_name,
            ],
            'client' => [
                'id' => $clientPurchaseOrder->client?->id,
                'client_code' => $clientPurchaseOrder->client?->client_code,
                'client_name' => $clientPurchaseOrder->client?->client_name,
            ],
            'quotations' => $clientPurchaseOrder->relationLoaded('quotations')
                ? $clientPurchaseOrder->quotations->map(fn (Quotation $quotation): array => [
                    'id' => $quotation->id,
                    'quotation_no' => $quotation->quotation_no,
                ])->values()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ClientPurchaseOrder $clientPurchaseOrder): array
    {
        return [
            ...$this->summaryPayload($clientPurchaseOrder),
            'notes' => $clientPurchaseOrder->notes,
            'received_at' => $clientPurchaseOrder->received_at?->toDateTimeString(),
            'created_at' => $clientPurchaseOrder->created_at?->toDateString(),
            'company' => $clientPurchaseOrder->company ? [
                'id' => $clientPurchaseOrder->company->id,
                'company_code' => $clientPurchaseOrder->company->company_code,
                'company_name' => $clientPurchaseOrder->company->company_name,
                'email' => $clientPurchaseOrder->company->email,
                'phone' => $clientPurchaseOrder->company->phone,
                'address' => $clientPurchaseOrder->company->address,
            ] : null,
            'client' => $clientPurchaseOrder->client ? [
                'id' => $clientPurchaseOrder->client->id,
                'client_code' => $clientPurchaseOrder->client->client_code,
                'client_name' => $clientPurchaseOrder->client->client_name,
                'email' => $clientPurchaseOrder->client->email,
                'phone' => $clientPurchaseOrder->client->phone,
                'billing_address' => $clientPurchaseOrder->client->billing_address,
                'shipping_address' => $clientPurchaseOrder->client->shipping_address,
            ] : null,
            'quotations' => $clientPurchaseOrder->relationLoaded('quotations')
                ? $clientPurchaseOrder->quotations->map(fn (Quotation $quotation): array => [
                    'id' => $quotation->id,
                    'quotation_no' => $quotation->quotation_no,
                    'quotation_date' => $quotation->quotation_date->toDateString(),
                    'status' => $quotation->status,
                    'currency' => $quotation->currency,
                    'total_amount' => $quotation->total_amount,
                ])->values()
                : [],
            'receiver' => $clientPurchaseOrder->receiver ? [
                'id' => $clientPurchaseOrder->receiver->id,
                'name' => $clientPurchaseOrder->receiver->name,
            ] : null,
            'documents' => $clientPurchaseOrder->relationLoaded('documents')
                ? $clientPurchaseOrder->documents->map->toPayload()->values()
                : [],
            'items' => $clientPurchaseOrder->relationLoaded('items')
                ? $clientPurchaseOrder->items->map(fn (ClientPurchaseOrderItem $item): array => $this->itemPayload($item))->values()
                : [],
            'inventory_deductions' => $clientPurchaseOrder->relationLoaded('items')
                ? $clientPurchaseOrder->items
                    ->filter(fn (ClientPurchaseOrderItem $item): bool => $item->productService?->type === ProductService::TYPE_PRODUCT)
                    ->map(fn (ClientPurchaseOrderItem $item): array => [
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                    ])
                    ->values()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(ClientPurchaseOrder $clientPurchaseOrder): array
    {
        return [
            'id' => $clientPurchaseOrder->id,
            'company_id' => $clientPurchaseOrder->company_id,
            'client_id' => $clientPurchaseOrder->client_id,
            'quotation_ids' => $clientPurchaseOrder->relationLoaded('quotations')
                ? $clientPurchaseOrder->quotations->pluck('id')->map(fn ($id): int => (int) $id)->values()
                : [],
            'client_po_no' => $clientPurchaseOrder->client_po_no,
            'po_date' => $clientPurchaseOrder->po_date->toDateString(),
            'currency' => $clientPurchaseOrder->currency,
            'amount' => $clientPurchaseOrder->amount,
            'status' => $clientPurchaseOrder->status,
            'received_at' => $clientPurchaseOrder->received_at?->format('Y-m-d\TH:i'),
            'notes' => $clientPurchaseOrder->notes,
            'items' => $clientPurchaseOrder->relationLoaded('items')
                ? $clientPurchaseOrder->items->map(fn (ClientPurchaseOrderItem $item): array => [
                    'product_service_id' => $item->product_service_id,
                    'quotation_item_id' => $item->quotation_item_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'tax' => $item->tax,
                ])->values()
                : [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(ClientPurchaseOrder $clientPurchaseOrder, array $items): void
    {
        $clientPurchaseOrder->items()->delete();
        $amount = 0.0;

        foreach (array_values($items) as $sortOrder => $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $discount = (float) ($item['discount'] ?? 0);
            $tax = (float) ($item['tax'] ?? 0);
            $lineTotal = max(0, ($quantity * $unitPrice) - $discount + $tax);
            $amount += round($lineTotal, 2);

            $clientPurchaseOrder->items()->create([
                'product_service_id' => $item['product_service_id'] ?? null,
                'quotation_item_id' => $item['quotation_item_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
                'discount' => $discount,
                'tax' => $tax,
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'sort_order' => $sortOrder,
            ]);
        }

        $clientPurchaseOrder->update(['amount' => number_format($amount, 2, '.', '')]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transition(Request $request, ClientPurchaseOrder $clientPurchaseOrder, string $status, AuditLogger $audit, string $action, array $extra = []): void
    {
        if (! $clientPurchaseOrder->canTransitionTo($status)) {
            throw ValidationException::withMessages(['status' => __('Invalid client purchase order status transition.')]);
        }

        if ($status === ClientPurchaseOrder::STATUS_CANCELLED && $clientPurchaseOrder->isFulfilled()) {
            throw ValidationException::withMessages(['status' => __('Fulfilled client purchase orders cannot be cancelled in this workflow.')]);
        }

        $oldValues = $clientPurchaseOrder->only(['status']);
        $clientPurchaseOrder->update(['status' => $status, ...$extra]);
        $audit->recordChanges('client-pos', $action, $clientPurchaseOrder, $oldValues, $clientPurchaseOrder->getChanges(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client purchase order status updated.')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(ClientPurchaseOrderItem $item): array
    {
        return [
            'id' => $item->id,
            'product_service_id' => $item->product_service_id,
            'quotation_item_id' => $item->quotation_item_id,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'unit_price' => $item->unit_price,
            'discount' => $item->discount,
            'tax' => $item->tax,
            'line_total' => $item->line_total,
            'product_service' => $item->productService ? [
                'id' => $item->productService->id,
                'code' => $item->productService->code,
                'name' => $item->productService->name,
                'type' => $item->productService->type,
                'quantity' => $item->productService->quantity,
                'primary_image' => $item->productService->primaryImage ? [
                    'url' => Storage::disk('public')->url($item->productService->primaryImage->image_path),
                ] : null,
            ] : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function auditedAttributes(): array
    {
        return ['company_id', 'client_id', 'client_po_no', 'po_date', 'currency', 'amount', 'status', 'received_by', 'received_at', 'fulfilled_at', 'cancelled_at'];
    }
}
