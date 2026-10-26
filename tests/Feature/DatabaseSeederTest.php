<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('database seeder creates a super admin user with all permissions', function () {
    $this->seed(DatabaseSeeder::class);

    $superAdmin = User::where('email', 'superadmin@email.com')->firstOrFail();
    $permissions = Permission::query()->pluck('name')->all();

    expect($superAdmin->name)->toBe('Super Admin')
        ->and($superAdmin->is_active)->toBeTrue()
        ->and($superAdmin->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('password', $superAdmin->password))->toBeTrue()
        ->and($superAdmin->hasRole('Super Admin'))->toBeTrue()
        ->and($superAdmin->hasAllPermissions($permissions))->toBeTrue()
        ->and(Role::where('name', 'Super Admin')->firstOrFail()->permissions)->toHaveCount(count($permissions));
});

test('database seeder can be rerun without duplicating default users', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'superadmin@email.com')->count())->toBe(1)
        ->and(User::where('email', 'test@example.com')->count())->toBe(1);
});
