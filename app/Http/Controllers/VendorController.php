<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Vendor::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $companyId = $request->integer('company_id');
        $categoryId = $request->integer('vendor_category_id');
        $user = $request->user();

        if ($companyId > 0 && ! $this->userCanAccessCompanyId($user, $companyId)) {
            abort(403);
        }

        return Inertia::render('vendors/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'all' : $status,
                'company_id' => $companyId > 0 ? $companyId : null,
                'vendor_category_id' => $categoryId > 0 ? $categoryId : null,
            ],
            'companies' => $this->companyOptions($user),
            'categories' => $this->categoryOptions(),
            'vendors' => Vendor::query()
                ->select(['id', 'vendor_code', 'vendor_name', 'trade_name', 'tin', 'email', 'phone', 'vendor_category_id', 'status', 'created_at'])
                ->with(['companies:id,company_code,company_name', 'category:id,name'])
                ->withCount('contactPeople')
                ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                    'companies.users',
                    fn (Builder $query) => $query->whereKey($user->id),
                ))
                ->when($companyId > 0, fn (Builder $query) => $query->whereHas(
                    'companies',
                    fn (Builder $query) => $query->whereKey($companyId),
                ))
                ->when($categoryId > 0, fn (Builder $query) => $query->where('vendor_category_id', $categoryId))
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('vendor_code', 'like', "%{$search}%")
                            ->orWhere('vendor_name', 'like', "%{$search}%")
                            ->orWhere('trade_name', 'like', "%{$search}%")
                            ->orWhere('tin', 'like', "%{$search}%");
                    });
                })
                ->when(in_array($status, [Vendor::STATUS_ACTIVE, Vendor::STATUS_INACTIVE], true), fn (Builder $query) => $query->where('status', $status))
                ->orderBy('vendor_name')
                ->orderBy('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (Vendor $vendor): array => [
                    'id' => $vendor->id,
                    'vendor_code' => $vendor->vendor_code,
                    'vendor_name' => $vendor->vendor_name,
                    'trade_name' => $vendor->trade_name,
                    'tin' => $vendor->tin,
                    'email' => $vendor->email,
                    'phone' => $vendor->phone,
                    'status' => $vendor->status,
                    'created_at' => $vendor->created_at?->toDateString(),
                    'contacts_count' => $vendor->contact_people_count,
                    'category' => $vendor->category === null ? null : [
                        'id' => $vendor->category->id,
                        'name' => $vendor->category->name,
                    ],
                    'companies' => $vendor->companies->map(fn (Company $company): array => [
                        'id' => $company->id,
                        'company_code' => $company->company_code,
                        'company_name' => $company->company_name,
                    ])->values(),
                ]),
            'can' => [
                'create' => $request->user()->can('create', Vendor::class),
                'edit' => $request->user()->can('vendors.edit'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Vendor::class);

        return Inertia::render('vendors/Create', [
            'companies' => $this->companyOptions($request->user()),
            'categories' => $this->categoryOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVendorRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $vendor = DB::transaction(function () use ($request, $validated): Vendor {
            $vendor = Vendor::create([
                ...$this->vendorAttributes($validated),
                'vendor_category_id' => $this->resolveCategoryId($validated),
                'created_by' => $request->user()->id,
            ]);

            $this->syncCompanies($vendor, $validated['company_ids']);
            $this->syncContactPeople($vendor, $validated['contacts'] ?? []);

            return $vendor;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor created.')]);

        return to_route('vendors.show', $vendor);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Vendor $vendor): Response
    {
        Gate::authorize('view', $vendor);

        $vendor->load(['companies:id,company_code,company_name,status', 'category:id,name,status', 'contactPeople']);

        return Inertia::render('vendors/Show', [
            'vendor' => $this->vendorPayload($vendor),
            'can' => [
                'edit' => $request->user()->can('update', $vendor),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Vendor $vendor): Response
    {
        Gate::authorize('update', $vendor);

        $vendor->load(['companies:id', 'category:id,name,status', 'contactPeople']);

        return Inertia::render('vendors/Edit', [
            'vendor' => [
                ...$this->vendorPayload($vendor),
                'company_ids' => $vendor->companies->pluck('id')->values(),
            ],
            'companies' => $this->companyOptions($request->user()),
            'categories' => $this->categoryOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($vendor, $validated): void {
            $vendor->update([
                ...$this->vendorAttributes($validated),
                'vendor_category_id' => $this->resolveCategoryId($validated),
            ]);
            $this->syncCompanies($vendor, $validated['company_ids']);
            $this->syncContactPeople($vendor, $validated['contacts'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor updated.')]);

        return to_route('vendors.show', $vendor);
    }

    public function activate(Vendor $vendor): RedirectResponse
    {
        Gate::authorize('update', $vendor);

        $vendor->update(['status' => Vendor::STATUS_ACTIVE]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor activated.')]);

        return back();
    }

    public function deactivate(Vendor $vendor): RedirectResponse
    {
        Gate::authorize('update', $vendor);

        $vendor->update(['status' => Vendor::STATUS_INACTIVE]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor deactivated.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function vendorAttributes(array $validated): array
    {
        return [
            'vendor_code' => $validated['vendor_code'],
            'vendor_name' => $validated['vendor_name'],
            'trade_name' => $validated['trade_name'] ?? null,
            'tin' => $validated['tin'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'status' => $validated['status'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vendorPayload(Vendor $vendor): array
    {
        return [
            'id' => $vendor->id,
            'vendor_code' => $vendor->vendor_code,
            'vendor_name' => $vendor->vendor_name,
            'trade_name' => $vendor->trade_name,
            'tin' => $vendor->tin,
            'email' => $vendor->email,
            'phone' => $vendor->phone,
            'address' => $vendor->address,
            'vendor_category_id' => $vendor->vendor_category_id,
            'status' => $vendor->status,
            'created_at' => $vendor->created_at?->toDateString(),
            'category' => $vendor->relationLoaded('category') && $vendor->category !== null
                ? [
                    'id' => $vendor->category->id,
                    'name' => $vendor->category->name,
                    'status' => $vendor->category->status,
                ]
                : null,
            'companies' => $vendor->relationLoaded('companies')
                ? $vendor->companies->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'company_code' => $company->company_code,
                    'company_name' => $company->company_name,
                    'status' => $company->status,
                ])->values()
                : [],
            'contacts' => $vendor->relationLoaded('contactPeople')
                ? $vendor->contactPeople->map(fn ($contact): array => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'position' => $contact->position,
                    'department' => $contact->department,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'mobile' => $contact->mobile,
                    'is_primary' => $contact->is_primary,
                ])->values()
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveCategoryId(array $validated): int
    {
        if (filled($validated['new_category_name'] ?? null)) {
            return VendorCategory::create([
                'name' => $validated['new_category_name'],
                'status' => VendorCategory::STATUS_ACTIVE,
            ])->id;
        }

        return (int) $validated['vendor_category_id'];
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
     * @return array<int, array{id: int, name: string}>
     */
    private function categoryOptions(): array
    {
        return VendorCategory::query()
            ->select(['id', 'name'])
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (VendorCategory $category): array => [
                'id' => (int) $category->id,
                'name' => $category->name,
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
     * @param  array<int>  $companyIds
     */
    private function syncCompanies(Vendor $vendor, array $companyIds): void
    {
        $vendor->companies()->syncWithPivotValues(
            Company::query()
                ->whereIn('id', $companyIds)
                ->pluck('id')
                ->all(),
            ['status' => Company::STATUS_ACTIVE],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     */
    private function syncContactPeople(Vendor $vendor, array $contacts): void
    {
        $normalizedContacts = collect($contacts)
            ->filter(fn (array $contact): bool => filled($contact['name'] ?? null))
            ->values();

        $primaryIndex = $normalizedContacts->search(fn (array $contact): bool => (bool) ($contact['is_primary'] ?? false));

        if ($primaryIndex === false && $normalizedContacts->isNotEmpty()) {
            $primaryIndex = 0;
        }

        $vendor->contactPeople()->delete();

        $normalizedContacts->each(function (array $contact, int $index) use ($vendor, $primaryIndex): void {
            $vendor->contactPeople()->create([
                'name' => $contact['name'],
                'position' => $contact['position'] ?? null,
                'department' => $contact['department'] ?? null,
                'email' => $contact['email'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'mobile' => $contact['mobile'] ?? null,
                'is_primary' => $index === $primaryIndex,
            ]);
        });
    }
}
