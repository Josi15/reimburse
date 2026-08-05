<?php

namespace App\Services;

use App\Enums\ReimbursementStatus;
use App\Models\Project;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung serapan anggaran proyek: berapa yang sudah dibayar, berapa yang
 * masih tertahan di alur persetujuan (komitmen), dan berapa SISA dana yang
 * masih boleh dipakai. Dipakai Project Manager (pemegang proyek) lewat
 * /api/project-budgets, serta Direksi/Admin/Finance/Auditor untuk semua proyek.
 *
 * Kesepakatan perhitungan:
 * - realisasi (paid)   : klaim berstatus paid — uang sudah keluar.
 * - komitmen (pending) : klaim yang masih berjalan (submitted s.d. disetujui
 *                        Direksi, termasuk yang diminta revisi) — belum keluar
 *                        tapi sudah "memesan" anggaran.
 * - terpakai           : realisasi + komitmen.
 * - sisa               : anggaran - terpakai (null bila proyek tak dianggarkan).
 * Draft & klaim yang ditolak TIDAK mengurangi anggaran.
 */
class ProjectBudgetService
{
    /** Status yang uangnya sudah benar-benar keluar. */
    private const PAID = [ReimbursementStatus::Paid];

    /** Status yang masih berjalan — menahan anggaran (komitmen). */
    private const PENDING = [
        ReimbursementStatus::Submitted,
        ReimbursementStatus::ManagerApproved,
        ReimbursementStatus::FinanceApproved,
        ReimbursementStatus::DirectorApproved,
        ReimbursementStatus::RevisionRequested,
    ];

    /** Status yang tidak membebani anggaran. */
    private const REJECTED = [
        ReimbursementStatus::ManagerRejected,
        ReimbursementStatus::FinanceRejected,
        ReimbursementStatus::DirectorRejected,
    ];

