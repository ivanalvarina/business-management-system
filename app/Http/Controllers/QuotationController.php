<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuotationRequest;
use App\Http\Requests\UpdateQuotationRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\ProductService;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\QuotationPdfRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class QuotationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Quotation::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $companyId = $request->integer('company_id');
        $user = $request->user();

        if ($companyId > 0 && ! $this->userCanAccessCompanyId($user, $companyId)) {
            abort(403);
        }

        return Inertia::render('quotations/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'all' : $status,
                'company_id' => $companyId > 0 ? $companyId : null,
            ],
            'companies' => $this->companyOptions($user),
            'quotations' => Quotation::query()
                ->select(['id', 'quotation_no', 'company_id', 'client_id', 'quotation_date', 'valid_until', 'currency', 'status', 'total_amount', 'created_at'])
                ->with(['company:id,company_code,company_name', 'client:id,client_code,client_name'])
                ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                    'company.users',
                    fn (Builder $query) => $query->whereKey($user->id),
                ))
                ->when($companyId > 0, fn (Builder $query) => $query->where('company_id', $companyId))
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('quotation_no', 'like', "%{$search}%")
                            ->orWhereHas('client', fn (Builder $query) => $query
                                ->where('client_code', 'like', "%{$search}%")
                                ->orWhere('client_name', 'like', "%{$search}%"));
                    });
                })
                ->when(in_array($status, Quotation::statuses(), true), fn (Builder $query) => $query->where('status', $status))
                ->latest('quotation_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (Quotation $quotation): array => $this->quotationSummaryPayload($quotation, $user)),
            'statuses' => Quotation::statuses(),
            'can' => [
                'create' => $request->user()->can('create', Quotation::class),
                'edit' => $request->user()->can('quotations.edit'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Quotation::class);

        return Inertia::render('quotations/Create', $this->formOptions($request->user()));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreQuotationRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        $quotation = DB::transaction(function () use ($request, $validated): Quotation {
            $totals = $this->calculateTotals($validated['items']);
            $company = Company::query()
                ->with('activeQuotationTemplate')
                ->whereKey($validated['company_id'])
                ->firstOrFail();
            $template = $company->activeQuotationTemplate()->first();

            $quotation = Quotation::create([
                ...$this->quotationAttributes($validated),
                ...$totals,
                'quotation_no' => DocumentSequence::nextQuotationNumber($validated['quotation_date']),
                'quotation_template_id' => $template?->id,
                'quotation_template_snapshot' => $template?->snapshot(),
                'status' => Quotation::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($quotation, $validated['items']);

            return $quotation;
        });
        $audit->record('quotations', 'created', $quotation, newValues: $quotation->only($this->auditedAttributes()), request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation created.')]);

        return to_route('quotations.show', $quotation);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Quotation $quotation): Response
    {
        Gate::authorize('view', $quotation);

        $quotation->load([
            'company:id,company_code,company_name,email,phone,address',
            'client:id,client_code,client_name,email,phone,billing_address,shipping_address',
            'items.productService:id,code,name,type',
            'creator:id,name',
            'documents.uploader:id,name',
        ]);

        return Inertia::render('quotations/Show', [
            'quotation' => $this->quotationPayload($quotation),
            'activityLogs' => AuditLog::recentFor($quotation),
            'can' => $this->quotationPermissions($request->user(), $quotation),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Quotation $quotation): Response
    {
        Gate::authorize('update', $quotation);
        abort_unless($quotation->isEditable(), 422, __('Only draft or rejected quotations can be edited.'));

        $quotation->load(['items.productService:id,code,name,type']);

        return Inertia::render('quotations/Edit', [
            'quotation' => $this->quotationFormPayload($quotation),
            ...$this->formOptions($request->user()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateQuotationRequest $request, Quotation $quotation, AuditLogger $audit): RedirectResponse
    {
        abort_unless($quotation->isEditable(), 422, __('Only draft or rejected quotations can be edited.'));

        $validated = $request->validated();
        $oldValues = $quotation->only($this->auditedAttributes());

        DB::transaction(function () use ($quotation, $validated): void {
            $attributes = [
                ...$this->quotationAttributes($validated),
                ...$this->calculateTotals($validated['items']),
                'status' => $quotation->status === Quotation::STATUS_REJECTED
                    ? Quotation::STATUS_DRAFT
                    : $quotation->status,
            ];

            if ((int) $quotation->company_id !== (int) $validated['company_id'] || $quotation->quotation_template_snapshot === null) {
                $company = Company::query()
                    ->with('activeQuotationTemplate')
                    ->whereKey($validated['company_id'])
                    ->firstOrFail();
                $template = $company->activeQuotationTemplate()->first();
                $attributes['quotation_template_id'] = $template?->id;
                $attributes['quotation_template_snapshot'] = $template?->snapshot();
            }

            $quotation->update([
                ...$attributes,
            ]);

            $this->syncItems($quotation, $validated['items']);
        });
        $audit->recordChanges('quotations', 'updated', $quotation, $oldValues, $quotation->getChanges(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation updated.')]);

        return to_route('quotations.show', $quotation);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Quotation $quotation, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $quotation);
        abort_unless($quotation->isEditable(), 422, __('Only draft or rejected quotations can be deleted.'));
        $oldValues = $quotation->only($this->auditedAttributes());

        $quotation->delete();
        $audit->record('quotations', 'deleted', $quotation, oldValues: $oldValues, request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation deleted.')]);

        return to_route('quotations.index');
    }

    public function submit(Request $request, Quotation $quotation, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('submit', $quotation);

        return $this->transition($request, $audit, $quotation, Quotation::STATUS_FOR_APPROVAL, 'submitted_for_approval', __('Quotation submitted for approval.'));
    }

    public function approve(Request $request, Quotation $quotation, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('approve', $quotation);

        return $this->transition($request, $audit, $quotation, Quotation::STATUS_APPROVED, 'approved', __('Quotation approved.'));
    }

    public function reject(Request $request, Quotation $quotation, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('reject', $quotation);

        return $this->transition($request, $audit, $quotation, Quotation::STATUS_REJECTED, 'rejected', __('Quotation rejected.'));
    }

    public function cancel(Request $request, Quotation $quotation, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $quotation);

        return $this->transition($request, $audit, $quotation, Quotation::STATUS_CANCELLED, 'cancelled', __('Quotation cancelled.'));
    }

    public function print(Request $request, Quotation $quotation, QuotationPdfRenderer $renderer): HttpResponse
    {
        Gate::authorize('print', $quotation);

        $quotation->load([
            'company:id,company_code,company_name,email,phone,address,tin',
            'client:id,client_code,client_name,email,phone,billing_address,shipping_address,tin',
            'items.productService:id,code,name,type',
            'creator:id,name',
        ]);

        // try {
        //     $pdf = $renderer->render($quotation);
        // } catch (RuntimeException $exception) {
        //     abort(422, $exception->getMessage());
        // }
        try {
            $pdf = $renderer
                ->debug(app()->isLocal() && $request->boolean('debug'))
                ->render($quotation);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }
        $filename = preg_replace('/[^\w\-]+/', '-', $quotation->quotation_no).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function transition(Request $request, AuditLogger $audit, Quotation $quotation, string $status, string $action, string $message): RedirectResponse
    {
        abort_unless($quotation->canTransitionTo($status), 422, __('This status transition is not allowed.'));
        $oldValues = $quotation->only(['status']);

        $quotation->update(['status' => $status]);
        $audit->recordChanges('quotations', $action, $quotation, $oldValues, $quotation->getChanges(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function quotationAttributes(array $validated): array
    {
        return [
            'company_id' => $validated['company_id'],
            'client_id' => $validated['client_id'],
            'quotation_date' => $validated['quotation_date'],
            'valid_until' => $validated['valid_until'] ?? null,
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
    private function syncItems(Quotation $quotation, array $items): void
    {
        $quotation->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $lineBase = round(((float) $item['quantity']) * ((float) $item['unit_price']), 2);
            $discount = round((float) ($item['discount'] ?? 0), 2);
            $tax = round((float) ($item['tax'] ?? 0), 2);

            $quotation->items()->create([
                'product_service_id' => $item['product_service_id'] ?? null,
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
            'clients' => $this->clientOptions($user),
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
    private function quotationSummaryPayload(Quotation $quotation, ?User $user = null): array
    {
        $payload = [
            'id' => $quotation->id,
            'quotation_no' => $quotation->quotation_no,
            'quotation_date' => $quotation->quotation_date->toDateString(),
            'valid_until' => $quotation->valid_until?->toDateString(),
            'currency' => $quotation->currency,
            'status' => $quotation->status,
            'total_amount' => $quotation->total_amount,
            'company' => [
                'id' => $quotation->company?->id,
                'company_code' => $quotation->company?->company_code,
                'company_name' => $quotation->company?->company_name,
            ],
            'client' => [
                'id' => $quotation->client?->id,
                'client_code' => $quotation->client?->client_code,
                'client_name' => $quotation->client?->client_name,
            ],
        ];

        if ($user instanceof User) {
            $payload['can'] = [
                'print' => $user->can('print', $quotation),
            ];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function quotationPayload(Quotation $quotation): array
    {
        return [
            ...$this->quotationSummaryPayload($quotation),
            'notes' => $quotation->notes,
            'terms_conditions' => $quotation->terms_conditions,
            'quotation_template' => $this->quotationTemplatePayload($quotation),
            'subtotal' => $quotation->subtotal,
            'discount' => $quotation->discount,
            'tax_amount' => $quotation->tax_amount,
            'created_at' => $quotation->created_at?->toDateString(),
            'company' => $quotation->company ? [
                'id' => $quotation->company->id,
                'company_code' => $quotation->company->company_code,
                'company_name' => $quotation->company->company_name,
                'tin' => $quotation->company->tin ?? null,
                'email' => $quotation->company->email ?? null,
                'phone' => $quotation->company->phone ?? null,
                'address' => $quotation->company->address ?? null,
            ] : null,
            'client' => $quotation->client ? [
                'id' => $quotation->client->id,
                'client_code' => $quotation->client->client_code,
                'client_name' => $quotation->client->client_name,
                'tin' => $quotation->client->tin ?? null,
                'email' => $quotation->client->email ?? null,
                'phone' => $quotation->client->phone ?? null,
                'billing_address' => $quotation->client->billing_address ?? null,
                'shipping_address' => $quotation->client->shipping_address ?? null,
            ] : null,
            'items' => $quotation->relationLoaded('items')
                ? $quotation->items->map(fn (QuotationItem $item): array => $this->quotationItemPayload($item))->values()
                : [],
            'creator' => $quotation->creator ? [
                'id' => $quotation->creator->id,
                'name' => $quotation->creator->name,
            ] : null,
            'documents' => $quotation->relationLoaded('documents')
                ? $quotation->documents->map->toPayload()->values()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function quotationFormPayload(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'quotation_no' => $quotation->quotation_no,
            'company_id' => $quotation->company_id,
            'client_id' => $quotation->client_id,
            'quotation_date' => $quotation->quotation_date->toDateString(),
            'valid_until' => $quotation->valid_until?->toDateString(),
            'currency' => $quotation->currency,
            'notes' => $quotation->notes,
            'terms_conditions' => $quotation->terms_conditions,
            'items' => $quotation->items->map(fn (QuotationItem $item): array => [
                'product_service_id' => $item->product_service_id,
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
    private function quotationItemPayload(QuotationItem $item): array
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
            'discount' => $item->discount,
            'tax' => $item->tax,
            'line_total' => $item->line_total,
            'sort_order' => $item->sort_order,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function quotationPermissions(User $user, Quotation $quotation): array
    {
        return [
            'edit' => $user->can('update', $quotation) && $quotation->isEditable(),
            'delete' => $user->can('delete', $quotation) && $quotation->isEditable(),
            'submit' => $user->can('submit', $quotation) && $quotation->canTransitionTo(Quotation::STATUS_FOR_APPROVAL),
            'approve' => $user->can('approve', $quotation) && $quotation->canTransitionTo(Quotation::STATUS_APPROVED),
            'reject' => $user->can('reject', $quotation) && $quotation->canTransitionTo(Quotation::STATUS_REJECTED),
            'cancel' => $user->can('update', $quotation) && $quotation->canTransitionTo(Quotation::STATUS_CANCELLED),
            'print' => $user->can('print', $quotation),
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
        return ['quotation_no', 'company_id', 'client_id', 'quotation_date', 'valid_until', 'currency', 'status', 'quotation_template_id', 'quotation_template_snapshot', 'subtotal', 'discount', 'tax_amount', 'total_amount'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function quotationTemplatePayload(Quotation $quotation): ?array
    {
        $snapshot = $quotation->quotation_template_snapshot;

        if (! is_array($snapshot)) {
            return null;
        }

        $storedPath = (string) ($snapshot['stored_path'] ?? '');

        return [
            'id' => $snapshot['id'] ?? null,
            'name' => $snapshot['name'] ?? null,
            'original_filename' => $snapshot['original_filename'] ?? null,
            'file_type' => $snapshot['file_type'] ?? null,
            'file_url' => $this->quotationTemplatePathIsSafe($storedPath)
                ? Storage::disk('public')->url($storedPath)
                : null,
            'config' => $snapshot['config'] ?? null,
        ];
    }

    private function quotationTemplatePathIsSafe(string $path): bool
    {
        return ! str_contains($path, '..') && str_starts_with($path, 'quotation-templates/');
    }
}
