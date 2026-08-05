<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Email "lupa password" versi FundBack: berbahasa Indonesia dan menyebut
 * masa berlaku tautan secara eksplisit.
 *
 * Sengaja TIDAK memakai ShouldQueue. Reset password adalah alur yang ditunggu
 * pengguna di depan layar; kalau diantre, email baru terkirim ketika queue
 * worker jalan — dan di lingkungan dev worker sering tidak dihidupkan.
 */
class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Atur Ulang Password '.config('app.name'))
            ->greeting('Halo, '.($notifiable->name ?? '').'!')
            ->line('Kami menerima permintaan untuk mengatur ulang password akun '.config('app.name').' Anda.')
            ->action('Atur Password Baru', $url)
            ->line("Tautan ini berlaku {$minutes} menit dan hanya bisa dipakai sekali.")
            ->line('Kalau Anda tidak merasa meminta ini, abaikan saja email ini — password Anda tidak berubah.')
            ->salutation('Salam, Tim '.config('app.name'));
    }
}
