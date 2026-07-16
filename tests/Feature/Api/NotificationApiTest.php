<?php

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Reimbursement;
use App\Notifications\ReimbursementActioned;
use App\Notifications\ReimbursementPaid;
use App\Notifications\ReimbursementSubmitted;
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
