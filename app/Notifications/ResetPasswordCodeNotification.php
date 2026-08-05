<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email berisi kode reset password 6 digit.
 *
 * Sengaja TIDAK memakai ShouldQueue. Reset password adalah alur yang ditunggu
 * pengguna di depan layar; kalau diantre, email baru terkirim ketika queue
 * worker jalan — dan di lingkungan dev worker sering tidak dihidupkan.
 */
class ResetPasswordCodeNotification extends Notification
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $code,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Kode Reset Password '.config('app.name').': '.$this->code)
            ->greeting('Halo, '.($notifiable->name ?? '').'!')
            ->line('Masukkan kode ini di halaman reset password '.config('app.name').':')
            ->line('**'.$this->code.'**')
            ->line("Kode berlaku {$minutes} menit dan hanya bisa dipakai sekali.")
            // Diletakkan di email, bukan cuma di layar: pengguna yang tidak
            // merasa meminta kode ini adalah tanda seseorang sedang mencoba
            // masuk ke akunnya, dan hanya lewat email inilah mereka tahu.
            ->line('Kalau Anda tidak merasa meminta ini, abaikan email ini dan jangan berikan kodenya kepada siapa pun — termasuk yang mengaku dari tim kami.')
            ->salutation('Salam, Tim '.config('app.name'));
    }
}
