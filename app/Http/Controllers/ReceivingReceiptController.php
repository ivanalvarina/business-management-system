<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReceivingReceiptRequest;
use App\Http\Requests\UpdateReceivingReceiptRequest;
use App\Models\DocumentSequence;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReceipt;
use App\Models\ReceivingReceiptItem;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReceivingReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ReceivingReceipt::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $user = $request->user();

        return Inertia::render('receiving-receipts/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'all' : $status,
            ],
            'receivingReceipts' => ReceivingReceipt::query()
                ->select(['id', 'rr_no', 'company_id', 'purchase_order_id', 'vendor_id', 'invoice_no', 'received_date', 'status'])
                ->with(['company:id,company_code,company_name', 'vendor:id,vendor_code,vendor_name', 'purchaseOrder:id,po_no'])
                ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('company.users', fn (Builder $query) => $query->whereKey($user->id)))
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('rr_no', 'like', "%{$search}%")
                        ->orWhere('invoice_no', 'like', "%{$search}%")
                        ->orWhereHas('purchaseOrder', fn (Builder $query) => $query->where('po_no', 'like', "%{$search}%"))
                        ->orWhereHas('vendor', fn (Builder $query) => $query
                            ->where('vendor_code', 'like', "%{$search}%")
                            ->orWhere('vendor_name', 'like', "%{$search}%"));
                }))
                ->when(in_array($status, ReceivingReceipt::statuses(), true), fn (Builder $query) => $query->where('status', $status))
                ->latest('received_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (ReceivingReceipt $receivingReceipt): array => $this->summaryPayload($receivingReceipt)),
            'statuses' => ReceivingReceipt::statuses(),
            'can' => [
                'create' => $request->user()->can('create', ReceivingReceipt::class),
                'edit' => $request->user()->can('receiving-receipts.edit'),
                'print' => $request->user()->can('receiving-receipts.print'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', ReceivingReceipt::class);

        return Inertia::render('receiving-receipts/Create', $this->formOptions($request->user()));
    }

    public function store(StoreReceivingReceiptRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $purchaseOrder = PurchaseOrder::findOrFail($validated['purchase_order_id']);

        $receivingReceipt = DB::transaction(function () use ($request, $validated, $purchaseOrder): ReceivingReceipt {
            $receivingReceipt = ReceivingReceipt::create([
                'rr_no' => DocumentSequence::nextReceivingReceiptNumber($validated['received_date']),
                'company_id' => $purchaseOrder->company_id,
                'purchase_order_id' => $purchaseOrder->id,
                'vendor_id' => $purchaseOrder->vendor_id,
                'invoice_no' => $validated['invoice_no'] ?? null,
                'received_date' => $validated['received_date'],
                'received_by_name' => $validated['received_by_name'] ?? null,
                'checked_by_name' => $validated['checked_by_name'] ?? null,
                'status' => ReceivingReceipt::STATUS_RECEIVED,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($receivingReceipt, $validated['items']);

            return $receivingReceipt;
        });

        $audit->record('receiving-receipts', 'created', $receivingReceipt, newValues: $receivingReceipt->only($this->auditedAttributes()), request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Receiving receipt recorded.')]);

        return to_route('receiving-receipts.show', $receivingReceipt);
    }

    public function show(Request $request, ReceivingReceipt $receivingReceipt): Response
    {
        Gate::authorize('view', $receivingReceipt);

        $receivingReceipt->load($this->detailRelations());

        return Inertia::render('receiving-receipts/Show', [
            'receivingReceipt' => $this->payload($receivingReceipt),
            'can' => [
                'edit' => $request->user()->can('update', $receivingReceipt),
                'delete' => $request->user()->can('delete', $receivingReceipt),
                'print' => $request->user()->can('print', $receivingReceipt),
            ],
        ]);
    }

    public function edit(Request $request, ReceivingReceipt $receivingReceipt): Response
    {
        Gate::authorize('update', $receivingReceipt);

        $receivingReceipt->load(['items', 'purchaseOrder.items']);

        return Inertia::render('receiving-receipts/Edit', [
            'receivingReceipt' => $this->formPayload($receivingReceipt),
            ...$this->formOptions($request->user()),
        ]);
    }

    public function update(UpdateReceivingReceiptRequest $request, ReceivingReceipt $receivingReceipt, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $purchaseOrder = PurchaseOrder::findOrFail($validated['purchase_order_id']);
        $oldValues = $receivingReceipt->only($this->auditedAttributes());

        DB::transaction(function () use ($receivingReceipt, $validated, $purchaseOrder): void {
            $receivingReceipt->update([
                'company_id' => $purchaseOrder->company_id,
                'purchase_order_id' => $purchaseOrder->id,
                'vendor_id' => $purchaseOrder->vendor_id,
                'invoice_no' => $validated['invoice_no'] ?? null,
                'received_date' => $validated['received_date'],
                'received_by_name' => $validated['received_by_name'] ?? null,
                'checked_by_name' => $validated['checked_by_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncItems($receivingReceipt, $validated['items']);
        });

        $audit->recordChanges('receiving-receipts', 'updated', $receivingReceipt, $oldValues, $receivingReceipt->getChanges(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Receiving receipt updated.')]);

        return to_route('receiving-receipts.show', $receivingReceipt);
    }

    public function destroy(Request $request, ReceivingReceipt $receivingReceipt, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $receivingReceipt);

        $oldValues = $receivingReceipt->only($this->auditedAttributes());
        $receivingReceipt->delete();

        $audit->record('receiving-receipts', 'deleted', $receivingReceipt, oldValues: $oldValues, request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Receiving receipt deleted.')]);

        return to_route('receiving-receipts.index');
    }

    public function print(ReceivingReceipt $receivingReceipt): Response
    {
        Gate::authorize('print', $receivingReceipt);

        $receivingReceipt->load($this->detailRelations());

        return Inertia::render('receiving-receipts/Print', [
            'receivingReceipt' => $this->payload($receivingReceipt),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(ReceivingReceipt $receivingReceipt, array $items): void
    {
        $receivingReceipt->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $receivingReceipt->items()->create([
                'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                'quantity' => $item['quantity'],
                'description' => $item['description'],
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
            'purchaseOrders' => PurchaseOrder::query()
                ->select(['id', 'po_no', 'company_id', 'vendor_id', 'po_date', 'status'])
                ->with(['company:id,company_code,company_name', 'vendor:id,vendor_code,vendor_name', 'items:id,purchase_order_id,description,quantity,unit,sort_order'])
                ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('company.users', fn (Builder $query) => $query->whereKey($user->id)))
                ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_COMPLETED])
                ->latest('po_date')
                ->limit(100)
                ->get()
                ->map(fn (PurchaseOrder $purchaseOrder): array => [
                    'id' => (int) $purchaseOrder->id,
                    'po_no' => $purchaseOrder->po_no,
                    'company' => [
                        'company_code' => $purchaseOrder->company?->company_code,
                        'company_name' => $purchaseOrder->company?->company_name,
                    ],
                    'vendor' => [
                        'vendor_code' => $purchaseOrder->vendor?->vendor_code,
                        'vendor_name' => $purchaseOrder->vendor?->vendor_name,
                    ],
                    'items' => $purchaseOrder->items->map(fn ($item): array => [
                        'id' => (int) $item->id,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit' => $item->unit,
                    ])->values(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(ReceivingReceipt $receivingReceipt): array
    {
        return [
            'id' => $receivingReceipt->id,
            'rr_no' => $receivingReceipt->rr_no,
            'invoice_no' => $receivingReceipt->invoice_no,
            'received_date' => $receivingReceipt->received_date->toDateString(),
            'status' => $receivingReceipt->status,
            'purchase_order' => [
                'id' => $receivingReceipt->purchaseOrder?->id,
                'po_no' => $receivingReceipt->purchaseOrder?->po_no,
            ],
            'company' => [
                'id' => $receivingReceipt->company?->id,
                'company_code' => $receivingReceipt->company?->company_code,
                'company_name' => $receivingReceipt->company?->company_name,
            ],
            'vendor' => [
                'id' => $receivingReceipt->vendor?->id,
                'vendor_code' => $receivingReceipt->vendor?->vendor_code,
                'vendor_name' => $receivingReceipt->vendor?->vendor_name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ReceivingReceipt $receivingReceipt): array
    {
        return [
            ...$this->summaryPayload($receivingReceipt),
            'received_by_name' => $receivingReceipt->received_by_name,
            'checked_by_name' => $receivingReceipt->checked_by_name,
            'notes' => $receivingReceipt->notes,
            'company' => [
                'id' => $receivingReceipt->company?->id,
                'company_code' => $receivingReceipt->company?->company_code,
                'company_name' => $receivingReceipt->company?->company_name,
                'logo_url' => $receivingReceipt->company?->logo === null ? null : Storage::disk('public')->url($receivingReceipt->company->logo),
            ],
            'vendor' => [
                'id' => $receivingReceipt->vendor?->id,
                'vendor_code' => $receivingReceipt->vendor?->vendor_code,
                'vendor_name' => $receivingReceipt->vendor?->vendor_name,
                'address' => $receivingReceipt->vendor?->address,
            ],
            'purchase_order' => [
                'id' => $receivingReceipt->purchaseOrder?->id,
                'po_no' => $receivingReceipt->purchaseOrder?->po_no,
            ],
            'items' => $receivingReceipt->relationLoaded('items')
                ? $receivingReceipt->items->map(fn (ReceivingReceiptItem $item): array => [
                    'id' => $item->id,
                    'purchase_order_item_id' => $item->purchase_order_item_id,
                    'quantity' => $item->quantity,
                    'description' => $item->description,
                ])->values()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(ReceivingReceipt $receivingReceipt): array
    {
        return [
            'id' => $receivingReceipt->id,
            'rr_no' => $receivingReceipt->rr_no,
            'purchase_order_id' => $receivingReceipt->purchase_order_id,
            'invoice_no' => $receivingReceipt->invoice_no,
            'received_date' => $receivingReceipt->received_date->toDateString(),
            'received_by_name' => $receivingReceipt->received_by_name,
            'checked_by_name' => $receivingReceipt->checked_by_name,
            'notes' => $receivingReceipt->notes,
            'items' => $receivingReceipt->items->map(fn (ReceivingReceiptItem $item): array => [
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'quantity' => $item->quantity,
                'description' => $item->description,
            ])->values(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function detailRelations(): array
    {
        return [
            'company:id,company_code,company_name,logo',
            'purchaseOrder:id,po_no',
            'vendor:id,vendor_code,vendor_name,address',
            'items',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function auditedAttributes(): array
    {
        return ['rr_no', 'company_id', 'purchase_order_id', 'vendor_id', 'invoice_no', 'received_date', 'status'];
    }
}
