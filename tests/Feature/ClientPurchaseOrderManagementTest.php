<?php

use App\Models\Client;
use App\Models\ClientPurchaseOrder;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function clientPoCompanyClient(User $user): array
{
    $company = Company::factory()->create();
    $client = Client::factory()->create();
    $company->users()->attach($user);
    $client->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);

    return [$company, $client];
}

function clientPoPayload(Company $company, Client $client, ?Quotation $quotation = null): array
{
    return [
        'company_id' => $company->id,
        'client_id' => $client->id,
        'quotation_ids' => $quotation === null ? [] : [$quotation->id],
        'client_po_no' => 'CLIENT-PO-001',
        'po_date' => '2026-04-15',
        'currency' => 'PHP',
        'amount' => '15000.75',
        'status' => ClientPurchaseOrder::STATUS_RECEIVED,
        'received_at' => '2026-04-16 09:30:00',
        'notes' => 'Original client reference retained.',
        'items' => [
            [
                'product_service_id' => null,
                'description' => 'Manual client PO line',
                'quantity' => '1',
                'unit' => 'lot',
                'unit_price' => '15000.75',
                'discount' => '0.00',
                'tax' => '0.00',
            ],
        ],
    ];
}

test('users without permission cannot access client purchase order management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('client-pos.index'))
        ->assertForbidden();
});

test('authorized users can load client purchase order options with active company client relationships', function () {
    $user = userWithPermissions('client-pos.view');
    clientPoCompanyClient($user);

    $this->actingAs($user)
        ->get(route('client-pos.index'))
        ->assertOk();
});

test('authorized users can record client purchase orders and link multiple approved quotations safely', function () {
    $user = userWithPermissions(['client-pos.create', 'client-pos.view']);
    [$company, $client] = clientPoCompanyClient($user);
    $firstQuotation = Quotation::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'total_amount' => '15000.75',
        'status' => Quotation::STATUS_APPROVED,
    ]);
    $secondQuotation = Quotation::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'total_amount' => '5000.00',
        'status' => Quotation::STATUS_APPROVED,
    ]);
    $firstQuotationItem = QuotationItem::factory()->create([
        'quotation_id' => $firstQuotation->id,
        'description' => 'First quote line',
        'quantity' => '1',
        'unit' => 'lot',
        'unit_price' => '15000.75',
        'discount' => '0.00',
        'tax' => '0.00',
    ]);
    $secondQuotationItem = QuotationItem::factory()->create([
        'quotation_id' => $secondQuotation->id,
        'description' => 'Second quote line',
        'quantity' => '1',
        'unit' => 'lot',
        'unit_price' => '5000.00',
        'discount' => '0.00',
        'tax' => '0.00',
    ]);
    $payload = clientPoPayload($company, $client);
    $payload['quotation_ids'] = [$firstQuotation->id, $secondQuotation->id];
    $payload['items'] = [
        [
            'product_service_id' => null,
            'quotation_item_id' => $firstQuotationItem->id,
            'description' => 'First quote line snapshot',
            'quantity' => '1',
            'unit' => 'lot',
            'unit_price' => '15000.75',
            'discount' => '0.00',
            'tax' => '0.00',
        ],
        [
            'product_service_id' => null,
            'quotation_item_id' => $secondQuotationItem->id,
            'description' => 'Second quote line snapshot',
            'quantity' => '1',
            'unit' => 'lot',
            'unit_price' => '5000.00',
            'discount' => '0.00',
            'tax' => '0.00',
        ],
    ];

    $this->actingAs($user)
        ->post(route('client-pos.store'), $payload)
        ->assertRedirect();

    $clientPurchaseOrder = ClientPurchaseOrder::with(['items', 'quotations'])->firstOrFail();

    expect($clientPurchaseOrder->company_id)->toBe($company->id)
        ->and($clientPurchaseOrder->client_id)->toBe($client->id)
        ->and($clientPurchaseOrder->quotations)->toHaveCount(2)
        ->and($clientPurchaseOrder->quotations->pluck('id')->all())->toContain($firstQuotation->id, $secondQuotation->id)
        ->and($clientPurchaseOrder->items->pluck('quotation_item_id')->all())->toContain($firstQuotationItem->id, $secondQuotationItem->id)
        ->and($clientPurchaseOrder->client_po_no)->toBe('CLIENT-PO-001')
        ->and($clientPurchaseOrder->currency)->toBe('PHP')
        ->and($clientPurchaseOrder->amount)->toBe('20000.75')
        ->and($clientPurchaseOrder->received_by)->toBe($user->id);
});

