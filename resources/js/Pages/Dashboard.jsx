import {
    ReimbursementNumberCell,
    StatusCell,
} from '@/Components/ReimbursementRow';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import { DashboardSkeleton } from '@/Components/ui/Skeleton';
import StatCard from '@/Components/ui/StatCard';
import { Table, TBody, TD, TH, THead, TR } from '@/Components/ui/Table';
import useFetch from '@/hooks/useFetch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { cn, formatDate, rupiah } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

const PENDING_LABELS = {
    manager_approval: 'Menunggu Persetujuan Manager',
    finance_approval: 'Menunggu Persetujuan Finance',
    awaiting_payment: 'Menunggu Pembayaran',
    my_revision_requested: 'Pengajuan Saya Perlu Revisi',
};

/** Singkat nominal rupiah: 12500000 -> "12,5jt", 1500 -> "1,5rb". */
function abbrevRupiah(value) {
    const n = Number(value ?? 0);
    const fmt = (x) =>
        x
            .toFixed(1)
            .replace(/[.,]0$/, '')
            .replace('.', ',');
    if (n >= 1_000_000_000) return fmt(n / 1_000_000_000) + 'M';
    if (n >= 1_000_000) return fmt(n / 1_000_000) + 'jt';
    if (n >= 1_000) return fmt(n / 1_000) + 'rb';
    return String(n);
}

