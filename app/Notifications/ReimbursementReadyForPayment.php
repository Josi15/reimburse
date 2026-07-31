<?php

namespace App\Notifications;

use App\Models\Reimbursement;
use App\Notifications\Concerns\DeliversInAppImmediately;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke petugas pembayaran (pemegang permission payment.process) setelah
 * Finance menyetujui: pengajuan siap dibayarkan. Tanpa ini, mata rantai
 * terakhir sebelum dana cair tidak punya pemicu.
 */
class ReimbursementReadyForPayment extends Notification implements ShouldQueue
{
    use DeliversInAppImmediately, Queueable;

    public function __construct(public Reimbursement $reimbursement) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'reimbursement_ready_for_payment',
            'reimbursement_id' => $this->reimbursement->id,
            'number' => $this->reimbursement->reimbursement_number,
            'title' => $this->reimbursement->title,
            'amount' => $this->reimbursement->amount,
            'url' => '/reimbursements/'.$this->reimbursement->id,
            'message' => "Pengajuan {$this->reimbursement->reimbursement_number} telah disetujui Finance dan siap dibayarkan.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reimbursement siap dibayarkan')
            ->greeting('Halo '.$notifiable->name)
            ->line("Pengajuan {$this->reimbursement->reimbursement_number} sudah disetujui Finance dan menunggu pembayaran.")
            ->line('Pengaju: '.($this->reimbursement->user?->name ?? '-'))
            ->line('Jumlah: '.$this->reimbursement->formatted_amount)
            ->action('Proses Pembayaran', url('/reimbursements/'.$this->reimbursement->id));
    }
}
