<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('users without permission cannot access role management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});

test('authorized users can list roles', function () {
    $admin = userWithPermissions('roles.view');
    Role::create(['name' => 'Finance', 'guard_name' => 'web']);

    $this->actingAs($admin)
        ->get(route('admin.roles.index', ['search' => 'Finance']))
        ->assertOk();
});

test('authorized users can create roles with permissions', function () {
    $admin = userWithPermissions(['roles.create', 'roles.view']);
    $permission = Permission::findOrCreate('users.view', 'web');

    $this->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => 'User Viewer',
            'permissions' => [$permission->id],
        ])
        ->assertRedirect();

    $role = Role::where('name', 'User Viewer')->firstOrFail();

    expect($role->hasPermissionTo('users.view'))->toBeTrue();
});

test('authorized users can update role permissions', function () {
    $admin = userWithPermissions(['roles.edit', 'roles.view']);
    $role = Role::create(['name' => 'User Manager', 'guard_name' => 'web']);
    $permission = Permission::findOrCreate('users.edit', 'web');

    $this->actingAs($admin)
        ->put(route('admin.roles.update', $role), [
            'name' => 'User Manager',
            'permissions' => [$permission->id],
        ])
        ->assertRedirect(route('admin.roles.show', $role));

    expect($role->fresh()->hasPermissionTo('users.edit'))->toBeTrue();
});

test('roles assigned to users cannot be deleted', function () {
    $admin = userWithPermissions('roles.delete');
    $role = Role::create(['name' => 'Assigned Role', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($role);

    $this->actingAs($admin)
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect();

    expect($role->fresh())->not->toBeNull();
});
