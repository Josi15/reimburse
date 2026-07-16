<?php

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Department;
use App\Models\Reimbursement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

// ---- Auto-logging via Auditable trait -------------------------------------

test('creating master data is audit-logged with the actor', function () {
    $admin = userWithRole('admin');
    Sanctum::actingAs($admin);

    $this->postJson('/api/departments', ['name' => 'Legal', 'code' => 'LGL'])->assertCreated();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'create',
        'auditable_type' => Department::class,
        'user_id' => $admin->id,
    ]);
});

// ---- Semantic events (approve / payment) ----------------------------------

test('approving records an approve event, not a duplicate update', function () {
    $claim = Reimbursement::factory()->submitted()->create();
    Sanctum::actingAs(userWithRole('manager'));

    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'approve',
        'auditable_type' => Reimbursement::class,
        'auditable_id' => $claim->id,
    ]);

    $updateLogged = AuditLog::where('auditable_type', Reimbursement::class)
        ->where('auditable_id', $claim->id)
        ->where('event', 'update')
        ->exists();
    expect($updateLogged)->toBeFalse();
});

test('payment records a payment event', function () {
    Storage::fake('local');
    $employee = employeeUser();
    $bank = Bank::factory()->create();
    $account = BankAccount::factory()->primary()->create(['user_id' => $employee->id, 'bank_id' => $bank->id]);
    $claim = Reimbursement::factory()->financeApproved()->create([
        'user_id' => $employee->id, 'bank_account_id' => $account->id,
    ]);

    Sanctum::actingAs(userWithRole('finance'));
    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])->assertCreated();

    $this->assertDatabaseHas('audit_logs', ['event' => 'payment']);
});

// ---- Auth events ----------------------------------------------------------

test('login is audit-logged', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'user_id' => $user->id]);
});

// ---- Access control (read-only, Auditor) ----------------------------------

test('auditor can read activity log but employee cannot', function () {
    AuditLog::query()->exists() ?: Department::factory()->create(); // pastikan ada data

    Sanctum::actingAs(userWithRole('auditor'));
    $this->getJson('/api/audit-logs')->assertOk()
        ->assertJsonStructure(['data' => [['event', 'event_label', 'created_at']]]);

    Sanctum::actingAs(userWithRole('employee'));
    $this->getJson('/api/audit-logs')->assertForbidden();
});

test('activity log can be filtered by event', function () {
    Department::factory()->create();               // create event
    $d = Department::factory()->create();
    $d->update(['name' => 'Renamed']);             // update event
    Sanctum::actingAs(userWithRole('auditor'));

    $data = $this->getJson('/api/audit-logs?event=update')->assertOk()->json('data');

    expect(collect($data)->every(fn ($l) => $l['event'] === 'update'))->toBeTrue();
});

test('activity log exports to CSV', function () {
    Department::factory()->create();
    Sanctum::actingAs(userWithRole('auditor'));

    $this->get('/api/audit-logs/export?format=csv')
        ->assertOk()->assertDownload('activity-log.csv');
});
