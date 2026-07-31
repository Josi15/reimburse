<?php

namespace App\Notifications;

use App\Models\Reimbursement;
use App\Notifications\Concerns\DeliversInAppImmediately;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tanda terima untuk PENGAJU: pengajuannya sudah masuk sistem dan sedang
 * menunggu persetujuan. Ini titik awal rantai notifikasi — pengaju tahu
 * berkasnya terkirim, lalu terus dikabari sampai dana cair.
 */
class ReimbursementSubmittedReceipt extends Notification implements ShouldQueue
{
    use DeliversInAppImmediately, Queueable;

    public function __construct(
        public Reimbursement $reimbursement,
        public ?string $approverName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function waitingOn(): string
    {
        return $this->approverName
            ? "persetujuan {$this->approverName} (Manager)"
            : 'persetujuan Manager';
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'reimbursement_submitted_receipt',
            'reimbursement_id' => $this->reimbursement->id,
            'number' => $this->reimbursement->reimbursement_number,
            'title' => $this->reimbursement->title,
            'amount' => $this->reimbursement->amount,
            'url' => '/reimbursements/'.$this->reimbursement->id,
            'message' => "Pengajuan {$this->reimbursement->reimbursement_number} berhasil dikirim dan menunggu {$this->waitingOn()}.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan Anda telah dikirim')
            ->greeting('Halo '.$notifiable->name)
            ->line("Pengajuan {$this->reimbursement->reimbursement_number} berhasil dikirim.")
            ->line('Judul: '.$this->reimbursement->title)
            ->line('Jumlah: '.$this->reimbursement->formatted_amount)
            ->line('Saat ini menunggu '.$this->waitingOn().'. Anda akan dikabari setiap ada perkembangan.')
            ->action('Lihat Pengajuan', url('/reimbursements/'.$this->reimbursement->id));
    }
}
