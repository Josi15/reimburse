<?php

namespace App\Observers;

use App\Models\Payment;
use App\Support\SequentialNumber;

/**
 * Mengisi payment_number otomatis: PAY-{tahun}-{urut 6 digit}.
 */
class PaymentObserver
{
    public function creating(Payment $payment): void
    {
        if (empty($payment->payment_number)) {
            $payment->payment_number = SequentialNumber::next(
                Payment::class, 'payment_number', 'PAY-'.date('Y').'-',
            );
        }
    }
}
