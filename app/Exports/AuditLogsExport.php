<?php

namespace App\Exports;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export activity log ke Excel/CSV (Phase 15), memakai query yang sudah
 * difilter dari AuditLogController.
 */
class AuditLogsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $builder) {}

    public function query()
    {
        return $this->builder;
    }

    public function headings(): array
    {
        return ['Waktu', 'User', 'Event', 'Deskripsi', 'Entitas', 'IP', 'URL'];
    }

    /** @param  AuditLog  $row */
    public function map($row): array
    {
        return [
            $row->created_at?->format('Y-m-d H:i:s'),
            $row->user?->name,
            $row->event->label(),
            $row->description,
            $row->auditable_type ? class_basename($row->auditable_type).' #'.$row->auditable_id : null,
            $row->ip_address,
            $row->url,
        ];
    }
}
