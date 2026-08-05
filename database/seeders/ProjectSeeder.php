<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed contoh master data proyek. Idempotent (firstOrCreate per kode).
 * Semua proyek contoh ditugaskan ke Project Manager hasil UserSeeder agar
 * halaman "Anggaran Proyek" langsung ada isinya. Karena itu seeder ini
 * dijalankan SETELAH UserSeeder.
 */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $pm = User::where('email', 'pm@fundback.test')->first();

        $projects = [
            ['code' => 'PRJ-2026-001', 'name' => 'Implementasi ERP', 'budget' => 250_000_000],
            ['code' => 'PRJ-2026-002', 'name' => 'Pengembangan Aplikasi Mobile', 'budget' => 150_000_000],
            ['code' => 'PRJ-2026-003', 'name' => 'Audit & Kepatuhan 2026', 'budget' => 80_000_000],
            ['code' => 'PRJ-2026-004', 'name' => 'Ekspansi Cabang Surabaya', 'budget' => 500_000_000],
        ];

        // Anggota contoh: pengaju harian (karyawan & magang) supaya dropdown
        // "Project" di form pengajuan mereka langsung ada isinya.
        $memberIds = User::query()
            ->withRole('employee', 'intern')
            ->pluck('id')
            ->all();

        foreach ($projects as $p) {
            $project = Project::firstOrCreate(
                ['code' => $p['code']],
                [
                    'name' => $p['name'],
                    'budget' => $p['budget'],
                    'manager_id' => $pm?->id,
                    'start_date' => now()->startOfYear()->toDateString(),
                    'end_date' => now()->endOfYear()->toDateString(),
                    'is_active' => true,
                ],
            );

            // syncWithoutDetaching agar re-seed tidak membuang penugasan
            // yang sudah diatur lewat UI.
            $project->members()->syncWithoutDetaching($memberIds);
        }
    }
}
