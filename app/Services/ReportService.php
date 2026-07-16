<?php

namespace App\Services;

use App\Models\Reimbursement;
use Illuminate\Database\Eloquent\Builder;

/**
 * Membangun query laporan reimbursement dengan filter lintas-dimensi
 * (tanggal, department, employee, status, kategori, kata kunci) serta ringkasan
 * statistik. Dipakai untuk tampilan laporan & export (Phase 14).
 */
class ReportService
{
    /** Terapkan filter ke query dasar. */
    private function apply(array $f): Builder
    {
        $query = Reimbursement::query();

        if (! empty($f['date_from'])) {
            $query->whereDate('created_at', '>=', $f['date_from']);
        }
        if (! empty($f['date_to'])) {
            $query->whereDate('created_at', '<=', $f['date_to']);
        }
        if (! empty($f['department_id'])) {
            $query->where('department_id', $f['department_id']);
        }
        if (! empty($f['user_id'])) {
            $query->where('user_id', $f['user_id']);
        }
        if (! empty($f['status'])) {
            $query->where('status', $f['status']);
        }
        if (! empty($f['category_id'])) {
            $query->where('category_id', $f['category_id']);
        }
        if (! empty($f['q'])) {
            $query->where(function (Builder $sub) use ($f) {
                $sub->where('reimbursement_number', 'ilike', '%'.$f['q'].'%')
                    ->orWhere('title', 'ilike', '%'.$f['q'].'%');
            });
        }

        return $query;
    }

    /** Query siap-tampil/ekspor dengan relasi termuat. */
    public function list(array $f): Builder
    {
        return $this->apply($f)
            ->with(['user:id,name', 'category:id,name', 'department:id,name'])
            ->latest();
    }

    /** Ringkasan statistik untuk filter yang sama. */
    public function summary(array $f): array
    {
        $rows = $this->apply($f)
            ->selectRaw('status, COUNT(*) as c, COALESCE(SUM(amount),0) as total')
            ->groupBy('status')
            ->get();

        return [
            'count' => (int) $rows->sum('c'),
            'total_amount' => (int) $rows->sum('total'),
            'by_status' => $rows->mapWithKeys(fn ($r) => [
                $r->status->value => ['count' => (int) $r->c, 'total' => (int) $r->total],
            ])->all(),
        ];
    }
}
