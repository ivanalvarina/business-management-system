<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;

test('users without permission cannot access client management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('clients.index'))
        ->assertForbidden();
});

test('authorized users can create clients for multiple companies with contacts', function () {
    $admin = userWithPermissions([
        'clients.create',
        'clients.view',
        'companies.access-all',
    ]);
    $firstCompany = Company::factory()->create(['company_code' => 'LCI']);
    $secondCompany = Company::factory()->create(['company_code' => 'LSI']);

    $this->actingAs($admin)
        ->post(route('clients.store'), [
            'client_code' => 'CLI-001',
            'client_name' => 'Acme Customer',
            'trade_name' => 'Acme',
            'tin' => '123-456-789-000',
            'email' => 'billing@acme.test',
            'phone' => '555-2000',
            'billing_address' => 'Billing Office',
            'shipping_address' => 'Warehouse',
            'status' => Client::STATUS_ACTIVE,
            'company_ids' => [$firstCompany->id, $secondCompany->id],
            'contacts' => [
                [
                    'name' => 'Jane Buyer',
                    'position' => 'Purchasing Manager',
                    'department' => 'Procurement',
                    'email' => 'jane@acme.test',
                    'phone' => '555-3000',
                    'mobile' => '555-3001',
                    'is_primary' => true,
                ],
                [
                    'name' => 'John Accounts',
                    'position' => 'Accountant',
                    'department' => 'Finance',
                    'email' => 'john@acme.test',
                    'phone' => '555-4000',
                    'mobile' => null,
                    'is_primary' => false,
                ],
            ],
        ])
        ->assertRedirect();

    $client = Client::where('client_code', 'CLI-001')->firstOrFail();

    expect($client->companies()->pluck('companies.id')->sort()->values()->all())
        ->toBe([$firstCompany->id, $secondCompany->id])
        ->and($client->contactPeople()->count())->toBe(2)
        ->and($client->contactPeople()->where('is_primary', true)->first()?->name)->toBe('Jane Buyer');
});

test('client code and company relationships must be unique', function () {
    $admin = userWithPermissions(['clients.create', 'companies.access-all']);
    $company = Company::factory()->create();
    Client::factory()->create(['client_code' => 'CLI-001']);

    $this->actingAs($admin)
        ->post(route('clients.store'), [
            'client_code' => 'CLI-001',
            'client_name' => 'Duplicate Client',
            'status' => Client::STATUS_ACTIVE,
            'company_ids' => [$company->id],
        ])
        ->assertSessionHasErrors('client_code');

    $this->actingAs($admin)
        ->post(route('clients.store'), [
            'client_code' => 'CLI-002',
            'client_name' => 'Duplicate Company Assignment',
            'status' => Client::STATUS_ACTIVE,
            'company_ids' => [$company->id, $company->id],
        ])
        ->assertSessionHasErrors('company_ids.0');
});

test('scoped users only see and access clients for their companies', function () {
    $user = userWithPermissions('clients.view');
    $assignedCompany = Company::factory()->create();
    $unassignedCompany = Company::factory()->create();
    $assignedClient = Client::factory()->create(['client_name' => 'Visible Client']);
    $hiddenClient = Client::factory()->create(['client_name' => 'Hidden Client']);

    $user->companies()->attach($assignedCompany);
    $assignedClient->companies()->attach($assignedCompany, ['status' => Company::STATUS_ACTIVE]);
    $hiddenClient->companies()->attach($unassignedCompany, ['status' => Company::STATUS_ACTIVE]);

    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Visible Client')
        ->assertDontSee('Hidden Client');

    $this->actingAs($user)
        ->get(route('clients.show', $assignedClient))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('clients.show', $hiddenClient))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('clients.index', ['company_id' => $unassignedCompany->id]))
        ->assertForbidden();
});

test('client listing supports search company and status filters', function () {
    $admin = userWithPermissions(['clients.view', 'companies.access-all']);
    $firstCompany = Company::factory()->create();
    $secondCompany = Company::factory()->create();
    $activeClient = Client::factory()->create([
        'client_code' => 'FIND-ME',
        'client_name' => 'Filtered Client',
        'tin' => 'TIN-123',
        'status' => Client::STATUS_ACTIVE,
    ]);
    $inactiveClient = Client::factory()->create([
        'client_name' => 'Inactive Other',
        'status' => Client::STATUS_INACTIVE,
    ]);

    $activeClient->companies()->attach($firstCompany, ['status' => Company::STATUS_ACTIVE]);
    $inactiveClient->companies()->attach($secondCompany, ['status' => Company::STATUS_ACTIVE]);

    $this->actingAs($admin)
        ->get(route('clients.index', [
            'search' => 'TIN-123',
            'company_id' => $firstCompany->id,
            'status' => Client::STATUS_ACTIVE,
        ]))
        ->assertOk()
        ->assertSee('Filtered Client')
        ->assertDontSee('Inactive Other');
});

test('client assignment cannot include unauthorized companies', function () {
    $user = userWithPermissions(['clients.create', 'clients.view']);
    $assignedCompany = Company::factory()->create();
    $unassignedCompany = Company::factory()->create();
    $user->companies()->attach($assignedCompany);

    $this->actingAs($user)
        ->post(route('clients.store'), [
            'client_code' => 'CLI-003',
            'client_name' => 'Unauthorized Company Client',
            'status' => Client::STATUS_ACTIVE,
            'company_ids' => [$assignedCompany->id, $unassignedCompany->id],
        ])
        ->assertSessionHasErrors('company_ids');

    expect(Client::where('client_code', 'CLI-003')->exists())->toBeFalse();
});

test('authorized users can update and deactivate clients', function () {
    $user = userWithPermissions(['clients.view', 'clients.edit']);
    $company = Company::factory()->create();
    $client = Client::factory()->create([
        'client_code' => 'OLD',
        'client_name' => 'Old Client',
    ]);

    $user->companies()->attach($company);
    $client->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);

    $this->actingAs($user)
        ->put(route('clients.update', $client), [
            'client_code' => 'NEW',
            'client_name' => 'New Client',
            'status' => Client::STATUS_ACTIVE,
            'company_ids' => [$company->id],
            'contacts' => [
                [
                    'name' => 'Updated Contact',
                    'is_primary' => false,
                ],
            ],
        ])
        ->assertRedirect(route('clients.show', $client));

    expect($client->fresh()->client_code)->toBe('NEW')
        ->and($client->contactPeople()->where('is_primary', true)->first()?->name)->toBe('Updated Contact');

    $this->actingAs($user)
        ->patch(route('clients.deactivate', $client))
        ->assertRedirect();

    expect($client->fresh()->status)->toBe(Client::STATUS_INACTIVE);
});
