<?php

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Department;
use App\Models\Reimbursement;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ReimbursementSubmittedReceipt;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Role::query()->update(['reimbursement_limit' => null]);   // fokus pada hak akses
    $this->category = Category::factory()->create(['max_amount' => null]);
    $this->department = Department::factory()->create();
});

/** User dengan role tertentu + department (department_id wajib pada klaim). */
function staffWithRole(string $role): User
{
    $user = userWithRole($role);
    $user->update(['department_id' => test()->department->id]);

    return $user->fresh();
}

// ---- Semua role operasional boleh mengajukan ------------------------------

test('every operational role can create and submit a claim', function (string $role) {
    $user = staffWithRole($role);
    Sanctum::actingAs($user);

    $id = $this->postJson('/api/reimbursements', [
        'category_id' => $this->category->id,
        'title' => "Klaim milik {$role}",
        'reason' => 'Uji hak akses',
        'amount' => 200_000,
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/reimbursements/{$id}/submit")->assertOk();

    expect(Reimbursement::find($id)->status->value)->toBe('submitted');
})->with(['employee', 'manager', 'finance', 'admin']);

// ---- Hanya Employee yang jenisnya dibatasi --------------------------------

test('only the employee role is limited to expense and overtime', function () {
    Sanctum::actingAs(staffWithRole('employee'));
    expect($this->getJson('/api/options/claim-types')->json('data.*.value'))
        ->toBe(['expense', 'overtime']);

    foreach (['manager', 'finance', 'admin'] as $role) {
        Sanctum::actingAs(staffWithRole($role));
        expect($this->getJson('/api/options/claim-types')->json('data.*.value'))
            ->toBe(['expense', 'goods', 'service', 'overtime'], "role {$role}");
    }
});

test('a manager can submit a goods claim that an employee cannot', function () {
    $payload = fn (string $title) => [
        'claim_type' => 'goods',
        'category_id' => test()->category->id,
        'title' => $title,
        'reason' => 'Perangkat baru',
        'details' => ['item_name' => 'Laptop', 'quantity' => 1, 'unit_price' => 15_000_000, 'urgency' => 'normal'],
    ];

    Sanctum::actingAs(staffWithRole('manager'));
    $this->postJson('/api/reimbursements', $payload('Pengadaan laptop tim'))->assertCreated();

    Sanctum::actingAs(staffWithRole('employee'));
    $this->postJson('/api/reimbursements', $payload('Pengadaan laptop'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('claim_type');
});

// ---- Pemisahan tugas: tidak boleh memutuskan klaim sendiri ----------------

test('a manager cannot approve their own claim', function () {
    $manager = staffWithRole('manager');
    $claim = Reimbursement::factory()->submitted()->create(['user_id' => $manager->id]);

    Sanctum::actingAs($manager);

    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertForbidden();
    $this->postJson("/api/reimbursements/{$claim->id}/reject", ['notes' => 'Bukti kurang lengkap'])
        ->assertForbidden();

    expect($claim->refresh()->status->value)->toBe('submitted');
});

test('another manager can approve that same claim', function () {
    $owner = staffWithRole('manager');
    $claim = Reimbursement::factory()->submitted()->create(['user_id' => $owner->id]);

    Sanctum::actingAs(staffWithRole('manager'));   // manager lain

    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();

    expect($claim->refresh()->status->value)->toBe('manager_approved');
});

test('a finance staffer cannot approve their own claim', function () {
    $finance = staffWithRole('finance');
    $claim = Reimbursement::factory()->managerApproved()->create(['user_id' => $finance->id]);

    Sanctum::actingAs($finance);

    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertForbidden();

    expect($claim->refresh()->status->value)->toBe('manager_approved');
});

test('a finance staffer cannot pay their own claim', function () {
    $finance = staffWithRole('finance');
    $bank = Bank::factory()->create();
    $account = BankAccount::factory()->primary()->create(['user_id' => $finance->id, 'bank_id' => $bank->id]);
    $claim = Reimbursement::factory()->financeApproved()->create([
        'user_id' => $finance->id, 'bank_account_id' => $account->id,
    ]);

    Sanctum::actingAs($finance);

    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])
        ->assertForbidden();

    expect($claim->refresh()->status->value)->toBe('finance_approved')
        ->and($claim->payments()->count())->toBe(0);
});

test('a different finance staffer can pay that claim', function () {
    $owner = staffWithRole('finance');
    $bank = Bank::factory()->create();
    $account = BankAccount::factory()->primary()->create(['user_id' => $owner->id, 'bank_id' => $bank->id]);
    $claim = Reimbursement::factory()->financeApproved()->create([
        'user_id' => $owner->id, 'bank_account_id' => $account->id,
    ]);

    Sanctum::actingAs(staffWithRole('finance'));   // staf finance lain

    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])
        ->assertCreated();

    expect($claim->refresh()->status->value)->toBe('paid');
});

test('a manager submitting a claim is not notified as its own approver', function () {
    $manager = staffWithRole('manager');
    $other = staffWithRole('manager');
    $claim = Reimbursement::factory()->create(['user_id' => $manager->id, 'status' => 'draft']);

    Sanctum::actingAs($manager);
    $this->postJson("/api/reimbursements/{$claim->id}/submit")->assertOk();

    // Pengaju hanya menerima tanda terima, bukan permintaan approval.
    $types = $manager->notifications()->pluck('type')->all();
    expect($types)->toBe([ReimbursementSubmittedReceipt::class])
        ->and($other->notifications()->count())->toBe(1);   // manager lain yang diminta menilai
});
