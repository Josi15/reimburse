<?php

namespace App\Enums;

enum ApprovalAction: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case RevisionRequested = 'revision_requested';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::RevisionRequested => 'Minta Revisi',
        };
    }

    /** Aksi yang mewajibkan pengisian catatan/alasan. */
    public function requiresNotes(): bool
    {
        return in_array($this, [self::Rejected, self::RevisionRequested], true);
    }
}
