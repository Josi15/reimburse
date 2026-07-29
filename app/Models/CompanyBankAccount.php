<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\CompanyBankAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rekening perusahaan (sumber pembayaran reimbursement oleh Finance).
 */
class CompanyBankAccount extends Model
{
    /** @use HasFactory<CompanyBankAccountFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'bank_id',
        'label',
        'account_number',
        'account_holder_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'source_account_id');
    }

    /** Nomor rekening tersamar untuk tampilan, mis. "******7890". */
    protected function maskedNumber(): Attribute
    {
        return Attribute::get(function () {
            $number = (string) $this->account_number;
            $visible = substr($number, -4);

            return str_repeat('*', max(0, strlen($number) - 4)).$visible;
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
