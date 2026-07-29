<?php

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\CompanyBankAccount;
use App\Models\Reimbursement;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

test('employee cannot manage company bank accounts', function () {
    Sanctum::actingAs(userWithRole('employee'));
    $this->getJson('/api/company-accounts')->assertForbidden();
});

test('finance can create a company bank account', function () {
    $bank = Bank::factory()->create();
    Sanctum::actingAs(userWithRole('finance'));

    $this->postJson('/api/company-accounts', [
        'bank_id' => $bank->id,
        'label' => 'Kas Operasional',
        'account_number' => '1234567890',
        'account_holder_name' => 'PT Contoh',
    ])->assertCreated()
        ->assertJsonPath('data.label', 'Kas Operasional')
        ->assertJsonPath('data.masked_number', '******7890');
});

test('company account number must be numeric', function () {
    $bank = Bank::factory()->create();
    Sanctum::actingAs(userWithRole('finance'));

    $this->postJson('/api/company-accounts', [
        'bank_id' => $bank->id,
        'label' => 'X',
        'account_number' => 'ABC123',
        'account_holder_name' => 'PT Contoh',
    ])->assertUnprocessable()->assertJsonValidationErrors(['account_number']);
});

test('finance sees active company accounts via options', function () {
    CompanyBankAccount::factory()->create(['label' => 'Aktif']);
    CompanyBankAccount::factory()->inactive()->create(['label' => 'Nonaktif']);
    Sanctum::actingAs(userWithRole('finance'));

    $data = $this->getJson('/api/options/company-accounts')->assertOk()->json('data');
    expect(collect($data)->pluck('label'))->toContain('Aktif')->not->toContain('Nonaktif');
});

test('a payment records the company source account', function () {
    $employee = employeeUser();
    $bank = Bank::factory()->create();
    $account = BankAccount::factory()->primary()->create([
        'user_id' => $employee->id, 'bank_id' => $bank->id, 'is_active' => true,
    ]);
    $claim = Reimbursement::factory()->financeApproved()->create([
        'user_id' => $employee->id, 'bank_account_id' => $account->id, 'amount' => 500_000,
    ]);
    $source = CompanyBankAccount::factory()->create(['bank_id' => $bank->id]);

    Sanctum::actingAs(userWithRole('finance'));
    $this->postJson("/api/reimbursements/{$claim->id}/pay", [
        'method' => 'bank_transfer',
        'source_account_id' => $source->id,
    ])->assertCreated()->assertJsonPath('data.source_account_id', $source->id);
});
