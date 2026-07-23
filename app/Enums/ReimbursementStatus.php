<?php

namespace App\Enums;

/**
 * Status reimbursement + state machine eksplisit (Phase 1/9).
 * Backing value cocok dengan CHECK constraint di migration.
 */
enum ReimbursementStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ManagerApproved = 'manager_approved';
    case ManagerRejected = 'manager_rejected';
    case FinanceApproved = 'finance_approved';
    case FinanceRejected = 'finance_rejected';
    case RevisionRequested = 'revision_requested';
    case Paid = 'paid';

    /** Label ramah-tampilan (UI). */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Menunggu Manager',
            self::ManagerApproved => 'Disetujui Manager',
            self::ManagerRejected => 'Ditolak Manager',
            self::FinanceApproved => 'Disetujui Finance',
            self::FinanceRejected => 'Ditolak Finance',
            self::RevisionRequested => 'Perlu Revisi',
            self::Paid => 'Dibayar',
        };
    }

    /** Warna badge untuk UI (Tailwind palette hint). */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted, self::ManagerApproved => 'blue',
            self::FinanceApproved => 'indigo',
            self::ManagerRejected, self::FinanceRejected => 'red',
            self::RevisionRequested => 'amber',
            self::Paid => 'green',
        };
    }

    /** Status final (tidak ada transisi keluar). */
    public function isFinal(): bool
    {
        return $this === self::Paid;
    }

    /** Transisi state machine yang diizinkan dari status ini. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::ManagerApproved, self::ManagerRejected, self::RevisionRequested],
            self::ManagerApproved => [self::FinanceApproved, self::FinanceRejected, self::RevisionRequested],
            self::RevisionRequested => [self::Draft, self::Submitted],
            self::ManagerRejected => [self::RevisionRequested],
            self::FinanceRejected => [self::RevisionRequested],
            self::FinanceApproved => [self::Paid],
            self::Paid => [],
        };
    }

    /** Validasi apakah transisi ke status target diperbolehkan. */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Level approval yang berlaku untuk status ini (satu-satunya sumber
     * pemetaan status → level; dipakai service, controller, & policy).
     */
    public function approvalLevel(): ?ApprovalLevel
    {
        return match ($this) {
            self::Submitted => ApprovalLevel::Manager,
            self::ManagerApproved => ApprovalLevel::Finance,
            default => null,
        };
    }
}
