<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\Reimbursement;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->employee = employeeUser();               // punya department sendiri
    $this->ownDept = $this->employee->department_id;
    $this->otherDept = Department::factory()->create(['name' => 'Marketing', 'is_active' => true]);
    $this->category = Category::factory()->create(['max_amount' => null]);
    Sanctum::actingAs($this->employee);
});

function claimPayload(array $override = []): array
{
    return array_merge([
        'category_id' => test()->category->id,
        'title' => 'Beli spanduk pameran',
        'reason' => 'Kebutuhan event',
        'amount' => 250_000,
    ], $override);
}

test('a claim defaults to the submitter own department', function () {
    $response = $this->postJson('/api/reimbursements', claimPayload())->assertCreated();

    expect($response->json('data.department_id'))->toBe($this->ownDept);
});

test('the submitter can charge the claim to another department', function () {
    $response = $this->postJson('/api/reimbursements', claimPayload([
        'department_id' => $this->otherDept->id,
    ]))->assertCreated();

    expect($response->json('data.department_id'))->toBe($this->otherDept->id);

    $claim = Reimbursement::find($response->json('data.id'));
    expect($claim->department_id)->toBe($this->otherDept->id)
        ->and($claim->user_id)->toBe($this->employee->id);   // pengaju tetap dirinya
});

test('an inactive department is rejected', function () {
    $inactive = Department::factory()->create(['is_active' => false]);

    $this->postJson('/api/reimbursements', claimPayload(['department_id' => $inactive->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('department_id');
});

test('a user without a department must pick one', function () {
    $this->employee->update(['department_id' => null]);

    $this->postJson('/api/reimbursements', claimPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('department_id');

    $this->postJson('/api/reimbursements', claimPayload(['department_id' => $this->otherDept->id]))
        ->assertCreated();
});

test('the department can be changed while the claim is still a draft', function () {
    $claim = Reimbursement::factory()->create([
        'user_id' => $this->employee->id,
        'department_id' => $this->ownDept,
        'category_id' => $this->category->id,
        'status' => 'draft',
    ]);

    $this->putJson("/api/reimbursements/{$claim->id}", [
        'department_id' => $this->otherDept->id,
    ])->assertOk();

    expect($claim->refresh()->department_id)->toBe($this->otherDept->id);
});

test('the list can be filtered by department', function () {
    Reimbursement::factory()->create(['user_id' => $this->employee->id, 'department_id' => $this->ownDept]);
    Reimbursement::factory()->create(['user_id' => $this->employee->id, 'department_id' => $this->otherDept->id]);

    $this->getJson("/api/reimbursements?department_id={$this->otherDept->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.department_id', $this->otherDept->id);
});

test('finance gets a per-department recap of submitted and paid amounts', function () {
    Reimbursement::factory()->paid()->create([
        'user_id' => $this->employee->id, 'department_id' => $this->otherDept->id, 'amount' => 400_000,
    ]);
    Reimbursement::factory()->submitted()->create([
        'user_id' => $this->employee->id, 'department_id' => $this->otherDept->id, 'amount' => 100_000,
    ]);
    Reimbursement::factory()->paid()->create([
        'user_id' => $this->employee->id, 'department_id' => $this->ownDept, 'amount' => 50_000,
    ]);

    Sanctum::actingAs(userWithRole('finance'));

    $rows = collect($this->getJson('/api/reports/departments')->assertOk()->json('data'));

    $marketing = $rows->firstWhere('department_id', $this->otherDept->id);

    // Diurutkan menurun berdasarkan total, jadi Marketing (500rb) di atas.
    expect($rows->first()['department_id'])->toBe($this->otherDept->id)
        ->and($marketing['count'])->toBe(2)
        ->and($marketing['total_amount'])->toBe(500_000)
        ->and($marketing['paid_amount'])->toBe(400_000)
        ->and($marketing['pending_amount'])->toBe(100_000);
});

test('the department recap honours the report filters', function () {
    Reimbursement::factory()->paid()->create([
        'user_id' => $this->employee->id, 'department_id' => $this->otherDept->id, 'amount' => 400_000,
    ]);
    Reimbursement::factory()->paid()->create([
        'user_id' => $this->employee->id, 'department_id' => $this->ownDept, 'amount' => 50_000,
    ]);

    Sanctum::actingAs(userWithRole('finance'));

    $rows = $this->getJson("/api/reports/departments?department_id={$this->ownDept}")
        ->assertOk()->json('data');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['department_id'])->toBe($this->ownDept);
});
