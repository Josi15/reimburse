<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\CompanyBankAccount;
use Illuminate\Database\Seeder;

/**
 * Seed rekening perusahaan (sumber pembayaran). Idempotent per nomor rekening.
 * Bergantung pada BankSeeder (dijalankan lebih dulu di DatabaseSeeder).
 */
class CompanyBankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['bank' => 'BCA', 'label' => 'Kas Operasional', 'number' => '1234567890', 'holder' => 'PT Reimburse Nusantara', 'opening' => 100_000_000],
            ['bank' => 'MANDIRI', 'label' => 'Payroll', 'number' => '9876543210', 'holder' => 'PT Reimburse Nusantara', 'opening' => 50_000_000],
            ['bank' => 'BRI', 'label' => 'Kas Proyek', 'number' => '5551112223', 'holder' => 'PT Reimburse Nusantara', 'opening' => 75_000_000],
        ];

        foreach ($accounts as $a) {
            $bank = Bank::where('code', $a['bank'])->first();
            if (! $bank) {
                continue;
            }

            CompanyBankAccount::firstOrCreate(
                ['account_number' => $a['number']],
                [
                    'bank_id' => $bank->id,
                    'label' => $a['label'],
                    'account_holder_name' => $a['holder'],
                    'opening_balance' => $a['opening'],
                    'is_active' => true,
                ],
            );
        }
    }
}
