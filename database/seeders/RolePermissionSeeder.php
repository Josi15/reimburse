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
            'bankaccount.manage' => 'Kelola Rekening Sendiri',
            'reimbursement.viewAny' => 'Lihat Semua Reimbursement',
            'reimbursement.view' => 'Lihat Reimbursement Sendiri',
            'reimbursement.create' => 'Buat Reimbursement',
            'reimbursement.update' => 'Ubah Reimbursement',
            'reimbursement.delete' => 'Hapus Draft Reimbursement',
            'reimbursement.submit' => 'Submit Reimbursement',
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

        // ---- Pemetaan role → permission ----------------------------------
        $map = [
            'super_admin' => ['Super Admin', $all], // akses penuh
            'admin' => ['Admin', [
                'user.view', 'user.create', 'user.update', 'user.delete',
                'department.manage', 'category.manage', 'bank.manage',
                'reimbursement.viewAny', 'dashboard.viewAll',
                'report.view', 'report.export', 'audit.view',
            ]],
            'employee' => ['Employee', [
                'reimbursement.view', 'reimbursement.create', 'reimbursement.update',
                'reimbursement.delete', 'reimbursement.submit',
                'bankaccount.manage', 'report.export',
            ]],
            'manager' => ['Manager', [
                'reimbursement.viewAny', 'reimbursement.approve.manager',
                'bankaccount.manage', 'dashboard.viewAll', 'report.view', 'report.export',
            ]],
            'finance' => ['Finance', [
                'reimbursement.viewAny', 'reimbursement.approve.finance',
                'payment.view', 'payment.process', 'bankaccount.manage',
                'dashboard.viewAll', 'report.view', 'report.export',
            ]],
            'auditor' => ['Auditor', [ // read-only penuh
                'reimbursement.viewAny', 'payment.view', 'dashboard.viewAll',
                'report.view', 'report.export', 'audit.view',
            ]],
        ];

        foreach ($map as $slug => [$display, $perms]) {
            $role = Role::firstOrCreate(
                ['name' => $slug],
                ['display_name' => $display, 'guard_name' => 'web'],
            );
            $ids = collect($perms)->map(fn ($p) => $permModels[$p]->id)->all();
            $role->permissions()->sync($ids);
        }
    }
}
