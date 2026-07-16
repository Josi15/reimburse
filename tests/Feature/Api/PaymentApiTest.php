<?php

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Payment;
use App\Models\Reimbursement;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

/** Reimbursement finance-approved dengan rekening tujuan aktif milik pengaju. */
function payableClaim(int $amount = 500_000): Reimbursement
{
    $employee = employeeUser();
    $bank = Bank::factory()->create();
    $account = BankAccount::factory()->primary()->create([
        'user_id' => $employee->id, 'bank_id' => $bank->id, 'is_active' => true,
    ]);

    return Reimbursement::factory()->financeApproved()->create([
        'user_id' => $employee->id,
        'bank_account_id' => $account->id,
        'amount' => $amount,
    ]);
}

test('finance pays a finance-approved claim and status becomes paid', function () {
    $claim = payableClaim();
    Sanctum::actingAs(userWithRole('finance'));

    $this->postJson("/api/reimbursements/{$claim->id}/pay", [
        'method' => 'bank_transfer',
        'reference_number' => 'TRX123',
    ])->assertCreated()->assertJsonPath('data.status.value', 'paid');

    expect($claim->fresh()->status->value)->toBe('paid')
        ->and($claim->fresh()->completed_at)->not->toBeNull();
    $this->assertDatabaseHas('payments', ['reimbursement_id' => $claim->id, 'status' => 'paid']);
});

test('a claim not finance-approved cannot be paid', function () {
    $claim = Reimbursement::factory()->submitted()->create();
    Sanctum::actingAs(userWithRole('finance'));

    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])
        ->assertForbidden();
});

test('employees cannot process payments', function () {
    $claim = payableClaim();
    Sanctum::actingAs(userWithRole('employee'));

    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])
        ->assertForbidden();
});

test('payment amount cannot exceed the approved amount', function () {
    $claim = payableClaim(500_000);
    Sanctum::actingAs(userWithRole('finance'));

    $this->postJson("/api/reimbursements/{$claim->id}/pay", [
        'method' => 'bank_transfer',
        'amount' => 900_000,
    ])->assertUnprocessable()->assertJsonValidationErrors(['payment']);
});

test('a claim without a target account cannot be paid', function () {
    $claim = Reimbursement::factory()->financeApproved()->create(['bank_account_id' => null]);
    Sanctum::actingAs(userWithRole('finance'));

    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])
        ->assertUnprocessable()->assertJsonValidationErrors(['payment']);
});

test('double payment is prevented (second attempt rejected)', function () {
    $claim = payableClaim();
    Sanctum::actingAs(userWithRole('finance'));

    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])->assertCreated();

    // Reimbursement kini "paid" -> policy menolak (bukan finance_approved).
    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])->assertForbidden();

    expect(Payment::where('reimbursement_id', $claim->id)->count())->toBe(1);
});

test('payment proof is uploaded and stored', function () {
    $claim = payableClaim();
    Sanctum::actingAs(userWithRole('finance'));

    $res = $this->postJson("/api/reimbursements/{$claim->id}/pay", [
        'method' => 'bank_transfer',
        'proof' => UploadedFile::fake()->create('bukti-transfer.pdf', 80, 'application/pdf'),
    ])->assertCreated();

    $payment = Payment::find($res->json('data.id'));
    expect($payment->attachments)->toHaveCount(1);
    Storage::disk('local')->assertExists($payment->attachments->first()->file_path);
});

test('finance and auditor can view payment history', function () {
    $claim = payableClaim();
    Sanctum::actingAs(userWithRole('finance'));
    $this->postJson("/api/reimbursements/{$claim->id}/pay", ['method' => 'bank_transfer'])->assertCreated();

    $this->getJson('/api/payments')->assertOk()->assertJsonStructure(['data' => [['payment_number', 'status']]]);

    Sanctum::actingAs(userWithRole('auditor'));
    $this->getJson('/api/payments')->assertOk();
});
