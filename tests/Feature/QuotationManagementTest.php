<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\ProductService;
use App\Models\Quotation;
use App\Models\User;

function quotationPayload(Company $company, Client $client, ?ProductService $product = null): array
{
    return [
        'company_id' => $company->id,
        'client_id' => $client->id,
        'quotation_date' => '2026-02-15',
        'valid_until' => '2026-03-15',
        'currency' => 'PHP',
        'notes' => 'Internal note',
        'terms_conditions' => 'Standard terms',
        'subtotal' => '999999.99',
        'items' => [
            [
                'product_service_id' => $product?->id,
                'description' => 'Snapshot line',
                'quantity' => '2.0000',
                'unit' => 'pc',
                'unit_price' => '100.00',
                'discount' => '10.00',
                'tax' => '12.00',
            ],
            [
                'product_service_id' => null,
                'description' => 'Manual service',
                'quantity' => '1.5000',
                'unit' => 'hour',
                'unit_price' => '50.00',
                'discount' => '0.00',
                'tax' => '9.00',
            ],
        ],
    ];
}

function companyClientForQuotation(User $user): array
{
    $company = Company::factory()->create();
    $client = Client::factory()->create();
    $company->users()->attach($user);
    $client->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);

    return [$company, $client];
}

test('users without permission cannot access quotation management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('quotations.index'))
        ->assertForbidden();
});

test('authorized users can load quotation options with active company client relationships', function () {
    $user = userWithPermissions('quotations.view');
    companyClientForQuotation($user);

    $this->actingAs($user)
        ->get(route('quotations.index'))
        ->assertOk();
});

test('authorized users can create quotations with server calculated totals and sequential numbers', function () {
    $user = userWithPermissions(['quotations.create', 'quotations.view']);
    [$company, $client] = companyClientForQuotation($user);
    $product = ProductService::factory()->create([
        'default_price' => '999.99',
        'status' => ProductService::STATUS_ACTIVE,
    ]);

    $this->actingAs($user)
        ->post(route('quotations.store'), quotationPayload($company, $client, $product))
        ->assertRedirect();

    $quotation = Quotation::with('items')->firstOrFail();

    expect($quotation->quotation_no)->toBe('QT-2026-000001')
        ->and($quotation->subtotal)->toBe('275.00')
        ->and($quotation->discount)->toBe('10.00')
        ->and($quotation->tax_amount)->toBe('21.00')
        ->and($quotation->total_amount)->toBe('286.00')
        ->and($quotation->items)->toHaveCount(2)
        ->and($quotation->items->first()->line_total)->toBe('202.00');

    $this->actingAs($user)
        ->post(route('quotations.store'), quotationPayload($company, $client, $product))
        ->assertRedirect();

    expect(Quotation::latest('id')->firstOrFail()->quotation_no)->toBe('QT-2026-000002');
});

test('company access and company client relationships are enforced', function () {
    $user = userWithPermissions('quotations.create');
    [$company, $client] = companyClientForQuotation($user);
    $otherCompany = Company::factory()->create();
    $otherClient = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('quotations.store'), quotationPayload($otherCompany, $client))
        ->assertSessionHasErrors('company_id');

    $this->actingAs($user)
        ->post(route('quotations.store'), quotationPayload($company, $otherClient))
        ->assertSessionHasErrors('client_id');
});

test('inactive product services cannot be selected on quotation lines', function () {
    $user = userWithPermissions('quotations.create');
    [$company, $client] = companyClientForQuotation($user);
    $inactiveProduct = ProductService::factory()->create([
        'status' => ProductService::STATUS_INACTIVE,
    ]);

    $this->actingAs($user)
        ->post(route('quotations.store'), quotationPayload($company, $client, $inactiveProduct))
        ->assertSessionHasErrors('items.0.product_service_id');
});

test('quotation item snapshots are preserved when product service records change', function () {
    $user = userWithPermissions(['quotations.create', 'quotations.view']);
    [$company, $client] = companyClientForQuotation($user);
    $product = ProductService::factory()->create([
        'name' => 'Original Product',
        'default_price' => '100.00',
    ]);

    $this->actingAs($user)
        ->post(route('quotations.store'), quotationPayload($company, $client, $product))
        ->assertRedirect();

    $product->update([
        'name' => 'Updated Product',
        'default_price' => '500.00',
    ]);

    $item = Quotation::firstOrFail()->items()->firstOrFail();

    expect($item->product_service_id)->toBe($product->id)
        ->and($item->description)->toBe('Snapshot line')
        ->and($item->unit_price)->toBe('100.00');
});

test('quotation status transitions are controlled', function () {
    $user = userWithPermissions([
        'quotations.view',
        'quotations.submit',
        'quotations.approve',
    ]);
    [$company, $client] = companyClientForQuotation($user);
    $quotation = Quotation::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'created_by' => $user->id,
        'status' => Quotation::STATUS_DRAFT,
    ]);

    $this->actingAs($user)
        ->patch(route('quotations.approve', $quotation))
        ->assertStatus(422);

    $this->actingAs($user)
        ->patch(route('quotations.submit', $quotation))
        ->assertRedirect();

    expect($quotation->fresh()->status)->toBe(Quotation::STATUS_FOR_APPROVAL);

    $this->actingAs($user)
        ->patch(route('quotations.approve', $quotation))
        ->assertRedirect();

    expect($quotation->fresh()->status)->toBe(Quotation::STATUS_APPROVED);
});
