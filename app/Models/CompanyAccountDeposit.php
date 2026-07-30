<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\CompanyAccountDepositFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pemasukan (top-up) ke rekening perusahaan.
 */
class CompanyAccountDeposit extends Model
{
    /** @use HasFactory<CompanyAccountDepositFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_bank_account_id',
        'amount',
        'deposited_at',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'deposited_at' => 'date',
        ];
    }

    public function companyBankAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
