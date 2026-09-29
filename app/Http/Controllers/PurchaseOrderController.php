<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $companyId = $request->integer('company_id');
        $user = $request->user();

        if ($companyId > 0 && ! $this->userCanAccessCompanyId($user, $companyId)) {
            abort(403);
        }

        return Inertia::render('purchase-orders/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'all' : $status,
                'company_id' => $companyId > 0 ? $companyId : null,
            ],
            'companies' => $this->companyOptions($user),
            'purchaseOrders' => PurchaseOrder::query()
                ->select(['id', 'po_no', 'company_id', 'vendor_id', 'po_date', 'expected_delivery', 'currency', 'status', 'total_amount', 'created_at'])
                ->with(['company:id,company_code,company_name', 'vendor:id,vendor_code,vendor_name', 'purchaseRequests:id,pr_no'])
                ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                    'company.users',
                    fn (Builder $query) => $query->whereKey($user->id),
                ))
                ->when($companyId > 0, fn (Builder $query) => $query->where('company_id', $companyId))
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('po_no', 'like', "%{$search}%")
                            ->orWhereHas('vendor', fn (Builder $query) => $query
                                ->where('vendor_code', 'like', "%{$search}%")
                                ->orWhere('vendor_name', 'like', "%{$search}%"));
                    });
                })
                ->when(in_array($status, PurchaseOrder::statuses(), true), fn (Builder $query) => $query->where('status', $status))
                ->latest('po_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (PurchaseOrder $purchaseOrder): array => $this->purchaseOrderSummaryPayload($purchaseOrder)),
            'statuses' => PurchaseOrder::statuses(),
            'can' => [
                'create' => $request->user()->can('create', PurchaseOrder::class),
                'edit' => $request->user()->can('purchase-orders.edit'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', PurchaseOrder::class);

        return Inertia::render('purchase-orders/Create', $this->formOptions($request->user()));
    }

    public function store(StorePurchaseOrderRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        $purchaseOrder = DB::transaction(function () use ($request, $validated): PurchaseOrder {
            $totals = $this->calculateTotals($validated['items']);

            $purchaseOrder = PurchaseOrder::create([
                ...$this->purchaseOrderAttributes($validated),
                ...$totals,
                'po_no' => DocumentSequence::nextPurchaseOrderNumber($validated['po_date']),
                'status' => PurchaseOrder::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);

            $purchaseOrder->purchaseRequests()->sync($validated['purchase_request_ids'] ?? []);
            $this->syncItems($purchaseOrder, $validated['items']);

            return $purchaseOrder;
        });
        $audit->record('purchase-orders', 'created', $purchaseOrder, newValues: $purchaseOrder->only($this->auditedAttributes()), request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase order created.')]);

        return to_route('purchase-orders.show', $purchaseOrder);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        Gate::authorize('view', $purchaseOrder);

        $purchaseOrder->load([
            'company:id,company_code,company_name,email,phone,address',
            'vendor:id,vendor_code,vendor_name,email,phone,address',
            'purchaseRequests:id,pr_no',
            'items.productService:id,code,name,type',
            'creator:id,name',
            'documents.uploader:id,name',
        ]);

        return Inertia::render('purchase-orders/Show', [
            'purchaseOrder' => $this->purchaseOrderPayload($purchaseOrder),
            'activityLogs' => AuditLog::recentFor($purchaseOrder),
            'can' => $this->purchaseOrderPermissions($request->user(), $purchaseOrder),
        ]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        Gate::authorize('update', $purchaseOrder);
        abort_unless($purchaseOrder->isEditable(), 422, __('Only draft or rejected purchase orders can be edited.'));

        $purchaseOrder->load(['purchaseRequests:id,pr_no', 'items.productService:id,code,name,type']);

        return Inertia::render('purchase-orders/Edit', [
            'purchaseOrder' => $this->purchaseOrderFormPayload($purchaseOrder),
            ...$this->formOptions($request->user()),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, AuditLogger $audit): RedirectResponse
    {
        abort_unless($purchaseOrder->isEditable(), 422, __('Only draft or rejected purchase orders can be edited.'));

        $validated = $request->validated();
        $oldValues = $purchaseOrder->only($this->auditedAttributes());

        DB::transaction(function () use ($purchaseOrder, $validated): void {
            $purchaseOrder->update([
                ...$this->purchaseOrderAttributes($validated),
                ...$this->calculateTotals($validated['items']),
                'status' => $purchaseOrder->status === PurchaseOrder::STATUS_REJECTED
                    ? PurchaseOrder::STATUS_DRAFT
                    : $purchaseOrder->status,
            ]);

            $purchaseOrder->purchaseRequests()->sync($validated['purchase_request_ids'] ?? []);
            $this->syncItems($purchaseOrder, $validated['items']);
        });
        $audit->recordChanges('purchase-orders', 'updated', $purchaseOrder, $oldValues, $purchaseOrder->getChanges(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase order updated.')]);

        return to_route('purchase-orders.show', $purchaseOrder);
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $purchaseOrder);
        abort_unless($purchaseOrder->isEditable(), 422, __('Only draft or rejected purchase orders can be deleted.'));
        $oldValues = $purchaseOrder->only($this->auditedAttributes());

        $purchaseOrder->delete();
        $audit->record('purchase-orders', 'deleted', $purchaseOrder, oldValues: $oldValues, request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase order deleted.')]);

        return to_route('purchase-orders.index');
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('submit', $purchaseOrder);

        return $this->transition($request, $audit, $purchaseOrder, PurchaseOrder::STATUS_FOR_APPROVAL, 'submitted_for_approval', __('Purchase order submitted for approval.'));
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('approve', $purchaseOrder);

        return $this->transition($request, $audit, $purchaseOrder, PurchaseOrder::STATUS_APPROVED, 'approved', __('Purchase order approved.'));
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('reject', $purchaseOrder);

        return $this->transition($request, $audit, $purchaseOrder, PurchaseOrder::STATUS_REJECTED, 'rejected', __('Purchase order rejected.'));
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $purchaseOrder);

        return $this->transition($request, $audit, $purchaseOrder, PurchaseOrder::STATUS_SENT, 'sent', __('Purchase order marked as sent.'));
    }

    public function complete(Request $request, PurchaseOrder $purchaseOrder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $purchaseOrder);

        return $this->transition($request, $audit, $purchaseOrder, PurchaseOrder::STATUS_COMPLETED, 'completed', __('Purchase order completed.'));
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $purchaseOrder);

        return $this->transition($request, $audit, $purchaseOrder, PurchaseOrder::STATUS_CANCELLED, 'cancelled', __('Purchase order cancelled.'));
    }

    public function print(PurchaseOrder $purchaseOrder): Response
    {
        Gate::authorize('print', $purchaseOrder);

        $purchaseOrder->load([
            'company:id,company_code,company_name,email,phone,address,tin,logo,purchasing_assistant_name,corporate_sales_manager_name',
            'vendor:id,vendor_code,vendor_name,email,phone,address,tin',
            'purchaseRequests:id,pr_no',
            'items.productService:id,code,name,type',
            'creator:id,name',
        ]);

        return Inertia::render('purchase-orders/Print', [
            'purchaseOrder' => $this->purchaseOrderPayload($purchaseOrder),
        ]);
    }

    private function transition(Request $request, AuditLogger $audit, PurchaseOrder $purchaseOrder, string $status, string $action, string $message): RedirectResponse
    {
        abort_unless($purchaseOrder->canTransitionTo($status), 422, __('This status transition is not allowed.'));
        $oldValues = $purchaseOrder->only(['status']);

        $purchaseOrder->update(['status' => $status]);
        $audit->recordChanges('purchase-orders', $action, $purchaseOrder, $oldValues, $purchaseOrder->getChanges(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function purchaseOrderAttributes(array $validated): array
    {
        return [
            'company_id' => $validated['company_id'],
            'vendor_id' => $validated['vendor_id'],
            'po_date' => $validated['po_date'],
            'expected_delivery' => $validated['expected_delivery'] ?? null,
            'currency' => strtoupper($validated['currency']),
            'notes' => $validated['notes'] ?? null,
            'terms_conditions' => $validated['terms_conditions'] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: float, discount: float, tax_amount: float, total_amount: float}
     */
    private function calculateTotals(array $items): array
    {
        $subtotal = 0.0;
        $discount = 0.0;
        $taxAmount = 0.0;

        foreach ($items as $item) {
            $lineBase = round(((float) $item['quantity']) * ((float) $item['unit_price']), 2);

            $subtotal += $lineBase;
            $discount += round((float) ($item['discount'] ?? 0), 2);
            $taxAmount += round((float) ($item['tax'] ?? 0), 2);
        }

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($subtotal - $discount + $taxAmount, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $purchaseOrder->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $lineBase = round(((float) $item['quantity']) * ((float) $item['unit_price']), 2);
            $discount = round((float) ($item['discount'] ?? 0), 2);
            $tax = round((float) ($item['tax'] ?? 0), 2);

            $purchaseOrder->items()->create([
                'product_service_id' => $item['product_service_id'] ?? null,
                'purchase_request_item_id' => $item['purchase_request_item_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
                'discount' => $discount,
                'tax' => $tax,
                'line_total' => round($lineBase - $discount + $tax, 2),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        return [
            'companies' => $this->companyOptions($user),
            'vendors' => $this->vendorOptions($user),
            'products' => $this->productOptions(),
            'purchaseRequests' => $this->approvedPurchaseRequestOptions($user),
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
    private function vendorOptions(User $user): array
    {
        return Vendor::query()
            ->select(['id', 'vendor_code', 'vendor_name'])
            ->where('vendors.status', Vendor::STATUS_ACTIVE)
            ->with(['companies' => fn ($query) => $query
                ->select(['companies.id'])
                ->where('companies.status', Company::STATUS_ACTIVE)
                ->where('company_vendor.status', Company::STATUS_ACTIVE)])
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                'companies.users',
                fn (Builder $query) => $query->whereKey($user->id),
            ))
            ->whereHas('companies', fn (Builder $query) => $query
                ->where('companies.status', Company::STATUS_ACTIVE)
                ->where('company_vendor.status', Company::STATUS_ACTIVE))
            ->orderBy('vendor_name')
            ->get()
            ->map(fn (Vendor $vendor): array => [
                'id' => (int) $vendor->id,
                'vendor_code' => $vendor->vendor_code,
                'vendor_name' => $vendor->vendor_name,
                'company_ids' => $vendor->companies->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productOptions(): array
    {
        return ProductService::query()
            ->select(['id', 'code', 'type', 'name', 'description', 'unit', 'default_price'])
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (ProductService $productService): array => [
                'id' => (int) $productService->id,
                'code' => $productService->code,
                'type' => $productService->type,
                'name' => $productService->name,
                'description' => $productService->description,
                'unit' => $productService->unit,
                'default_price' => $productService->default_price,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function approvedPurchaseRequestOptions(User $user): array
    {
        return PurchaseRequest::query()
            ->select(['id', 'pr_no', 'company_id', 'vendor_id', 'currency', 'request_date', 'status'])
            ->with([
                'company:id,company_code,company_name',
                'vendor:id,vendor_code,vendor_name',
                'items.productService:id,code,name,type',
            ])
            ->where('status', PurchaseRequest::STATUS_APPROVED)
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                'company.users',
                fn (Builder $query) => $query->whereKey($user->id),
            ))
            ->latest('request_date')
            ->latest('id')
            ->get()
            ->map(fn (PurchaseRequest $purchaseRequest): array => [
                'id' => (int) $purchaseRequest->id,
                'pr_no' => $purchaseRequest->pr_no,
                'company_id' => (int) $purchaseRequest->company_id,
                'vendor_id' => $purchaseRequest->vendor_id === null ? null : (int) $purchaseRequest->vendor_id,
                'currency' => $purchaseRequest->currency,
                'request_date' => $purchaseRequest->request_date->toDateString(),
                'company' => [
                    'company_code' => $purchaseRequest->company?->company_code,
                    'company_name' => $purchaseRequest->company?->company_name,
                ],
                'vendor' => $purchaseRequest->vendor ? [
                    'vendor_code' => $purchaseRequest->vendor->vendor_code,
                    'vendor_name' => $purchaseRequest->vendor->vendor_name,
                ] : null,
                'items' => $purchaseRequest->items->map(fn ($item): array => [
                    'id' => (int) $item->id,
                    'product_service_id' => $item->product_service_id === null ? null : (int) $item->product_service_id,
                    'product_service' => $item->productService ? [
                        'id' => (int) $item->productService->id,
                        'code' => $item->productService->code,
                        'name' => $item->productService->name,
                        'type' => $item->productService->type,
                    ] : null,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'tax' => $item->tax,
                ])->values()->all(),
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
    private function purchaseOrderSummaryPayload(PurchaseOrder $purchaseOrder): array
    {
        return [
            'id' => $purchaseOrder->id,
            'po_no' => $purchaseOrder->po_no,
            'po_date' => $purchaseOrder->po_date->toDateString(),
            'expected_delivery' => $purchaseOrder->expected_delivery?->toDateString(),
            'currency' => $purchaseOrder->currency,
            'status' => $purchaseOrder->status,
            'total_amount' => $purchaseOrder->total_amount,
            'company' => [
                'id' => $purchaseOrder->company?->id,
                'company_code' => $purchaseOrder->company?->company_code,
                'company_name' => $purchaseOrder->company?->company_name,
            ],
            'vendor' => [
                'id' => $purchaseOrder->vendor?->id,
                'vendor_code' => $purchaseOrder->vendor?->vendor_code,
                'vendor_name' => $purchaseOrder->vendor?->vendor_name,
            ],
            'purchase_requests' => $purchaseOrder->relationLoaded('purchaseRequests')
                ? $purchaseOrder->purchaseRequests->map(fn (PurchaseRequest $purchaseRequest): array => [
                    'id' => $purchaseRequest->id,
                    'pr_no' => $purchaseRequest->pr_no,
                ])->values()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseOrderPayload(PurchaseOrder $purchaseOrder): array
    {
        return [
            ...$this->purchaseOrderSummaryPayload($purchaseOrder),
            'notes' => $purchaseOrder->notes,
            'terms_conditions' => $purchaseOrder->terms_conditions,
            'shipping_method' => null,
            'payment_terms' => $purchaseOrder->terms_conditions,
            'subtotal' => $purchaseOrder->subtotal,
            'discount' => $purchaseOrder->discount,
            'tax_amount' => $purchaseOrder->tax_amount,
            'created_at' => $purchaseOrder->created_at?->toDateString(),
            'company' => $purchaseOrder->company ? [
                'id' => $purchaseOrder->company->id,
                'company_code' => $purchaseOrder->company->company_code,
                'company_name' => $purchaseOrder->company->company_name,
                'tin' => $purchaseOrder->company->tin ?? null,
                'email' => $purchaseOrder->company->email ?? null,
                'phone' => $purchaseOrder->company->phone ?? null,
                'address' => $purchaseOrder->company->address ?? null,
                'logo_url' => $purchaseOrder->company->logo === null ? null : Storage::disk('public')->url($purchaseOrder->company->logo),
                'purchasing_assistant_name' => $purchaseOrder->company->purchasing_assistant_name ?? null,
                'corporate_sales_manager_name' => $purchaseOrder->company->corporate_sales_manager_name ?? null,
            ] : null,
            'ship_to' => $purchaseOrder->company?->address,
            'prepared_by' => [
                'name' => $purchaseOrder->company?->purchasing_assistant_name,
                'title' => 'Purchasing Assistant',
            ],
            'noted_by' => [
                'name' => $purchaseOrder->company?->corporate_sales_manager_name,
                'title' => 'Corporate Sales Manager',
            ],
            'vendor' => $purchaseOrder->vendor ? [
                'id' => $purchaseOrder->vendor->id,
                'vendor_code' => $purchaseOrder->vendor->vendor_code,
                'vendor_name' => $purchaseOrder->vendor->vendor_name,
                'tin' => $purchaseOrder->vendor->tin ?? null,
                'email' => $purchaseOrder->vendor->email ?? null,
                'phone' => $purchaseOrder->vendor->phone ?? null,
                'address' => $purchaseOrder->vendor->address ?? null,
            ] : null,
            'items' => $purchaseOrder->relationLoaded('items')
                ? $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => $this->purchaseOrderItemPayload($item))->values()
                : [],
            'creator' => $purchaseOrder->creator ? [
                'id' => $purchaseOrder->creator->id,
                'name' => $purchaseOrder->creator->name,
            ] : null,
            'documents' => $purchaseOrder->relationLoaded('documents')
                ? $purchaseOrder->documents->map->toPayload()->values()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseOrderFormPayload(PurchaseOrder $purchaseOrder): array
    {
        return [
            'id' => $purchaseOrder->id,
            'po_no' => $purchaseOrder->po_no,
            'company_id' => $purchaseOrder->company_id,
            'vendor_id' => $purchaseOrder->vendor_id,
            'purchase_request_ids' => $purchaseOrder->relationLoaded('purchaseRequests')
                ? $purchaseOrder->purchaseRequests->pluck('id')->map(fn ($id): int => (int) $id)->values()
                : [],
            'po_date' => $purchaseOrder->po_date->toDateString(),
            'expected_delivery' => $purchaseOrder->expected_delivery?->toDateString(),
            'currency' => $purchaseOrder->currency,
            'notes' => $purchaseOrder->notes,
            'terms_conditions' => $purchaseOrder->terms_conditions,
            'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => [
                'product_service_id' => $item->product_service_id,
                'purchase_request_item_id' => $item->purchase_request_item_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'tax' => $item->tax,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseOrderItemPayload(PurchaseOrderItem $item): array
    {
        return [
            'id' => $item->id,
            'product_service_id' => $item->product_service_id,
            'purchase_request_item_id' => $item->purchase_request_item_id,
            'product_service' => $item->productService ? [
                'id' => $item->productService->id,
                'code' => $item->productService->code,
                'name' => $item->productService->name,
                'type' => $item->productService->type,
            ] : null,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'unit_price' => $item->unit_price,
            'discount' => $item->discount,
            'tax' => $item->tax,
            'line_total' => $item->line_total,
            'sort_order' => $item->sort_order,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function purchaseOrderPermissions(User $user, PurchaseOrder $purchaseOrder): array
    {
        return [
            'edit' => $user->can('update', $purchaseOrder) && $purchaseOrder->isEditable(),
            'delete' => $user->can('delete', $purchaseOrder) && $purchaseOrder->isEditable(),
            'submit' => $user->can('submit', $purchaseOrder) && $purchaseOrder->canTransitionTo(PurchaseOrder::STATUS_FOR_APPROVAL),
            'approve' => $user->can('approve', $purchaseOrder) && $purchaseOrder->canTransitionTo(PurchaseOrder::STATUS_APPROVED),
            'reject' => $user->can('reject', $purchaseOrder) && $purchaseOrder->canTransitionTo(PurchaseOrder::STATUS_REJECTED),
            'send' => $user->can('update', $purchaseOrder) && $purchaseOrder->canTransitionTo(PurchaseOrder::STATUS_SENT),
            'complete' => $user->can('update', $purchaseOrder) && $purchaseOrder->canTransitionTo(PurchaseOrder::STATUS_COMPLETED),
            'cancel' => $user->can('update', $purchaseOrder) && $purchaseOrder->canTransitionTo(PurchaseOrder::STATUS_CANCELLED),
            'print' => $user->can('print', $purchaseOrder),
            'uploadDocuments' => $user->can('documents.upload'),
            'downloadDocuments' => $user->can('documents.download'),
            'deleteDocuments' => $user->can('documents.delete'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function auditedAttributes(): array
    {
        return ['po_no', 'company_id', 'vendor_id', 'po_date', 'expected_delivery', 'currency', 'status', 'subtotal', 'discount', 'tax_amount', 'total_amount'];
    }
}
