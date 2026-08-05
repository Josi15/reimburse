<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Database\Factories\DepartmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Departemen bersama untuk test.
 *
 * Cakupan data disaring per departemen (lihat Reimbursement::visibleTo), jadi
 * pengaju dan approver HARUS berada di unit yang sama agar mencerminkan kondisi
 * normal aplikasi. firstOrCreate, bukan static: setiap test dijalankan dalam
 * transaksi yang di-rollback, sehingga barisnya dibuat ulang per test.
 */
function testDepartment(): Department
{
    return DepartmentFactory::shared();
}

/**
 * Buat user terverifikasi dengan role tertentu (role harus sudah di-seed).
 * Tanpa $department, user masuk ke departemen bersama testDepartment().
 */
function userWithRole(string $role, ?Department $department = null): User
{
    $user = User::factory()->create([
        'department_id' => ($department ?? testDepartment())->id,
    ]);
    $user->roles()->attach(Role::where('name', $role)->firstOrFail());

    return $user->fresh();
}

/** Employee dengan department (department_id wajib pada reimbursement). */
function employeeUser(?Department $department = null): User
{
    return userWithRole('employee', $department);
}
