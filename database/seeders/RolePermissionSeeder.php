<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seed 6 role kanonik + permission dasar + pemetaannya (sesuai matriks Phase 1).
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
            'company_account.manage' => 'Kelola Rekening Perusahaan',
            'bankaccount.manage' => 'Kelola Rekening Sendiri',
            'reimbursement.viewAny' => 'Lihat Semua Reimbursement',
            'reimbursement.view' => 'Lihat Reimbursement Sendiri',
            'reimbursement.create' => 'Buat Reimbursement',
            'reimbursement.update' => 'Ubah Reimbursement',
            'reimbursement.delete' => 'Hapus Draft Reimbursement',
            'reimbursement.submit' => 'Submit Reimbursement',
            // Employee hanya boleh mengajukan biaya & lembur; pengadaan barang
            // dan layanan/server butuh permission tambahan ini.
            'reimbursement.procurement' => 'Ajukan Pengadaan Barang & Layanan',
            'reimbursement.approve.manager' => 'Approve/Reject (Manager)',
            'reimbursement.approve.finance' => 'Approve/Reject (Finance)',
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
        // boleh dipilih (lihat reimbursement.procurement di bawah).
        $canSubmitClaims = [
            'reimbursement.view', 'reimbursement.create', 'reimbursement.update',
            'reimbursement.delete', 'reimbursement.submit',
            'bankaccount.manage',   // butuh rekening sendiri untuk dibayar
        ];

        // ---- Pemetaan role → [display, permissions, plafon] --------------
        // Plafon (IDR/pengajuan): null = tanpa batas, 0 = tak boleh mengajukan.
        $map = [
            'super_admin' => ['Super Admin', $all, 50_000_000], // akses penuh
            'admin' => ['Admin', [
                ...$canSubmitClaims,
                'user.view', 'user.create', 'user.update', 'user.delete',
                'department.manage', 'category.manage', 'bank.manage', 'project.manage',
                'company_account.manage',
                'reimbursement.viewAny', 'dashboard.viewAll',
                'report.view', 'report.export', 'audit.view',
                'reimbursement.procurement',
            ], 25_000_000],
            // Satu-satunya role yang jenis pengajuannya dibatasi: biaya & lembur.
            'employee' => ['Employee', [
                ...$canSubmitClaims,
                'report.export',
            ], 1_500_000],
            'manager' => ['Manager', [
                ...$canSubmitClaims,
                'reimbursement.viewAny', 'reimbursement.approve.manager',
                'dashboard.viewAll', 'report.view', 'report.export',
                'reimbursement.procurement',
            ], 5_000_000],
            'finance' => ['Finance', [
                ...$canSubmitClaims,
                'reimbursement.viewAny', 'reimbursement.approve.finance',
                'payment.view', 'payment.process',
                'company_account.manage',
                'dashboard.viewAll', 'report.view', 'report.export',
                'reimbursement.procurement',
            ], 10_000_000],
            'auditor' => ['Auditor', [ // read-only penuh, tak mengajukan
                'reimbursement.viewAny', 'payment.view', 'dashboard.viewAll',
                'report.view', 'report.export', 'audit.view',
            ], 0],
        ];

        foreach ($map as $slug => [$display, $perms, $limit]) {
            $role = Role::firstOrCreate(
                ['name' => $slug],
                ['display_name' => $display, 'guard_name' => 'web'],
            );
            // Perbarui plafon secara eksplisit agar re-seed menyetel ulang nilainya.
            $role->update(['reimbursement_limit' => $limit]);
            $ids = collect($perms)->map(fn ($p) => $permModels[$p]->id)->all();
            $role->permissions()->sync($ids);
        }
    }
}
