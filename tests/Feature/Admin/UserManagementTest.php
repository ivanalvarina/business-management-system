<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

test('users without permission cannot access user management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('authorized users can list users', function () {
    $admin = userWithPermissions('users.view');
    User::factory()->create(['name' => 'Taylor Admin']);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => 'Taylor']))
        ->assertOk();
});

test('authorized users can create users and assign roles', function () {
    Notification::fake();

    $admin = userWithPermissions(['users.create', 'users.view']);
    $company = Company::factory()->create();
    $admin->companies()->attach($company);
    $role = Role::create(['name' => 'Operations', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Morgan Lee',
            'email' => 'morgan@example.test',
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
            'is_active' => true,
            'roles' => [$role->id],
            'company_ids' => [$company->id],
        ])
        ->assertRedirect();

    $createdUser = User::where('email', 'morgan@example.test')->firstOrFail();

    expect($createdUser->is_active)->toBeTrue()
        ->and($createdUser->must_change_password)->toBeTrue()
        ->and($createdUser->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('abcdefgh', $createdUser->password))->toBeTrue()
        ->and($createdUser->hasRole($role))->toBeTrue()
        ->and($createdUser->companies()->sole()->is($company))->toBeTrue();

    Notification::assertNothingSent();
});

test('temporary password must contain at least eight characters', function () {
    Notification::fake();

    $admin = userWithPermissions('users.create');
    $company = Company::factory()->create();
    $admin->companies()->attach($company);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Morgan Lee',
            'email' => 'morgan@example.test',
            'password' => 'short7',
            'password_confirmation' => 'short7',
            'is_active' => true,
            'roles' => [],
            'company_ids' => [$company->id],
        ])
        ->assertSessionHasErrors('password');

    expect(User::where('email', 'morgan@example.test')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

test('authorized users can update company access for another user', function () {
    $admin = userWithPermissions('users.edit');
    $firstCompany = Company::factory()->create();
    $secondCompany = Company::factory()->create();
    $admin->companies()->attach([$firstCompany->id, $secondCompany->id]);
    $managedUser = User::factory()->create();
    $managedUser->companies()->attach($firstCompany);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'is_active' => true,
            'roles' => [],
            'company_ids' => [$secondCompany->id],
        ])
        ->assertRedirect();

    expect($managedUser->companies()->pluck('companies.id')->all())
        ->toBe([$secondCompany->id]);
});

test('users cannot assign company access outside their own scope', function () {
    $admin = userWithPermissions('users.create');
    $assignedCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $admin->companies()->attach($assignedCompany);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Morgan Lee',
            'email' => 'morgan@example.test',
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
            'is_active' => true,
            'roles' => [],
            'company_ids' => [$otherCompany->id],
        ])
        ->assertSessionHasErrors('company_ids');

    expect(User::where('email', 'morgan@example.test')->exists())->toBeFalse();
});

test('authorized users can deactivate another user', function () {
    $admin = userWithPermissions('users.edit');
    $managedUser = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.users.deactivate', $managedUser))
        ->assertRedirect();

    expect($managedUser->fresh()->is_active)->toBeFalse();
});

test('users cannot deactivate their own account', function () {
    $admin = userWithPermissions('users.edit');

    $this->actingAs($admin)
        ->patch(route('admin.users.deactivate', $admin))
        ->assertRedirect();

    expect($admin->fresh()->is_active)->toBeTrue();
});
