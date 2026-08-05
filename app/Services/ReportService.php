<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReimbursementStatus;
use App\Models\CompanyBankAccount;
use App\Models\Payment;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Membangun query laporan reimbursement dengan filter lintas-dimensi
 * (tanggal, department, employee, status, kategori, project, kata kunci) serta
 * ringkasan statistik & rekap per-project/per-rekening. Dipakai untuk tampilan
 * laporan & export (Phase 14).
 */
class ReportService
{
    /**
     * Penonton laporan. Semua rekap berbasis reimbursement disaring ke
     * cakupannya (Admin/Manager/Supervisor: departemen sendiri; Finance/
     * Direksi/Auditor: seluruh perusahaan) — lihat Reimbursement::visibleTo.
     */
    private ?User $viewer = null;

    /** Tetapkan penonton laporan; dipanggil sekali di controller. */
    public function forUser(User $user): static
    {
        $this->viewer = $user;

        return $this;
    }

    /** Terapkan filter ke query dasar (kolom di-qualify agar aman untuk join). */
    private function apply(array $f): Builder
    {
        $query = Reimbursement::query();

        if ($this->viewer) {
            $query->visibleTo($this->viewer);
        }

        if (! empty($f['date_from'])) {
            $query->whereDate('reimbursements.created_at', '>=', $f['date_from']);
        }
        if (! empty($f['date_to'])) {
            $query->whereDate('reimbursements.created_at', '<=', $f['date_to']);
        }
        if (! empty($f['department_id'])) {
            $query->where('reimbursements.department_id', $f['department_id']);
        }
        if (! empty($f['user_id'])) {
            $query->where('reimbursements.user_id', $f['user_id']);
        }
        if (! empty($f['status'])) {
            $query->where('reimbursements.status', $f['status']);
        }
        if (! empty($f['category_id'])) {
            $query->where('reimbursements.category_id', $f['category_id']);
        }
        if (! empty($f['project_id'])) {
            $query->where('reimbursements.project_id', $f['project_id']);
        }
        if (! empty($f['q'])) {
            $query->where(function (Builder $sub) use ($f) {
                $sub->where('reimbursements.reimbursement_number', 'ilike', '%'.$f['q'].'%')
                    ->orWhere('reimbursements.title', 'ilike', '%'.$f['q'].'%');
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

    /**
     * Rekap pengeluaran per proyek (menghormati filter yang sama). Menampilkan
     * jumlah pengajuan, total nominal diajukan, total yang sudah dibayar, dan
     * anggaran proyek untuk perbandingan realisasi.
     */
    public function projectRecap(array $f): array
    {
        return $this->apply($f)
            ->whereNotNull('reimbursements.project_id')
            ->join('projects', 'projects.id', '=', 'reimbursements.project_id')
            ->selectRaw(
                'projects.id as pid, projects.code, projects.name, projects.budget, '.
                'COUNT(*) as c, '.
                'COALESCE(SUM(reimbursements.amount),0) as total, '.
                'COALESCE(SUM(CASE WHEN reimbursements.status = ? THEN reimbursements.amount ELSE 0 END),0) as paid',
                [ReimbursementStatus::Paid->value],
            )
            ->groupBy('projects.id', 'projects.code', 'projects.name', 'projects.budget')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'project_id' => (int) $r->pid,
                'code' => $r->code,
                'name' => $r->name,
                'budget' => $r->budget !== null ? (int) $r->budget : null,
                'count' => (int) $r->c,
                'total_amount' => (int) $r->total,
                'paid_amount' => (int) $r->paid,
            ])
            ->all();
    }

    /**
     * Rekap pengeluaran per departemen (menghormati filter yang sama).
     * Dipakai Finance untuk melihat unit mana yang paling banyak mengajukan dan
     * berapa yang benar-benar sudah dicairkan.
     */
    public function departmentRecap(array $f): array
    {
        return $this->apply($f)
            ->join('departments', 'departments.id', '=', 'reimbursements.department_id')
            ->selectRaw(
                'departments.id as did, departments.code, departments.name, '.
                'COUNT(*) as c, '.
                'COALESCE(SUM(reimbursements.amount),0) as total, '.
                'COALESCE(SUM(CASE WHEN reimbursements.status = ? THEN reimbursements.amount ELSE 0 END),0) as paid, '.
                'COALESCE(SUM(CASE WHEN reimbursements.status IN (?,?,?,?) THEN reimbursements.amount ELSE 0 END),0) as pending',
                [
                    ReimbursementStatus::Paid->value,
                    ReimbursementStatus::Submitted->value,
                    ReimbursementStatus::ManagerApproved->value,
                    ReimbursementStatus::FinanceApproved->value,
                    ReimbursementStatus::RevisionRequested->value,
                ],
            )
            ->groupBy('departments.id', 'departments.code', 'departments.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'department_id' => (int) $r->did,
                'code' => $r->code,
                'name' => $r->name,
                'count' => (int) $r->c,
                'total_amount' => (int) $r->total,
                'paid_amount' => (int) $r->paid,
                'pending_amount' => (int) $r->pending,
            ])
            ->all();
    }

    /**
     * Rekap pembayaran per rekening perusahaan (sumber). Memakai rentang tanggal
     * dari filter (paid_at) sehingga bisa dipakai untuk rekap bulanan.
     */
    public function companyAccountRecap(array $f): array
    {
        $query = Payment::query()->where('payments.status', PaymentStatus::Paid->value);

        if (! empty($f['date_from'])) {
            $query->whereDate('payments.paid_at', '>=', $f['date_from']);
        }
        if (! empty($f['date_to'])) {
            $query->whereDate('payments.paid_at', '<=', $f['date_to']);
        }

        return $query
            ->leftJoin('company_bank_accounts', 'company_bank_accounts.id', '=', 'payments.source_account_id')
            ->selectRaw(
                'payments.source_account_id as sid, company_bank_accounts.label, '.
                'COUNT(*) as c, COALESCE(SUM(payments.amount),0) as total',
            )
            ->groupBy('payments.source_account_id', 'company_bank_accounts.label')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'source_account_id' => $r->sid !== null ? (int) $r->sid : null,
                'label' => $r->label ?? 'Tanpa rekening sumber',
                'count' => (int) $r->c,
                'total_amount' => (int) $r->total,
            ])
            ->all();
    }

    /**
     * Buku kas rekening perusahaan untuk sebuah periode (default: bulan berjalan).
     * Per rekening: saldo awal (opening_balance + mutasi sebelum periode),
     * pemasukan (top-up), pengeluaran (pembayaran keluar), saldo akhir.
     */
    public function cashflow(array $f): array
    {
        $start = ! empty($f['date_from']) ? Carbon::parse($f['date_from'])->startOfDay() : now()->startOfMonth();
        $end = ! empty($f['date_to']) ? Carbon::parse($f['date_to'])->endOfDay() : now()->endOfMonth();

        $rows = CompanyBankAccount::query()->with('bank:id,code')->orderBy('label')->get()
            ->map(function (CompanyBankAccount $acc) use ($start, $end) {
                $depIn = (int) $acc->deposits()->whereBetween('deposited_at', [$start, $end])->sum('amount');
                $depBefore = (int) $acc->deposits()->where('deposited_at', '<', $start)->sum('amount');

                $paid = fn () => $acc->payments()->where('status', PaymentStatus::Paid->value);
                $payIn = (int) $paid()->whereBetween('paid_at', [$start, $end])->sum('amount');
                $payBefore = (int) $paid()->where('paid_at', '<', $start)->sum('amount');

                $opening = (int) $acc->opening_balance + $depBefore - $payBefore;

                return [
                    'account_id' => $acc->id,
                    'label' => $acc->label,
                    'bank_code' => $acc->bank?->code,
                    'masked_number' => $acc->masked_number,
                    'opening_balance' => $opening,
                    'pemasukan' => $depIn,
                    'pengeluaran' => $payIn,
                    'ending_balance' => $opening + $depIn - $payIn,
                ];
            });

        return [
            'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'accounts' => $rows->values()->all(),
            'totals' => [
                'opening_balance' => (int) $rows->sum('opening_balance'),
                'pemasukan' => (int) $rows->sum('pemasukan'),
                'pengeluaran' => (int) $rows->sum('pengeluaran'),
                'ending_balance' => (int) $rows->sum('ending_balance'),
            ],
        ];
    }
}
