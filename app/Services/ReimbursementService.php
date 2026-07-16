<?php

namespace App\Services;

use App\Enums\ReimbursementStatus;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logika bisnis reimbursement: pembuatan draft, edit, submit (transisi state
 * machine), dan penyusunan timeline status.
 */
class ReimbursementService
{
    public function __construct(
        private readonly AttachmentService $attachments,
        private readonly ReimbursementNotifier $notifier,
    ) {}

    /** Buat draft baru milik user. */
    public function createDraft(User $user, array $data, iterable $files = []): Reimbursement
    {
        return DB::transaction(function () use ($user, $data, $files) {
            $reimbursement = Reimbursement::create([
                'user_id' => $user->id,
                'department_id' => $user->department_id,
                'category_id' => $data['category_id'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'reason' => $data['reason'],
                'amount' => $data['amount'],
                'currency' => 'IDR',
                'status' => ReimbursementStatus::Draft,
                'expense_date' => $data['expense_date'] ?? null,
            ]);

            $this->attachments->storeMany($files, $reimbursement, $user);

            return $reimbursement->load('attachments');
        });
    }

    /** Edit draft/revisi. Tambah file baru & hapus lampiran terpilih. */
    public function updateDraft(Reimbursement $reimbursement, User $user, array $data, iterable $files = [], array $deleteAttachmentIds = []): Reimbursement
    {
        return DB::transaction(function () use ($reimbursement, $user, $data, $files, $deleteAttachmentIds) {
            $reimbursement->update(collect($data)->only([
                'category_id', 'bank_account_id', 'title', 'description',
                'reason', 'amount', 'expense_date',
            ])->toArray());

            foreach ($reimbursement->attachments()->whereIn('id', $deleteAttachmentIds)->get() as $attachment) {
                $this->attachments->delete($attachment);
            }

            $this->attachments->storeMany($files, $reimbursement, $user);

            return $reimbursement->load('attachments');
        });
    }

    /** Ajukan reimbursement (Draft/Revisi → Submitted) mengikuti state machine. */
    public function submit(Reimbursement $reimbursement): Reimbursement
    {
        $this->assertTransition($reimbursement, ReimbursementStatus::Submitted);

        $reimbursement->update([
            'status' => ReimbursementStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->notifier->submitted($reimbursement);

        return $reimbursement;
    }

    /** Validasi transisi status terhadap state machine enum. */
    private function assertTransition(Reimbursement $reimbursement, ReimbursementStatus $target): void
    {
        if (! $reimbursement->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transisi dari {$reimbursement->status->value} ke {$target->value} tidak diperbolehkan.",
            ]);
        }
    }

    /** Susun timeline status dari data yang ada (tanpa tabel tambahan). */
    public function buildTimeline(Reimbursement $reimbursement): array
    {
        $events = [];

        $events[] = [
            'status' => ReimbursementStatus::Draft->value,
            'label' => 'Draft dibuat',
            'at' => $reimbursement->created_at,
            'by' => $reimbursement->user?->name,
            'note' => null,
        ];

        if ($reimbursement->submitted_at) {
            $events[] = [
                'status' => ReimbursementStatus::Submitted->value,
                'label' => 'Diajukan',
                'at' => $reimbursement->submitted_at,
                'by' => $reimbursement->user?->name,
                'note' => null,
            ];
        }

        foreach ($reimbursement->approvals()->with('approver')->orderBy('acted_at')->get() as $approval) {
            $events[] = [
                'status' => $approval->action->value,
                'label' => $approval->level->label().' — '.$approval->action->label(),
                'at' => $approval->acted_at,
                'by' => $approval->approver?->name,
                'note' => $approval->notes,
            ];
        }

        if ($reimbursement->completed_at) {
            $events[] = [
                'status' => ReimbursementStatus::Paid->value,
                'label' => 'Dibayar',
                'at' => $reimbursement->completed_at,
                'by' => null,
                'note' => null,
            ];
        }

        return $events;
    }
}
