<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

/**
 * Seed master data bank Indonesia. Idempotent (firstOrCreate per kode).
 */
class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            // Bank BUMN & besar
            ['name' => 'Bank Central Asia', 'code' => 'BCA', 'swift_code' => 'CENAIDJA'],
            ['name' => 'Bank Rakyat Indonesia', 'code' => 'BRI', 'swift_code' => 'BRINIDJA'],
            ['name' => 'Bank Negara Indonesia', 'code' => 'BNI', 'swift_code' => 'BNINIDJA'],
            ['name' => 'Bank Mandiri', 'code' => 'MANDIRI', 'swift_code' => 'BMRIIDJA'],
            ['name' => 'Bank Tabungan Negara', 'code' => 'BTN', 'swift_code' => 'BTANIDJA'],
            ['name' => 'Bank Syariah Indonesia', 'code' => 'BSI', 'swift_code' => 'BSMDIDJA'],
            // Bank swasta nasional
            ['name' => 'Bank CIMB Niaga', 'code' => 'CIMB', 'swift_code' => 'BNIAIDJA'],
            ['name' => 'Bank Danamon Indonesia', 'code' => 'DANAMON', 'swift_code' => 'BDINIDJA'],
            ['name' => 'Bank Permata', 'code' => 'PERMATA', 'swift_code' => 'BBBAIDJA'],
            ['name' => 'Bank Panin', 'code' => 'PANIN', 'swift_code' => 'PINBIDJA'],
            ['name' => 'Bank OCBC NISP', 'code' => 'OCBC', 'swift_code' => 'NISPIDJA'],
            ['name' => 'Bank Maybank Indonesia', 'code' => 'MAYBANK', 'swift_code' => 'IBBKIDJA'],
            ['name' => 'Bank Mega', 'code' => 'MEGA', 'swift_code' => 'MEGAIDJA'],
            ['name' => 'Bank BTPN', 'code' => 'BTPN', 'swift_code' => 'SUNIIDJA'],
            ['name' => 'Bank Sinarmas', 'code' => 'SINARMAS', 'swift_code' => 'SBJKIDJA'],
            ['name' => 'Bank Muamalat Indonesia', 'code' => 'MUAMALAT', 'swift_code' => 'MUABIDJA'],
            // Bank pembangunan daerah
            ['name' => 'Bank BJB', 'code' => 'BJB', 'swift_code' => 'PDJBIDJA'],
            ['name' => 'Bank DKI', 'code' => 'BANKDKI', 'swift_code' => 'BDKIIDJA'],
            ['name' => 'Bank Jateng', 'code' => 'JATENG', 'swift_code' => 'PDJGIDJA'],
            ['name' => 'Bank Jatim', 'code' => 'JATIM', 'swift_code' => 'PDJTIDJA'],
            // Bank digital
            ['name' => 'SeaBank Indonesia', 'code' => 'SEABANK', 'swift_code' => 'SEBIIDJ1'],
            ['name' => 'Bank Jago', 'code' => 'JAGO', 'swift_code' => 'JAGOIDJA'],
            ['name' => 'Bank Neo Commerce', 'code' => 'NEO', 'swift_code' => 'BABPIDJA'],
            ['name' => 'Allo Bank Indonesia', 'code' => 'ALLO', 'swift_code' => 'HRDAIDJA'],
            // Bank asing
            ['name' => 'Bank DBS Indonesia', 'code' => 'DBS', 'swift_code' => 'DBSBIDJA'],
            ['name' => 'Bank UOB Indonesia', 'code' => 'UOB', 'swift_code' => 'BBIJIDJA'],
            ['name' => 'Bank HSBC Indonesia', 'code' => 'HSBC', 'swift_code' => 'HSBCIDJA'],
        ];

        foreach ($banks as $bank) {
            Bank::firstOrCreate(
                ['code' => $bank['code']],
                ['name' => $bank['name'], 'swift_code' => $bank['swift_code'], 'is_active' => true],
            );
        }
    }
}
