<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orkestrasi seeding. Urutan penting: RBAC & master data dulu, baru user
 * (user membutuhkan role, department, dan bank), lalu project — karena tiap
 * project ditugaskan ke seorang Project Manager.
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
            CompanyBankAccountSeeder::class,
            UserSeeder::class,
            ProjectSeeder::class,
        ]);
    }
}
