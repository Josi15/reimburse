<?php

namespace App\Rules;

use App\Models\Project;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Pengaju hanya boleh membebankan biaya ke proyek yang memang menugaskannya.
 *
 * Tanpa aturan ini, siapa pun bisa memilih proyek mana saja dari dropdown dan
 * menggerus anggaran tim lain. Pemegang `project.manage` (Admin/Super Admin)
 * dikecualikan karena memang berwenang lintas proyek.
 */
class AssignedProject implements ValidationRule
{
    /**
     * @param  int|null  $currentProjectId  Proyek yang SUDAH tercatat pada pengajuan
     *                                      ini. Nilai yang tidak berubah selalu
     *                                      lolos, supaya pemilik draft tidak
     *                                      terkunci dari pengajuannya sendiri
     *                                      hanya karena penugasannya dicabut.
     */
    public function __construct(
        private readonly ?User $user,
        private readonly ?int $currentProjectId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $this->user === null) {
            return;
        }

        if ($this->currentProjectId !== null && (int) $value === $this->currentProjectId) {
            return;
        }

        if ($this->user->hasRole('super_admin') || $this->user->hasPermission('project.manage')) {
            return;
        }

        $assigned = Project::query()
            ->whereKey($value)
            ->assignedTo($this->user)
            ->exists();

        if (! $assigned) {
            $fail('Anda belum ditugaskan pada proyek tersebut.');
        }
    }
}
