<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed master data proyek. Idempotent (firstOrCreate per kode).
 *
 * Tiap departemen dipasangkan dengan 1–2 proyek: pemegangnya adalah Project
 * Manager departemen tersebut (fallback ke Manager-nya), dan anggotanya adalah
 * seluruh isi departemen itu. Dengan begitu dropdown "Project" di form
 * pengajuan selalu ada isinya dan halaman "Anggaran Proyek" langsung terisi.
 * Karena bergantung pada user, seeder ini dijalankan SETELAH UserSeeder.
 */
class ProjectSeeder extends Seeder
{
    /** Proyek per departemen: [kode, nama, anggaran IDR]. */
    private const PROJECTS = [
        'IT' => [
            ['PRJ-2026-001', 'Implementasi ERP', 250_000_000],
            ['PRJ-2026-002', 'Pengembangan Aplikasi Mobile', 150_000_000],
        ],
        'FIN' => [
            ['PRJ-2026-003', 'Audit & Kepatuhan 2026', 80_000_000],
            ['PRJ-2026-005', 'Digitalisasi Faktur & Pajak', 120_000_000],
        ],
        'OPS' => [
            ['PRJ-2026-004', 'Ekspansi Cabang Surabaya', 500_000_000],
            ['PRJ-2026-010', 'Optimasi Rantai Pasok', 180_000_000],
        ],
        'HR' => [
            ['PRJ-2026-006', 'Rekrutmen & Onboarding 2026', 90_000_000],
            ['PRJ-2026-007', 'Pelatihan & Sertifikasi Karyawan', 60_000_000],
        ],
        'MKT' => [
            ['PRJ-2026-008', 'Kampanye Digital Nasional', 200_000_000],
            ['PRJ-2026-009', 'Rebranding & Pameran Dagang', 110_000_000],
        ],
    ];

    /** Role yang ikut ditugaskan sebagai anggota proyek departemennya. */
    private const MEMBER_ROLES = [
        'manager', 'supervisor', 'admin', 'project_manager',
        'finance', 'employee', 'intern',
    ];

    public function run(): void
    {
        $departments = Department::pluck('id', 'code');

        foreach (self::PROJECTS as $deptCode => $projects) {
            $deptId = $departments[$deptCode] ?? null;
            if (! $deptId) {
                continue;
            }

            $owner = $this->departmentOwner($deptId);
            $memberIds = User::query()
                ->where('department_id', $deptId)
                ->withRole(...self::MEMBER_ROLES)
                ->pluck('id')
                ->all();

            foreach ($projects as [$code, $name, $budget]) {
                $project = Project::firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'budget' => $budget,
                        'manager_id' => $owner?->id,
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

    /** Pemegang proyek departemen: Project Manager, atau Manager bila kosong. */
    private function departmentOwner(int $deptId): ?User
    {
        return User::query()->where('department_id', $deptId)->withRole('project_manager')->first()
            ?? User::query()->where('department_id', $deptId)->withRole('manager')->first();
    }
}