    /**
     * Ringkasan anggaran semua proyek yang boleh dilihat $user.
     *
     * @param  array{q?: string, is_active?: bool|string}  $filters
     * @return array{data: array<int, array<string, mixed>>, totals: array<string, int|null>}
     */
    public function summaryFor(User $user, array $filters = []): array
    {
        $query = Project::query()->visibleTo($user)->with('manager:id,name')->withCount('members');

        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('code', 'ilike', '%'.$q.'%')
                    ->orWhere('name', 'ilike', '%'.$q.'%');
            });
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $projects = $query->orderBy('name')->get();
        $stats = $this->statsByProject($projects->pluck('id')->all());

        $rows = $projects->map(fn (Project $p) => $this->row($p, $stats[$p->id] ?? []))->values()->all();

        return [
            'data' => $rows,
            'totals' => [
                'project_count' => count($rows),
                'budget' => $this->sumNullable($rows, 'budget'),
                'paid_amount' => (int) array_sum(array_column($rows, 'paid_amount')),
                'pending_amount' => (int) array_sum(array_column($rows, 'pending_amount')),
                'used_amount' => (int) array_sum(array_column($rows, 'used_amount')),
                'remaining_amount' => $this->sumNullable($rows, 'remaining_amount'),
            ],
        ];
    }

    /**
     * Detail satu proyek: ringkasan yang sama + rincian per status + klaim
     * terbaru yang membebani anggarannya.
     *
     * @return array<string, mixed>
     */
    public function detail(Project $project, int $recentLimit = 10): array
    {
        $project->loadMissing('manager:id,name', 'members:id,name,email,department_id', 'members.department:id,name');
        $stats = $this->statsByProject([$project->id])[$project->id] ?? [];

        $recent = Reimbursement::query()
            ->where('project_id', $project->id)
            ->with(['user:id,name', 'category:id,name'])
            ->latest()
            ->limit($recentLimit)
            ->get()
            ->map(fn (Reimbursement $r) => [
                'id' => $r->id,
                'number' => $r->reimbursement_number,
                'title' => $r->title,
                'user' => $r->user?->name,
                'category' => $r->category?->name,
                'amount' => (int) $r->amount,
                'formatted_amount' => $r->formatted_amount,
                'status' => [
                    'value' => $r->status->value,
                    'label' => $r->status->label(),
                    'color' => $r->status->color(),
                ],
                'created_at' => $r->created_at,
            ])
            ->all();

        return $this->row($project, $stats) + [
            'description' => $project->description,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'by_status' => $this->byStatus($stats),
            'recent_reimbursements' => $recent,
            // Anggota yang ditugaskan — merekalah yang boleh membebankan
            // pengajuan ke proyek ini (lihat App\Rules\AssignedProject).
            'members' => $project->members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'department' => $m->department?->name,
            ])->values()->all(),
        ];
    }

    /**
     * Agregasi (count, sum) reimbursement per proyek & status dalam satu query.
     * Memakai query builder (bukan Eloquent) karena yang dibutuhkan hanya
     * angka — tak perlu menghidrasi model. Soft delete disaring manual.
     *
     * @param  array<int, int>  $projectIds
     * @return array<int, array<string, array{count: int, total: int}>>
     */
    private function statsByProject(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('reimbursements')
            ->whereIn('project_id', $projectIds)
            ->whereNull('deleted_at')
            ->selectRaw('project_id, status, COUNT(*) as c, COALESCE(SUM(amount),0) as total')
            ->groupBy('project_id', 'status')
            ->get();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row->project_id][(string) $row->status] = [
                'count' => (int) $row->c,
                'total' => (int) $row->total,
            ];
        }

        return $stats;
    }

    /**
     * Satu baris ringkasan proyek.
     *
     * @param  array<string, array{count: int, total: int}>  $stats  agregasi per status
     * @return array<string, mixed>
     */
    private function row(Project $project, array $stats): array
    {
        $paid = $this->sumOf($stats, self::PAID);
        $pending = $this->sumOf($stats, self::PENDING);
        $draft = $this->sumOf($stats, [ReimbursementStatus::Draft]);
        $rejected = $this->sumOf($stats, self::REJECTED);

        $budget = $project->budget !== null ? (int) $project->budget : null;
        $used = $paid + $pending;
        $remaining = $budget !== null ? $budget - $used : null;

        return [
            'id' => $project->id,
            'code' => $project->code,
            'name' => $project->name,
            'is_active' => (bool) $project->is_active,
            'manager' => $project->manager ? [
                'id' => $project->manager->id,
                'name' => $project->manager->name,
            ] : null,
            'budget' => $budget,
            'paid_amount' => $paid,
            'pending_amount' => $pending,
            'draft_amount' => $draft,
            'rejected_amount' => $rejected,
            'used_amount' => $used,
            'remaining_amount' => $remaining,
            // Persentase serapan; null bila proyek tak dianggarkan.
            'usage_percent' => $budget !== null && $budget > 0
                ? round($used / $budget * 100, 1)
                : null,
            'is_over_budget' => $remaining !== null && $remaining < 0,
            'reimbursement_count' => (int) array_sum(array_column($stats, 'count')),
            'member_count' => $project->members_count
                ?? ($project->relationLoaded('members') ? $project->members->count() : null),
        ];
    }

    /**
     * Rincian per status (hanya status yang punya data), sudah berlabel UI.
     *
     * @param  array<string, array{count: int, total: int}>  $stats
     * @return array<int, array<string, mixed>>
     */
    private function byStatus(array $stats): array
    {
        return collect(ReimbursementStatus::cases())
            ->filter(fn (ReimbursementStatus $s) => isset($stats[$s->value]))
            ->map(fn (ReimbursementStatus $s) => [
                'status' => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()],
                'count' => $stats[$s->value]['count'],
                'total_amount' => $stats[$s->value]['total'],
            ])
            ->values()
            ->all();
    }

    /**
     * Jumlahkan nominal untuk sekumpulan status.
     *
     * @param  array<string, array{count: int, total: int}>  $stats
     * @param  array<int, ReimbursementStatus>  $statuses
     */
    private function sumOf(array $stats, array $statuses): int
    {
        return (int) collect($statuses)->sum(fn (ReimbursementStatus $s) => $stats[$s->value]['total'] ?? 0);
    }

    /**
     * Total kolom yang boleh null (proyek tanpa anggaran diabaikan; hasil null
     * bila TIDAK ADA proyek yang dianggarkan sama sekali).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function sumNullable(array $rows, string $key): ?int
    {
        $values = array_filter(array_column($rows, $key), fn ($v) => $v !== null);

        return $values === [] ? null : (int) array_sum($values);
    }
}
