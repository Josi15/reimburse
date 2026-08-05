<?php

use App\Models\Department;
use App\Models\Reimbursement;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * Cakupan data per departemen.
 *
 * Finance, Direksi, dan Auditor mengawasi perusahaan secara utuh; Admin,
 * Manager, dan Supervisor bekerja di unitnya sendiri. Aturannya harus berlaku
 * seragam di daftar, detail, persetujuan, dashboard, dan laporan — kalau salah
 * satu jalur bocor, data unit lain ikut terlihat.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->otherDept = Department::factory()->create(['name' => 'Unit Lain']);

    // Satu klaim di departemen bersama, satu di departemen lain.
    $this->ownClaim = Reimbursement::factory()->submitted()->create([
        'user_id' => employeeUser()->id,
    ]);
    $this->foreignClaim = Reimbursement::factory()->submitted()->create([
        'user_id' => employeeUser($this->otherDept)->id,
    ]);
});

test('a supervisor only lists claims from their own department', function () {
    Sanctum::actingAs(userWithRole('supervisor'));

    $ids = collect($this->getJson('/api/reimbursements')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toContain($this->ownClaim->id)
        ->not->toContain($this->foreignClaim->id);
});

test('an admin cannot open a claim belonging to another department', function () {
    Sanctum::actingAs(userWithRole('admin'));

    $this->getJson("/api/reimbursements/{$this->ownClaim->id}")->assertOk();
    $this->getJson("/api/reimbursements/{$this->foreignClaim->id}")->assertForbidden();
});

test('a manager cannot approve a claim from another department', function () {
    Sanctum::actingAs(userWithRole('manager'));

    $this->postJson("/api/reimbursements/{$this->foreignClaim->id}/approve")->assertForbidden();
    $this->postJson("/api/reimbursements/{$this->ownClaim->id}/approve")->assertOk();
});

test('finance and the director see every department', function () {
    foreach (['finance', 'director', 'auditor'] as $role) {
        Sanctum::actingAs(userWithRole($role));

        $ids = collect($this->getJson('/api/reimbursements')->assertOk()->json('data'))->pluck('id');

        expect($ids)->toContain($this->ownClaim->id, $this->foreignClaim->id);
    }
});

test('reports follow the same scope as the list', function () {
    Sanctum::actingAs(userWithRole('supervisor'));
    expect($this->getJson('/api/reports/reimbursements')->assertOk()->json('summary.count'))->toBe(1);

    Sanctum::actingAs(userWithRole('finance'));
    expect($this->getJson('/api/reports/reimbursements')->assertOk()->json('summary.count'))->toBe(2);
});

test('an admin only manages users from their own department', function () {
    $outsider = employeeUser($this->otherDept);
    Sanctum::actingAs(userWithRole('admin'));

    $emails = collect($this->getJson('/api/users?per_page=100')->assertOk()->json('data'))->pluck('email');

    expect($emails)->not->toContain($outsider->email);
});

test('a boss without a department falls back to their own claims only', function () {
    // Jaring pengaman: atasan yang departemennya belum diisi tidak boleh
    // otomatis mewarisi seluruh perusahaan.
    $manager = userWithRole('manager');
    $manager->update(['department_id' => null]);
    Sanctum::actingAs($manager->fresh());

    expect($this->getJson('/api/reimbursements')->assertOk()->json('data'))->toBe([]);
});
