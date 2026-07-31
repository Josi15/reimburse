<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Enums\AuditEvent;
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
    public function __construct(
        private readonly ReimbursementNotifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    public function approve(Reimbursement $reimbursement, User $approver, ?string $notes = null): Reimbursement
    {
        $level = $this->resolveLevel($reimbursement);

        return $this->apply($reimbursement, $approver, $level, ApprovalAction::Approved, $level->approvedStatus(), $notes);
    }

    public function reject(Reimbursement $reimbursement, User $approver, string $notes): Reimbursement
    {
        $level = $this->resolveLevel($reimbursement);

        return $this->apply($reimbursement, $approver, $level, ApprovalAction::Rejected, $level->rejectedStatus(), $notes);
    }

    public function requestRevision(Reimbursement $reimbursement, User $approver, string $notes): Reimbursement
    {
        $level = $this->resolveLevel($reimbursement);

        return $this->apply(
            $reimbursement, $approver, $level,
            ApprovalAction::RevisionRequested, ReimbursementStatus::RevisionRequested, $notes,
        );
    }

    /**
     * Level approval yang sedang ditunggu. Memakai method model (bukan enum
     * status saja) karena tahap Direksi bergantung pada nominal pengajuan.
     */
    private function resolveLevel(Reimbursement $reimbursement): ApprovalLevel
    {
        return $reimbursement->pendingApprovalLevel()
            ?? $this->fail('Status ini tidak menunggu persetujuan.');
    }

    private function apply(
        Reimbursement $reimbursement,
        User $approver,
        ApprovalLevel $level,
        ApprovalAction $action,
        ReimbursementStatus $target,
        ?string $notes,
    ): Reimbursement {
        $reimbursement = DB::transaction(function () use ($reimbursement, $approver, $level, $action, $target, $notes) {
            // Kunci baris agar dua approver tidak memproses status yang sama
            // secara bersamaan (race → dua baris approval / transisi ganda).
            $locked = Reimbursement::whereKey($reimbursement->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo($target)) {
                $this->fail("Transisi ke {$target->value} tidak diperbolehkan.");
            }

            // Setelah baris dikunci, pastikan tingkat yang ditunggu belum
            // bergeser (mis. approver lain menyetujui lebih dulu).
            if ($action !== ApprovalAction::RevisionRequested && $locked->pendingApprovalLevel() !== $level) {
                $this->fail('Pengajuan ini sudah diproses approver lain.');
            }

            Approval::create([
                'reimbursement_id' => $locked->id,
                'approver_id' => $approver->id,
                'level' => $level,
                'action' => $action,
                'notes' => $notes,
                'acted_at' => now(),
            ]);

            // Status diubah tanpa auto-audit; dicatat sebagai event semantik.
            Reimbursement::withoutAuditing(fn () => $locked->update(['status' => $target]));

            // Audit ditulis di dalam transaksi: transisi & jejaknya atomik.
            $this->audit->log(
                $action === ApprovalAction::Approved ? AuditEvent::Approve : AuditEvent::Reject,
                $locked,
                null,
                ['status' => $target->value],
                "{$level->label()} — {$action->label()}",
            );

            return $locked->refresh();
        });

        // Notifikasi dikirim setelah commit: pertama balik ke pengaju...
        $this->notifier->actioned($reimbursement, $level, $action, $notes);

        // ...lalu diteruskan ke pihak yang harus bertindak berikutnya
        // (Finance setelah Manager, petugas bayar setelah Finance).
        if ($action === ApprovalAction::Approved) {
            $this->notifier->advancedTo($reimbursement, $target);
        }

        return $reimbursement;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['status' => $message]);
    }
}
