<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->user()->can('dashboard.view') || abort(403);

        $user = $request->user();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return Inertia::render('Dashboard', [
            'metrics' => array_values(array_filter([
                $user->can('clients.view') ? [
                    'label' => 'Active clients',
                    'value' => $this->clientQuery($user)->active()->count(),
                    'description' => 'Accessible client master records',
                ] : null,
                $user->can('vendors.view') ? [
                    'label' => 'Active vendors',
                    'value' => $this->vendorQuery($user)->active()->count(),
                    'description' => 'Accessible vendor master records',
                ] : null,
                $user->can('quotations.view') ? [
                    'label' => 'Quotations this month',
                    'value' => $this->quotationQuery($user)->whereBetween('quotation_date', [$monthStart, $monthEnd])->count(),
                    'description' => 'Created within current month',
                ] : null,
                $user->can('quotations.view') ? [
                    'label' => 'Quotation value',
                    'value' => number_format((float) $this->quotationQuery($user)->whereBetween('quotation_date', [$monthStart, $monthEnd])->sum('total_amount'), 2),
                    'description' => 'Current month total value',
                ] : null,
                $user->can('quotations.approve') ? [
                    'label' => 'Pending quotation approvals',
                    'value' => $this->quotationQuery($user)->where('status', Quotation::STATUS_FOR_APPROVAL)->count(),
                    'description' => 'Waiting for approval',
                ] : null,
                $user->can('client-pos.view') ? [
                    'label' => 'Client POs received',
                    'value' => $this->clientPurchaseOrderQuery($user)->whereBetween('po_date', [$monthStart, $monthEnd])->count(),
                    'description' => 'Received within current month',
                ] : null,
                $user->can('purchase-orders.view') ? [
                    'label' => 'Vendor POs this month',
                    'value' => $this->purchaseOrderQuery($user)->whereBetween('po_date', [$monthStart, $monthEnd])->count(),
                    'description' => 'Issued within current month',
                ] : null,
                $user->can('purchase-orders.approve') ? [
                    'label' => 'Pending PO approvals',
                    'value' => $this->purchaseOrderQuery($user)->where('status', PurchaseOrder::STATUS_FOR_APPROVAL)->count(),
                    'description' => 'Waiting for approval',
                ] : null,
            ])),
            'recentActivity' => class_exists(AuditLog::class) && $user->can('audit-logs.view')
                ? AuditLog::query()
                    ->select(['id', 'user_id', 'module', 'action', 'subject_type', 'subject_id', 'created_at'])
                    ->with('user:id,name')
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
                    ])
                : [],
        ]);
    }

    /**
     * @return Builder<Client>
     */
    private function clientQuery(User $user): Builder
    {
        return Client::query()
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('companies.users', fn (Builder $query) => $query->whereKey($user->id)));
    }

    /**
     * @return Builder<Vendor>
     */
    private function vendorQuery(User $user): Builder
    {
        return Vendor::query()
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('companies.users', fn (Builder $query) => $query->whereKey($user->id)));
    }

    /**
     * @return Builder<Quotation>
     */
    private function quotationQuery(User $user): Builder
    {
        return Quotation::query()
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('company.users', fn (Builder $query) => $query->whereKey($user->id)));
    }

    /**
     * @return Builder<ClientPurchaseOrder>
     */
    private function clientPurchaseOrderQuery(User $user): Builder
    {
        return ClientPurchaseOrder::query()
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('company.users', fn (Builder $query) => $query->whereKey($user->id)));
    }

    /**
     * @return Builder<PurchaseOrder>
     */
    private function purchaseOrderQuery(User $user): Builder
    {
        return PurchaseOrder::query()
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('company.users', fn (Builder $query) => $query->whereKey($user->id)));
    }
}
