<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Approval — satu baris riwayat tindakan persetujuan (timeline).
 */
class Approval extends Model
{
    /** @use HasFactory<\Database\Factories\ApprovalFactory> */
    use HasFactory;

    protected $fillable = [
        'reimbursement_id',
        'approver_id',
        'level',
        'action',
        'notes',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => ApprovalLevel::class,
            'action' => ApprovalAction::class,
            'acted_at' => 'datetime',
        ];
    }

    public function reimbursement(): BelongsTo
    {
        return $this->belongsTo(Reimbursement::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
