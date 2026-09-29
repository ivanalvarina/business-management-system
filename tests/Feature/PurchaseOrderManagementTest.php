<?php

use App\Models\Company;
use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Models\Vendor;
use Inertia\Testing\AssertableInertia as Assert;

function purchaseOrderPayload(Company $company, Vendor $vendor, ?ProductService $product = null): array
{
    return [
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'po_date' => '2026-05-15',
        'expected_delivery' => '2026-06-15',
        'currency' => 'PHP',
        'notes' => 'Internal procurement note',
        'terms_conditions' => 'Standard procurement terms',
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

function companyVendorForPurchaseOrder(User $user): array
{
    $company = Company::factory()->create();
    $vendor = Vendor::factory()->create();
    $company->users()->attach($user);
    $vendor->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);

    return [$company, $vendor];
}

test('users without permission cannot access purchase order management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('purchase-orders.index'))
        ->assertForbidden();
});

test('authorized users can load purchase order options with active company vendor relationships', function () {
    $user = userWithPermissions('purchase-orders.view');
    companyVendorForPurchaseOrder($user);

    $this->actingAs($user)
        ->get(route('purchase-orders.index'))
        ->assertOk();
});

test('authorized users can create purchase orders with server calculated totals and sequential numbers', function () {
    $user = userWithPermissions(['purchase-orders.create', 'purchase-orders.view']);
    [$company, $vendor] = companyVendorForPurchaseOrder($user);
    $product = ProductService::factory()->create([
        'default_price' => '999.99',
        'status' => ProductService::STATUS_ACTIVE,
    ]);

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), purchaseOrderPayload($company, $vendor, $product))
        ->assertRedirect();

    $purchaseOrder = PurchaseOrder::with('items')->firstOrFail();

    expect($purchaseOrder->po_no)->toBe('PO-2026-000001')
        ->and($purchaseOrder->subtotal)->toBe('275.00')
        ->and($purchaseOrder->discount)->toBe('10.00')
        ->and($purchaseOrder->tax_amount)->toBe('21.00')
        ->and($purchaseOrder->total_amount)->toBe('286.00')
        ->and($purchaseOrder->items)->toHaveCount(2)
        ->and($purchaseOrder->items->first()->line_total)->toBe('202.00');

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), purchaseOrderPayload($company, $vendor, $product))
        ->assertRedirect();

    expect(PurchaseOrder::latest('id')->firstOrFail()->po_no)->toBe('PO-2026-000002');
});

test('company access and company vendor relationships are enforced', function () {
    $user = userWithPermissions('purchase-orders.create');
    [$company, $vendor] = companyVendorForPurchaseOrder($user);
    $otherCompany = Company::factory()->create();
    $otherVendor = Vendor::factory()->create();

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), purchaseOrderPayload($otherCompany, $vendor))
        ->assertSessionHasErrors('company_id');

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), purchaseOrderPayload($company, $otherVendor))
        ->assertSessionHasErrors('vendor_id');
});

test('inactive product services cannot be selected on purchase order lines', function () {
    $user = userWithPermissions('purchase-orders.create');
    [$company, $vendor] = companyVendorForPurchaseOrder($user);
    $inactiveProduct = ProductService::factory()->create([
        'status' => ProductService::STATUS_INACTIVE,
    ]);

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), purchaseOrderPayload($company, $vendor, $inactiveProduct))
        ->assertSessionHasErrors('items.0.product_service_id');
});

test('purchase order item snapshots are preserved when product service records change', function () {
    $user = userWithPermissions(['purchase-orders.create', 'purchase-orders.view']);
    [$company, $vendor] = companyVendorForPurchaseOrder($user);
    $product = ProductService::factory()->create([
        'name' => 'Original Product',
        'default_price' => '100.00',
    ]);

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), purchaseOrderPayload($company, $vendor, $product))
        ->assertRedirect();

    $product->update([
        'name' => 'Updated Product',
        'default_price' => '500.00',
    ]);

    $item = PurchaseOrder::firstOrFail()->items()->firstOrFail();

    expect($item->product_service_id)->toBe($product->id)
        ->and($item->description)->toBe('Snapshot line')
        ->and($item->unit_price)->toBe('100.00');
});