test('company client relationship and company access are enforced', function () {
    $user = userWithPermissions('client-pos.create');
    [$company, $client] = clientPoCompanyClient($user);
    $otherCompany = Company::factory()->create();
    $otherClient = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('client-pos.store'), clientPoPayload($otherCompany, $client))
        ->assertSessionHasErrors('company_id');

    $this->actingAs($user)
        ->post(route('client-pos.store'), clientPoPayload($company, $otherClient))
        ->assertSessionHasErrors('client_id');
});

test('linked quotation must belong to the selected company and client', function () {
    $user = userWithPermissions('client-pos.create');
    [$company, $client] = clientPoCompanyClient($user);
    $otherClient = Client::factory()->create();
    $otherClient->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);
    $quotation = Quotation::factory()->create([
        'company_id' => $company->id,
        'client_id' => $otherClient->id,
        'status' => Quotation::STATUS_APPROVED,
    ]);

    $this->actingAs($user)
        ->post(route('client-pos.store'), clientPoPayload($company, $client, $quotation))
        ->assertSessionHasErrors('quotation_ids');
});

test('client po form only exposes approved quotations and rejects draft links', function () {
    $user = userWithPermissions(['client-pos.create']);
    [$company, $client] = clientPoCompanyClient($user);
    $approvedQuotation = Quotation::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => Quotation::STATUS_APPROVED,
    ]);
    $draftQuotation = Quotation::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => Quotation::STATUS_DRAFT,
    ]);
    $payload = clientPoPayload($company, $client);
    $payload['quotation_ids'] = [$draftQuotation->id];

    $this->actingAs($user)
        ->get(route('client-pos.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client-pos/Create')
            ->has('quotations', 1)
            ->where('quotations.0.id', $approvedQuotation->id)
        );

    $this->actingAs($user)
        ->post(route('client-pos.store'), $payload)
        ->assertSessionHasErrors('quotation_ids');
});

test('client po quotation items must belong to selected quotations', function () {
    $user = userWithPermissions(['client-pos.create']);
    [$company, $client] = clientPoCompanyClient($user);
    $selectedQuotation = Quotation::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => Quotation::STATUS_APPROVED,
    ]);
    $otherQuotation = Quotation::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => Quotation::STATUS_APPROVED,
    ]);
    $otherQuotationItem = QuotationItem::factory()->create([
        'quotation_id' => $otherQuotation->id,
    ]);
    $payload = clientPoPayload($company, $client);
    $payload['quotation_ids'] = [$selectedQuotation->id];
    $payload['items'][0]['quotation_item_id'] = $otherQuotationItem->id;

    $this->actingAs($user)
        ->post(route('client-pos.store'), $payload)
        ->assertSessionHasErrors('items.0.quotation_item_id');
});

test('client po numbers are unique per company client pair', function () {
    $user = userWithPermissions('client-pos.create');
    [$company, $client] = clientPoCompanyClient($user);
    ClientPurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'client_po_no' => 'CLIENT-PO-001',
    ]);

    $this->actingAs($user)
        ->post(route('client-pos.store'), clientPoPayload($company, $client))
        ->assertSessionHasErrors('client_po_no');
});

test('authorized users can update client purchase orders', function () {
    $user = userWithPermissions(['client-pos.view', 'client-pos.edit']);
    [$company, $client] = clientPoCompanyClient($user);
    $clientPurchaseOrder = ClientPurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'client_po_no' => 'CLIENT-PO-001',
        'amount' => '1000.00',
    ]);

    $this->actingAs($user)
        ->put(route('client-pos.update', $clientPurchaseOrder), [
            ...clientPoPayload($company, $client),
            'client_po_no' => 'CLIENT-PO-002',
            'currency' => 'usd',
            'amount' => '2000.25',
            'status' => ClientPurchaseOrder::STATUS_CONFIRMED,
        ])
        ->assertRedirect(route('client-pos.show', $clientPurchaseOrder));

    expect($clientPurchaseOrder->fresh()->client_po_no)->toBe('CLIENT-PO-002')
        ->and($clientPurchaseOrder->fresh()->currency)->toBe('USD')
        ->and($clientPurchaseOrder->fresh()->amount)->toBe('15000.75')
        ->and($clientPurchaseOrder->fresh()->status)->toBe(ClientPurchaseOrder::STATUS_CONFIRMED);
});

