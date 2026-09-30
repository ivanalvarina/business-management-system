<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\QuotationTemplate;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Company::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $user = $request->user();

        return Inertia::render('companies/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'all' : $status,
            ],
            'companies' => Company::query()
                ->select(['id', 'company_code', 'company_name', 'trade_name', 'email', 'phone', 'status', 'created_at'])
                ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                    'users',
                    fn (Builder $query) => $query->whereKey($user->id),
                ))
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('company_code', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%")
                            ->orWhere('trade_name', 'like', "%{$search}%")
                            ->orWhere('tin', 'like', "%{$search}%");
                    });
                })
                ->when(in_array($status, [Company::STATUS_ACTIVE, Company::STATUS_INACTIVE], true), fn (Builder $query) => $query->where('status', $status))
                ->orderBy('company_name')
                ->orderBy('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (Company $company): array => [
                    'id' => $company->id,
                    'company_code' => $company->company_code,
                    'company_name' => $company->company_name,
                    'trade_name' => $company->trade_name,
                    'email' => $company->email,
                    'phone' => $company->phone,
                    'status' => $company->status,
                    'created_at' => $company->created_at?->toDateString(),
                ]),
            'can' => [
                'create' => $request->user()->can('create', Company::class),
                'edit' => $request->user()->can('companies.edit'),
                'delete' => $request->user()->can('companies.delete'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Company::class);

        return Inertia::render('companies/Create', [
            'users' => $this->userOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompanyRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company = Company::create($this->companyAttributes($validated));
        $this->storeQuotationTemplate($company, $validated, $request);

        $userIds = $validated['user_ids'] ?? [];

        if (! $request->user()->hasGlobalCompanyAccess()) {
            $userIds[] = $request->user()->id;
        }

        $this->syncUsers($company, $userIds);
        $audit->record('companies', 'created', $company, newValues: $company->only($this->auditedAttributes()), request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company created.')]);

        return to_route('companies.show', $company);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Company $company): Response
    {
        Gate::authorize('view', $company);

        $company->load(['users:id,name,email', 'documents.uploader:id,name', 'activeQuotationTemplate']);

        return Inertia::render('companies/Show', [
            'company' => $this->companyPayload($company),
            'activityLogs' => AuditLog::recentFor($company),
            'can' => [
                'edit' => $request->user()->can('update', $company),
                'delete' => $request->user()->can('delete', $company),
                'switch' => $request->user()->can('switch', $company),
                'uploadDocuments' => $request->user()->can('documents.upload'),
                'downloadDocuments' => $request->user()->can('documents.download'),
                'deleteDocuments' => $request->user()->can('documents.delete'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company): Response
    {
        Gate::authorize('update', $company);

        $company->load(['users:id', 'activeQuotationTemplate']);

        return Inertia::render('companies/Edit', [
            'company' => [
                ...$this->companyPayload($company),
                'user_ids' => $company->users->pluck('id')->values(),
            ],
            'users' => $this->userOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompanyRequest $request, Company $company, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $attributes = $this->companyAttributes($validated);
        $oldValues = $company->only($this->auditedAttributes());

        unset($attributes['logo']);

        if ($request->boolean('remove_logo') && $company->logo !== null) {
            Storage::disk('public')->delete($company->logo);
            $attributes['logo'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($company->logo !== null) {
                Storage::disk('public')->delete($company->logo);
            }

            $attributes['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company->update($attributes);
        $this->storeQuotationTemplate($company, $validated, $request);

        $userIds = $validated['user_ids'] ?? [];

        if (! $request->user()->hasGlobalCompanyAccess()) {
            $userIds[] = $request->user()->id;
        }

        $this->syncUsers($company, $userIds);
        $audit->recordChanges('companies', 'updated', $company, $oldValues, $company->getChanges(), $request);

        if ((int) $request->session()->get('current_company_id') === $company->id && ! $company->fresh()->isActive()) {
            $request->session()->forget('current_company_id');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return to_route('companies.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Company $company, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $company);
        $oldValues = $company->only($this->auditedAttributes());

        if ($company->logo !== null) {
            Storage::disk('public')->delete($company->logo);
        }

        $company->quotationTemplates->each(function (QuotationTemplate $template): void {
            if ($this->quotationTemplatePathIsSafe($template->stored_path)) {
                Storage::disk('public')->delete($template->stored_path);
            }
        });

        if ((int) $request->session()->get('current_company_id') === $company->id) {
            $request->session()->forget('current_company_id');
        }

        $company->delete();
        $audit->record('companies', 'deleted', $company, oldValues: $oldValues, request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company deleted.')]);

        return to_route('companies.index');
    }

    public function activate(Request $request, Company $company, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $company);
        $oldValues = $company->only(['status']);

        $company->update(['status' => Company::STATUS_ACTIVE]);
        $audit->recordChanges('companies', 'status_changed', $company, $oldValues, $company->getChanges(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company activated.')]);

        return back();
    }

    public function deactivate(Request $request, Company $company, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $company);
        $oldValues = $company->only(['status']);

        $company->update(['status' => Company::STATUS_INACTIVE]);
        $audit->recordChanges('companies', 'status_changed', $company, $oldValues, $company->getChanges(), $request);

        if ((int) $request->session()->get('current_company_id') === $company->id) {
            $request->session()->forget('current_company_id');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company deactivated.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function companyAttributes(array $validated): array
    {
        return [
            'company_code' => $validated['company_code'],
            'company_name' => $validated['company_name'],
            'trade_name' => $validated['trade_name'] ?? null,
            'tin' => $validated['tin'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'logo' => $validated['logo'] ?? null,
            'purchasing_assistant_name' => $validated['purchasing_assistant_name'] ?? null,
            'corporate_sales_manager_name' => $validated['corporate_sales_manager_name'] ?? null,
            'status' => $validated['status'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function companyPayload(Company $company): array
    {
        $quotationTemplate = $company->relationLoaded('activeQuotationTemplate')
            ? $company->activeQuotationTemplate
            : null;

        return [
            'id' => $company->id,
            'company_code' => $company->company_code,
            'company_name' => $company->company_name,
            'trade_name' => $company->trade_name,
            'tin' => $company->tin,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'logo' => $company->logo,
            'logo_url' => $company->logo === null ? null : Storage::disk('public')->url($company->logo),
            'purchasing_assistant_name' => $company->purchasing_assistant_name,
            'corporate_sales_manager_name' => $company->corporate_sales_manager_name,
            'quotation_template' => $quotationTemplate ? $this->quotationTemplatePayload($quotationTemplate) : null,
            'status' => $company->status,
            'created_at' => $company->created_at?->toDateString(),
            'users' => $company->relationLoaded('users')
                ? $company->users->map(fn ($user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])->values()
                : [],
            'documents' => $company->relationLoaded('documents')
                ? $company->documents->map->toPayload()->values()
                : [],
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function userOptions(): array
    {
        return User::query()
            ->select(['id', 'name', 'email'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();
    }

    /**
     * @param  array<int>  $userIds
     */
    private function syncUsers(Company $company, array $userIds): void
    {
        $company->users()->sync(User::query()
            ->whereIn('id', $userIds)
            ->pluck('id')
            ->all());
    }

    /**
     * @return array<int, string>
     */
    private function auditedAttributes(): array
    {
        return ['company_code', 'company_name', 'trade_name', 'tin', 'email', 'phone', 'address', 'logo', 'purchasing_assistant_name', 'corporate_sales_manager_name', 'status'];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function storeQuotationTemplate(Company $company, array $validated, Request $request): void
    {
        $file = $request->file('quotation_template_file');
        $activeTemplate = $company->activeQuotationTemplate()->first();
        $templateName = trim((string) ($validated['quotation_template_name'] ?? ''));
        $templateConfig = $this->decodeQuotationTemplateConfig($validated['quotation_template_config'] ?? null);

        if (! $file instanceof UploadedFile && $activeTemplate === null) {
            return;
        }

        $name = $templateName !== ''
            ? $templateName
            : ($activeTemplate?->name ?? __('Quotation template'));

        $storedPath = $activeTemplate?->stored_path;
        $originalFilename = $activeTemplate?->original_filename;
        $mimeType = $activeTemplate?->mime_type;
        $fileSize = $activeTemplate?->file_size;
        $fileType = $activeTemplate?->file_type ?? 'pdf';

        if ($file instanceof UploadedFile) {
            $storedPath = $file->store('quotation-templates', 'public');
            $originalFilename = $this->safeOriginalFilename($file->getClientOriginalName());
            $mimeType = $file->getMimeType() ?: 'application/pdf';
            $fileSize = $file->getSize();
            $fileType = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        } elseif (
            $activeTemplate !== null
            && $activeTemplate->name === $name
            && $activeTemplate->config === $templateConfig
        ) {
            return;
        }

        if ($storedPath === null || $originalFilename === null || $mimeType === null || $fileSize === null) {
            return;
        }

        $company->quotationTemplates()->update(['is_active' => false]);

        $company->quotationTemplates()->create([
            'name' => $name,
            'original_filename' => $originalFilename,
            'stored_path' => $storedPath,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_type' => $fileType,
            'config' => $templateConfig,
            'is_active' => true,
            'uploaded_by' => $request->user()?->id,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeQuotationTemplateConfig(mixed $config): ?array
    {
        $json = trim((string) $config);

        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'quotation_template_config' => __('The quotation template config must be a JSON object.'),
            ]);
        }

        return $decoded;
    }

    private function safeOriginalFilename(string $filename): string
    {
        $basename = pathinfo($filename, PATHINFO_BASENAME);
        $clean = trim((string) preg_replace('/[^\w.\- ()]/', '_', $basename));

        return mb_substr($clean === '' ? 'quotation-template.pdf' : $clean, 0, 255);
    }

    private function quotationTemplatePathIsSafe(string $path): bool
    {
        return ! str_contains($path, '..') && str_starts_with($path, 'quotation-templates/');
    }

    /**
     * @return array<string, mixed>
     */
    private function quotationTemplatePayload(QuotationTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'original_filename' => $template->original_filename,
            'file_url' => $template->file_url,
            'file_type' => $template->file_type,
            'config' => $template->config,
            'config_json' => $template->config === null
                ? ''
                : json_encode($template->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }
}
