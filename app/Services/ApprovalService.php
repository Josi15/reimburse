<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Enums\ReimbursementStatus;
use App\Models\Approval;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sistem approval berjenjang (Manager → Finance). Level ditentukan dari status
 * reimbursement saat ini; setiap aksi mencatat baris `approvals` (history &
 * timeline) dan memindahkan status sesuai state machine enum.
 */
class ApprovalService
{
    public function __construct(private readonly ReimbursementNotifier $notifier) {}

    public function approve(Reimbursement $reimbursement, User $approver, ?string $notes = null): Reimbursement
    {
        [$level, $target] = $this->resolveApprove($reimbursement->status);

        return $this->apply($reimbursement, $approver, $level, ApprovalAction::Approved, $target, $notes);
    }

    public function reject(Reimbursement $reimbursement, User $approver, string $notes): Reimbursement
    {
        [$level, $target] = $this->resolveReject($reimbursement->status);

        return $this->apply($reimbursement, $approver, $level, ApprovalAction::Rejected, $target, $notes);
    }

    public function requestRevision(Reimbursement $reimbursement, User $approver, string $notes): Reimbursement
    {
        $level = $this->resolveLevel($reimbursement->status);

        return $this->apply(
            $reimbursement, $approver, $level,
            ApprovalAction::RevisionRequested, ReimbursementStatus::RevisionRequested, $notes,
        );
    }

    /** Level approval yang berlaku untuk status sekarang. */
    private function resolveLevel(ReimbursementStatus $status): ApprovalLevel
    {
        return match ($status) {
            ReimbursementStatus::Submitted => ApprovalLevel::Manager,
            ReimbursementStatus::ManagerApproved => ApprovalLevel::Finance,
            default => $this->fail('Status ini tidak menunggu persetujuan.'),
        };
    }

    private function resolveApprove(ReimbursementStatus $status): array
    {
        return match ($status) {
            ReimbursementStatus::Submitted => [ApprovalLevel::Manager, ReimbursementStatus::ManagerApproved],
            ReimbursementStatus::ManagerApproved => [ApprovalLevel::Finance, ReimbursementStatus::FinanceApproved],
            default => $this->fail('Status ini tidak menunggu persetujuan.'),
        };
    }

    private function resolveReject(ReimbursementStatus $status): array
    {
        return match ($status) {
            ReimbursementStatus::Submitted => [ApprovalLevel::Manager, ReimbursementStatus::ManagerRejected],
            ReimbursementStatus::ManagerApproved => [ApprovalLevel::Finance, ReimbursementStatus::FinanceRejected],
            default => $this->fail('Status ini tidak dapat ditolak.'),
        };
    }

    private function apply(
        Reimbursement $reimbursement,
        User $approver,
        ApprovalLevel $level,
        ApprovalAction $action,
        ReimbursementStatus $target,
        ?string $notes,
    ): Reimbursement {
        if (! $reimbursement->status->canTransitionTo($target)) {
            $this->fail("Transisi ke {$target->value} tidak diperbolehkan.");
        }

        $reimbursement = DB::transaction(function () use ($reimbursement, $approver, $level, $action, $target, $notes) {
            Approval::create([
                'reimbursement_id' => $reimbursement->id,
                'approver_id' => $approver->id,
                'level' => $level,
                'action' => $action,
                'notes' => $notes,
                'acted_at' => now(),
            ]);

            $reimbursement->update(['status' => $target]);

            return $reimbursement->refresh();
        });

        // Notifikasi dikirim setelah commit.
        $this->notifier->actioned($reimbursement, $level, $action, $notes);

        if ($action === ApprovalAction::Approved && $target === ReimbursementStatus::ManagerApproved) {
            $this->notifier->forwardedToFinance($reimbursement);
        }

        return $reimbursement;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['status' => $message]);
    }
}
