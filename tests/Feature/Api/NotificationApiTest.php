<?php

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Reimbursement;
use App\Notifications\ReimbursementActioned;
use App\Notifications\ReimbursementPaid;
use App\Notifications\ReimbursementReadyForPayment;
use App\Notifications\ReimbursementSubmitted;
use App\Notifications\ReimbursementSubmittedReceipt;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

// ---- Notifications fired on domain events ---------------------------------

test('submitting notifies the direct manager', function () {
    Notification::fake();
    $manager = userWithRole('manager');
    $employee = employeeUser();
    $employee->update(['manager_id' => $manager->id]);
    $draft = Reimbursement::factory()->create(['user_id' => $employee->id, 'status' => 'draft']);

    Sanctum::actingAs($employee);
    $this->postJson("/api/reimbursements/{$draft->id}/submit")->assertOk();

    Notification::assertSentTo($manager, ReimbursementSubmitted::class);
});

test('manager approval notifies the owner and the finance team', function () {
    Notification::fake();
    $finance = userWithRole('finance');
    $claim = Reimbursement::factory()->submitted()->create();

    Sanctum::actingAs(userWithRole('manager'));
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();

    Notification::assertSentTo($claim->user, ReimbursementActioned::class);
    Notification::assertSentTo($finance, ReimbursementSubmitted::class);
});

test('rejection notifies the owner', function () {
    Notification::fake();
    $claim = Reimbursement::factory()->submitted()->create();

    Sanctum::actingAs(userWithRole('manager'));
    $this->postJson("/api/reimbursements/{$claim->id}/reject", ['notes' => 'Bukti kurang'])->assertOk();

    Notification::assertSentTo($claim->user, ReimbursementActioned::class);
});

test('payment notifies the owner', function () {
    Notification::fake();
    Storage::fake('local');

    $employee = employeeUser();
    $bank = Bank::factory()->create();
    $account = BankAccount::factory()->primary()->create(['user_id' => $employee->id, 'bank_id' => $bank->id]);
    $claim = Reimbursement::factory()->financeApproved()->create([
        'user_id' => $employee->id, 'bank_account_id' => $account->id,
    ]);

    Sanctum::actingAs(userWithRole('finance'));
    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])->assertCreated();

    Notification::assertSentTo($employee, ReimbursementPaid::class);
});

test('submitting also sends a receipt back to the submitter', function () {
    Notification::fake();
    $employee = employeeUser();
    $draft = Reimbursement::factory()->create(['user_id' => $employee->id, 'status' => 'draft']);

    Sanctum::actingAs($employee);
    $this->postJson("/api/reimbursements/{$draft->id}/submit")->assertOk();

    Notification::assertSentTo($employee, ReimbursementSubmittedReceipt::class);
});

test('finance approval notifies whoever may process the payment', function () {
    Notification::fake();
    $finance = userWithRole('finance');   // pemegang payment.process
    $auditor = userWithRole('auditor');   // tidak boleh ikut dikabari
    $claim = Reimbursement::factory()->managerApproved()->create();

    Sanctum::actingAs($finance);
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();

    Notification::assertSentTo($claim->user, ReimbursementActioned::class);
    Notification::assertSentTo($finance, ReimbursementReadyForPayment::class);
    Notification::assertNotSentTo($auditor, ReimbursementReadyForPayment::class);
});

test('the full chain reaches the submitter at every stage', function () {
    Storage::fake('local');
    $manager = userWithRole('manager');
    $employee = employeeUser();
    $employee->update(['manager_id' => $manager->id]);

    $bank = Bank::factory()->create();
    $account = BankAccount::factory()->primary()->create(['user_id' => $employee->id, 'bank_id' => $bank->id]);
    $claim = Reimbursement::factory()->create([
        'user_id' => $employee->id, 'status' => 'draft', 'bank_account_id' => $account->id,
    ]);

    // 1. pengaju mengirim
    Sanctum::actingAs($employee);
    $this->postJson("/api/reimbursements/{$claim->id}/submit")->assertOk();

    // 2. manager menyetujui, 3. finance menyetujui, 4. finance membayar
    Sanctum::actingAs($manager);
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();

    Sanctum::actingAs(userWithRole('finance'));
    $this->postJson("/api/reimbursements/{$claim->id}/approve")->assertOk();
    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])->assertCreated();

    // Channel database berjalan pada koneksi sync → notifikasi in-app sudah
    // tersimpan tanpa menjalankan queue worker.
    $types = $employee->notifications()->pluck('type')->all();

    expect($types)->toContain(ReimbursementSubmittedReceipt::class)
        ->toContain(ReimbursementActioned::class)
        ->toContain(ReimbursementPaid::class);
    expect($employee->unreadNotifications()->count())->toBe(4); // terima + 2 approval + dibayar
});

// ---- In-app (database) notification endpoints -----------------------------

test('a user can list, count and mark their in-app notifications read', function () {
    $user = employeeUser();
    $claim = Reimbursement::factory()->create(['user_id' => $user->id]);
    $user->notify(new ReimbursementActioned($claim, ApprovalLevel::Manager, ApprovalAction::Approved, null));

    Sanctum::actingAs($user);

    $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('count', 1);

    $id = $this->getJson('/api/notifications')->assertOk()->json('data.0.id');
    $this->postJson("/api/notifications/{$id}/read")->assertNoContent();

    $this->getJson('/api/notifications/unread-count')->assertJsonPath('count', 0);
});
