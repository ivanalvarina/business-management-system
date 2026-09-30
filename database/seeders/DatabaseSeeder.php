<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const SUPER_ADMIN_ROLE = 'Super Admin';

    private const SUPER_ADMIN_EMAIL = 'superadmin@email.com';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $superAdminRole = Role::findOrCreate(self::SUPER_ADMIN_ROLE, 'web');
        $superAdminRole->syncPermissions(Permission::query()->pluck('name')->all());

        $superAdmin = User::updateOrCreate(
            ['email' => self::SUPER_ADMIN_EMAIL],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $superAdmin->syncRoles([$superAdminRole]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
