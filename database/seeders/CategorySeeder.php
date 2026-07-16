<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Seed kategori pengeluaran contoh. `max_amount` dalam rupiah penuh (IDR).
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Perjalanan Dinas', 'code' => 'TRAVEL', 'max_amount' => 5_000_000],
            ['name' => 'Konsumsi & Rapat', 'code' => 'MEALS', 'max_amount' => 1_000_000],
            ['name' => 'Akomodasi', 'code' => 'LODGING', 'max_amount' => 2_500_000],
            ['name' => 'Alat Tulis Kantor', 'code' => 'OFFICE', 'max_amount' => 500_000],
            ['name' => 'Transportasi', 'code' => 'TRANSPORT', 'max_amount' => 1_000_000],
            ['name' => 'Pelatihan', 'code' => 'TRAINING', 'max_amount' => null],
            ['name' => 'Kesehatan', 'code' => 'MEDICAL', 'max_amount' => 3_000_000],
            ['name' => 'Komunikasi', 'code' => 'COMM', 'max_amount' => 500_000],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['code' => $cat['code']],
                ['name' => $cat['name'], 'max_amount' => $cat['max_amount'], 'is_active' => true],
            );
        }
    }
}
