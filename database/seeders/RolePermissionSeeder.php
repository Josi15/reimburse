<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seed seluruh role + permission dasar + pemetaannya (dasar: matriks Phase 1).
 * Idempotent: memakai firstOrCreate sehingga aman dijalankan berulang.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Definisi permission granular --------------------------------
        $permissions = [
            'user.view' => 'Lihat User',
            'user.create' => 'Buat User',
            'user.update' => 'Ubah User',
            'user.delete' => 'Hapus User',
            'role.manage' => 'Kelola Role & Permission',
            'department.manage' => 'Kelola Department',
            'category.manage' => 'Kelola Category',
            'bank.manage' => 'Kelola Master Bank',
            'project.manage' => 'Kelola Master Project',
            // Pantauan anggaran: .view = proyek yang dipegang sendiri
            // (Project Manager), .viewAny = seluruh proyek perusahaan.
            'project.budget.view' => 'Lihat Sisa Anggaran Proyek Sendiri',
            'project.budget.viewAny' => 'Lihat Sisa Anggaran Semua Proyek',
            'company_account.manage' => 'Kelola Rekening Perusahaan',
            'bankaccount.manage' => 'Kelola Rekening Sendiri',
            'reimbursement.viewAny' => 'Lihat Semua Reimbursement',
            'reimbursement.view' => 'Lihat Reimbursement Sendiri',
            'reimbursement.create' => 'Buat Reimbursement',
            'reimbursement.update' => 'Ubah Reimbursement',
            'reimbursement.delete' => 'Hapus Draft Reimbursement',
            'reimbursement.submit' => 'Submit Reimbursement',
            // Jenis pengajuan berjenjang: biaya bebas untuk semua pengaju,
            // lembur & pengadaan masing-masing butuh permission sendiri.
            // Magang: biaya saja. Employee: + lembur. Selebihnya: semua jenis.
            'reimbursement.overtime' => 'Ajukan Lembur',
            'reimbursement.procurement' => 'Ajukan Pengadaan Barang & Layanan',
            'reimbursement.approve.manager' => 'Approve/Reject (Manager)',
            'reimbursement.approve.finance' => 'Approve/Reject (Finance)',
            'reimbursement.approve.director' => 'Approve/Reject (Direksi)',
            'payment.view' => 'Lihat Pembayaran',
            'payment.process' => 'Proses Pembayaran',
            'dashboard.viewAll' => 'Lihat Dashboard Keseluruhan',
            'report.view' => 'Lihat Laporan',
            'report.export' => 'Export Laporan',
            'audit.view' => 'Lihat Audit Log',
        ];

        $permModels = [];
        foreach ($permissions as $name => $display) {
            $permModels[$name] = Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $display, 'guard_name' => 'web'],
            );
        }

        $all = array_keys($permissions);

        // Hak dasar "bisa mengajukan reimbursement untuk diri sendiri".
        // Dipakai semua role operasional; yang membedakan hanya JENIS yang
        // boleh dipilih (reimbursement.overtime & .procurement di bawah).
        $canSubmitClaims = [
            'reimbursement.view', 'reimbursement.create', 'reimbursement.update',
            'reimbursement.delete', 'reimbursement.submit',
            'bankaccount.manage',   // butuh rekening sendiri untuk dibayar
        ];

        // Jenis pengajuan penuh: biaya (bebas) + lembur + pengadaan.
        $allClaimTypes = ['reimbursement.overtime', 'reimbursement.procurement'];

        // ---- Pemetaan role → [display, permissions, plafon, upah lembur] --
        // Plafon (IDR/bulan): null = tanpa batas, 0 = tak boleh mengajukan.
        // Upah lembur (IDR/jam): dimulai Rp 30.000 di Karyawan lalu naik
        // Rp 10.000 tiap jenjang ke atas; null = tak berhak lembur.
        $map = [
            'super_admin' => ['Super Admin', $all, 50_000_000, 80_000], // akses penuh
            'director' => ['Direktur', [
                ...$canSubmitClaims, ...$allClaimTypes,
                // Gerbang terakhir untuk pengajuan bernilai besar.
                'reimbursement.approve.director',
                'reimbursement.viewAny', 'payment.view',
                'project.budget.view', 'project.budget.viewAny',
                'dashboard.viewAll', 'report.view', 'report.export', 'audit.view',
            ], 50_000_000, 80_000],
            'admin' => ['Admin', [
                ...$canSubmitClaims, ...$allClaimTypes,
                'user.view', 'user.create', 'user.update', 'user.delete',
                'department.manage', 'category.manage', 'bank.manage', 'project.manage',
                'company_account.manage',
                'project.budget.view', 'project.budget.viewAny',
                'reimbursement.viewAny', 'dashboard.viewAll',
                'report.view', 'report.export', 'audit.view',
            ], 25_000_000, 70_000],
            'finance' => ['Finance', [
                ...$canSubmitClaims, ...$allClaimTypes,
                'reimbursement.viewAny', 'reimbursement.approve.finance',
                'payment.view', 'payment.process',
                'company_account.manage',
                'project.budget.view', 'project.budget.viewAny',
                'dashboard.viewAll', 'report.view', 'report.export',
            ], 10_000_000, 60_000],
            'manager' => ['Manager', [
                ...$canSubmitClaims, ...$allClaimTypes,
                'reimbursement.viewAny', 'reimbursement.approve.manager',
                // Bila ditunjuk sebagai pemegang proyek, boleh memantau
                // sisa anggaran proyek tersebut (proyek sendiri saja).
                'project.budget.view',
                'dashboard.viewAll', 'report.view', 'report.export',
            ], 5_000_000, 50_000],
            // Pemegang proyek: mengelola jalannya proyek dan memantau berapa
            // sisa dana perusahaan yang masih tersedia untuk proyeknya.
            'project_manager' => ['Project Manager', [
                ...$canSubmitClaims, ...$allClaimTypes,
                'project.budget.view',
                'report.view', 'report.export',
            ], 5_000_000, 50_000],
            'supervisor' => ['Supervisor', [
                ...$canSubmitClaims, ...$allClaimTypes,
                // Atasan lini pertama: menilai di tingkat yang sama dengan Manager.
                'reimbursement.viewAny', 'reimbursement.approve.manager',
                'project.budget.view',
                'dashboard.viewAll', 'report.view', 'report.export',
            ], 3_000_000, 40_000],
            // Biaya & lembur saja — tidak boleh mengajukan pengadaan.
            'employee' => ['Employee', [
                ...$canSubmitClaims,
                'reimbursement.overtime',
                'report.export',
            ], 1_500_000, 30_000],
            // Paling terbatas: hanya penggantian biaya.
            'intern' => ['Staf Magang', [
                ...$canSubmitClaims,
            ], 500_000, null],
            'auditor' => ['Auditor', [ // read-only penuh, tak mengajukan
                'reimbursement.viewAny', 'payment.view', 'dashboard.viewAll',
                'project.budget.view', 'project.budget.viewAny',
                'report.view', 'report.export', 'audit.view',
            ], 0, null],
        ];

        foreach ($map as $slug => [$display, $perms, $limit, $overtimeRate]) {
            $role = Role::firstOrCreate(
                ['name' => $slug],
                ['display_name' => $display, 'guard_name' => 'web'],
            );
            // Perbarui plafon & upah lembur secara eksplisit agar re-seed
            // menyetel ulang nilainya.
            $role->update(['reimbursement_limit' => $limit, 'overtime_rate' => $overtimeRate]);
            $ids = collect($perms)->map(fn ($p) => $permModels[$p]->id)->all();
            $role->permissions()->sync($ids);
        }
    }
}
