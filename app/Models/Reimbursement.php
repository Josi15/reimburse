<?php

namespace App\Models;

use App\Enums\ApprovalLevel;
use App\Enums\ClaimType;
use App\Enums\ReimbursementStatus;
use App\Models\Concerns\Auditable;
use App\Observers\ReimbursementObserver;
use Database\Factories\ReimbursementFactory;
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
    /** @use HasFactory<ReimbursementFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'reimbursement_number',
        'claim_type',
        'details',
        'user_id',
        'department_id',
        'category_id',
        'project_id',
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
            'claim_type' => ClaimType::class,
            'details' => 'array',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    /** Detail spesifik jenis pengajuan, siap tampil (label → nilai). */
    protected function displayDetails(): Attribute
    {
        return Attribute::get(function () {
            $details = $this->details;

            return ($this->claim_type ?? ClaimType::Expense)
                ->displayDetails(is_array($details) ? $details : null);
        });
    }

    // ---- State machine helpers ------------------------------------------

    public function canTransitionTo(ReimbursementStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    /**
     * Pengajuan ini wajib lewat Direksi? Ditentukan nominal terhadap ambang
     * di config/reimbursement.php (null = tahap Direksi dimatikan).
     */
    public function needsDirectorApproval(): bool
    {
        $threshold = config('reimbursement.director_approval_threshold');

        return $threshold !== null && (int) $this->amount > (int) $threshold;
    }

    /**
     * Level approval yang SEDANG menunggu tindakan, sudah memperhitungkan
     * ambang Direksi. Ini sumber tunggal "siapa yang harus bertindak sekarang"
     * — dipakai policy, service, controller, dan notifier.
     */
    public function pendingApprovalLevel(): ?ApprovalLevel
    {
        $level = $this->status->approvalLevel();

        // Status FinanceApproved memetakan ke Direksi, tapi hanya berlaku bila
        // nominalnya memang di atas ambang; kalau tidak, tak ada yang ditunggu.
        if ($level === ApprovalLevel::Director && ! $this->needsDirectorApproval()) {
            return null;
        }

        return $level;
    }

    /** Seluruh persetujuan yang diperlukan sudah lengkap → boleh dicairkan. */
    public function isReadyForPayment(): bool
    {
        return match ($this->status) {
            ReimbursementStatus::DirectorApproved => true,
            ReimbursementStatus::FinanceApproved => ! $this->needsDirectorApproval(),
            default => false,
        };
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

    public function scopeClaimType(Builder $query, ClaimType|string $type): Builder
    {
        return $query->where('claim_type', $type instanceof ClaimType ? $type->value : $type);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Cakupan data yang boleh dilihat $user — sumber tunggal aturan "siapa
     * melihat apa", dipakai daftar pengajuan, antrean persetujuan, dashboard,
     * dan laporan agar semuanya konsisten.
     *
     * Tiga tingkat:
     * 1. Lintas departemen (Super Admin, Direksi, Finance, Auditor) — semua.
     * 2. Satu departemen (Admin, Manager, Supervisor) — pengajuan unitnya
     *    sendiri, ditambah miliknya sendiri bila ia belum punya departemen.
     * 3. Pribadi (Employee, Magang, Project Manager) — hanya miliknya.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->seesAllDepartments()) {
            return $query;
        }

        if (! $user->hasPermission('reimbursement.viewAny')) {
            return $query->where('reimbursements.user_id', $user->id);
        }

        // Atasan tanpa departemen tidak bisa "mengklaim" seluruh perusahaan;
        // ia hanya melihat pengajuannya sendiri sampai departemennya diisi.
        if (! $user->department_id) {
            return $query->where('reimbursements.user_id', $user->id);
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('reimbursements.department_id', $user->department_id)
                ->orWhere('reimbursements.user_id', $user->id);
        });
    }

    /**
     * Siap dibayar: sudah lolos Direksi, atau lolos Finance dengan nominal di
     * bawah ambang sehingga tak perlu Direksi. Padanan SQL dari
     * isReadyForPayment().
     */
    public function scopeAwaitingPayment(Builder $query): Builder
    {
        $threshold = config('reimbursement.director_approval_threshold');

        return $query->where(function (Builder $q) use ($threshold) {
            $q->where('status', ReimbursementStatus::DirectorApproved->value)
                ->orWhere(function (Builder $sub) use ($threshold) {
                    $sub->where('status', ReimbursementStatus::FinanceApproved->value);

                    if ($threshold !== null) {
                        $sub->where('amount', '<=', (int) $threshold);
                    }
                });
        });
    }

    /** Menunggu persetujuan Direksi (nominal di atas ambang). */
    public function scopeAwaitingDirector(Builder $query): Builder
    {
        $threshold = config('reimbursement.director_approval_threshold');

        $query->where('status', ReimbursementStatus::FinanceApproved->value);

        return $threshold === null
            ? $query->whereRaw('1 = 0')          // tahap Direksi dimatikan
            : $query->where('amount', '>', (int) $threshold);
    }
}
