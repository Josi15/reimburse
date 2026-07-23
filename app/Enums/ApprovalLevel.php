<?php

namespace App\Enums;

enum ApprovalLevel: string
{
    case Manager = 'manager';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Finance => 'Finance',
        };
    }

    /** Nama ability policy untuk level ini (dipakai ApprovalController). */
    public function ability(): string
    {
        return match ($this) {
            self::Manager => 'approveManager',
            self::Finance => 'approveFinance',
        };
    }

    /** Status hasil bila level ini menyetujui. */
    public function approvedStatus(): ReimbursementStatus
    {
        return match ($this) {
            self::Manager => ReimbursementStatus::ManagerApproved,
            self::Finance => ReimbursementStatus::FinanceApproved,
        };
    }

    /** Status hasil bila level ini menolak. */
    public function rejectedStatus(): ReimbursementStatus
    {
        return match ($this) {
            self::Manager => ReimbursementStatus::ManagerRejected,
            self::Finance => ReimbursementStatus::FinanceRejected,
        };
    }
}
