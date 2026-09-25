<?php

use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategory;

test('users without permission cannot access vendor management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('vendors.index'))
        ->assertForbidden();
});

test('authorized users can create vendors for multiple companies with contacts and a new category', function () {
    $admin = userWithPermissions([
        'vendors.create',
        'vendors.view',
        'companies.access-all',
    ]);
    $firstCompany = Company::factory()->create(['company_code' => 'LCI']);
    $secondCompany = Company::factory()->create(['company_code' => 'LSI']);

    $this->actingAs($admin)
        ->post(route('vendors.store'), [
            'vendor_code' => 'VEN-001',
            'vendor_name' => 'Acme Supplier',
            'trade_name' => 'Acme Supply',
            'tin' => '987-654-321-000',
            'email' => 'sales@supplier.test',
            'phone' => '555-7000',
            'address' => 'Supplier Office',
            'new_category_name' => 'Raw Materials',
            'status' => Vendor::STATUS_ACTIVE,
            'company_ids' => [$firstCompany->id, $secondCompany->id],
            'contacts' => [
                [
                    'name' => 'Jane Supplier',
                    'position' => 'Account Manager',
                    'department' => 'Sales',
                    'email' => 'jane@supplier.test',
                    'phone' => '555-7001',
                    'mobile' => '555-7002',
                    'is_primary' => true,
                ],
                [
                    'name' => 'John Logistics',
                    'position' => 'Coordinator',
                    'department' => 'Logistics',
                    'email' => 'john@supplier.test',
                    'phone' => '555-7003',
                    'mobile' => null,
                    'is_primary' => false,
                ],
            ],
        ])
        ->assertRedirect();

    $vendor = Vendor::where('vendor_code', 'VEN-001')->firstOrFail();

    expect($vendor->companies()->pluck('companies.id')->sort()->values()->all())
        ->toBe([$firstCompany->id, $secondCompany->id])
        ->and($vendor->category->name)->toBe('Raw Materials')
        ->and($vendor->contactPeople()->count())->toBe(2)
        ->and($vendor->contactPeople()->where('is_primary', true)->first()?->name)->toBe('Jane Supplier');
});

test('vendor code category and company relationships must be valid and unique', function () {
    $admin = userWithPermissions(['vendors.create', 'companies.access-all']);
    $company = Company::factory()->create();
    $category = VendorCategory::factory()->create();
    Vendor::factory()->create(['vendor_code' => 'VEN-001']);

    $this->actingAs($admin)
        ->post(route('vendors.store'), [
            'vendor_code' => 'VEN-001',
            'vendor_name' => 'Duplicate Vendor',
            'vendor_category_id' => $category->id,
            'status' => Vendor::STATUS_ACTIVE,
            'company_ids' => [$company->id],
        ])
        ->assertSessionHasErrors('vendor_code');

    $this->actingAs($admin)
        ->post(route('vendors.store'), [
            'vendor_code' => 'VEN-002',
            'vendor_name' => 'Duplicate Company Assignment',
            'vendor_category_id' => $category->id,
            'status' => Vendor::STATUS_ACTIVE,
            'company_ids' => [$company->id, $company->id],
        ])
        ->assertSessionHasErrors('company_ids.0');

    $this->actingAs($admin)
        ->post(route('vendors.store'), [
            'vendor_code' => 'VEN-003',
            'vendor_name' => 'Missing Category',
            'status' => Vendor::STATUS_ACTIVE,
            'company_ids' => [$company->id],
        ])
        ->assertSessionHasErrors('vendor_category_id');
});

test('scoped users only see and access vendors for their companies', function () {
    $user = userWithPermissions('vendors.view');
    $assignedCompany = Company::factory()->create();
    $unassignedCompany = Company::factory()->create();
    $assignedVendor = Vendor::factory()->create(['vendor_name' => 'Visible Vendor']);
    $hiddenVendor = Vendor::factory()->create(['vendor_name' => 'Hidden Vendor']);

    $user->companies()->attach($assignedCompany);
    $assignedVendor->companies()->attach($assignedCompany, ['status' => Company::STATUS_ACTIVE]);
    $hiddenVendor->companies()->attach($unassignedCompany, ['status' => Company::STATUS_ACTIVE]);

    $this->actingAs($user)
        ->get(route('vendors.index'))
        ->assertOk()
        ->assertSee('Visible Vendor')
        ->assertDontSee('Hidden Vendor');

    $this->actingAs($user)
        ->get(route('vendors.show', $assignedVendor))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('vendors.show', $hiddenVendor))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('vendors.index', ['company_id' => $unassignedCompany->id]))
        ->assertForbidden();
});

