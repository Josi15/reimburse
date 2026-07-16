<?php

namespace App\Support;

use App\Models\User;

/**
 * Sumber tunggal definisi menu sidebar. Setiap item dapat menetapkan
 * `permissions` (any-of); item tanpa syarat selalu tampil. Super Admin melihat
 * semua. Beberapa href menuju modul yang dibuat pada fase berikutnya.
 */
class Navigation
{
    /** Definisi lengkap menu (belum difilter). */
    private static function items(): array
    {
        return [
            ['label' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'home'],
            ['label' => 'Reimbursement', 'href' => '/reimbursements', 'icon' => 'receipt',
                'permissions' => ['reimbursement.view', 'reimbursement.viewAny']],
            ['label' => 'Persetujuan', 'href' => '/approvals', 'icon' => 'check-circle',
                'permissions' => ['reimbursement.approve.manager', 'reimbursement.approve.finance']],
            ['label' => 'Pembayaran', 'href' => '/payments', 'icon' => 'banknotes',
                'permissions' => ['payment.view', 'payment.process']],
            ['label' => 'Rekening Bank', 'href' => '/bank-accounts', 'icon' => 'credit-card',
                'permissions' => ['bankaccount.manage']],
            ['label' => 'Master Data', 'href' => '/master', 'icon' => 'database',
                'permissions' => ['user.view', 'department.manage', 'category.manage', 'bank.manage']],
            ['label' => 'Laporan', 'href' => '/reports', 'icon' => 'chart-bar',
                'permissions' => ['report.view']],
            ['label' => 'Audit Log', 'href' => '/audit-logs', 'icon' => 'shield-check',
                'permissions' => ['audit.view']],
        ];
    }

    /** Menu yang sudah difilter untuk user tertentu. */
    public static function for(User $user): array
    {
        $isSuperAdmin = $user->hasRole('super_admin');

        return array_values(array_filter(self::items(), function (array $item) use ($user, $isSuperAdmin) {
            if (empty($item['permissions'])) {
                return true; // item publik untuk semua user terautentikasi
            }
            if ($isSuperAdmin) {
                return true;
            }

            foreach ($item['permissions'] as $permission) {
                if ($user->hasPermission($permission)) {
                    return true;
                }
            }

            return false;
        }));
    }
}
