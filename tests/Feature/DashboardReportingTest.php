<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;

test('dashboard metrics respect permissions and company access', function () {
    $user = userWithPermissions(['dashboard.view', 'clients.view', 'quotations.view']);
    $company = Company::factory()->create();
    $company->users()->attach($user);
    $otherCompany = Company::factory()->create();
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

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Active clients')
        ->assertSee('Quotation value')
        ->assertSee('2,500.00')
        ->assertDontSee('Active vendors');
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