test('client purchase orders cannot be fulfilled through ordinary edit updates', function () {
    $user = userWithPermissions(['client-pos.view', 'client-pos.edit']);
    [$company, $client] = clientPoCompanyClient($user);
    $clientPurchaseOrder = ClientPurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => ClientPurchaseOrder::STATUS_PROCESSING,
    ]);

    $this->actingAs($user)
        ->put(route('client-pos.update', $clientPurchaseOrder), [
            ...clientPoPayload($company, $client),
            'status' => ClientPurchaseOrder::STATUS_FULFILLED,
        ])
        ->assertSessionHasErrors('status');

    expect($clientPurchaseOrder->fresh()->status)->toBe(ClientPurchaseOrder::STATUS_PROCESSING);
});

test('client purchase orders can contain product and service line items with server calculated totals', function () {
    $user = userWithPermissions(['client-pos.create', 'client-pos.view']);
    [$company, $client] = clientPoCompanyClient($user);
    $product = ProductService::factory()->create(['type' => ProductService::TYPE_PRODUCT, 'quantity' => 50]);
    $service = ProductService::factory()->create(['type' => ProductService::TYPE_SERVICE]);

    $this->actingAs($user)
        ->post(route('client-pos.store'), [
            ...clientPoPayload($company, $client),
            'amount' => '1.00',
            'items' => [
                [
                    'product_service_id' => $product->id,
                    'description' => 'Wireless Mouse snapshot',
                    'quantity' => '10',
                    'unit' => 'pc',
                    'unit_price' => '500.00',
                    'discount' => '0.00',
                    'tax' => '0.00',
                ],
                [
                    'product_service_id' => $service->id,
                    'description' => 'Installation snapshot',
                    'quantity' => '1',
                    'unit' => 'lot',
                    'unit_price' => '2000.00',
                    'discount' => '0.00',
                    'tax' => '0.00',
                ],
            ],
        ])
        ->assertRedirect();

    $clientPurchaseOrder = ClientPurchaseOrder::firstOrFail();

    expect($clientPurchaseOrder->items)->toHaveCount(2)
        ->and($clientPurchaseOrder->amount)->toBe('7000.00')
        ->and($product->fresh()->quantity)->toBe(50);
});

test('line item quantity must be greater than zero', function () {
    $user = userWithPermissions('client-pos.create');
    [$company, $client] = clientPoCompanyClient($user);

    $this->actingAs($user)
        ->post(route('client-pos.store'), [
            ...clientPoPayload($company, $client),
            'items' => [
                [
                    'product_service_id' => null,
                    'description' => 'Invalid line',
                    'quantity' => '0',
                    'unit' => 'pc',
                    'unit_price' => '100.00',
                ],
            ],
        ])
        ->assertSessionHasErrors('items.0.quantity');
});

test('quotation line items can be stored as stable client po snapshots', function () {
    $user = userWithPermissions(['client-pos.create', 'client-pos.view']);
    [$company, $client] = clientPoCompanyClient($user);
    $product = ProductService::factory()->create(['type' => ProductService::TYPE_PRODUCT, 'name' => 'Original Product']);
    $quotation = Quotation::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => Quotation::STATUS_APPROVED,
    ]);
    $quotationItem = QuotationItem::factory()->create([
        'quotation_id' => $quotation->id,
        'product_service_id' => $product->id,
        'description' => 'Quoted Product Snapshot',
        'quantity' => '3',
        'unit' => 'pc',
        'unit_price' => '250.00',
        'line_total' => '750.00',
    ]);

    $this->actingAs($user)
        ->post(route('client-pos.store'), [
            ...clientPoPayload($company, $client, $quotation),
            'items' => [
                [
                    'product_service_id' => $product->id,
                    'quotation_item_id' => $quotationItem->id,
                    'description' => 'Quoted Product Snapshot',
                    'quantity' => '3',
                    'unit' => 'pc',
                    'unit_price' => '250.00',
                    'discount' => '0.00',
                    'tax' => '0.00',
                ],
            ],
        ])
        ->assertRedirect();

    $product->update(['name' => 'Renamed Product']);

    expect(ClientPurchaseOrder::firstOrFail()->items()->firstOrFail()->description)
        ->toBe('Quoted Product Snapshot');
});

