<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $companyContext = [
            'current' => null,
            'options' => [],
        ];

        if ($user !== null) {
            $companies = Company::query()
                ->select(['id', 'company_code', 'company_name'])
                ->active()
                ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                    'users',
                    fn (Builder $query) => $query->whereKey($user->id),
                ))
                ->orderBy('company_name')
                ->orderBy('id')
                ->get();

            $currentCompany = $companies->firstWhere('id', (int) $request->session()->get('current_company_id'))
                ?? $companies->first();

            if ($currentCompany !== null) {
                $request->session()->put('current_company_id', $currentCompany->id);
            } else {
                $request->session()->forget('current_company_id');
            }

            $companyContext = [
                'current' => $currentCompany === null ? null : [
                    'id' => $currentCompany->id,
                    'company_code' => $currentCompany->company_code,
                    'company_name' => $currentCompany->company_name,
                ],
                'options' => $companies
                    ->map(fn (Company $company): array => [
                        'id' => $company->id,
                        'company_code' => $company->company_code,
                        'company_name' => $company->company_name,
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => $user?->getAllPermissions()
                    ->pluck('name')
                    ->values()
                    ->all() ?? [],
            ],
            'companyContext' => $companyContext,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
