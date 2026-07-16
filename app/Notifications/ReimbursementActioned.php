<?php

namespace App\Notifications;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Models\Reimbursement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke pemilik pengajuan ketika ada tindakan approval (approve/reject/
 * revisi) oleh Manager atau Finance.
 */
class ReimbursementActioned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reimbursement $reimbursement,
        public ApprovalLevel $level,
        public ApprovalAction $action,
        public ?string $notes = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function headline(): string
    {
        return "{$this->level->label()} — {$this->action->label()}";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'reimbursement_actioned',
            'reimbursement_id' => $this->reimbursement->id,
            'number' => $this->reimbursement->reimbursement_number,
            'level' => $this->level->value,
            'action' => $this->action->value,
            'status' => $this->reimbursement->status->value,
            'notes' => $this->notes,
            'message' => "Pengajuan {$this->reimbursement->reimbursement_number}: {$this->headline()}.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Update status reimbursement Anda')
            ->greeting('Halo '.$notifiable->name)
            ->line("Pengajuan {$this->reimbursement->reimbursement_number} kini: {$this->headline()}.");

        if ($this->notes) {
            $mail->line('Catatan: '.$this->notes);
        }

        return $mail->action('Lihat Detail', url('/reimbursements/'.$this->reimbursement->id));
    }
}
