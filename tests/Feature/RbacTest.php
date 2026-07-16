<?php

use App\Models\Reimbursement;
use App\Support\Navigation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

// (helper userWithRole() didefinisikan di tests/Pest.php)

// ---- Middleware: role -----------------------------------------------------

test('role middleware blocks users without the role', function () {
    $this->actingAs(userWithRole('employee'))->get('/approvals')->assertForbidden();
});

test('role middleware allows users with the role', function () {
    $this->actingAs(userWithRole('manager'))->get('/approvals')->assertOk();
    $this->actingAs(userWithRole('finance'))->get('/approvals')->assertOk();
});

// ---- Middleware: permission ----------------------------------------------

test('permission middleware enforces payment.view', function () {
    $this->actingAs(userWithRole('employee'))->get('/payments')->assertForbidden();
    $this->actingAs(userWithRole('finance'))->get('/payments')->assertOk();
    $this->actingAs(userWithRole('auditor'))->get('/payments')->assertOk();
});

test('permission middleware enforces audit.view', function () {
    $this->actingAs(userWithRole('employee'))->get('/audit-logs')->assertForbidden();
    $this->actingAs(userWithRole('auditor'))->get('/audit-logs')->assertOk();
});

test('super admin bypasses every restriction', function () {
    $super = userWithRole('super_admin');

    $this->actingAs($super)->get('/approvals')->assertOk();
    $this->actingAs($super)->get('/payments')->assertOk();
    $this->actingAs($super)->get('/audit-logs')->assertOk();
    $this->actingAs($super)->get('/master')->assertOk();
});

// ---- Gate (permission-style abilities) -----------------------------------

test('gate resolves permission abilities via rbac', function () {
    $finance = userWithRole('finance');
    $employee = userWithRole('employee');

    expect(Gate::forUser($finance)->allows('payment.process'))->toBeTrue()
        ->and(Gate::forUser($employee)->allows('payment.process'))->toBeFalse()
        ->and(Gate::forUser(userWithRole('super_admin'))->allows('payment.process'))->toBeTrue();
});

// ---- Policy ---------------------------------------------------------------

test('policy lets owner view but blocks others', function () {
    $owner = userWithRole('employee');
    $other = userWithRole('employee');
    $r = Reimbursement::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('view', $r))->toBeTrue()
        ->and($other->can('view', $r))->toBeFalse();
});

test('finance cannot approve a draft (wrong state)', function () {
    $finance = userWithRole('finance');
    $draft = Reimbursement::factory()->create(['status' => 'draft']);
    $ready = Reimbursement::factory()->managerApproved()->create();

    expect($finance->can('approveFinance', $draft))->toBeFalse()
        ->and($finance->can('approveFinance', $ready))->toBeTrue();
});

// ---- Dynamic navigation ---------------------------------------------------

test('navigation is filtered per role', function () {
    $employeeNav = collect(Navigation::for(userWithRole('employee')))->pluck('label');
    expect($employeeNav)->toContain('Reimbursement')
        ->and($employeeNav)->not->toContain('Audit Log')
        ->and($employeeNav)->not->toContain('Pembayaran');

    $auditorNav = collect(Navigation::for(userWithRole('auditor')))->pluck('label');
    expect($auditorNav)->toContain('Audit Log')
        ->and($auditorNav)->toContain('Pembayaran');

    $superNav = collect(Navigation::for(userWithRole('super_admin')))->pluck('label');
    expect($superNav)->toContain('Master Data')->toContain('Audit Log');
});
