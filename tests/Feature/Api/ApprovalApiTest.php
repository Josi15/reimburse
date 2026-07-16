<?php

use App\Models\Reimbursement;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function submittedClaim(): Reimbursement
{
    return Reimbursement::factory()->submitted()->create();
}

// ---- Manager stage --------------------------------------------------------

test('manager approves a submitted claim and an approval row is recorded', function () {
    $claim = submittedClaim();
    Sanctum::actingAs(userWithRole('manager'));

    $this->postJson("/api/reimbursements/{$claim->id}/approve")
        ->assertOk()->assertJsonPath('data.status.value', 'manager_approved');

    $this->assertDatabaseHas('approvals', [
        'reimbursement_id' => $claim->id,
        'level' => 'manager',
        'action' => 'approved',
    ]);
});

test('manager reject requires notes and sets manager_rejected', function () {
    $claim = submittedClaim();
    Sanctum::actingAs(userWithRole('manager'));

    $this->postJson("/api/reimbursements/{$claim->id}/reject", [])
        ->assertUnprocessable()->assertJsonValidationErrors(['notes']);

    $this->postJson("/api/reimbursements/{$claim->id}/reject", ['notes' => 'Bukti kurang lengkap'])
        ->assertOk()->assertJsonPath('data.status.value', 'manager_rejected');
});

test('manager can request revision', function () {
    $claim = submittedClaim();
    Sanctum::actingAs(userWithRole('manager'));

    $this->postJson("/api/reimbursements/{$claim->id}/revision", ['notes' => 'Mohon perbaiki nominal'])
        ->assertOk()->assertJsonPath('data.status.value', 'revision_requested');
});

// ---- Level enforcement ----------------------------------------------------

test('finance cannot act at the manager stage', function () {
    $claim = submittedClaim();
    Sanctum::actingAs(userWithRole('finance'));

    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertForbidden();
});

test('manager cannot act at the finance stage', function () {
    $claim = Reimbursement::factory()->managerApproved()->create();
    Sanctum::actingAs(userWithRole('manager'));

    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertForbidden();
});

test('employees cannot approve', function () {
    $claim = submittedClaim();
    Sanctum::actingAs(userWithRole('employee'));

    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertForbidden();
});

// ---- Finance stage & full flow -------------------------------------------

test('finance approves a manager-approved claim', function () {
    $claim = Reimbursement::factory()->managerApproved()->create();
    Sanctum::actingAs(userWithRole('finance'));

    $this->postJson("/api/reimbursements/{$claim->id}/approve")
        ->assertOk()->assertJsonPath('data.status.value', 'finance_approved');
});

test('the full approval chain reaches finance_approved with two approval rows', function () {
    $claim = submittedClaim();

    Sanctum::actingAs(userWithRole('manager'));
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();

    Sanctum::actingAs(userWithRole('finance'));
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();

    expect($claim->fresh()->status->value)->toBe('finance_approved')
        ->and($claim->approvals()->count())->toBe(2);
});

// ---- History & timeline ---------------------------------------------------

test('approval history endpoint returns recorded actions', function () {
    $claim = submittedClaim();
    Sanctum::actingAs(userWithRole('manager'));
    $this->postJson("/api/reimbursements/{$claim->id}/reject", ['notes' => 'Ditolak'])->assertOk();

    $history = $this->getJson("/api/reimbursements/{$claim->id}/approvals")->assertOk()->json('data');

    expect($history)->toHaveCount(1)
        ->and($history[0]['action'])->toBe('rejected')
        ->and($history[0]['notes'])->toBe('Ditolak');
});

test('approval actions appear in the reimbursement timeline', function () {
    $claim = submittedClaim();
    $owner = $claim->user;

    Sanctum::actingAs(userWithRole('manager'));
    $this->postJson("/api/reimbursements/{$claim->id}/approve", ['notes' => 'OK'])->assertOk();

    Sanctum::actingAs($owner);
    $timeline = $this->getJson("/api/reimbursements/{$claim->id}")->json('timeline');

    expect(collect($timeline)->pluck('status'))->toContain('approved');
});
