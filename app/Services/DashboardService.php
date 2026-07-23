<?php

namespace App\Services;

use App\Enums\ReimbursementStatus;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Data dashboard per-role. Employee melihat miliknya sendiri; Manager/Finance
 * melihat antrean yang menunggu aksinya; Admin/Auditor/Super Admin melihat
 * keseluruhan (termasuk top category/department & grafik bulanan).
 */
class DashboardService
{
    public function for(User $user): array
    {
        $seesAll = $user->hasPermission('reimbursement.viewAny') || $user->hasRole('super_admin');

        return [
            'scope' => $seesAll ? 'global' : 'personal',
            'cards' => $this->cards($user, $seesAll),
            'pending' => $this->pending($user),
            'recent' => $this->recent($user, $seesAll),
            'monthly_expense' => $this->monthlyExpense($user, $seesAll),
            'top_categories' => $this->topCategories($user, $seesAll),
            'top_departments' => $seesAll ? $this->topDepartments() : [],
        ];
    }

    /** Query dasar sesuai cakupan role. */
    private function base(User $user, bool $seesAll): Builder
    {
        $query = Reimbursement::query();

        return $seesAll ? $query : $query->where('user_id', $user->id);
    }

    private function cards(User $user, bool $seesAll): array
    {
        $counts = (clone $this->base($user, $seesAll))
            ->selectRaw('status, COUNT(*) as c, COALESCE(SUM(amount),0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (ReimbursementStatus $s) => (int) ($counts[$s->value]->c ?? 0);

        return [
            'total' => (int) $counts->sum('c'),
            'draft' => $count(ReimbursementStatus::Draft),
            'submitted' => $count(ReimbursementStatus::Submitted),
            'approved' => $count(ReimbursementStatus::FinanceApproved),
            'rejected' => $count(ReimbursementStatus::ManagerRejected) + $count(ReimbursementStatus::FinanceRejected),
            'paid' => $count(ReimbursementStatus::Paid),
            'total_paid_amount' => (int) ($counts[ReimbursementStatus::Paid->value]->total ?? 0),
        ];
    }

    /** Antrean yang menunggu aksi user (berdasarkan role). */
    private function pending(User $user): array
    {
        $result = [];

        if ($user->hasPermission('reimbursement.approve.manager') || $user->hasRole('super_admin')) {
            $result['manager_approval'] = Reimbursement::where('status', ReimbursementStatus::Submitted->value)->count();
        }

        if ($user->hasPermission('reimbursement.approve.finance') || $user->hasRole('super_admin')) {
            $result['finance_approval'] = Reimbursement::where('status', ReimbursementStatus::ManagerApproved->value)->count();
        }

        if ($user->hasPermission('payment.process') || $user->hasRole('super_admin')) {
            $result['awaiting_payment'] = Reimbursement::where('status', ReimbursementStatus::FinanceApproved->value)->count();
        }

        // Employee: pengajuan sendiri yang butuh tindak lanjut (revisi).
        $result['my_revision_requested'] = Reimbursement::where('user_id', $user->id)
            ->where('status', ReimbursementStatus::RevisionRequested->value)->count();

        return $result;
    }

    private function recent(User $user, bool $seesAll): array
    {
        return (clone $this->base($user, $seesAll))
            ->with(['user:id,name', 'category:id,name'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Reimbursement $r) => [
                'id' => $r->id,
                'reimbursement_number' => $r->reimbursement_number,
                'title' => $r->title,
                'amount' => $r->amount,
                // Bentuk status seragam dengan endpoint lain: { value, label, color }.
                'status' => [
                    'value' => $r->status->value,
                    'label' => $r->status->label(),
                    'color' => $r->status->color(),
                ],
                'user' => $r->user?->name,
                'category' => $r->category?->name,
                'created_at' => $r->created_at,
            ])
            ->all();
    }

    /** Total pembayaran per bulan (tahun berjalan). Selalu 12 bucket. */
    private function monthlyExpense(User $user, bool $seesAll): array
    {
        $year = (int) now()->year;

        $rows = (clone $this->base($user, $seesAll))
            ->where('status', ReimbursementStatus::Paid->value)
            ->whereYear('completed_at', $year)
            ->selectRaw('EXTRACT(MONTH FROM completed_at)::int as m, COALESCE(SUM(amount),0) as total')
            ->groupByRaw('EXTRACT(MONTH FROM completed_at)')
            ->pluck('total', 'm');

        return collect(range(1, 12))->map(fn ($m) => [
            'month' => $m,
            'total' => (int) ($rows[$m] ?? 0),
        ])->all();
    }

    private function topCategories(User $user, bool $seesAll): array
    {
        return (clone $this->base($user, $seesAll))
            ->join('categories', 'categories.id', '=', 'reimbursements.category_id')
            ->selectRaw('categories.name as name, COUNT(*) as count, COALESCE(SUM(reimbursements.amount),0) as total')
            ->groupBy('categories.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count, 'total' => (int) $r->total])
            ->all();
    }

    private function topDepartments(): array
    {
        return Reimbursement::query()
            ->join('departments', 'departments.id', '=', 'reimbursements.department_id')
            ->selectRaw('departments.name as name, COUNT(*) as count, COALESCE(SUM(reimbursements.amount),0) as total')
            ->groupBy('departments.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count, 'total' => (int) $r->total])
            ->all();
    }
}
