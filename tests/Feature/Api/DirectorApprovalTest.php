<?php

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Department;
use App\Models\Reimbursement;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ReimbursementSubmitted;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

/** Ambang Direksi pada konfigurasi bawaan. */
const THRESHOLD = 20_000_000;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Role::query()->update(['reimbursement_limit' => null]);   // fokus pada alur, bukan plafon
    $this->department = Department::factory()->create();
});

function staff(string $role): User
{
    $user = userWithRole($role);
    $user->update(['department_id' => test()->department->id]);

    return $user->fresh();
}

/** Klaim yang sudah disetujui Finance dengan nominal tertentu. */
function financeApprovedClaim(int $amount): Reimbursement
{
    $owner = staff('employee');
    $bank = Bank::factory()->create();
    $account = BankAccount::factory()->primary()->create(['user_id' => $owner->id, 'bank_id' => $bank->id]);

    return Reimbursement::factory()->financeApproved()->create([
        'user_id' => $owner->id,
        'department_id' => test()->department->id,
        'bank_account_id' => $account->id,
        'amount' => $amount,
    ]);
}

// ---- Ambang nominal --------------------------------------------------------

test('a claim at or below the threshold is payable straight after finance', function () {
    $claim = financeApprovedClaim(THRESHOLD);

    expect($claim->needsDirectorApproval())->toBeFalse()
        ->and($claim->isReadyForPayment())->toBeTrue()
        ->and($claim->pendingApprovalLevel())->toBeNull();

    Sanctum::actingAs(staff('finance'));
    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])
        ->assertCreated();

    expect($claim->refresh()->status->value)->toBe('paid');
});

test('a claim above the threshold must pass the board before it can be paid', function () {
    $claim = financeApprovedClaim(THRESHOLD + 1);

    expect($claim->needsDirectorApproval())->toBeTrue()
        ->and($claim->isReadyForPayment())->toBeFalse()
        ->and($claim->pendingApprovalLevel()->value)->toBe('director');

    // Finance tidak bisa langsung mencairkan.
    Sanctum::actingAs(staff('finance'));
    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])
        ->assertForbidden();

    expect($claim->refresh()->payments()->count())->toBe(0);

    // Direksi menyetujui, barulah bisa dibayar.
    Sanctum::actingAs(staff('director'));
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();

    expect($claim->refresh()->status->value)->toBe('director_approved')
        ->and($claim->isReadyForPayment())->toBeTrue();

    Sanctum::actingAs(staff('finance'));
    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])
        ->assertCreated();

    expect($claim->refresh()->status->value)->toBe('paid');
});

test('the board can reject a large claim', function () {
    $claim = financeApprovedClaim(50_000_000);

    Sanctum::actingAs(staff('director'));
    $this->postJson("/api/reimbursements/{$claim->id}/reject", ['notes' => 'Anggaran belum tersedia'])
        ->assertOk();

    expect($claim->refresh()->status->value)->toBe('director_rejected')
        ->and($claim->isReadyForPayment())->toBeFalse();
});

test('nobody but a director may act at the board stage', function () {
    $claim = financeApprovedClaim(THRESHOLD + 1);

    foreach (['manager', 'supervisor', 'finance', 'admin'] as $role) {
        Sanctum::actingAs(staff($role));
        $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertForbidden();
    }

    expect($claim->refresh()->status->value)->toBe('finance_approved');
});

test('the board is notified when a large claim reaches its stage', function () {
    Notification::fake();

    $director = staff('director');
    $claim = Reimbursement::factory()->managerApproved()->create([
        'department_id' => $this->department->id,
        'amount' => THRESHOLD + 5_000_000,
    ]);

    Sanctum::actingAs(staff('finance'));
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();

    Notification::assertSentTo($director, ReimbursementSubmitted::class);
});

// ---- Pemisahan tugas -------------------------------------------------------

test('a super admin cannot approve or pay their own claim', function () {
    $super = staff('super_admin');
    $bank = Bank::factory()->create();
    $account = BankAccount::factory()->primary()->create(['user_id' => $super->id, 'bank_id' => $bank->id]);

    $claim = Reimbursement::factory()->submitted()->create([
        'user_id' => $super->id, 'department_id' => $this->department->id,
        'bank_account_id' => $account->id, 'amount' => 1_000_000,
    ]);

    Sanctum::actingAs($super);
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertForbidden();

    // Bahkan setelah orang lain meloloskannya, ia tak boleh mencairkan sendiri.
    $claim->update(['status' => 'finance_approved']);
    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])
        ->assertForbidden();

    expect($claim->refresh()->payments()->count())->toBe(0);
});

test('a super admin can still decide other people claims', function () {
    $claim = Reimbursement::factory()->submitted()->create([
        'department_id' => $this->department->id, 'amount' => 1_000_000,
    ]);

    Sanctum::actingAs(staff('super_admin'));
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();

    expect($claim->refresh()->status->value)->toBe('manager_approved');
});

test('one person cannot wave the same claim through two gates', function () {
    // User yang kebetulan memegang wewenang Manager sekaligus Finance.
    $both = staff('manager');
    $both->roles()->attach(Role::where('name', 'finance')->firstOrFail());
    $both = $both->fresh();

    $claim = Reimbursement::factory()->submitted()->create([
        'department_id' => $this->department->id, 'amount' => 1_000_000,
    ]);

    Sanctum::actingAs($both);

    // Gerbang pertama boleh.
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();
    expect($claim->refresh()->status->value)->toBe('manager_approved');

    // Gerbang kedua atas klaim yang sama: ditolak.
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertForbidden();
    expect($claim->refresh()->status->value)->toBe('manager_approved');

    // Orang lain tetap bisa melanjutkannya.
    Sanctum::actingAs(staff('finance'));
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();
    expect($claim->refresh()->status->value)->toBe('finance_approved');
});

// ---- Jenis pengajuan per jabatan baru --------------------------------------

test('each position is offered the claim types it is entitled to', function (string $role, array $expected) {
    Sanctum::actingAs(staff($role));

    expect($this->getJson('/api/options/claim-types')->assertOk()->json('data.*.value'))
        ->toBe($expected);
})->with([
    'magang' => ['intern', ['expense']],
    'employee' => ['employee', ['expense', 'overtime']],
    'supervisor' => ['supervisor', ['expense', 'goods', 'service', 'overtime']],
    'direktur' => ['director', ['expense', 'goods', 'service', 'overtime']],
]);

test('an intern cannot submit an overtime claim', function () {
    Sanctum::actingAs(staff('intern'));

    $this->postJson('/api/reimbursements', [
        'claim_type' => 'overtime',
        'category_id' => Category::factory()->create(['max_amount' => null])->id,
        'title' => 'Lembur',
        'reason' => 'Bantu rilis',
        'details' => [
            'overtime_date' => now()->subDay()->toDateString(),
            'start_time' => '19:00', 'end_time' => '21:00',
            'hours' => 2, 'hourly_rate' => 30_000,
            'work_description' => 'Bantu rilis',
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('claim_type');
});

test('a supervisor approves at the manager gate', function () {
    $claim = Reimbursement::factory()->submitted()->create([
        'department_id' => $this->department->id, 'amount' => 1_000_000,
    ]);

    Sanctum::actingAs(staff('supervisor'));
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();

    expect($claim->refresh()->status->value)->toBe('manager_approved');
});
