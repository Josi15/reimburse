<?php

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

test('manager dashboard reports the manager approval queue', function () {
    Reimbursement::factory()->submitted()->count(2)->create();
    Sanctum::actingAs(userWithRole('manager'));

    $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

    expect($data['scope'])->toBe('global')
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

test('admin sees global stats with top categories, departments and 12 month chart', function () {
    Reimbursement::factory()->paid()->count(2)->create();
    Sanctum::actingAs(userWithRole('admin'));

    $data = $this->getJson('/api/dashboard')->assertOk()->json('data');

    expect($data['scope'])->toBe('global')
        ->and($data['monthly_expense'])->toHaveCount(12)
        ->and($data['top_categories'])->not->toBeEmpty()
        ->and($data['top_departments'])->not->toBeEmpty();
});

test('monthly expense reflects paid amounts in the current month', function () {
    Reimbursement::factory()->paid()->create(['amount' => 400_000]);
    Reimbursement::factory()->paid()->create(['amount' => 600_000]);
    Sanctum::actingAs(userWithRole('admin'));

    $monthly = collect($this->getJson('/api/dashboard')->json('data.monthly_expense'));
    $thisMonth = $monthly->firstWhere('month', (int) now()->month);

    expect($thisMonth['total'])->toBe(1_000_000);
});

test('dashboard requires authentication', function () {
    $this->getJson('/api/dashboard')->assertUnauthorized();
});
