<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
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
    $role = Role::create(['name' => 'Operations', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Morgan Lee',
            'email' => 'morgan@example.test',
            'is_active' => true,
            'roles' => [$role->id],
        ])
        ->assertRedirect();

    $createdUser = User::where('email', 'morgan@example.test')->firstOrFail();

    expect($createdUser->is_active)->toBeTrue()
        ->and($createdUser->hasRole($role))->toBeTrue();

    Notification::assertSentTo($createdUser, ResetPassword::class);
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
