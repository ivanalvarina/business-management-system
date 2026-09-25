<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Client::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $companyId = $request->integer('company_id');
        $user = $request->user();

        if ($companyId > 0 && ! $this->userCanAccessCompanyId($user, $companyId)) {
            abort(403);
        }

        return Inertia::render('clients/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'all' : $status,
                'company_id' => $companyId > 0 ? $companyId : null,
            ],
            'companies' => $this->companyOptions($user),
            'clients' => Client::query()
                ->select(['id', 'client_code', 'client_name', 'trade_name', 'tin', 'email', 'phone', 'status', 'created_at'])
                ->with(['companies:id,company_code,company_name'])
                ->withCount('contactPeople')
                ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                    'companies.users',
                    fn (Builder $query) => $query->whereKey($user->id),
                ))
                ->when($companyId > 0, fn (Builder $query) => $query->whereHas(
                    'companies',
                    fn (Builder $query) => $query->whereKey($companyId),
                ))
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('client_code', 'like', "%{$search}%")
                            ->orWhere('client_name', 'like', "%{$search}%")
                            ->orWhere('trade_name', 'like', "%{$search}%")
                            ->orWhere('tin', 'like', "%{$search}%");
                    });
                })
                ->when(in_array($status, [Client::STATUS_ACTIVE, Client::STATUS_INACTIVE], true), fn (Builder $query) => $query->where('status', $status))
                ->orderBy('client_name')
                ->orderBy('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (Client $client): array => [
                    'id' => $client->id,
                    'client_code' => $client->client_code,
                    'client_name' => $client->client_name,
                    'trade_name' => $client->trade_name,
                    'tin' => $client->tin,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'status' => $client->status,
                    'created_at' => $client->created_at?->toDateString(),
                    'contacts_count' => $client->contact_people_count,
                    'companies' => $client->companies->map(fn (Company $company): array => [
                        'id' => $company->id,
                        'company_code' => $company->company_code,
                        'company_name' => $company->company_name,
                    ])->values(),
                ]),
            'can' => [
                'create' => $request->user()->can('create', Client::class),
                'edit' => $request->user()->can('clients.edit'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Client::class);

        return Inertia::render('clients/Create', [
            'companies' => $this->companyOptions($request->user()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClientRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $client = DB::transaction(function () use ($request, $validated): Client {
            $client = Client::create([
                ...$this->clientAttributes($validated),
                'created_by' => $request->user()->id,
            ]);

            $this->syncCompanies($client, $validated['company_ids']);
            $this->syncContactPeople($client, $validated['contacts'] ?? []);

            return $client;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return to_route('clients.show', $client);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Client $client): Response
    {
        Gate::authorize('view', $client);

        $client->load(['companies:id,company_code,company_name,status', 'contactPeople']);

        return Inertia::render('clients/Show', [
            'client' => $this->clientPayload($client),
            'can' => [
                'edit' => $request->user()->can('update', $client),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Client $client): Response
    {
        Gate::authorize('update', $client);

        $client->load(['companies:id', 'contactPeople']);

        return Inertia::render('clients/Edit', [
            'client' => [
                ...$this->clientPayload($client),
                'company_ids' => $client->companies->pluck('id')->values(),
            ],
            'companies' => $this->companyOptions($request->user()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($client, $validated): void {
            $client->update($this->clientAttributes($validated));
            $this->syncCompanies($client, $validated['company_ids']);
            $this->syncContactPeople($client, $validated['contacts'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return to_route('clients.show', $client);
    }

    public function activate(Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $client->update(['status' => Client::STATUS_ACTIVE]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client activated.')]);

        return back();
    }

    public function deactivate(Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $client->update(['status' => Client::STATUS_INACTIVE]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deactivated.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function clientAttributes(array $validated): array
    {
        return [
            'client_code' => $validated['client_code'],
            'client_name' => $validated['client_name'],
            'trade_name' => $validated['trade_name'] ?? null,
            'tin' => $validated['tin'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'billing_address' => $validated['billing_address'] ?? null,
            'shipping_address' => $validated['shipping_address'] ?? null,
            'status' => $validated['status'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(Client $client): array
    {
        return [
            'id' => $client->id,
            'client_code' => $client->client_code,
            'client_name' => $client->client_name,
            'trade_name' => $client->trade_name,
            'tin' => $client->tin,
            'email' => $client->email,
            'phone' => $client->phone,
            'billing_address' => $client->billing_address,
            'shipping_address' => $client->shipping_address,
            'status' => $client->status,
            'created_at' => $client->created_at?->toDateString(),
            'companies' => $client->relationLoaded('companies')
                ? $client->companies->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'company_code' => $company->company_code,
                    'company_name' => $company->company_name,
                    'status' => $company->status,
                ])->values()
                : [],
            'contacts' => $client->relationLoaded('contactPeople')
                ? $client->contactPeople->map(fn ($contact): array => [
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
    private function syncCompanies(Client $client, array $companyIds): void
    {
        $client->companies()->syncWithPivotValues(
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
    private function syncContactPeople(Client $client, array $contacts): void
    {
        $normalizedContacts = collect($contacts)
            ->filter(fn (array $contact): bool => filled($contact['name'] ?? null))
            ->values();

        $primaryIndex = $normalizedContacts->search(fn (array $contact): bool => (bool) ($contact['is_primary'] ?? false));

        if ($primaryIndex === false && $normalizedContacts->isNotEmpty()) {
            $primaryIndex = 0;
        }

        $client->contactPeople()->delete();

        $normalizedContacts->each(function (array $contact, int $index) use ($client, $primaryIndex): void {
            $client->contactPeople()->create([
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
