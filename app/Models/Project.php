<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'manager_id',
        'budget',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'budget' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function reimbursements(): HasMany
    {
        return $this->hasMany(Reimbursement::class);
    }

    /** Pemegang proyek (Project Manager) yang bertanggung jawab atas anggaran. */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Anggota proyek: karyawan/magang yang ditugaskan mengerjakannya. Satu
     * orang boleh berada di beberapa proyek sekaligus.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Proyek yang boleh DIPILIH $user saat mengajukan: proyek tempat ia
     * ditugaskan, ditambah proyek yang dipegangnya sebagai Project Manager.
     */
    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->whereHas('members', fn (Builder $m) => $m->whereKey($user->id))
                ->orWhere('projects.manager_id', $user->id);
        });
    }

    /**
     * Proyek yang boleh dilihat anggarannya oleh $user. Pemegang permission
     * `project.budget.viewAny` (Direksi/Admin/Finance/Auditor) melihat semua;
     * selebihnya hanya proyek yang dipegangnya sendiri.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super_admin') || $user->hasPermission('project.budget.viewAny')) {
            return $query;
        }

        return $query->where('projects.manager_id', $user->id);
    }
}
