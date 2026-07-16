<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

/**
 * Seed master data bank bawaan sistem (Phase 5/11): BCA, BRI, BNI, Mandiri, SeaBank.
 */
class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['name' => 'Bank Central Asia', 'code' => 'BCA', 'swift_code' => 'CENAIDJA'],
            ['name' => 'Bank Rakyat Indonesia', 'code' => 'BRI', 'swift_code' => 'BRINIDJA'],
            ['name' => 'Bank Negara Indonesia', 'code' => 'BNI', 'swift_code' => 'BNINIDJA'],
            ['name' => 'Bank Mandiri', 'code' => 'MANDIRI', 'swift_code' => 'BMRIIDJA'],
            ['name' => 'SeaBank Indonesia', 'code' => 'SEABANK', 'swift_code' => 'SEBIIDJ1'],
        ];

        foreach ($banks as $bank) {
            Bank::firstOrCreate(
                ['code' => $bank['code']],
                ['name' => $bank['name'], 'swift_code' => $bank['swift_code'], 'is_active' => true],
            );
        }
    }
}