test('vendor listing supports search company category and status filters', function () {
    $admin = userWithPermissions(['vendors.view', 'companies.access-all']);
    $firstCompany = Company::factory()->create();
    $secondCompany = Company::factory()->create();
    $materials = VendorCategory::factory()->create(['name' => 'Materials']);
    $services = VendorCategory::factory()->create(['name' => 'Services']);
    $activeVendor = Vendor::factory()->create([
        'vendor_code' => 'FIND-ME',
        'vendor_name' => 'Filtered Vendor',
        'tin' => 'TIN-789',
        'vendor_category_id' => $materials->id,
        'status' => Vendor::STATUS_ACTIVE,
    ]);
    $inactiveVendor = Vendor::factory()->create([
        'vendor_name' => 'Inactive Other',
        'vendor_category_id' => $services->id,
        'status' => Vendor::STATUS_INACTIVE,
    ]);

    $activeVendor->companies()->attach($firstCompany, ['status' => Company::STATUS_ACTIVE]);
    $inactiveVendor->companies()->attach($secondCompany, ['status' => Company::STATUS_ACTIVE]);

    $this->actingAs($admin)
        ->get(route('vendors.index', [
            'search' => 'TIN-789',
            'company_id' => $firstCompany->id,
            'vendor_category_id' => $materials->id,
            'status' => Vendor::STATUS_ACTIVE,
        ]))
        ->assertOk()
        ->assertSee('Filtered Vendor')
        ->assertDontSee('Inactive Other');
});

test('vendor assignment cannot include unauthorized companies', function () {
    $user = userWithPermissions(['vendors.create', 'vendors.view']);
    $assignedCompany = Company::factory()->create();
    $unassignedCompany = Company::factory()->create();
    $category = VendorCategory::factory()->create();
    $user->companies()->attach($assignedCompany);

    $this->actingAs($user)
        ->post(route('vendors.store'), [
            'vendor_code' => 'VEN-004',
            'vendor_name' => 'Unauthorized Company Vendor',
            'vendor_category_id' => $category->id,
            'status' => Vendor::STATUS_ACTIVE,
            'company_ids' => [$assignedCompany->id, $unassignedCompany->id],
        ])
        ->assertSessionHasErrors('company_ids');

    expect(Vendor::where('vendor_code', 'VEN-004')->exists())->toBeFalse();
});

test('authorized users can update and deactivate vendors', function () {
    $user = userWithPermissions(['vendors.view', 'vendors.edit']);
    $company = Company::factory()->create();
    $category = VendorCategory::factory()->create();
    $vendor = Vendor::factory()->create([
        'vendor_code' => 'OLD',
        'vendor_name' => 'Old Vendor',
        'vendor_category_id' => $category->id,
    ]);

    $user->companies()->attach($company);
    $vendor->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);

    $this->actingAs($user)
        ->put(route('vendors.update', $vendor), [
            'vendor_code' => 'NEW',
            'vendor_name' => 'New Vendor',
            'vendor_category_id' => $category->id,
            'status' => Vendor::STATUS_ACTIVE,
            'company_ids' => [$company->id],
            'contacts' => [
                [
                    'name' => 'Updated Contact',
                    'is_primary' => false,
                ],
            ],
        ])
        ->assertRedirect(route('vendors.show', $vendor));

    expect($vendor->fresh()->vendor_code)->toBe('NEW')
        ->and($vendor->contactPeople()->where('is_primary', true)->first()?->name)->toBe('Updated Contact');

    $this->actingAs($user)
        ->patch(route('vendors.deactivate', $vendor))
        ->assertRedirect();

    expect($vendor->fresh()->status)->toBe(Vendor::STATUS_INACTIVE);
});
