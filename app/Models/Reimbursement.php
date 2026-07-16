<?php

namespace App\Models;

use App\Enums\ReimbursementStatus;
use App\Observers\ReimbursementObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reimbursement — entitas inti. Status mengikuti state machine (Phase 1/9),
 * dicast ke enum ReimbursementStatus. Nomor diisi ReimbursementObserver.
 */
#[ObservedBy([ReimbursementObserver::class])]
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
            'status' => ReimbursementStatus::class,
            'expense_date' => 'date',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ---- Relationships ---------------------------------------------------

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

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Pembayaran aktif terkini (bukan failed/cancelled). */
    public function activePayment(): HasOne
    {
        return $this->hasOne(Payment::class)->active()->latestOfMany();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ---- Accessors -------------------------------------------------------

    /** "Rp 1.500.000" */
    protected function formattedAmount(): Attribute
    {
        return Attribute::get(fn () => 'Rp '.number_format((int) $this->amount, 0, ',', '.'));
    }

    // ---- State machine helpers ------------------------------------------

    public function canTransitionTo(ReimbursementStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            ReimbursementStatus::Draft,
            ReimbursementStatus::RevisionRequested,
        ], true);
    }

    // ---- Scopes ----------------------------------------------------------

    public function scopeStatus(Builder $query, ReimbursementStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof ReimbursementStatus ? $status->value : $status);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** Menunggu tindakan Finance (siap dibayar). */
    public function scopeAwaitingPayment(Builder $query): Builder
    {
        return $query->where('status', ReimbursementStatus::FinanceApproved->value);
    }
}
