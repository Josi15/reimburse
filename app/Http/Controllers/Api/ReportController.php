<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReimbursementsExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReimbursementResource;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service) {}

    /** Laporan reimbursement terfilter + ringkasan statistik. */
    public function reimbursements(Request $request): AnonymousResourceCollection
    {
        $filters = $this->validatedFilters($request);

        $list = $this->service->list($filters)
            ->paginate(min((int) $request->query('per_page', 15), 100))
            ->withQueryString();

        return ReimbursementResource::collection($list)
            ->additional(['summary' => $this->service->summary($filters)]);
    }

    /** Rekap pengeluaran per proyek (menghormati filter laporan). */
    public function projects(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->projectRecap($this->validatedFilters($request)),
        ]);
    }

    /** Rekap pengeluaran per departemen (menghormati filter laporan). */
    public function departments(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->departmentRecap($this->validatedFilters($request)),
        ]);
    }

    /** Rekap pembayaran per rekening perusahaan (rekap bulanan via filter tanggal). */
    public function companyAccounts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->companyAccountRecap($this->validatedFilters($request)),
        ]);
    }

    /** Buku kas rekening perusahaan: pemasukan vs pengeluaran + saldo per periode. */
    public function cashflow(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return response()->json(
            $this->service->cashflow($request->only(['date_from', 'date_to'])),
        );
    }

    /** Export laporan ke csv/xlsx/pdf. */
    public function export(Request $request): BinaryFileResponse|Response
    {
        $filters = $this->validatedFilters($request);
        $format = $request->query('format', 'csv');

        return match ($format) {
            'xlsx' => Excel::download(
                new ReimbursementsExport($filters, $this->service),
                'laporan-reimbursement.xlsx',
            ),
            'csv' => Excel::download(
                new ReimbursementsExport($filters, $this->service),
                'laporan-reimbursement.csv',
                ExcelFormat::CSV,
            ),
            'pdf' => Pdf::loadView('reports.reimbursements', [
                'rows' => $this->service->list($filters)->get(),
                'summary' => $this->service->summary($filters),
                'generatedAt' => now()->format('Y-m-d H:i'),
            ])->download('laporan-reimbursement.pdf'),
            default => abort(422, 'Format tidak didukung. Gunakan csv, xlsx, atau pdf.'),
        };
    }

    private function validatedFilters(Request $request): array
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string'],
        ]);

        return $request->only([
            'date_from', 'date_to', 'department_id', 'user_id', 'status', 'category_id', 'project_id', 'q',
        ]);
    }
}
