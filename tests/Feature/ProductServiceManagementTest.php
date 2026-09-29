<?php

use App\Models\Company;
use App\Models\ProductImage;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('products support quantity and services reset quantity to zero', function () {
    $user = userWithPermissions('products-services.create');

    $this->actingAs($user)
        ->post(route('product-services.store'), [
            'code' => 'QTY-001',
            'type' => ProductService::TYPE_PRODUCT,
            'name' => 'Stocked Product',
            'unit' => 'pc',
            'default_price' => '100.00',
            'quantity' => 25,
            'status' => ProductService::STATUS_ACTIVE,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('product-services.store'), [
            'code' => 'QTY-002',
            'type' => ProductService::TYPE_SERVICE,
            'name' => 'Service Item',
            'unit' => 'hour',
            'default_price' => '100.00',
            'quantity' => 25,
            'status' => ProductService::STATUS_ACTIVE,
        ])
        ->assertRedirect();

    expect(ProductService::where('code', 'QTY-001')->firstOrFail()->quantity)->toBe(25)
        ->and(ProductService::where('code', 'QTY-002')->firstOrFail()->quantity)->toBe(0);
});

test('quantity cannot be negative', function () {
    $user = userWithPermissions('products-services.create');

    $this->actingAs($user)
        ->post(route('product-services.store'), [
            'code' => 'QTY-NEG',
            'type' => ProductService::TYPE_PRODUCT,
            'name' => 'Negative Product',
            'unit' => 'pc',
            'default_price' => '100.00',
            'quantity' => -1,
            'status' => ProductService::STATUS_ACTIVE,
        ])
        ->assertSessionHasErrors('quantity');
});

test('products can upload images with first image primary by default', function () {
    Storage::fake('public');
    $user = userWithPermissions('products-services.create');

    $this->actingAs($user)
        ->post(route('product-services.store'), [
            'code' => 'IMG-001',
            'type' => ProductService::TYPE_PRODUCT,
            'name' => 'Image Product',
            'unit' => 'pc',
            'default_price' => '100.00',
            'status' => ProductService::STATUS_ACTIVE,
            'images' => [
                UploadedFile::fake()->image('first.jpg'),
                UploadedFile::fake()->image('second.png'),
            ],
        ])
        ->assertRedirect();

    $product = ProductService::where('code', 'IMG-001')->firstOrFail();

    expect($product->images)->toHaveCount(2)
        ->and($product->images()->where('is_primary', true)->firstOrFail()->original_filename)->toBe('first.jpg');
});

test('products can upload five images and reject a sixth image', function () {
    Storage::fake('public');
    $user = userWithPermissions('products-services.create');

    $this->actingAs($user)
        ->post(route('product-services.store'), [
            'code' => 'IMG-005',
            'type' => ProductService::TYPE_PRODUCT,
            'name' => 'Five Image Product',
            'unit' => 'pc',
            'default_price' => '100.00',
            'status' => ProductService::STATUS_ACTIVE,
            'images' => collect(range(1, 5))->map(fn (int $number) => UploadedFile::fake()->image("image-{$number}.jpg"))->all(),
        ])
        ->assertRedirect();

    expect(ProductService::where('code', 'IMG-005')->firstOrFail()->images)->toHaveCount(5);

    $this->actingAs($user)
        ->post(route('product-services.store'), [
            'code' => 'IMG-006',
            'type' => ProductService::TYPE_PRODUCT,
            'name' => 'Six Image Product',
            'unit' => 'pc',
            'default_price' => '100.00',
            'status' => ProductService::STATUS_ACTIVE,
            'images' => collect(range(1, 6))->map(fn (int $number) => UploadedFile::fake()->image("image-{$number}.jpg"))->all(),
        ])
        ->assertSessionHasErrors('images');
});

test('invalid and oversized product images are rejected', function () {
    Storage::fake('public');
    $user = userWithPermissions('products-services.create');
    $payload = [
        'code' => 'IMG-BAD',
        'type' => ProductService::TYPE_PRODUCT,
        'name' => 'Bad Image Product',
        'unit' => 'pc',
        'default_price' => '100.00',
        'status' => ProductService::STATUS_ACTIVE,
    ];

    $this->actingAs($user)
        ->post(route('product-services.store'), [
            ...$payload,
            'images' => [UploadedFile::fake()->create('payload.php', 1, 'application/x-php')],
        ])
        ->assertSessionHasErrors('images.0');

    $this->actingAs($user)
        ->post(route('product-services.store'), [
            ...$payload,
            'code' => 'IMG-BIG',
            'images' => [UploadedFile::fake()->image('large.jpg')->size(3000)],
        ])
        ->assertSessionHasErrors('images.0');
});

test('primary image can be changed and deleting primary selects another image', function () {
    Storage::fake('public');
    $user = userWithPermissions('products-services.edit');
    $product = ProductService::factory()->create(['type' => ProductService::TYPE_PRODUCT]);
    $primary = ProductImage::factory()->for($product)->create(['image_path' => 'product-images/primary.jpg', 'is_primary' => true, 'sort_order' => 0]);
    $secondary = ProductImage::factory()->for($product)->create(['image_path' => 'product-images/secondary.jpg', 'is_primary' => false, 'sort_order' => 1]);

    $this->actingAs($user)
        ->post(route('product-services.update', $product), [
            '_method' => 'put',
            'code' => $product->code,
            'type' => ProductService::TYPE_PRODUCT,
            'name' => $product->name,
            'unit' => $product->unit,
            'default_price' => $product->default_price,
            'quantity' => $product->quantity,
            'status' => $product->status,
            'existing_image_ids' => [$primary->id, $secondary->id],
            'primary_existing_image_id' => $secondary->id,
        ])
        ->assertRedirect();

    expect($secondary->fresh()->is_primary)->toBeTrue();

    $this->actingAs($user)
        ->post(route('product-services.update', $product), [
            '_method' => 'put',
            'code' => $product->code,
            'type' => ProductService::TYPE_PRODUCT,
            'name' => $product->name,
            'unit' => $product->unit,
            'default_price' => $product->default_price,
            'quantity' => $product->quantity,
            'status' => $product->status,
            'existing_image_ids' => [$primary->id],
        ])
        ->assertRedirect();

    expect($primary->fresh()->is_primary)->toBeTrue()
        ->and(ProductImage::whereKey($secondary->id)->exists())->toBeFalse();
});

test('existing plus new images cannot exceed five', function () {
    Storage::fake('public');
    $user = userWithPermissions('products-services.edit');
    $product = ProductService::factory()->create(['type' => ProductService::TYPE_PRODUCT]);
    $images = ProductImage::factory()->count(4)->for($product)->create();

    $this->actingAs($user)
        ->post(route('product-services.update', $product), [
            '_method' => 'put',
            'code' => $product->code,
            'type' => ProductService::TYPE_PRODUCT,
            'name' => $product->name,
            'unit' => $product->unit,
            'default_price' => $product->default_price,
            'quantity' => $product->quantity,
            'status' => $product->status,
            'existing_image_ids' => $images->pluck('id')->all(),
            'images' => [
                UploadedFile::fake()->image('new-1.jpg'),
                UploadedFile::fake()->image('new-2.jpg'),
            ],
        ])
        ->assertSessionHasErrors('images');
});

test('listing includes the primary image thumbnail', function () {
    $user = userWithPermissions('products-services.view');
    $product = ProductService::factory()->create([
        'type' => ProductService::TYPE_PRODUCT,
        'name' => 'Thumbnail Product',
    ]);
    ProductImage::factory()->for($product)->create([
        'image_path' => 'product-images/thumb.jpg',
        'is_primary' => true,
    ]);

    $this->actingAs($user)
        ->get(route('product-services.index'))
        ->assertOk()
        ->assertSee('Thumbnail Product')
        ->assertSee('product-images/thumb.jpg');
});

test('products receive unique secure public tokens', function () {
    $first = ProductService::factory()->create();
    $second = ProductService::factory()->create();

    expect($first->public_token)->toHaveLength(64)
        ->and(ctype_xdigit($first->public_token))->toBeTrue()
        ->and($first->public_token)->not->toBe($second->public_token);
});

test('product qr print endpoints require product service view permission', function () {
    $product = ProductService::factory()->create([
        'type' => ProductService::TYPE_PRODUCT,
        'status' => ProductService::STATUS_ACTIVE,
        'is_public' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('product-services.qr-print', $product))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('product-services.bulk-qr-print'))
        ->assertForbidden();
});

test('authorized users can print single and bulk public product qr codes', function () {
    $user = userWithPermissions('products-services.view');
    $publicProduct = ProductService::factory()->create([
        'code' => 'QR-001',
        'name' => 'Printable Product',
        'type' => ProductService::TYPE_PRODUCT,
        'status' => ProductService::STATUS_ACTIVE,
        'is_public' => true,
    ]);
    $privateProduct = ProductService::factory()->create([
        'type' => ProductService::TYPE_PRODUCT,
        'status' => ProductService::STATUS_ACTIVE,
        'is_public' => false,
    ]);
    $service = ProductService::factory()->create([
        'type' => ProductService::TYPE_SERVICE,
        'status' => ProductService::STATUS_ACTIVE,
        'is_public' => true,
    ]);

    $singleResponse = $this->actingAs($user)
        ->get(route('product-services.qr-print', $publicProduct))
        ->assertOk();

    expect($singleResponse->inertiaProps('items.0.code'))->toBe('QR-001')
        ->and($singleResponse->inertiaProps('items.0.public_url'))->toBe(route('public-products.show', $publicProduct->public_token));

    $bulkResponse = $this->actingAs($user)
        ->get(route('product-services.bulk-qr-print', [
            'ids' => "{$publicProduct->id},{$privateProduct->id},{$service->id}",
        ]))
        ->assertOk();

    expect($bulkResponse->inertiaProps('items'))->toHaveCount(1)
        ->and($bulkResponse->inertiaProps('items.0.id'))->toBe($publicProduct->id);
});

test('qr print rejects products without a public active product page', function () {
    $user = userWithPermissions('products-services.view');
    $privateProduct = ProductService::factory()->create([
        'type' => ProductService::TYPE_PRODUCT,
        'status' => ProductService::STATUS_ACTIVE,
        'is_public' => false,
    ]);
    $service = ProductService::factory()->create([
        'type' => ProductService::TYPE_SERVICE,
        'status' => ProductService::STATUS_ACTIVE,
        'is_public' => true,
    ]);

    $this->actingAs($user)
        ->get(route('product-services.qr-print', $privateProduct))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('product-services.qr-print', $service))
        ->assertNotFound();
});