test('purchase orders can link multiple approved purchase requests and their items', function () {
    $user = userWithPermissions(['purchase-orders.create', 'purchase-orders.view']);
    [$company, $vendor] = companyVendorForPurchaseOrder($user);
    $product = ProductService::factory()->create([
        'status' => ProductService::STATUS_ACTIVE,
    ]);
    $firstPurchaseRequest = PurchaseRequest::factory()->create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => PurchaseRequest::STATUS_APPROVED,
        'created_by' => $user->id,
    ]);
    $secondPurchaseRequest = PurchaseRequest::factory()->create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => PurchaseRequest::STATUS_APPROVED,
        'created_by' => $user->id,
    ]);
    $firstPurchaseRequestItem = PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $firstPurchaseRequest->id,
        'product_service_id' => $product->id,
        'description' => 'Approved PR line',
        'quantity' => '3.0000',
        'unit' => 'pcs',
        'unit_price' => '125.00',
        'tax' => '0.00',
    ]);
    $secondPurchaseRequestItem = PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $secondPurchaseRequest->id,
        'product_service_id' => null,
        'description' => 'Second approved PR line',
        'quantity' => '2.0000',
        'unit' => 'lot',
        'unit_price' => '50.00',
        'tax' => '0.00',
    ]);
    $payload = purchaseOrderPayload($company, $vendor, $product);
    $payload['purchase_request_ids'] = [$firstPurchaseRequest->id, $secondPurchaseRequest->id];
    $payload['items'] = [
        [
            'product_service_id' => $product->id,
            'purchase_request_item_id' => $firstPurchaseRequestItem->id,
            'description' => 'Approved PR line snapshot',
            'quantity' => '3.0000',
            'unit' => 'pcs',
            'unit_price' => '125.00',
            'discount' => '0.00',
            'tax' => '0.00',
        ],
        [
            'product_service_id' => null,
            'purchase_request_item_id' => $secondPurchaseRequestItem->id,
            'description' => 'Second approved PR line snapshot',
            'quantity' => '2.0000',
            'unit' => 'lot',
            'unit_price' => '50.00',
            'discount' => '0.00',
            'tax' => '0.00',
        ],
    ];

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), $payload)
        ->assertRedirect();

    $purchaseOrder = PurchaseOrder::with(['items', 'purchaseRequests'])->firstOrFail();

    expect($purchaseOrder->purchaseRequests)->toHaveCount(2)
        ->and($purchaseOrder->purchaseRequests->pluck('id')->all())->toContain($firstPurchaseRequest->id, $secondPurchaseRequest->id)
        ->and($purchaseOrder->items->pluck('purchase_request_item_id')->all())->toContain($firstPurchaseRequestItem->id, $secondPurchaseRequestItem->id)
        ->and($purchaseOrder->subtotal)->toBe('475.00')
        ->and($purchaseOrder->total_amount)->toBe('475.00');
});

test('purchase orders only expose and accept approved purchase requests', function () {
    $user = userWithPermissions(['purchase-orders.create']);
    [$company, $vendor] = companyVendorForPurchaseOrder($user);
    $approvedPurchaseRequest = PurchaseRequest::factory()->create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => PurchaseRequest::STATUS_APPROVED,
    ]);
    PurchaseRequest::factory()->create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => PurchaseRequest::STATUS_DRAFT,
    ]);
    $draftPurchaseRequest = PurchaseRequest::factory()->create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => PurchaseRequest::STATUS_DRAFT,
    ]);
    $payload = purchaseOrderPayload($company, $vendor);
    $payload['purchase_request_ids'] = [$draftPurchaseRequest->id];

    $this->actingAs($user)
        ->get(route('purchase-orders.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchase-orders/Create')
            ->has('purchaseRequests', 1)
            ->where('purchaseRequests.0.id', $approvedPurchaseRequest->id)
        );

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), $payload)
        ->assertSessionHasErrors('purchase_request_ids');
});

