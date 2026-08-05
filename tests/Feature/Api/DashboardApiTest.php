<?php

use App\Enums\ReimbursementStatus;
use App\Models\Department;
use App\Models\Reimbursement;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('employee dashboard is scoped to their own claims', function () {
    $me = employeeUser();
    Reimbursement::factory()->count(2)->create(['user_id' => $me->id]);
    Reimbursement::factory()->paid()->create(['user_id' => $me->id]);
    Reimbursement::factory()->create(); // milik orang lain

    Sanctum::actingAs($me);
    $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

    expect($data['scope'])->toBe('personal')
        ->and($data['cards']['total'])->toBe(3)
        ->and($data['cards']['paid'])->toBe(1)
        ->and($data['top_departments'])->toBe([]);
});

test('approved card counts both manager- and finance-approved (symmetric with rejected)', function () {
    Reimbursement::factory()->managerApproved()->count(2)->create();
    Reimbursement::factory()->financeApproved()->create();
    Reimbursement::factory()->create(['status' => ReimbursementStatus::ManagerRejected]);
    Sanctum::actingAs(userWithRole('super_admin'));

    $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

    expect($data['cards']['approved'])->toBe(3)   // 2 manager-approved + 1 finance-approved
        ->and($data['cards']['rejected'])->toBe(1);
});

test('manager dashboard is scoped to their department and reports the approval queue', function () {
    Reimbursement::factory()->submitted()->count(2)->create();
    // Departemen lain: tidak boleh ikut terhitung di antrean Manager.
    Reimbursement::factory()->submitted()->create([
        'user_id' => employeeUser(Department::factory()->create())->id,
    ]);
    Sanctum::actingAs(userWithRole('manager'));

    $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

    expect($data['scope'])->toBe('department')
        ->and($data['pending']['manager_approval'])->toBe(2);
});

test('finance dashboard reports approval and payment queues', function () {
    Reimbursement::factory()->managerApproved()->create();
    Reimbursement::factory()->financeApproved()->count(3)->create();
    Sanctum::actingAs(userWithRole('finance'));

    $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

    expect($data['pending']['finance_approval'])->toBe(1)
        ->and($data['pending']['awaiting_payment'])->toBe(3);
});

test('admin dashboard is limited to their own department', function () {
    Reimbursement::factory()->paid()->count(2)->create();
    Sanctum::actingAs(userWithRole('admin'));

    $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

    expect($data['scope'])->toBe('department')
        ->and($data['monthly_expense'])->toHaveCount(12)
        ->and($data['top_categories'])->not->toBeEmpty()
        // Rekap antar-departemen hanya untuk yang melihat lintas departemen.
        ->and($data['top_departments'])->toBe([]);
});

test('finance sees global stats with top categories, departments and 12 month chart', function () {
    Reimbursement::factory()->paid()->count(2)->create();
    Sanctum::actingAs(userWithRole('finance'));

    $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

    expect($data['scope'])->toBe('global')
        ->and($data['monthly_expense'])->toHaveCount(12)
        ->and($data['top_categories'])->not->toBeEmpty()
        ->and($data['top_departments'])->not->toBeEmpty();
});

test('monthly expense reflects paid amounts in the current month', function () {
    Reimbursement::factory()->paid()->create(['amount' => 400_000]);
    Reimbursement::factory()->paid()->create(['amount' => 600_000]);
    Sanctum::actingAs(userWithRole('finance'));

    $monthly = collect($this->getJson('/api/dashboard')->json('data.monthly_expense'));
    $thisMonth = $monthly->firstWhere('month', (int) now()->month);

    expect($thisMonth['total'])->toBe(1_000_000);
});

test('dashboard requires authentication', function () {
    $this->getJson('/api/dashboard')->assertUnauthorized();
});
