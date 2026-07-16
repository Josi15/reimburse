<?php

namespace App\Observers;

use App\Models\Payment;

/**
 * Mengisi payment_number otomatis: PAY-{tahun}-{urut 6 digit}.
 */
class PaymentObserver
{
    public function creating(Payment $payment): void
    {
        if (empty($payment->payment_number)) {
            $payment->payment_number = $this->nextNumber();
        }
    }

    private function nextNumber(): string
    {
        $prefix = 'PAY-'.date('Y').'-';

        $last = Payment::withTrashed()
            ->where('payment_number', 'like', $prefix.'%')
            ->orderByDesc('payment_number')
            ->value('payment_number');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
