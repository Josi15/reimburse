<?php

use App\Models\Bank;
use App\Models\CompanyAccountDeposit;
use App\Models\CompanyBankAccount;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->account = CompanyBankAccount::factory()->create([
        'bank_id' => Bank::factory(),
        'opening_balance' => 100_000_000,
    ]);
});

test('employee cannot record a company deposit', function () {
    Sanctum::actingAs(userWithRole('employee'));
    $this->postJson('/api/company-deposits', [
        'company_bank_account_id' => $this->account->id,
        'amount' => 10_000_000,
        'deposited_at' => now()->toDateString(),
    ])->assertForbidden();
});

test('finance records a deposit and it is stamped to the user', function () {
    $finance = userWithRole('finance');
    Sanctum::actingAs($finance);

    $this->postJson('/api/company-deposits', [
        'company_bank_account_id' => $this->account->id,
        'amount' => 30_000_000,
        'deposited_at' => now()->toDateString(),
        'note' => 'Top-up operasional',
    ])->assertCreated()->assertJsonPath('data.amount', 30000000);

    $this->assertDatabaseHas('company_account_deposits', [
        'company_bank_account_id' => $this->account->id,
        'amount' => 30_000_000,
        'created_by' => $finance->id,
    ]);
});

test('deposit amount must be positive', function () {
    Sanctum::actingAs(userWithRole('finance'));
    $this->postJson('/api/company-deposits', [
        'company_bank_account_id' => $this->account->id,
        'amount' => 0,
        'deposited_at' => now()->toDateString(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['amount']);
});

test('cashflow report sums opening balance, pemasukan and pengeluaran', function () {
    // Pemasukan bulan ini Rp30jt.
    CompanyAccountDeposit::factory()->create([
        'company_bank_account_id' => $this->account->id,
        'amount' => 30_000_000,
        'deposited_at' => now()->toDateString(),
    ]);

    Sanctum::actingAs(userWithRole('finance'));
    $data = $this->getJson('/api/reports/cashflow')->assertOk()->json();

    $row = collect($data['accounts'])->firstWhere('account_id', $this->account->id);

    expect($row['opening_balance'])->toBe(100000000)   // saldo awal
        ->and($row['pemasukan'])->toBe(30000000)
        ->and($row['pengeluaran'])->toBe(0)
        ->and($row['ending_balance'])->toBe(130000000) // 100jt + 30jt - 0
        ->and($data['totals']['pemasukan'])->toBe(30000000);
});
