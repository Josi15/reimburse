<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Seed contoh master data proyek. Idempotent (firstOrCreate per kode).
 */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            ['code' => 'PRJ-2026-001', 'name' => 'Implementasi ERP', 'budget' => 250_000_000],
            ['code' => 'PRJ-2026-002', 'name' => 'Pengembangan Aplikasi Mobile', 'budget' => 150_000_000],
            ['code' => 'PRJ-2026-003', 'name' => 'Audit & Kepatuhan 2026', 'budget' => 80_000_000],
            ['code' => 'PRJ-2026-004', 'name' => 'Ekspansi Cabang Surabaya', 'budget' => 500_000_000],
        ];

        foreach ($projects as $p) {
            Project::firstOrCreate(
                ['code' => $p['code']],
                [
                    'name' => $p['name'],
                    'budget' => $p['budget'],
                    'start_date' => now()->startOfYear()->toDateString(),
                    'end_date' => now()->endOfYear()->toDateString(),
                    'is_active' => true,
                ],
            );
        }
    }
}
