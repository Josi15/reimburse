<?php

namespace App\Enums;

/**
 * Status pembayaran (Phase 11). Backing value cocok dengan CHECK constraint.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Processing => 'Diproses',
            self::Paid => 'Dibayar',
            self::Failed => 'Gagal',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Processing => 'blue',
            self::Paid => 'green',
            self::Failed => 'red',
            self::Cancelled => 'amber',
        };
    }

    /** Status yang dianggap "aktif" (memblokir pembayaran ganda). */
    public function isActive(): bool
    {
        return ! in_array($this, [self::Failed, self::Cancelled], true);
    }
}
