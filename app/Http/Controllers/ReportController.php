<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientPurchaseOrder;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const REPORTS = [
        'client-master' => 'Client Master Report',
        'vendor-master' => 'Vendor Master Report',
        'quotations' => 'Quotation Report',
        'client-pos' => 'Client PO Report',
        'purchase-orders' => 'Vendor Purchase Order Report',
    ];

    public function index(Request $request): Response
    {
        $request->user()->can('reports.view') || abort(403);

        $report = $this->reportKey($request);
        $query = $this->reportQuery($request, $report);

        return Inertia::render('reports/Index', [
            'reports' => collect(self::REPORTS)->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
            ])->values(),
            'filters' => $this->filters($request, $report),
            'options' => [
                'companies' => $this->companyOptions($request->user()),
                'clients' => $this->clientOptions($request->user()),
                'vendors' => $this->vendorOptions($request->user()),
                'statuses' => $this->statuses($report),
            ],
            'columns' => $this->columns($report),
            'rows' => $query
                ->paginate(20)
                ->withQueryString()
                ->through(fn ($record): array => $this->row($report, $record)),
            'exportUrl' => route('reports.export', $this->filters($request, $report)),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $request->user()->can('reports.view') || abort(403);

        $report = $this->reportKey($request);
        $filename = $report.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request, $report): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open CSV output stream.');
            }

            fputcsv($handle, collect($this->columns($report))->pluck('label')->all());

            $this->reportQuery($request, $report)
                ->cursor()
                ->each(fn ($record) => fputcsv($handle, array_values($this->row($report, $record))));

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function reportKey(Request $request): string
    {
        $report = $request->string('report')->trim()->toString();

        return array_key_exists($report, self::REPORTS) ? $report : 'client-master';
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request, string $report): array
    {
        return [
            'report' => $report,
            'company_id' => $request->integer('company_id') ?: null,
            'client_id' => $request->integer('client_id') ?: null,
            'vendor_id' => $request->integer('vendor_id') ?: null,
            'status' => $request->string('status')->trim()->toString() ?: 'all',
            'date_from' => $request->date('date_from')?->toDateString(),
            'date_to' => $request->date('date_to')?->toDateString(),
        ];
    }

    /**
     * @return Builder<Client>|Builder<Vendor>|Builder<Quotation>|Builder<ClientPurchaseOrder>|Builder<PurchaseOrder>
     */
    private function reportQuery(Request $request, string $report): Builder
    {
        return match ($report) {
            'vendor-master' => $this->vendorReportQuery($request),
            'quotations' => $this->quotationReportQuery($request),
            'client-pos' => $this->clientPoReportQuery($request),
            'purchase-orders' => $this->purchaseOrderReportQuery($request),
            default => $this->clientReportQuery($request),
        };
    }

    /**
     * @return Builder<Client>
     */
    private function clientReportQuery(Request $request): Builder
    {
        $user = $request->user();
        $companyId = $request->integer('company_id');
        $status = $request->string('status')->trim()->toString();

        $this->abortIfCompanyForbidden($user, $companyId);

        return Client::query()
            ->select(['id', 'client_code', 'client_name', 'tin', 'email', 'phone', 'status', 'created_at'])
            ->with('companies:id,company_code,company_name')
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('companies.users', fn (Builder $query) => $query->whereKey($user->id)))
            ->when($companyId > 0, fn (Builder $query) => $query->whereHas('companies', fn (Builder $query) => $query->whereKey($companyId)))
            ->when(in_array($status, [Client::STATUS_ACTIVE, Client::STATUS_INACTIVE], true), fn (Builder $query) => $query->where('status', $status))
            ->orderBy('client_name');
    }

    /**
     * @return Builder<Vendor>
     */
    private function vendorReportQuery(Request $request): Builder
    {
        $user = $request->user();
        $companyId = $request->integer('company_id');
        $status = $request->string('status')->trim()->toString();

        $this->abortIfCompanyForbidden($user, $companyId);

        return Vendor::query()
            ->select(['id', 'vendor_code', 'vendor_name', 'tin', 'email', 'phone', 'status', 'created_at'])
            ->with('companies:id,company_code,company_name')
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('companies.users', fn (Builder $query) => $query->whereKey($user->id)))
            ->when($companyId > 0, fn (Builder $query) => $query->whereHas('companies', fn (Builder $query) => $query->whereKey($companyId)))
            ->when(in_array($status, [Vendor::STATUS_ACTIVE, Vendor::STATUS_INACTIVE], true), fn (Builder $query) => $query->where('status', $status))
            ->orderBy('vendor_name');
    }

    /**
     * @return Builder<Quotation>
     */
    private function quotationReportQuery(Request $request): Builder
    {
        $user = $request->user();
        $companyId = $request->integer('company_id');
        $clientId = $request->integer('client_id');
        $status = $request->string('status')->trim()->toString();

        $this->abortIfCompanyForbidden($user, $companyId);

        return Quotation::query()
            ->select(['id', 'quotation_no', 'company_id', 'client_id', 'quotation_date', 'currency', 'status', 'total_amount'])
            ->with(['company:id,company_code,company_name', 'client:id,client_code,client_name'])
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('company.users', fn (Builder $query) => $query->whereKey($user->id)))
            ->when($companyId > 0, fn (Builder $query) => $query->where('company_id', $companyId))
            ->when($clientId > 0, fn (Builder $query) => $query->where('client_id', $clientId))
            ->when(in_array($status, Quotation::statuses(), true), fn (Builder $query) => $query->where('status', $status))
            ->when($request->date('date_from') !== null, fn (Builder $query) => $query->whereDate('quotation_date', '>=', $request->date('date_from')))
            ->when($request->date('date_to') !== null, fn (Builder $query) => $query->whereDate('quotation_date', '<=', $request->date('date_to')))
            ->latest('quotation_date')
            ->latest('id');
    }

    /**
     * @return Builder<ClientPurchaseOrder>
     */
    private function clientPoReportQuery(Request $request): Builder
    {
        $user = $request->user();
        $companyId = $request->integer('company_id');
        $clientId = $request->integer('client_id');
        $status = $request->string('status')->trim()->toString();

        $this->abortIfCompanyForbidden($user, $companyId);

        return ClientPurchaseOrder::query()
            ->select(['id', 'client_po_no', 'company_id', 'client_id', 'po_date', 'currency', 'amount', 'status'])
            ->with(['company:id,company_code,company_name', 'client:id,client_code,client_name'])
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('company.users', fn (Builder $query) => $query->whereKey($user->id)))
            ->when($companyId > 0, fn (Builder $query) => $query->where('company_id', $companyId))
            ->when($clientId > 0, fn (Builder $query) => $query->where('client_id', $clientId))
            ->when(in_array($status, ClientPurchaseOrder::statuses(), true), fn (Builder $query) => $query->where('status', $status))
            ->when($request->date('date_from') !== null, fn (Builder $query) => $query->whereDate('po_date', '>=', $request->date('date_from')))
            ->when($request->date('date_to') !== null, fn (Builder $query) => $query->whereDate('po_date', '<=', $request->date('date_to')))
            ->latest('po_date')
            ->latest('id');
    }

    /**
     * @return Builder<PurchaseOrder>
     */
    private function purchaseOrderReportQuery(Request $request): Builder
    {
        $user = $request->user();
        $companyId = $request->integer('company_id');
        $vendorId = $request->integer('vendor_id');
        $status = $request->string('status')->trim()->toString();

        $this->abortIfCompanyForbidden($user, $companyId);

        return PurchaseOrder::query()
            ->select(['id', 'po_no', 'company_id', 'vendor_id', 'po_date', 'currency', 'status', 'total_amount'])
            ->with(['company:id,company_code,company_name', 'vendor:id,vendor_code,vendor_name'])
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('company.users', fn (Builder $query) => $query->whereKey($user->id)))
            ->when($companyId > 0, fn (Builder $query) => $query->where('company_id', $companyId))
            ->when($vendorId > 0, fn (Builder $query) => $query->where('vendor_id', $vendorId))
            ->when(in_array($status, PurchaseOrder::statuses(), true), fn (Builder $query) => $query->where('status', $status))
            ->when($request->date('date_from') !== null, fn (Builder $query) => $query->whereDate('po_date', '>=', $request->date('date_from')))
            ->when($request->date('date_to') !== null, fn (Builder $query) => $query->whereDate('po_date', '<=', $request->date('date_to')))
            ->latest('po_date')
            ->latest('id');
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function columns(string $report): array
    {
        return match ($report) {
            'vendor-master' => [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'name', 'label' => 'Vendor'],
                ['key' => 'tin', 'label' => 'TIN'],
                ['key' => 'companies', 'label' => 'Companies'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'quotations' => [
                ['key' => 'number', 'label' => 'Quotation No'],
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'company', 'label' => 'Company'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'amount', 'label' => 'Amount'],
            ],
            'client-pos' => [
                ['key' => 'number', 'label' => 'Client PO No'],
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'company', 'label' => 'Company'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'amount', 'label' => 'Amount'],
            ],
            'purchase-orders' => [
                ['key' => 'number', 'label' => 'PO No'],
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'company', 'label' => 'Company'],
                ['key' => 'vendor', 'label' => 'Vendor'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'amount', 'label' => 'Amount'],
            ],
            default => [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'name', 'label' => 'Client'],
                ['key' => 'tin', 'label' => 'TIN'],
                ['key' => 'companies', 'label' => 'Companies'],
                ['key' => 'status', 'label' => 'Status'],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function row(string $report, mixed $record): array
    {
        return match ($report) {
            'vendor-master' => [
                'code' => $record->vendor_code,
                'name' => $record->vendor_name,
                'tin' => $record->tin ?? '',
                'companies' => $record->companies->pluck('company_name')->implode(', '),
                'status' => $record->status,
            ],
            'quotations' => [
                'number' => $record->quotation_no,
                'date' => $record->quotation_date?->toDateString(),
                'company' => $record->company?->company_name,
                'client' => $record->client?->client_name,
                'status' => $record->status,
                'amount' => $record->currency.' '.$record->total_amount,
            ],
            'client-pos' => [
                'number' => $record->client_po_no,
                'date' => $record->po_date?->toDateString(),
                'company' => $record->company?->company_name,
                'client' => $record->client?->client_name,
                'status' => $record->status,
                'amount' => $record->currency.' '.$record->amount,
            ],
            'purchase-orders' => [
                'number' => $record->po_no,
                'date' => $record->po_date?->toDateString(),
                'company' => $record->company?->company_name,
                'vendor' => $record->vendor?->vendor_name,
                'status' => $record->status,
                'amount' => $record->currency.' '.$record->total_amount,
            ],
            default => [
                'code' => $record->client_code,
                'name' => $record->client_name,
                'tin' => $record->tin ?? '',
                'companies' => $record->companies->pluck('company_name')->implode(', '),
                'status' => $record->status,
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function statuses(string $report): array
    {
        return match ($report) {
            'vendor-master' => [Vendor::STATUS_ACTIVE, Vendor::STATUS_INACTIVE],
            'quotations' => Quotation::statuses(),
            'client-pos' => ClientPurchaseOrder::statuses(),
            'purchase-orders' => PurchaseOrder::statuses(),
            default => [Client::STATUS_ACTIVE, Client::STATUS_INACTIVE],
        };
    }

    private function abortIfCompanyForbidden(User $user, int $companyId): void
    {
        if ($companyId <= 0 || $user->hasGlobalCompanyAccess()) {
            return;
        }

        abort_unless(Company::query()->whereKey($companyId)->whereHas('users', fn (Builder $query) => $query->whereKey($user->id))->exists(), 403);
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
     * @return array<int, array{id: int, name: string}>
     */
    private function clientOptions(User $user): array
    {
        return Client::query()
            ->select(['id', 'client_code', 'client_name'])
            ->active()
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('companies.users', fn (Builder $query) => $query->whereKey($user->id)))
            ->orderBy('client_name')
            ->get()
            ->map(fn (Client $client): array => ['id' => (int) $client->id, 'name' => "{$client->client_code} - {$client->client_name}"])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function vendorOptions(User $user): array
    {
        return Vendor::query()
            ->select(['id', 'vendor_code', 'vendor_name'])
            ->active()
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('companies.users', fn (Builder $query) => $query->whereKey($user->id)))
            ->orderBy('vendor_name')
            ->get()
            ->map(fn (Vendor $vendor): array => ['id' => (int) $vendor->id, 'name' => "{$vendor->vendor_code} - {$vendor->vendor_name}"])
            ->all();
    }
}