test('public product page only exposes active public products and safe fields', function () {
    Company::factory()->create([
        'company_name' => 'Acme Corporation',
        'trade_name' => 'Acme Trading',
        'email' => 'sales@example.test',
        'phone' => '+63 900 000 0000',
        'logo' => 'company-logos/acme.png',
    ]);
    $publicProduct = ProductService::factory()->create([
        'type' => ProductService::TYPE_PRODUCT,
        'status' => ProductService::STATUS_ACTIVE,
        'is_public' => true,
        'name' => 'Public Product',
        'default_price' => '250.75',
        'quantity' => 17,
    ]);
    $privateProduct = ProductService::factory()->create([
        'type' => ProductService::TYPE_PRODUCT,
        'status' => ProductService::STATUS_ACTIVE,
        'is_public' => false,
    ]);
    $inactiveProduct = ProductService::factory()->create([
        'type' => ProductService::TYPE_PRODUCT,
        'status' => ProductService::STATUS_INACTIVE,
        'is_public' => true,
    ]);

    $response = $this->get(route('public-products.show', $publicProduct->public_token))
        ->assertOk()
        ->assertSee('Public Product')
        ->assertDontSee('"quantity"')
        ->assertDontSee('"id":'.$publicProduct->id);

    expect($response->inertiaProps('product.default_price'))->toBe('250.75')
        ->and($response->inertiaProps('product.company.name'))->toBe('Acme Trading')
        ->and($response->inertiaProps('product.company.email'))->toBe('sales@example.test')
        ->and($response->inertiaProps('product.company.phone'))->toBe('+63 900 000 0000')
        ->and($response->inertiaProps('product.company.logo_url'))->toBe('http://localhost:9090/storage/company-logos/acme.png');

    $this->get(route('public-products.show', $privateProduct->public_token))->assertNotFound();
    $this->get(route('public-products.show', $inactiveProduct->public_token))->assertNotFound();
    $this->get(route('public-products.show', 'not-a-valid-token'))->assertNotFound();
});
