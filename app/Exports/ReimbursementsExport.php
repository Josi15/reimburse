<?php

namespace App\Exports;

use App\Models\Reimbursement;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export laporan reimbursement ke Excel/CSV (Phase 14).
 * Memakai query terfilter dari ReportService.
 */
class ReimbursementsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private readonly array $filters,
        private readonly ReportService $service,
    ) {}

    public function query()
    {
        return $this->service->list($this->filters);
    }

    public function headings(): array
    {
        return ['Nomor', 'Judul', 'Pengaju', 'Department', 'Kategori', 'Nominal', 'Status', 'Tanggal'];
    }

    /** @param  Reimbursement  $row */
    public function map($row): array
    {
        return [
            $row->reimbursement_number,
            $row->title,
            $row->user?->name,
            $row->department?->name,
            $row->category?->name,
            $row->amount,
            $row->status->label(),
            $row->created_at?->format('Y-m-d H:i'),
        ];
    }
}