test('fulfillment deducts product stock once and creates inventory movement', function () {
    $user = userWithPermissions(['client-pos.fulfill', 'client-pos.edit', 'client-pos.view']);
    [$company, $client] = clientPoCompanyClient($user);
    $product = ProductService::factory()->create(['type' => ProductService::TYPE_PRODUCT, 'quantity' => 20, 'is_public' => true]);
    $service = ProductService::factory()->create(['type' => ProductService::TYPE_SERVICE]);

    $clientPurchaseOrder = ClientPurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => ClientPurchaseOrder::STATUS_PROCESSING,
    ]);
    $clientPurchaseOrder->items()->createMany([
        [
            'product_service_id' => $product->id,
            'description' => 'Stock product',
            'quantity' => '8',
            'unit' => 'pc',
            'unit_price' => '100.00',
            'line_total' => '800.00',
        ],
        [
            'product_service_id' => $service->id,
            'description' => 'Service line',
            'quantity' => '5',
            'unit' => 'hour',
            'unit_price' => '100.00',
            'line_total' => '500.00',
        ],
    ]);

    $token = $product->public_token;

    $this->actingAs($user)
        ->patch(route('client-pos.fulfill', $clientPurchaseOrder))
        ->assertRedirect();

    expect($product->fresh()->quantity)->toBe(12)
        ->and($service->fresh()->quantity)->toBe(0)
        ->and($product->fresh()->public_token)->toBe($token)
        ->and($clientPurchaseOrder->fresh()->status)->toBe(ClientPurchaseOrder::STATUS_FULFILLED)
        ->and(InventoryMovement::query()->where('product_id', $product->id)->count())->toBe(1);

    $this->actingAs($user)
        ->patch(route('client-pos.fulfill', $clientPurchaseOrder))
        ->assertSessionHasErrors('status');

    expect($product->fresh()->quantity)->toBe(12);
});

test('insufficient stock prevents fulfillment without partial deduction', function () {
    $user = userWithPermissions('client-pos.fulfill');
    [$company, $client] = clientPoCompanyClient($user);
    $firstProduct = ProductService::factory()->create(['type' => ProductService::TYPE_PRODUCT, 'quantity' => 10]);
    $secondProduct = ProductService::factory()->create(['type' => ProductService::TYPE_PRODUCT, 'quantity' => 2]);
    $clientPurchaseOrder = ClientPurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => ClientPurchaseOrder::STATUS_PROCESSING,
    ]);
    $clientPurchaseOrder->items()->createMany([
        [
            'product_service_id' => $firstProduct->id,
            'description' => 'Enough stock',
            'quantity' => '5',
            'unit' => 'pc',
            'unit_price' => '100.00',
            'line_total' => '500.00',
        ],
        [
            'product_service_id' => $secondProduct->id,
            'description' => 'Not enough stock',
            'quantity' => '5',
            'unit' => 'pc',
            'unit_price' => '100.00',
            'line_total' => '500.00',
        ],
    ]);

    $this->actingAs($user)
        ->patch(route('client-pos.fulfill', $clientPurchaseOrder))
        ->assertSessionHasErrors('status');

    expect($firstProduct->fresh()->quantity)->toBe(10)
        ->and($secondProduct->fresh()->quantity)->toBe(2)
        ->and(InventoryMovement::count())->toBe(0);
});

test('unauthorized users cannot fulfill client purchase orders', function () {
    $user = userWithPermissions('client-pos.view');
    [$company, $client] = clientPoCompanyClient($user);
    $clientPurchaseOrder = ClientPurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => ClientPurchaseOrder::STATUS_PROCESSING,
    ]);

    $this->actingAs($user)
        ->patch(route('client-pos.fulfill', $clientPurchaseOrder))
        ->assertForbidden();
});

test('public product availability reflects fulfilled stock deduction', function () {
    $user = userWithPermissions('client-pos.fulfill');
    [$company, $client] = clientPoCompanyClient($user);
    $product = ProductService::factory()->create([
        'type' => ProductService::TYPE_PRODUCT,
        'quantity' => 3,
        'is_public' => true,
        'status' => ProductService::STATUS_ACTIVE,
    ]);
    $clientPurchaseOrder = ClientPurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => ClientPurchaseOrder::STATUS_PROCESSING,
    ]);
    $clientPurchaseOrder->items()->create([
        'product_service_id' => $product->id,
        'description' => 'Public product',
        'quantity' => '3',
        'unit' => 'pc',
        'unit_price' => '100.00',
        'line_total' => '300.00',
    ]);
    $token = $product->public_token;

    $this->actingAs($user)->patch(route('client-pos.fulfill', $clientPurchaseOrder));

    expect($product->fresh()->public_token)->toBe($token);

    $this->get(route('public-products.show', $token))
        ->assertOk()
        ->assertSee('OUT OF STOCK');
});
