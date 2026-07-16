<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orkestrasi seeding. Urutan penting: RBAC & master data dulu, baru user
 * (user membutuhkan role, department, dan bank).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DepartmentSeeder::class,
            CategorySeeder::class,
            BankSeeder::class,
            UserSeeder::class,
        ]);
    }
}
