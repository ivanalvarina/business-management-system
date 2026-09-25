<?php

use App\Models\ProductService;
use App\Models\User;

test('users without permission cannot access product and service management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('product-services.index'))
        ->assertForbidden();
});

test('authorized users can create product and service catalog items', function () {
    $user = userWithPermissions(['products-services.create', 'products-services.view']);

    $this->actingAs($user)
        ->post(route('product-services.store'), [
            'code' => 'ITEM-001',
            'type' => ProductService::TYPE_PRODUCT,
            'name' => 'Steel Bracket',
            'description' => 'Standard bracket',
            'unit' => 'pc',
            'default_price' => '1250.50',
            'status' => ProductService::STATUS_ACTIVE,
        ])
        ->assertRedirect();

    $item = ProductService::where('code', 'ITEM-001')->firstOrFail();

    expect($item->type)->toBe(ProductService::TYPE_PRODUCT)
        ->and($item->default_price)->toBe('1250.50')
        ->and($item->isActive())->toBeTrue();
});

test('codes are unique and decimal values are validated', function () {
    $user = userWithPermissions('products-services.create');
    ProductService::factory()->create(['code' => 'ITEM-001']);

    $this->actingAs($user)
        ->post(route('product-services.store'), [
            'code' => 'ITEM-001',
            'type' => ProductService::TYPE_SERVICE,
            'name' => 'Installation',
            'unit' => 'hour',
            'default_price' => '100.00',
            'status' => ProductService::STATUS_ACTIVE,
        ])
        ->assertSessionHasErrors('code');

    $this->actingAs($user)
        ->post(route('product-services.store'), [
            'code' => 'ITEM-002',
            'type' => ProductService::TYPE_SERVICE,
            'name' => 'Installation',
            'unit' => 'hour',
            'default_price' => '100.999',
            'status' => ProductService::STATUS_ACTIVE,
        ])
        ->assertSessionHasErrors('default_price');
});

test('listing supports search type and status filters', function () {
    $user = userWithPermissions('products-services.view');
    ProductService::factory()->create([
        'code' => 'FIND-ME',
        'type' => ProductService::TYPE_PRODUCT,
        'name' => 'Filtered Product',
        'status' => ProductService::STATUS_ACTIVE,
    ]);
    ProductService::factory()->create([
        'code' => 'HIDDEN',
        'type' => ProductService::TYPE_SERVICE,
        'name' => 'Hidden Service',
        'status' => ProductService::STATUS_INACTIVE,
    ]);

    $this->actingAs($user)
        ->get(route('product-services.index', [
            'search' => 'FIND-ME',
            'type' => ProductService::TYPE_PRODUCT,
            'status' => ProductService::STATUS_ACTIVE,
        ]))
        ->assertOk()
        ->assertSee('Filtered Product')
        ->assertDontSee('Hidden Service');
});

test('authorized users can update and deactivate catalog items', function () {
    $user = userWithPermissions(['products-services.view', 'products-services.edit']);
    $item = ProductService::factory()->create([
        'code' => 'OLD',
        'default_price' => '10.00',
    ]);

    $this->actingAs($user)
        ->put(route('product-services.update', $item), [
            'code' => 'NEW',
            'type' => ProductService::TYPE_SERVICE,
            'name' => 'Updated Service',
            'description' => 'Updated',
            'unit' => 'hour',
            'default_price' => '250.75',
            'status' => ProductService::STATUS_ACTIVE,
        ])
        ->assertRedirect(route('product-services.show', $item));

    expect($item->fresh()->code)->toBe('NEW')
        ->and($item->fresh()->default_price)->toBe('250.75');

    $this->actingAs($user)
        ->patch(route('product-services.deactivate', $item))
        ->assertRedirect();

    expect($item->fresh()->status)->toBe(ProductService::STATUS_INACTIVE);
});
