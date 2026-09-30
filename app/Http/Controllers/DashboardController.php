<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientPurchaseOrder;
use App\Models\Company;
use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\ReceivingReceipt;
use App\Models\User;
use App\Models\Vendor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->user()->can('dashboard.view') || abort(403);

        $user = $request->user();
        $company = $this->currentCompany($request, $user);
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return Inertia::render('Dashboard', [
            'metrics' => array_values(array_filter([
                $company !== null && $user->can('clients.view') ? [
                    'label' => 'Active clients',
                    'value' => $this->clientQuery($company)->active()->count(),
                    'description' => 'Current company client records',
                ] : null,
                $company !== null && $user->can('vendors.view') ? [
                    'label' => 'Active vendors',
                    'value' => $this->vendorQuery($company)->active()->count(),
                    'description' => 'Current company vendor records',
                ] : null,
                $company !== null && $user->can('quotations.view') ? [
                    'label' => 'Quotations this month',
                    'value' => $this->quotationQuery($company)->whereBetween('quotation_date', [$monthStart, $monthEnd])->count(),
                    'description' => 'Created within current month',
                    'trend' => $this->monthlyCounts($this->quotationQuery($company), 'quotation_date'),
                    'delta' => $this->countDelta($this->quotationQuery($company), 'quotation_date'),
                ] : null,
                $company !== null && $user->can('quotations.view') ? [
                    'label' => 'Quotation value',
                    'value' => number_format((float) $this->quotationQuery($company)->whereBetween('quotation_date', [$monthStart, $monthEnd])->sum('total_amount'), 2),
                    'description' => 'Current month total value',
                    'trend' => $this->monthlySums($this->quotationQuery($company), 'quotation_date', 'total_amount'),
                    'delta' => $this->moneyDelta($this->quotationQuery($company), 'quotation_date', 'total_amount'),
                ] : null,
                $company !== null && $user->can('quotations.approve') ? [
                    'label' => 'Pending quotation approvals',
                    'value' => $this->quotationQuery($company)->where('status', Quotation::STATUS_FOR_APPROVAL)->count(),
                    'description' => 'Waiting for approval',
                ] : null,
                $company !== null && $user->can('client-pos.view') ? [
                    'label' => 'Client POs received',
                    'value' => $this->clientPurchaseOrderQuery($company)->whereBetween('po_date', [$monthStart, $monthEnd])->count(),
                    'description' => 'Received within current month',
                    'trend' => $this->monthlyCounts($this->clientPurchaseOrderQuery($company), 'po_date'),
                    'delta' => $this->countDelta($this->clientPurchaseOrderQuery($company), 'po_date'),
                ] : null,
                $company !== null && $user->can('purchase-orders.view') ? [
                    'label' => 'Vendor POs this month',
                    'value' => $this->purchaseOrderQuery($company)->whereBetween('po_date', [$monthStart, $monthEnd])->count(),
                    'description' => 'Issued within current month',
                    'trend' => $this->monthlyCounts($this->purchaseOrderQuery($company), 'po_date'),
                    'delta' => $this->countDelta($this->purchaseOrderQuery($company), 'po_date'),
                ] : null,
                $company !== null && $user->can('purchase-orders.approve') ? [
                    'label' => 'Pending PO approvals',
                    'value' => $this->purchaseOrderQuery($company)->where('status', PurchaseOrder::STATUS_FOR_APPROVAL)->count(),
                    'description' => 'Waiting for approval',
                ] : null,
            ])),
            'charts' => $company === null ? [] : [
                'quotationValueByMonth' => $user->can('quotations.view')
                    ? $this->monthlySeries($this->quotationQuery($company), 'quotation_date', 'total_amount')
                    : [],
                'purchaseRequestsByStatus' => $user->can('purchase-requests.view')
                    ? $this->purchaseRequestsByStatus($company)
                    : [],
                'documentsThisMonth' => $user->can('documents.view')
                    ? $this->documentsThisMonth($company, $monthStart, $monthEnd)
                    : [],
                'weeklyPurchaseOrders' => ($user->can('client-pos.view') || $user->can('purchase-orders.view'))
                    ? $this->weeklyPurchaseOrders($company, $user)
                    : null,
            ],
            'recentActivity' => $company !== null && $user->can('audit-logs.view')
                ? $this->recentActivity($company)
                : [],
        ]);
    }

    /**
     * @return Builder<Company>
     */
    private function companyQuery(User $user): Builder
    {
        return Company::query()
            ->select(['id', 'company_code', 'company_name'])
            ->active()
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                'users',
                fn (Builder $query) => $query->whereKey($user->id),
            ))
            ->orderBy('company_name')
            ->orderBy('id');
    }

    /**
     * @return Builder<Client>
     */
    private function clientQuery(Company $company): Builder
    {
        return Client::query()
            ->whereHas('companies', fn (Builder $query) => $query
                ->whereKey($company->id)
                ->where('company_client.status', Company::STATUS_ACTIVE));
    }

    /**
     * @return Builder<Vendor>
     */
    private function vendorQuery(Company $company): Builder
    {
        return Vendor::query()
            ->whereHas('companies', fn (Builder $query) => $query
                ->whereKey($company->id)
                ->where('company_vendor.status', Company::STATUS_ACTIVE));
    }

    /**
     * @return Builder<Quotation>
     */
    private function quotationQuery(Company $company): Builder
    {
        return Quotation::query()
            ->whereBelongsTo($company);
    }

    /**
     * @return Builder<ClientPurchaseOrder>
     */
    private function clientPurchaseOrderQuery(Company $company): Builder
    {
        return ClientPurchaseOrder::query()
            ->whereBelongsTo($company);
    }

    /**
     * @return Builder<PurchaseOrder>
     */
    private function purchaseOrderQuery(Company $company): Builder
    {
        return PurchaseOrder::query()
            ->whereBelongsTo($company);
    }

    private function currentCompany(Request $request, User $user): ?Company
    {
        $companies = $this->companyQuery($user)->get();
        $company = $companies->firstWhere('id', (int) $request->session()->get('current_company_id'))
            ?? $companies->first();

        if ($company === null) {
            $request->session()->forget('current_company_id');

            return null;
        }

        $request->session()->put('current_company_id', $company->id);

        return $company;
    }

    /**
     * @param  Builder<*>  $query
     * @return array<int, float|int>
     */
    private function monthlyCounts(Builder $query, string $dateColumn): array
    {
        return $this->recentMonths()
            ->map(fn (CarbonInterface $month): int => (clone $query)
                ->whereBetween($dateColumn, [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->count())
            ->values()
            ->all();
    }

    /**
     * @param  Builder<*>  $query
     * @return array<int, float|int>
     */
    private function monthlySums(Builder $query, string $dateColumn, string $sumColumn): array
    {
        return $this->recentMonths()
            ->map(fn (CarbonInterface $month): float => (float) (clone $query)
                ->whereBetween($dateColumn, [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->sum($sumColumn))
            ->values()
            ->all();
    }

    /**
     * @param  Builder<*>  $query
     * @return array<int, array{label: string, value: float}>
     */
    private function monthlySeries(Builder $query, string $dateColumn, string $sumColumn): array
    {
        return $this->recentMonths()
            ->map(fn (CarbonInterface $month): array => [
                'label' => $month->format('M'),
                'value' => (float) (clone $query)
                    ->whereBetween($dateColumn, [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                    ->sum($sumColumn),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function recentMonths(int $months = 6): Collection
    {
        return collect(range($months - 1, 0))
            ->map(fn (int $monthsAgo): CarbonImmutable => now()->subMonths($monthsAgo)->startOfMonth());
    }

    /**
     * @param  Builder<*>  $query
     */
    private function countDelta(Builder $query, string $dateColumn): string
    {
        $current = (clone $query)
            ->whereBetween($dateColumn, [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
        $previousMonth = now()->subMonth();
        $previous = (clone $query)
            ->whereBetween($dateColumn, [$previousMonth->copy()->startOfMonth(), $previousMonth->copy()->endOfMonth()])
            ->count();

        return $this->deltaText($current - $previous);
    }

    /**
     * @param  Builder<*>  $query
     */
    private function moneyDelta(Builder $query, string $dateColumn, string $sumColumn): string
    {
        $current = (float) (clone $query)
            ->whereBetween($dateColumn, [now()->startOfMonth(), now()->endOfMonth()])
            ->sum($sumColumn);
        $previousMonth = now()->subMonth();
        $previous = (float) (clone $query)
            ->whereBetween($dateColumn, [$previousMonth->copy()->startOfMonth(), $previousMonth->copy()->endOfMonth()])
            ->sum($sumColumn);

        if ($previous <= 0.0) {
            return $current > 0.0 ? 'New this month' : 'Same as last month';
        }

        $percent = (($current - $previous) / $previous) * 100;

        if (abs($percent) < 0.05) {
            return 'Same as last month';
        }

        return sprintf('%s%.1f%% vs last month', $percent > 0 ? '+' : '', $percent);
    }

    private function deltaText(int $delta): string
    {
        if ($delta === 0) {
            return 'Same as last month';
        }

        return sprintf('%s%d vs last month', $delta > 0 ? '+' : '', $delta);
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function purchaseRequestsByStatus(Company $company): array
    {
        return collect(PurchaseRequest::statuses())
            ->map(fn (string $status): array => [
                'label' => str($status)->replace('_', ' ')->title()->toString(),
                'value' => PurchaseRequest::query()
                    ->whereBelongsTo($company)
                    ->where('status', $status)
                    ->count(),
            ])
            ->filter(fn (array $point): bool => $point['value'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function documentsThisMonth(Company $company, CarbonInterface $monthStart, CarbonInterface $monthEnd): array
    {
        return collect([
            $this->documentCountFor('Quotations', Quotation::class, $this->quotationQuery($company), $monthStart, $monthEnd),
            $this->documentCountFor('Vendor POs', PurchaseOrder::class, $this->purchaseOrderQuery($company), $monthStart, $monthEnd),
            $this->documentCountFor('Purchase requests', PurchaseRequest::class, PurchaseRequest::query()->whereBelongsTo($company), $monthStart, $monthEnd),
            $this->documentCountFor('Client POs', ClientPurchaseOrder::class, $this->clientPurchaseOrderQuery($company), $monthStart, $monthEnd),
            $this->documentCountFor('Receiving receipts', ReceivingReceipt::class, ReceivingReceipt::query()->whereBelongsTo($company), $monthStart, $monthEnd),
        ])
            ->filter(fn (array $point): bool => $point['value'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  Builder<*>  $ids
     * @return array{label: string, value: int}
     */
    private function documentCountFor(string $label, string $modelClass, Builder $ids, CarbonInterface $monthStart, CarbonInterface $monthEnd): array
    {
        return [
            'label' => $label,
            'value' => Document::query()
                ->where('documentable_type', $this->morphClass($modelClass))
                ->whereIn('documentable_id', $ids->select('id'))
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, client: array<int, int>, vendor: array<int, int>}
     */
    private function weeklyPurchaseOrders(Company $company, User $user): array
    {
        $weeks = collect(range(7, 0))
            ->map(fn (int $weeksAgo): CarbonImmutable => now()->subWeeks($weeksAgo)->startOfWeek());

        return [
            'labels' => $weeks
                ->map(fn (CarbonInterface $week): string => $week->format('M j'))
                ->values()
                ->all(),
            'client' => $weeks
                ->map(fn (CarbonInterface $week): int => $user->can('client-pos.view')
                    ? $this->clientPurchaseOrderQuery($company)
                        ->whereBetween('po_date', [$week, $week->copy()->endOfWeek()])
                        ->count()
                    : 0)
                ->values()
                ->all(),
            'vendor' => $weeks
                ->map(fn (CarbonInterface $week): int => $user->can('purchase-orders.view')
                    ? $this->purchaseOrderQuery($company)
                        ->whereBetween('po_date', [$week, $week->copy()->endOfWeek()])
                        ->count()
                    : 0)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, array{id: int, module: string, action: string, subject: non-falsy-string|null, created_at: string|null, user: string|null}>
     */
    private function recentActivity(Company $company): Collection
    {
        return AuditLog::query()
            ->select(['id', 'user_id', 'module', 'action', 'subject_type', 'subject_id', 'created_at'])
            ->with('user:id,name')
            ->where(function (Builder $query) use ($company): void {
                $query->where(function (Builder $query) use ($company): void {
                    $query->where('subject_type', $company->getMorphClass())
                        ->where('subject_id', $company->id);
                })
                    ->orWhere(fn (Builder $query) => $this->whereAuditedCompanyIds($query, Quotation::class, $this->quotationQuery($company)))
                    ->orWhere(fn (Builder $query) => $this->whereAuditedCompanyIds($query, PurchaseRequest::class, PurchaseRequest::query()->whereBelongsTo($company)))
                    ->orWhere(fn (Builder $query) => $this->whereAuditedCompanyIds($query, PurchaseOrder::class, $this->purchaseOrderQuery($company)))
                    ->orWhere(fn (Builder $query) => $this->whereAuditedCompanyIds($query, ClientPurchaseOrder::class, $this->clientPurchaseOrderQuery($company)))
                    ->orWhere(fn (Builder $query) => $this->whereAuditedCompanyIds($query, ReceivingReceipt::class, ReceivingReceipt::query()->whereBelongsTo($company)));
            })
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'module' => $log->module,
                'action' => $log->action,
                'subject' => $log->subject_type === null ? null : class_basename($log->subject_type).' #'.$log->subject_id,
                'created_at' => $log->created_at?->toDateTimeString(),
                'user' => $log->user?->name,
            ]);
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @param  class-string<Model>  $modelClass
     * @param  Builder<*>  $ids
     */
    private function whereAuditedCompanyIds(Builder $query, string $modelClass, Builder $ids): void
    {
        $query->where('subject_type', $this->morphClass($modelClass))
            ->whereIn('subject_id', $ids->select('id'));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function morphClass(string $modelClass): string
    {
        return (new $modelClass)->getMorphClass();
    }
}
