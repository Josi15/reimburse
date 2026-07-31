<?php

namespace App\Enums;

enum ApprovalLevel: string
{
    case Manager = 'manager';
    case Finance = 'finance';
    case Director = 'director';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Finance => 'Finance',
            self::Director => 'Direksi',
        };
    }

    /** Nama ability policy untuk level ini (dipakai ApprovalController). */
    public function ability(): string
    {
        return match ($this) {
            self::Manager => 'approveManager',
            self::Finance => 'approveFinance',
            self::Director => 'approveDirector',
        };
    }

    /** Permission RBAC yang menandakan seseorang berwenang di level ini. */
    public function permission(): string
    {
        return match ($this) {
            self::Manager => 'reimbursement.approve.manager',
            self::Finance => 'reimbursement.approve.finance',
            self::Director => 'reimbursement.approve.director',
        };
    }

    /** Status hasil bila level ini menyetujui. */
    public function approvedStatus(): ReimbursementStatus
    {
        return match ($this) {
            self::Manager => ReimbursementStatus::ManagerApproved,
            self::Finance => ReimbursementStatus::FinanceApproved,
            self::Director => ReimbursementStatus::DirectorApproved,
        };
    }

    /** Status hasil bila level ini menolak. */
    public function rejectedStatus(): ReimbursementStatus
    {
        return match ($this) {
            self::Manager => ReimbursementStatus::ManagerRejected,
            self::Finance => ReimbursementStatus::FinanceRejected,
            self::Director => ReimbursementStatus::DirectorRejected,
        };
    }
}
