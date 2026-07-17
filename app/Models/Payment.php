<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Observers\PaymentObserver;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Payment — pembayaran atas reimbursement (Phase 11).
 * PaymentObserver mengisi payment_number otomatis saat dibuat.
 */
#[ObservedBy([PaymentObserver::class])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_number',
        'reimbursement_id',
        'bank_account_id',
        'processed_by',
        'amount',
        'currency',
        'method',
        'status',
        'reference_number',
        'notes',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    // ---- Relationships ---------------------------------------------------

    public function reimbursement(): BelongsTo
    {
        return $this->belongsTo(Reimbursement::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
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

    // ---- Scopes ----------------------------------------------------------

    /** Pembayaran aktif (bukan failed/cancelled) — cek anti double-pay. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [PaymentStatus::Failed->value, PaymentStatus::Cancelled->value]);
    }
}
