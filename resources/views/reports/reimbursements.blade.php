<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Reimbursement</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .muted { color: #6b7280; font-size: 10px; }
        .summary { margin: 12px 0; }
        .summary span { display: inline-block; margin-right: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; }
        th { background: #f3f4f6; }
        td.num { text-align: right; }
    </style>
</head>
<body>
    <h1>Laporan Reimbursement</h1>
    <div class="muted">Dibuat: {{ $generatedAt }}</div>

    <div class="summary">
        <span><strong>Total Pengajuan:</strong> {{ $summary['count'] }}</span>
        <span><strong>Total Nominal:</strong> Rp {{ number_format($summary['total_amount'], 0, ',', '.') }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nomor</th><th>Judul</th><th>Pengaju</th><th>Department</th>
                <th>Kategori</th><th>Nominal</th><th>Status</th><th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r->reimbursement_number }}</td>
                    <td>{{ $r->title }}</td>
                    <td>{{ $r->user?->name }}</td>
                    <td>{{ $r->department?->name }}</td>
                    <td>{{ $r->category?->name }}</td>
                    <td class="num">Rp {{ number_format($r->amount, 0, ',', '.') }}</td>
                    <td>{{ $r->status->label() }}</td>
                    <td>{{ $r->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
