<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\Reimbursement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke pemilik pengajuan ketika pembayaran berhasil (status Paid).
 */
class ReimbursementPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reimbursement $reimbursement,
        public Payment $payment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'reimbursement_paid',
            'reimbursement_id' => $this->reimbursement->id,
            'number' => $this->reimbursement->reimbursement_number,
            'payment_number' => $this->payment->payment_number,
            'amount' => $this->payment->amount,
            'message' => "Pengajuan {$this->reimbursement->reimbursement_number} telah dibayar ({$this->payment->payment_number}).",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reimbursement Anda telah dibayar')
            ->greeting('Halo '.$notifiable->name)
            ->line("Pengajuan {$this->reimbursement->reimbursement_number} telah dibayar.")
            ->line('Nomor pembayaran: '.$this->payment->payment_number)
            ->line('Jumlah: '.$this->payment->formatted_amount)
            ->action('Lihat Detail', url('/reimbursements/'.$this->reimbursement->id));
    }
}
