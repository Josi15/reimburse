<?php

namespace App\Http\Controllers\Api;

use App\Exports\AuditLogsExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Activity Log — READ-ONLY. Auditor (dan Admin/Super Admin) memperoleh akses
 * penuh baca. Tidak ada create/update/delete: audit log bersifat immutable.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $logs = $this->filteredQuery($request)
            ->paginate(min((int) $request->query('per_page', 20), 100))
            ->withQueryString();

        return AuditLogResource::collection($logs);
    }

    public function show(AuditLog $auditLog): AuditLogResource
    {
        return new AuditLogResource($auditLog->load('user'));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = $this->filteredQuery($request);
        $format = $request->query('format', 'csv');

        return match ($format) {
            'xlsx' => Excel::download(new AuditLogsExport($query), 'activity-log.xlsx'),
            'csv' => Excel::download(new AuditLogsExport($query), 'activity-log.csv', ExcelFormat::CSV),
            default => abort(422, 'Format tidak didukung. Gunakan csv atau xlsx.'),
        };
    }

    /** Query terfilter (event, user, entitas, rentang tanggal, kata kunci). */
    private function filteredQuery(Request $request): Builder
    {
        $query = AuditLog::query()->with('user');

        foreach (['event', 'user_id', 'auditable_type'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }
        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('description', 'ilike', "%{$q}%")
                    ->orWhere('ip_address', 'ilike', "%{$q}%")
                    ->orWhere('url', 'ilike', "%{$q}%");
            });
        }

        return $query->orderByDesc('created_at');
    }
}
