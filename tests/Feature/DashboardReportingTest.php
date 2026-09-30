<?php

use App\Models\Client;
use App\Models\ClientPurchaseOrder;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard metrics and charts use the selected current company', function () {
    $user = userWithPermissions([
        'dashboard.view',
        'clients.view',
        'quotations.view',
        'purchase-requests.view',
        'client-pos.view',
        'purchase-orders.view',
    ]);
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $company->users()->attach($user);
    $otherCompany->users()->attach($user);
    $client = Client::factory()->create(['client_name' => 'Visible Client']);
    $client->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);
    $hiddenClient = Client::factory()->create(['client_name' => 'Hidden Client']);
    $hiddenClient->companies()->attach($otherCompany, ['status' => Company::STATUS_ACTIVE]);

    Quotation::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'quotation_date' => now()->toDateString(),
        'total_amount' => '2500.00',
    ]);
    Quotation::factory()->create([
        'company_id' => $otherCompany->id,
        'client_id' => $hiddenClient->id,
        'quotation_date' => now()->toDateString(),
        'total_amount' => '9999.00',
    ]);
    PurchaseRequest::factory()->create([
        'company_id' => $company->id,
        'request_date' => now()->toDateString(),
        'status' => PurchaseRequest::STATUS_FOR_APPROVAL,
    ]);
    PurchaseRequest::factory()->create([
        'company_id' => $otherCompany->id,
        'request_date' => now()->toDateString(),
        'status' => PurchaseRequest::STATUS_APPROVED,
    ]);
    ClientPurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'po_date' => now()->toDateString(),
    ]);
    ClientPurchaseOrder::factory()->create([
        'company_id' => $otherCompany->id,
        'client_id' => $hiddenClient->id,
        'po_date' => now()->toDateString(),
    ]);
    PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'po_date' => now()->toDateString(),
    ]);
    PurchaseOrder::factory()->create([
        'company_id' => $otherCompany->id,
        'po_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('metrics.0.label', 'Active clients')
            ->where('metrics.0.value', 1)
            ->where('metrics.1.label', 'Quotations this month')
            ->where('metrics.1.value', 1)
            ->where('metrics.2.label', 'Quotation value')
            ->where('metrics.2.value', '2,500.00')
            ->where('charts.quotationValueByMonth.5.value', 2500)
            ->where('charts.purchaseRequestsByStatus.0.label', 'For Approval')
            ->where('charts.purchaseRequestsByStatus.0.value', 1)
            ->where('charts.weeklyPurchaseOrders.client.7', 1)
            ->where('charts.weeklyPurchaseOrders.vendor.7', 1)
        );
});

test('reports require permission', function () {
    $user = userWithPermissions('dashboard.view');

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertForbidden();
});

test('client master report filters by accessible company', function () {
    $user = userWithPermissions(['reports.view', 'clients.view']);
    $company = Company::factory()->create();
    $company->users()->attach($user);
    $otherCompany = Company::factory()->create();
    $visibleClient = Client::factory()->create(['client_name' => 'Visible Client']);
    $visibleClient->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);
    $hiddenClient = Client::factory()->create(['client_name' => 'Hidden Client']);
    $hiddenClient->companies()->attach($otherCompany, ['status' => Company::STATUS_ACTIVE]);

    $this->actingAs($user)
        ->get(route('reports.index', [
            'report' => 'client-master',
            'company_id' => $company->id,
        ]))
        ->assertOk()
        ->assertSee('Visible Client')
        ->assertDontSee('Hidden Client');
});

test('report export streams csv', function () {
    $user = userWithPermissions(['reports.view', 'clients.view']);
    $company = Company::factory()->create();
    $company->users()->attach($user);
    $client = Client::factory()->create(['client_name' => 'Export Client']);
    $client->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);

    $this->actingAs($user)
        ->get(route('reports.export', ['report' => 'client-master']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