test('purchase order linked items must belong to the selected purchase request', function () {
    $user = userWithPermissions(['purchase-orders.create']);
    [$company, $vendor] = companyVendorForPurchaseOrder($user);
    $purchaseRequest = PurchaseRequest::factory()->create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => PurchaseRequest::STATUS_APPROVED,
    ]);
    $otherPurchaseRequest = PurchaseRequest::factory()->create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => PurchaseRequest::STATUS_APPROVED,
    ]);
    $otherPurchaseRequestItem = PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $otherPurchaseRequest->id,
    ]);
    $payload = purchaseOrderPayload($company, $vendor);
    $payload['purchase_request_ids'] = [$purchaseRequest->id];
    $payload['items'][0]['purchase_request_item_id'] = $otherPurchaseRequestItem->id;

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), $payload)
        ->assertSessionHasErrors('items.0.purchase_request_item_id');
});

test('purchase order status transitions are controlled', function () {
    $user = userWithPermissions([
        'purchase-orders.view',
        'purchase-orders.submit',
        'purchase-orders.approve',
        'purchase-orders.edit',
    ]);
    [$company, $vendor] = companyVendorForPurchaseOrder($user);
    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'created_by' => $user->id,
        'status' => PurchaseOrder::STATUS_DRAFT,
    ]);

    $this->actingAs($user)
        ->patch(route('purchase-orders.approve', $purchaseOrder))
        ->assertStatus(422);

    $this->actingAs($user)
        ->patch(route('purchase-orders.submit', $purchaseOrder))
        ->assertRedirect();

    expect($purchaseOrder->fresh()->status)->toBe(PurchaseOrder::STATUS_FOR_APPROVAL);

    $this->actingAs($user)
        ->patch(route('purchase-orders.approve', $purchaseOrder))
        ->assertRedirect();

    expect($purchaseOrder->fresh()->status)->toBe(PurchaseOrder::STATUS_APPROVED);

    $this->actingAs($user)
        ->patch(route('purchase-orders.send', $purchaseOrder))
        ->assertRedirect();

    expect($purchaseOrder->fresh()->status)->toBe(PurchaseOrder::STATUS_SENT);

    $this->actingAs($user)
        ->patch(route('purchase-orders.complete', $purchaseOrder))
        ->assertRedirect();

    expect($purchaseOrder->fresh()->status)->toBe(PurchaseOrder::STATUS_COMPLETED);
});

test('approved purchase orders cannot be freely edited', function () {
    $user = userWithPermissions(['purchase-orders.view', 'purchase-orders.edit']);
    [$company, $vendor] = companyVendorForPurchaseOrder($user);
    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => PurchaseOrder::STATUS_APPROVED,
    ]);

    $this->actingAs($user)
        ->get(route('purchase-orders.edit', $purchaseOrder))
        ->assertStatus(422);

    $this->actingAs($user)
        ->put(route('purchase-orders.update', $purchaseOrder), purchaseOrderPayload($company, $vendor))
        ->assertStatus(422);
});

test('purchase order print uses company logo and print signatories', function () {
    $user = userWithPermissions(['purchase-orders.view', 'purchase-orders.print']);
    [$company, $vendor] = companyVendorForPurchaseOrder($user);
    $company->update([
        'logo' => 'company-logos/sample.png',
        'purchasing_assistant_name' => 'Harold Asuncion',
        'corporate_sales_manager_name' => 'Rose Paguia',
    ]);
    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'created_by' => $user->id,
        'terms_conditions' => '30-0-0',
    ]);

    $this->actingAs($user)
        ->get(route('purchase-orders.print', $purchaseOrder))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchase-orders/Print')
            ->where('purchaseOrder.company.logo_url', 'http://localhost:9090/storage/company-logos/sample.png')
            ->where('purchaseOrder.prepared_by.name', 'Harold Asuncion')
            ->where('purchaseOrder.noted_by.name', 'Rose Paguia')
            ->where('purchaseOrder.payment_terms', '30-0-0')
        );
});
