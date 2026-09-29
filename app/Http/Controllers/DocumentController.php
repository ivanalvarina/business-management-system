<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\Client;
use App\Models\ClientPurchaseOrder;
use App\Models\Company;
use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Vendor;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Document::class);

        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->trim()->toString();
        $user = $request->user();
        $typeMap = $this->documentableTypes();

        return Inertia::render('documents/Index', [
            'filters' => [
                'search' => $search,
                'type' => $type === '' ? 'all' : $type,
            ],
            'types' => array_keys($typeMap),
            'documents' => Document::query()
                ->with(['uploader:id,name', 'documentable'])
                ->whereHasMorph('documentable', array_values($typeMap), fn (Builder $query, string $documentableType) => $this->scopeVisibleParent($query, $documentableType, $user))
                ->when(isset($typeMap[$type]), fn (Builder $query) => $query->where('documentable_type', $typeMap[$type]))
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('original_filename', 'like', "%{$search}%")
                            ->orWhere('document_type', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(10)
                ->withQueryString()
                ->through(fn (Document $document): array => [
                    ...$document->toPayload(),
                    'parent' => $this->parentPayload($document),
                ]),
            'can' => [
                'create' => $user->can('create', Document::class),
                'download' => $user->can('documents.download'),
                'delete' => $user->can('documents.delete'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Document::class);

        return Inertia::render('documents/Create', [
            'parentOptions' => $this->parentOptions($request->user()),
            'types' => array_keys($this->documentableTypes()),
        ]);
    }

    public function store(StoreDocumentRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $parent = $this->resolveParent(
            (string) $validated['documentable_type'],
            (int) $validated['documentable_id'],
        );

        Gate::authorize('view', $parent);

        $file = $request->file('file');
        $storedPath = $file->store('documents', 'local');

        $document = $this->createDocument($parent, [
            'document_type' => $validated['document_type'],
            'original_filename' => $this->safeOriginalFilename($file->getClientOriginalName()),
            'stored_path' => $storedPath,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
            'expiration_date' => $validated['expiration_date'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);
        $audit->record('documents', 'uploaded', $document, newValues: [
            'document_type' => $document->document_type,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'documentable_type' => class_basename($document->documentable_type),
            'documentable_id' => $document->documentable_id,
        ], request: $request);

        return back();
    }

    public function download(Document $document): StreamedResponse
    {
        Gate::authorize('download', $document);
        abort_unless($this->storedPathIsSafe($document->stored_path), 404);
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download(
            $document->stored_path,
            $document->original_filename,
        );
    }

    public function destroy(Request $request, Document $document, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $document);
        $oldValues = [
            'document_type' => $document->document_type,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'documentable_type' => class_basename($document->documentable_type),
            'documentable_id' => $document->documentable_id,
        ];

        if ($this->storedPathIsSafe($document->stored_path)) {
            Storage::disk('local')->delete($document->stored_path);
        }

        $document->delete();
        $audit->record('documents', 'deleted', $document, oldValues: $oldValues, request: $request);

        return back();
    }

    private function resolveParent(string $type, int $id): Model
    {
        $class = $this->documentableTypes()[$type] ?? null;

        abort_unless($class !== null, 422, __('Unsupported document parent type.'));

        return $class::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDocument(Model $parent, array $attributes): Document
    {
        return match (true) {
            $parent instanceof Company => $parent->documents()->create($attributes),
            $parent instanceof Client => $parent->documents()->create($attributes),
            $parent instanceof Vendor => $parent->documents()->create($attributes),
            $parent instanceof Quotation => $parent->documents()->create($attributes),
            $parent instanceof ClientPurchaseOrder => $parent->documents()->create($attributes),
            $parent instanceof PurchaseOrder => $parent->documents()->create($attributes),
            default => abort(422, __('Unsupported document parent type.')),
        };
    }

    /**
     * @return array<string, class-string<Model>>
     */
    private function documentableTypes(): array
    {
        return [
            'company' => Company::class,
            'client' => Client::class,
            'vendor' => Vendor::class,
            'quotation' => Quotation::class,
            'client_purchase_order' => ClientPurchaseOrder::class,
            'purchase_order' => PurchaseOrder::class,
        ];
    }

    private function safeOriginalFilename(string $filename): string
    {
        $basename = pathinfo($filename, PATHINFO_BASENAME);
        $clean = trim((string) preg_replace('/[^\w.\- ()]/', '_', $basename));

        return Str::limit($clean === '' ? 'document' : $clean, 255, '');
    }

    /**
     * @return array<int, array{id: int, type: string, label: string, meta: string|null}>
     */
    private function parentOptions(User $user): array
    {
        return [
            ...Company::query()
                ->select(['id', 'company_code', 'company_name'])
                ->tap(fn (Builder $query) => $this->scopeCompany($query, $user, 'companies.view'))
                ->orderBy('company_name')
                ->get()
                ->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'type' => 'company',
                    'label' => $company->company_name,
                    'meta' => $company->company_code,
                ])
                ->all(),
            ...Client::query()
                ->select(['id', 'client_code', 'client_name'])
                ->tap(fn (Builder $query) => $this->scopeCompanyRelationship($query, $user, 'clients.view', 'companies'))
                ->orderBy('client_name')
                ->get()
                ->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'type' => 'client',
                    'label' => $client->client_name,
                    'meta' => $client->client_code,
                ])
                ->all(),
            ...Vendor::query()
                ->select(['id', 'vendor_code', 'vendor_name'])
                ->tap(fn (Builder $query) => $this->scopeCompanyRelationship($query, $user, 'vendors.view', 'companies'))
                ->orderBy('vendor_name')
                ->get()
                ->map(fn (Vendor $vendor): array => [
                    'id' => $vendor->id,
                    'type' => 'vendor',
                    'label' => $vendor->vendor_name,
                    'meta' => $vendor->vendor_code,
                ])
                ->all(),
            ...Quotation::query()
                ->select(['id', 'quotation_no', 'company_id'])
                ->tap(fn (Builder $query) => $this->scopeCompanyRelationship($query, $user, 'quotations.view', 'company'))
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (Quotation $quotation): array => [
                    'id' => $quotation->id,
                    'type' => 'quotation',
                    'label' => $quotation->quotation_no,
                    'meta' => null,
                ])
                ->all(),
            ...ClientPurchaseOrder::query()
                ->select(['id', 'client_po_no', 'company_id'])
                ->tap(fn (Builder $query) => $this->scopeCompanyRelationship($query, $user, 'client-pos.view', 'company'))
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (ClientPurchaseOrder $clientPurchaseOrder): array => [
                    'id' => $clientPurchaseOrder->id,
                    'type' => 'client_purchase_order',
                    'label' => $clientPurchaseOrder->client_po_no,
                    'meta' => null,
                ])
                ->all(),
            ...PurchaseOrder::query()
                ->select(['id', 'po_no', 'company_id'])
                ->tap(fn (Builder $query) => $this->scopeCompanyRelationship($query, $user, 'purchase-orders.view', 'company'))
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (PurchaseOrder $purchaseOrder): array => [
                    'id' => $purchaseOrder->id,
                    'type' => 'purchase_order',
                    'label' => $purchaseOrder->po_no,
                    'meta' => null,
                ])
                ->all(),
        ];
    }

    private function storedPathIsSafe(string $path): bool
    {
        return ! Str::contains($path, ['..', '\\']) && Str::startsWith($path, 'documents/');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function scopeVisibleParent(Builder $query, string $documentableType, User $user): void
    {
        match ($documentableType) {
            Company::class => $this->scopeCompany($query, $user, 'companies.view'),
            Client::class => $this->scopeCompanyRelationship($query, $user, 'clients.view', 'companies'),
            Vendor::class => $this->scopeCompanyRelationship($query, $user, 'vendors.view', 'companies'),
            Quotation::class => $this->scopeCompanyRelationship($query, $user, 'quotations.view', 'company'),
            ClientPurchaseOrder::class => $this->scopeCompanyRelationship($query, $user, 'client-pos.view', 'company'),
            PurchaseOrder::class => $this->scopeCompanyRelationship($query, $user, 'purchase-orders.view', 'company'),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function scopeCompany(Builder $query, User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (! $user->hasGlobalCompanyAccess()) {
            $query->whereHas('users', fn (Builder $query) => $query->whereKey($user->id));
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function scopeCompanyRelationship(Builder $query, User $user, string $permission, string $relationship): void
    {
        if (! $user->can($permission)) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (! $user->hasGlobalCompanyAccess()) {
            $query->whereHas(
                $relationship === 'companies' ? 'companies.users' : 'company.users',
                fn (Builder $query) => $query->whereKey($user->id),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parentPayload(Document $document): array
    {
        $parent = $document->documentable;

        return match (true) {
            $parent instanceof Company => ['type' => 'company', 'label' => $parent->company_name],
            $parent instanceof Client => ['type' => 'client', 'label' => $parent->client_name],
            $parent instanceof Vendor => ['type' => 'vendor', 'label' => $parent->vendor_name],
            $parent instanceof Quotation => ['type' => 'quotation', 'label' => $parent->quotation_no],
            $parent instanceof ClientPurchaseOrder => ['type' => 'client_purchase_order', 'label' => $parent->client_po_no],
            $parent instanceof PurchaseOrder => ['type' => 'purchase_order', 'label' => $parent->po_no],
            default => ['type' => 'unknown', 'label' => 'Unknown'],
        };
    }
}
