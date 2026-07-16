<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reimbursement — entitas inti. Status mengikuti state machine (Phase 1/9).
 * Relasi approvals/payment/attachments ditambahkan penuh di Phase 6/9/10/11.
 */
class Reimbursement extends Model
{
    /** @use HasFactory<\Database\Factories\ReimbursementFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reimbursement_number',
        'user_id',
        'department_id',
        'category_id',
        'bank_account_id',
        'title',
        'description',
        'reason',
        'amount',
        'currency',
        'status',
        'expense_date',
        'submitted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expense_date' => 'date',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
