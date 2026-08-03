import { rupiah } from '@/lib/format';

/**
 * Bar serapan anggaran proyek: hijau = sudah dibayar (realisasi),
 * kuning = masih di alur persetujuan (komitmen), sisanya = dana tersisa.
 * Bila anggaran melebihi 100%, bar berubah merah penuh.
 */
export default function BudgetBar({
    budget,
    paid = 0,
    pending = 0,
    showLegend = true,
}) {
    if (budget === null || budget === undefined) {
        return (
            <p className="text-xs text-gray-500 dark:text-gray-400">
                Proyek ini tidak dianggarkan — pengeluaran tidak dibatasi.
            </p>
        );
    }

    const safeBudget = Math.max(Number(budget), 1);
    const pct = (v) => Math.min((Number(v) / safeBudget) * 100, 100);
    const paidPct = pct(paid);
    const pendingPct = Math.min(pct(pending), 100 - paidPct);
    const over = Number(paid) + Number(pending) > Number(budget);

    return (
        <div>
            <div
                className="flex h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                role="img"
                aria-label={`Terpakai ${rupiah(Number(paid) + Number(pending))} dari anggaran ${rupiah(budget)}`}
            >
                {over ? (
                    <div className="h-full w-full bg-red-500" />
                ) : (
                    <>
                        <div
                            className="h-full bg-green-500"
                            style={{ width: `${paidPct}%` }}
                        />
                        <div
                            className="h-full bg-amber-400"
                            style={{ width: `${pendingPct}%` }}
                        />
                    </>
                )}
            </div>

            {showLegend && (
                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <span className="flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-green-500" />
                        Dibayar {rupiah(paid)}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-amber-400" />
                        Dalam proses {rupiah(pending)}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-gray-300 dark:bg-gray-600" />
                        Anggaran {rupiah(budget)}
                    </span>
                </div>
            )}
        </div>
    );
}