function MonthlyChart({ data }) {
    const max = Math.max(...data.map((d) => d.total), 1);
    const [active, setActive] = useState(null);

    return (
        <div>
            {/* Label maksimum sumbu-y */}
            <div className="mb-1 text-right text-[10px] text-gray-400 dark:text-gray-500">
                maks {abbrevRupiah(max)}
            </div>

            <div className="relative h-40">
                {/* Gridlines di belakang bar */}
                <div className="pointer-events-none absolute inset-0">
                    {[0, 0.25, 0.5, 0.75, 1].map((f) => (
                        <div
                            key={f}
                            className="absolute inset-x-0 border-t border-dashed border-gray-100 dark:border-gray-700/70"
                            style={{ top: `${f * 100}%` }}
                        />
                    ))}
                </div>

                {/* Bar */}
                <div className="relative flex h-full items-end gap-1.5">
                    {data.map((d, i) => {
                        const pct = Math.max(
                            (d.total / max) * 100,
                            d.total > 0 ? 4 : 2,
                        );
                        const label = `${MONTHS[d.month - 1]}: ${rupiah(d.total)}`;
                        return (
                            <div
                                key={d.month}
                                className="relative flex h-full flex-1 items-end"
                            >
                                {active === i && (
                                    <div
                                        role="tooltip"
                                        className="absolute left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-[10px] font-medium text-white shadow-lg dark:bg-gray-700"
                                        style={{
                                            bottom: `calc(${pct}% + 6px)`,
                                        }}
                                    >
                                        {label}
                                    </div>
                                )}
                                <div
                                    tabIndex={0}
                                    role="img"
                                    aria-label={label}
                                    onMouseEnter={() => setActive(i)}
                                    onMouseLeave={() => setActive(null)}
                                    onFocus={() => setActive(i)}
                                    onBlur={() => setActive(null)}
                                    className={cn(
                                        'w-full rounded-t transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                                        d.total > 0
                                            ? 'bg-indigo-500/80 hover:bg-indigo-600'
                                            : 'bg-gray-200 dark:bg-gray-700',
                                    )}
                                    style={{ height: `${pct}%` }}
                                />
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Label bulan */}
            <div className="mt-1 flex gap-1.5">
                {data.map((d) => (
                    <span
                        key={d.month}
                        className="flex-1 text-center text-[10px] text-gray-500 dark:text-gray-400"
                    >
                        {MONTHS[d.month - 1]}
                    </span>
                ))}
            </div>
        </div>
    );
}

function TopList({ title, items }) {
    return (
        <Card className="p-5">
            <h3 className="font-semibold text-gray-700 dark:text-gray-200">
                {title}
            </h3>
            {items.length === 0 ? (
                <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Belum ada data.
                </p>
            ) : (
                <ul className="mt-3 space-y-2">
                    {items.map((it) => (
                        <li
                            key={it.name}
                            className="flex items-center justify-between text-sm"
                        >
                            <span className="text-gray-600 dark:text-gray-300">
                                {it.name}
                            </span>
                            <span className="text-gray-500 dark:text-gray-400">
                                {it.count}× · {rupiah(it.total)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

export default function Dashboard() {
    const { data, loading } = useFetch('/api/dashboard');
    const d = data?.data;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {loading || !d ? (
                    <DashboardSkeleton />
                ) : (
                    <>
                        {/* Kartu statistik */}
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                            <StatCard
                                label="Total Pengajuan"
                                value={d.cards.total}
                                accent="gray"
                            />
                            <StatCard
                                label="Diajukan"
                                value={d.cards.submitted}
                                accent="blue"
                            />
                            <StatCard
                                label="Disetujui"
                                value={d.cards.approved}
                                accent="indigo"
                            />
                            <StatCard
                                label="Ditolak"
                                value={d.cards.rejected}
                                accent="red"
                            />
                            <StatCard
                                label="Dibayar"
                                value={d.cards.paid}
                                accent="green"
                            />
                            <StatCard
                                label="Total Dibayar"
                                value={rupiah(d.cards.total_paid_amount)}
                                accent="green"
                            />
                        </div>

                        {/* Antrean pending */}
                        {Object.entries(d.pending).some(([, v]) => v > 0) && (
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                {Object.entries(d.pending)
                                    .filter(([, v]) => v > 0)
                                    .map(([key, value]) => (
                                        <Card
                                            key={key}
                                            className="border-l-4 border-l-amber-400 p-4"
                                        >
                                            <div className="text-sm text-gray-500 dark:text-gray-400">
                                                {PENDING_LABELS[key] ?? key}
                                            </div>
                                            <div className="mt-1 text-xl font-bold text-amber-600 dark:text-amber-400">
                                                {value}
                                            </div>
                                        </Card>
                                    ))}
                            </div>
                        )}

                        <div className="grid gap-6 lg:grid-cols-3">
                            {/* Grafik bulanan */}
                            <Card className="p-5 lg:col-span-2">
                                <h3 className="font-semibold text-gray-700 dark:text-gray-200">
                                    Pengeluaran per Bulan (
                                    {new Date().getFullYear()})
                                </h3>
                                <div className="mt-4">
                                    <MonthlyChart data={d.monthly_expense} />
                                </div>
                            </Card>

                            <div className="space-y-6">
                                <TopList
                                    title="Top Kategori"
                                    items={d.top_categories}
                                />
                                {d.scope === 'global' && (
                                    <TopList
                                        title="Top Department"
                                        items={d.top_departments}
                                    />
                                )}
                            </div>
                        </div>

                        {/* Aktivitas terbaru */}
                        <Card>
                            <div className="flex items-center justify-between px-5 pt-5">
                                <h3 className="font-semibold text-gray-700 dark:text-gray-200">
                                    Aktivitas Terbaru
                                </h3>
                                <Link
                                    href="/reimbursements"
                                    className="text-sm text-indigo-600 hover:underline"
                                >
                                    Lihat semua →
                                </Link>
                            </div>
                            {d.recent.length === 0 ? (
                                <EmptyState title="Belum ada aktivitas" />
                            ) : (
                                <div className="mt-3">
                                    <Table>
                                        <THead>
                                            <TR>
                                                <TH>Nomor</TH>
                                                <TH>Judul</TH>
                                                <TH>Pengaju</TH>
                                                <TH>Nominal</TH>
                                                <TH>Status</TH>
                                                <TH>Tanggal</TH>
                                            </TR>
                                        </THead>
                                        <TBody>
                                            {(d.recent ?? []).map((r) => (
                                                <TR key={r.id}>
                                                    <ReimbursementNumberCell
                                                        id={r.id}
                                                        number={
                                                            r.reimbursement_number
                                                        }
                                                    />
                                                    <TD>{r.title}</TD>
                                                    <TD>{r.user ?? '-'}</TD>
                                                    <TD>{rupiah(r.amount)}</TD>
                                                    <StatusCell
                                                        status={r.status}
                                                    />
                                                    <TD>
                                                        {formatDate(
                                                            r.created_at,
                                                        )}
                                                    </TD>
                                                </TR>
                                            ))}
                                        </TBody>
                                    </Table>
                                </div>
                            )}
                        </Card>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
