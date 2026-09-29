<?php

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReceipt;
use App\Models\User;
use App\Models\Vendor;

function receivingReceiptPurchaseOrder(User $user): array
{
    $company = Company::factory()->create();
    $vendor = Vendor::factory()->create();
    $company->users()->attach($user);
    $vendor->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);
    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => PurchaseOrder::STATUS_APPROVED,
    ]);
    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'description' => 'UPS unit',
        'quantity' => '2.0000',
    ]);

    return [$company, $vendor, $purchaseOrder, $item];
}

function receivingReceiptPayload(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): array
{
    return [
        'purchase_order_id' => $purchaseOrder->id,
        'invoice_no' => 'DR-001',
        'received_date' => '2026-09-29',
        'received_by_name' => 'Receiver User',
        'checked_by_name' => 'Checker User',
        'notes' => 'Complete',
        'items' => [
            [
                'purchase_order_item_id' => $item->id,
                'quantity' => '2.0000',
                'description' => 'UPS unit',
            ],
        ],
    ];
}

test('users without permission cannot access receiving receipt management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('receiving-receipts.index'))
        ->assertForbidden();
});

test('authorized users can record receiving receipts against approved purchase orders', function () {
    $user = userWithPermissions(['receiving-receipts.create', 'receiving-receipts.view']);
    [$company, $vendor, $purchaseOrder, $item] = receivingReceiptPurchaseOrder($user);

    $this->actingAs($user)
        ->post(route('receiving-receipts.store'), receivingReceiptPayload($purchaseOrder, $item))
        ->assertRedirect();

    $receipt = ReceivingReceipt::with('items')->firstOrFail();

    expect($receipt->rr_no)->toBe('RR-2026-000001')
        ->and($receipt->company_id)->toBe($company->id)
        ->and($receipt->vendor_id)->toBe($vendor->id)
        ->and($receipt->items)->toHaveCount(1);
});

test('receiving receipt rejects inaccessible purchase orders and unrelated items', function () {
    $user = userWithPermissions('receiving-receipts.create');
    [$company, $vendor, $purchaseOrder, $item] = receivingReceiptPurchaseOrder($user);
    $otherPurchaseOrder = PurchaseOrder::factory()->create([
        'status' => PurchaseOrder::STATUS_APPROVED,
    ]);
    $otherItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $otherPurchaseOrder->id,
    ]);

    $this->actingAs($user)
        ->post(route('receiving-receipts.store'), receivingReceiptPayload($otherPurchaseOrder, $otherItem))
        ->assertSessionHasErrors('purchase_order_id');

    $this->actingAs($user)
        ->post(route('receiving-receipts.store'), receivingReceiptPayload($purchaseOrder, $otherItem))
        ->assertSessionHasErrors('items.0.purchase_order_item_id');
});
