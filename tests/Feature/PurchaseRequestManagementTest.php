<?php

use App\Models\Company;
use App\Models\ProductService;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\Vendor;

function purchaseRequestCompanyVendor(User $user): array
{
    $company = Company::factory()->create();
    $vendor = Vendor::factory()->create();
    $company->users()->attach($user);
    $vendor->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);

    return [$company, $vendor];
}

function purchaseRequestPayload(Company $company, ?Vendor $vendor = null, ?ProductService $product = null): array
{
    return [
        'company_id' => $company->id,
        'vendor_id' => $vendor?->id,
        'request_date' => '2026-09-29',
        'date_required' => '2026-10-05',
        'client_project' => 'Mariam Rose',
        'requested_by_name' => 'Harold Asuncion',
        'checked_by_name' => 'Checker User',
        'noted_by_name' => 'Rose Paguia',
        'currency' => 'PHP',
        'notes' => 'Urgent request',
        'items' => [
            [
                'product_service_id' => $product?->id,
                'description' => 'UPS unit',
                'quantity' => '2.0000',
                'unit' => 'Unit',
                'unit_price' => '100.00',
                'tax' => '12.00',
            ],
        ],
    ];
}

test('users without permission cannot access purchase request management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('purchase-requests.index'))
        ->assertForbidden();
});

test('authorized users can create purchase requests with calculated totals and system numbers', function () {
    $user = userWithPermissions(['purchase-requests.create', 'purchase-requests.view']);
    [$company, $vendor] = purchaseRequestCompanyVendor($user);
    $product = ProductService::factory()->create(['status' => ProductService::STATUS_ACTIVE]);

    $this->actingAs($user)
        ->post(route('purchase-requests.store'), purchaseRequestPayload($company, $vendor, $product))
        ->assertRedirect();

    $purchaseRequest = PurchaseRequest::with('items')->firstOrFail();

    expect($purchaseRequest->pr_no)->toBe('PR-2026-000001')
        ->and($purchaseRequest->subtotal)->toBe('200.00')
        ->and($purchaseRequest->tax_amount)->toBe('12.00')
        ->and($purchaseRequest->total_amount)->toBe('212.00')
        ->and($purchaseRequest->items)->toHaveCount(1);
});

test('purchase request company access and vendor relationship are enforced', function () {
    $user = userWithPermissions('purchase-requests.create');
    [$company, $vendor] = purchaseRequestCompanyVendor($user);
    $otherCompany = Company::factory()->create();
    $otherVendor = Vendor::factory()->create();

    $this->actingAs($user)
        ->post(route('purchase-requests.store'), purchaseRequestPayload($otherCompany, $vendor))
        ->assertSessionHasErrors('company_id');

    $this->actingAs($user)
        ->post(route('purchase-requests.store'), purchaseRequestPayload($company, $otherVendor))
        ->assertSessionHasErrors('vendor_id');
});

test('purchase request status transitions are controlled', function () {
    $user = userWithPermissions([
        'purchase-requests.view',
        'purchase-requests.submit',
        'purchase-requests.approve',
        'purchase-requests.edit',
    ]);
    [$company, $vendor] = purchaseRequestCompanyVendor($user);
    $purchaseRequest = PurchaseRequest::factory()->create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => PurchaseRequest::STATUS_DRAFT,
    ]);

    $this->actingAs($user)
        ->patch(route('purchase-requests.approve', $purchaseRequest))
        ->assertStatus(422);

    $this->actingAs($user)
        ->patch(route('purchase-requests.submit', $purchaseRequest))
        ->assertRedirect();

    expect($purchaseRequest->fresh()->status)->toBe(PurchaseRequest::STATUS_FOR_APPROVAL);

    $this->actingAs($user)
        ->patch(route('purchase-requests.approve', $purchaseRequest))
        ->assertRedirect();

    expect($purchaseRequest->fresh()->status)->toBe(PurchaseRequest::STATUS_APPROVED);
});
