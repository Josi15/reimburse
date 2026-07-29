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
            ['bank' => 'BCA', 'label' => 'Kas Operasional', 'number' => '1234567890', 'holder' => 'PT Reimburse Nusantara'],
            ['bank' => 'MANDIRI', 'label' => 'Payroll', 'number' => '9876543210', 'holder' => 'PT Reimburse Nusantara'],
            ['bank' => 'BRI', 'label' => 'Kas Proyek', 'number' => '5551112223', 'holder' => 'PT Reimburse Nusantara'],
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
                    'is_active' => true,
                ],
            );
        }
    }
}
