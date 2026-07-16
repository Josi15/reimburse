<?php

namespace App\Notifications;

use App\Models\Reimbursement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke approver (Manager atau Finance) ketika sebuah reimbursement
 * memasuki tahap yang menunggu persetujuannya. Email diproses lewat Queue.
 */
class ReimbursementSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reimbursement $reimbursement,
        public string $stage, // 'manager' | 'finance'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'reimbursement_submitted',
            'reimbursement_id' => $this->reimbursement->id,
            'number' => $this->reimbursement->reimbursement_number,
            'title' => $this->reimbursement->title,
            'amount' => $this->reimbursement->amount,
            'stage' => $this->stage,
            'message' => "Pengajuan {$this->reimbursement->reimbursement_number} menunggu persetujuan {$this->stage}.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reimbursement menunggu persetujuan Anda')
            ->greeting('Halo '.$notifiable->name)
            ->line("Pengajuan {$this->reimbursement->reimbursement_number} menunggu persetujuan Anda.")
            ->line('Judul: '.$this->reimbursement->title)
            ->line('Jumlah: '.$this->reimbursement->formatted_amount)
            ->action('Lihat Pengajuan', url('/reimbursements/'.$this->reimbursement->id));
    }
}
