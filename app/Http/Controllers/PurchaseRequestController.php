<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequestRequest;
use App\Http\Requests\UpdatePurchaseRequestRequest;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\ProductService;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
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

class PurchaseRequestController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PurchaseRequest::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $user = $request->user();

        return Inertia::render('purchase-requests/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'all' : $status,
            ],
            'purchaseRequests' => PurchaseRequest::query()
                ->select(['id', 'pr_no', 'company_id', 'vendor_id', 'request_date', 'date_required', 'currency', 'status', 'total_amount'])
                ->with(['company:id,company_code,company_name', 'vendor:id,vendor_code,vendor_name'])
                ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('company.users', fn (Builder $query) => $query->whereKey($user->id)))
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('pr_no', 'like', "%{$search}%")
                        ->orWhere('client_project', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn (Builder $query) => $query
                            ->where('vendor_code', 'like', "%{$search}%")
                            ->orWhere('vendor_name', 'like', "%{$search}%"));
                }))
                ->when(in_array($status, PurchaseRequest::statuses(), true), fn (Builder $query) => $query->where('status', $status))
                ->latest('request_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (PurchaseRequest $purchaseRequest): array => $this->summaryPayload($purchaseRequest)),
            'statuses' => PurchaseRequest::statuses(),
            'can' => [
                'create' => $request->user()->can('create', PurchaseRequest::class),
                'edit' => $request->user()->can('purchase-requests.edit'),
                'print' => $request->user()->can('purchase-requests.print'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', PurchaseRequest::class);

        return Inertia::render('purchase-requests/Create', $this->formOptions($request->user()));
    }

    public function store(StorePurchaseRequestRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        $purchaseRequest = DB::transaction(function () use ($request, $validated): PurchaseRequest {
            $purchaseRequest = PurchaseRequest::create([
                ...$this->attributes($validated),
                ...$this->calculateTotals($validated['items']),
                'pr_no' => DocumentSequence::nextPurchaseRequestNumber($validated['request_date']),
                'status' => PurchaseRequest::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($purchaseRequest, $validated['items']);

            return $purchaseRequest;
        });

        $audit->record('purchase-requests', 'created', $purchaseRequest, newValues: $purchaseRequest->only($this->auditedAttributes()), request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase request created.')]);

        return to_route('purchase-requests.show', $purchaseRequest);
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        Gate::authorize('view', $purchaseRequest);

        $purchaseRequest->load(['company:id,company_code,company_name,email,phone,address,logo', 'vendor:id,vendor_code,vendor_name,email,phone,address', 'items.productService:id,code,name,type']);

        return Inertia::render('purchase-requests/Show', [
            'purchaseRequest' => $this->payload($purchaseRequest),
            'can' => $this->permissions($request->user(), $purchaseRequest),
        ]);
    }

    public function edit(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        Gate::authorize('update', $purchaseRequest);
        abort_unless($purchaseRequest->isEditable(), 422, __('Only draft or rejected purchase requests can be edited.'));

        $purchaseRequest->load(['items.productService:id,code,name,type']);

        return Inertia::render('purchase-requests/Edit', [
            'purchaseRequest' => $this->formPayload($purchaseRequest),
            ...$this->formOptions($request->user()),
        ]);
    }

    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest, AuditLogger $audit): RedirectResponse
    {
        abort_unless($purchaseRequest->isEditable(), 422, __('Only draft or rejected purchase requests can be edited.'));

        $validated = $request->validated();
        $oldValues = $purchaseRequest->only($this->auditedAttributes());

        DB::transaction(function () use ($purchaseRequest, $validated): void {
            $purchaseRequest->update([
                ...$this->attributes($validated),
                ...$this->calculateTotals($validated['items']),
                'status' => $purchaseRequest->status === PurchaseRequest::STATUS_REJECTED
                    ? PurchaseRequest::STATUS_DRAFT
                    : $purchaseRequest->status,
            ]);

            $this->syncItems($purchaseRequest, $validated['items']);
        });

        $audit->recordChanges('purchase-requests', 'updated', $purchaseRequest, $oldValues, $purchaseRequest->getChanges(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase request updated.')]);

        return to_route('purchase-requests.show', $purchaseRequest);
    }

    public function destroy(Request $request, PurchaseRequest $purchaseRequest, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $purchaseRequest);
        abort_unless($purchaseRequest->isEditable(), 422, __('Only draft or rejected purchase requests can be deleted.'));

        $oldValues = $purchaseRequest->only($this->auditedAttributes());
        $purchaseRequest->delete();

        $audit->record('purchase-requests', 'deleted', $purchaseRequest, oldValues: $oldValues, request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase request deleted.')]);

        return to_route('purchase-requests.index');
    }

    public function submit(Request $request, PurchaseRequest $purchaseRequest, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('submit', $purchaseRequest);

        return $this->transition($request, $audit, $purchaseRequest, PurchaseRequest::STATUS_FOR_APPROVAL, 'submitted_for_approval', __('Purchase request submitted for approval.'));
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('approve', $purchaseRequest);

        return $this->transition($request, $audit, $purchaseRequest, PurchaseRequest::STATUS_APPROVED, 'approved', __('Purchase request approved.'));
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('reject', $purchaseRequest);

        return $this->transition($request, $audit, $purchaseRequest, PurchaseRequest::STATUS_REJECTED, 'rejected', __('Purchase request rejected.'));
    }

    public function cancel(Request $request, PurchaseRequest $purchaseRequest, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $purchaseRequest);

        return $this->transition($request, $audit, $purchaseRequest, PurchaseRequest::STATUS_CANCELLED, 'cancelled', __('Purchase request cancelled.'));
    }

    public function print(PurchaseRequest $purchaseRequest): Response
    {
        Gate::authorize('print', $purchaseRequest);

        $purchaseRequest->load(['company:id,company_code,company_name,email,phone,address,logo', 'vendor:id,vendor_code,vendor_name,email,phone,address', 'items.productService:id,code,name,type']);

        return Inertia::render('purchase-requests/Print', [
            'purchaseRequest' => $this->payload($purchaseRequest),
        ]);
    }

    private function transition(Request $request, AuditLogger $audit, PurchaseRequest $purchaseRequest, string $status, string $action, string $message): RedirectResponse
    {
        abort_unless($purchaseRequest->canTransitionTo($status), 422, __('This status transition is not allowed.'));

        $oldValues = $purchaseRequest->only(['status']);
        $purchaseRequest->update(['status' => $status]);

        $audit->recordChanges('purchase-requests', $action, $purchaseRequest, $oldValues, $purchaseRequest->getChanges(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'company_id' => $validated['company_id'],
            'vendor_id' => $validated['vendor_id'] ?? null,
            'request_date' => $validated['request_date'],
            'date_required' => $validated['date_required'] ?? null,
            'client_project' => $validated['client_project'] ?? null,
            'requested_by_name' => $validated['requested_by_name'] ?? null,
            'checked_by_name' => $validated['checked_by_name'] ?? null,
            'noted_by_name' => $validated['noted_by_name'] ?? null,
            'currency' => strtoupper($validated['currency']),
            'notes' => $validated['notes'] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: float, tax_amount: float, total_amount: float}
     */
    private function calculateTotals(array $items): array
    {
        $subtotal = 0.0;
        $taxAmount = 0.0;

        foreach ($items as $item) {
            $lineBase = round(((float) $item['quantity']) * ((float) $item['unit_price']), 2);
            $subtotal += $lineBase;
            $taxAmount += round((float) ($item['tax'] ?? 0), 2);
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($subtotal + $taxAmount, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(PurchaseRequest $purchaseRequest, array $items): void
    {
        $purchaseRequest->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $lineBase = round(((float) $item['quantity']) * ((float) $item['unit_price']), 2);
            $tax = round((float) ($item['tax'] ?? 0), 2);

            $purchaseRequest->items()->create([
                'product_service_id' => $item['product_service_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
                'tax' => $tax,
                'line_total' => round($lineBase + $tax, 2),
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
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('users', fn (Builder $query) => $query->whereKey($user->id)))
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
            ->with(['companies' => fn ($query) => $query->select(['companies.id'])->where('companies.status', Company::STATUS_ACTIVE)->where('company_vendor.status', Company::STATUS_ACTIVE)])
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('companies.users', fn (Builder $query) => $query->whereKey($user->id)))
            ->whereHas('companies', fn (Builder $query) => $query->where('companies.status', Company::STATUS_ACTIVE)->where('company_vendor.status', Company::STATUS_ACTIVE))
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
     * @return array<string, mixed>
     */
    private function summaryPayload(PurchaseRequest $purchaseRequest): array
    {
        return [
            'id' => $purchaseRequest->id,
            'pr_no' => $purchaseRequest->pr_no,
            'request_date' => $purchaseRequest->request_date->toDateString(),
            'date_required' => $purchaseRequest->date_required?->toDateString(),
            'currency' => $purchaseRequest->currency,
            'status' => $purchaseRequest->status,
            'total_amount' => $purchaseRequest->total_amount,
            'company' => [
                'id' => $purchaseRequest->company?->id,
                'company_code' => $purchaseRequest->company?->company_code,
                'company_name' => $purchaseRequest->company?->company_name,
            ],
            'vendor' => $purchaseRequest->vendor ? [
                'id' => $purchaseRequest->vendor->id,
                'vendor_code' => $purchaseRequest->vendor->vendor_code,
                'vendor_name' => $purchaseRequest->vendor->vendor_name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PurchaseRequest $purchaseRequest): array
    {
        return [
            ...$this->summaryPayload($purchaseRequest),
            'client_project' => $purchaseRequest->client_project,
            'requested_by_name' => $purchaseRequest->requested_by_name,
            'checked_by_name' => $purchaseRequest->checked_by_name,
            'noted_by_name' => $purchaseRequest->noted_by_name,
            'notes' => $purchaseRequest->notes,
            'subtotal' => $purchaseRequest->subtotal,
            'tax_amount' => $purchaseRequest->tax_amount,
            'company' => $purchaseRequest->company ? [
                'id' => $purchaseRequest->company->id,
                'company_code' => $purchaseRequest->company->company_code,
                'company_name' => $purchaseRequest->company->company_name,
                'email' => $purchaseRequest->company->email,
                'phone' => $purchaseRequest->company->phone,
                'address' => $purchaseRequest->company->address,
                'logo_url' => $purchaseRequest->company->logo === null ? null : Storage::disk('public')->url($purchaseRequest->company->logo),
            ] : null,
            'vendor' => $purchaseRequest->vendor ? [
                'id' => $purchaseRequest->vendor->id,
                'vendor_code' => $purchaseRequest->vendor->vendor_code,
                'vendor_name' => $purchaseRequest->vendor->vendor_name,
                'email' => $purchaseRequest->vendor->email,
                'phone' => $purchaseRequest->vendor->phone,
                'address' => $purchaseRequest->vendor->address,
            ] : null,
            'items' => $purchaseRequest->relationLoaded('items')
                ? $purchaseRequest->items->map(fn (PurchaseRequestItem $item): array => $this->itemPayload($item))->values()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(PurchaseRequest $purchaseRequest): array
    {
        return [
            'id' => $purchaseRequest->id,
            'pr_no' => $purchaseRequest->pr_no,
            'company_id' => $purchaseRequest->company_id,
            'vendor_id' => $purchaseRequest->vendor_id,
            'request_date' => $purchaseRequest->request_date->toDateString(),
            'date_required' => $purchaseRequest->date_required?->toDateString(),
            'client_project' => $purchaseRequest->client_project,
            'requested_by_name' => $purchaseRequest->requested_by_name,
            'checked_by_name' => $purchaseRequest->checked_by_name,
            'noted_by_name' => $purchaseRequest->noted_by_name,
            'currency' => $purchaseRequest->currency,
            'notes' => $purchaseRequest->notes,
            'items' => $purchaseRequest->items->map(fn (PurchaseRequestItem $item): array => [
                'product_service_id' => $item->product_service_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'tax' => $item->tax,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(PurchaseRequestItem $item): array
    {
        return [
            'id' => $item->id,
            'product_service_id' => $item->product_service_id,
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
            'tax' => $item->tax,
            'line_total' => $item->line_total,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(User $user, PurchaseRequest $purchaseRequest): array
    {
        return [
            'edit' => $user->can('update', $purchaseRequest) && $purchaseRequest->isEditable(),
            'delete' => $user->can('delete', $purchaseRequest) && $purchaseRequest->isEditable(),
            'submit' => $user->can('submit', $purchaseRequest) && $purchaseRequest->canTransitionTo(PurchaseRequest::STATUS_FOR_APPROVAL),
            'approve' => $user->can('approve', $purchaseRequest) && $purchaseRequest->canTransitionTo(PurchaseRequest::STATUS_APPROVED),
            'reject' => $user->can('reject', $purchaseRequest) && $purchaseRequest->canTransitionTo(PurchaseRequest::STATUS_REJECTED),
            'cancel' => $user->can('update', $purchaseRequest) && $purchaseRequest->canTransitionTo(PurchaseRequest::STATUS_CANCELLED),
            'print' => $user->can('print', $purchaseRequest),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function auditedAttributes(): array
    {
        return ['pr_no', 'company_id', 'vendor_id', 'request_date', 'date_required', 'currency', 'status', 'subtotal', 'tax_amount', 'total_amount'];
    }
}
