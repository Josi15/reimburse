import BudgetBar from '@/Components/BudgetBar';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/ui/Badge';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import ErrorState from '@/Components/ui/ErrorState';
import { CardSkeleton } from '@/Components/ui/Skeleton';
import StatCard from '@/Components/ui/StatCard';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import useFetch from '@/hooks/useFetch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { rupiah } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

/** "Rp 1.500.000" atau "Tanpa anggaran" bila null. */
const money = (v) => (v === null || v === undefined ? '—' : rupiah(v));

export default function Index() {
    const [q, setQ] = useState('');
    const dq = useDebouncedValue(q);
    const { data, loading, error, reload } = useFetch(
        `/api/project-budgets${dq ? `?q=${encodeURIComponent(dq)}` : ''}`,
    );

    const projects = data?.data ?? [];
    const totals = data?.totals;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Anggaran Proyek
                </h2>
            }
        >
            <Head title="Anggaran Proyek" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Sisa dana = anggaran − (yang sudah dibayar + yang masih
                    berjalan di alur persetujuan). Draft dan pengajuan yang
                    ditolak tidak mengurangi anggaran.
                </p>

                {loading ? (
                    <CardSkeleton />
                ) : error ? (
                    <Card>
                        <ErrorState onRetry={reload} />
                    </Card>
                ) : (
                    <>
                        {totals && (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <StatCard
                                    label="Total Anggaran"
                                    value={money(totals.budget)}
                                    hint={`${totals.project_count} proyek`}
                                    accent="indigo"
                                />
                                <StatCard
                                    label="Sudah Dibayar"
                                    value={rupiah(totals.paid_amount)}
                                    accent="green"
                                />
                                <StatCard
                                    label="Dalam Proses"
                                    value={rupiah(totals.pending_amount)}
                                    hint="Menunggu persetujuan / pembayaran"
                                    accent="amber"
                                />
                                <StatCard
                                    label="Sisa Anggaran"
                                    value={money(totals.remaining_amount)}
                                    accent={
                                        totals.remaining_amount !== null &&
                                        totals.remaining_amount < 0
                                            ? 'red'
                                            : 'blue'
                                    }
                                />
                            </div>
                        )}

                        <TextInput
                            placeholder="Cari proyek…"
                            className="w-full text-sm sm:w-72"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />

                        {projects.length === 0 ? (
                            <Card>
                                <EmptyState
                                    title="Belum ada proyek"
                                    description="Anda belum ditugaskan sebagai pemegang proyek mana pun."
                                />
                            </Card>
                        ) : (
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                {projects.map((p) => (
                                    <Card key={p.id} className="p-5">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <Link
                                                    href={`/projects/${p.id}`}
                                                    className="font-semibold text-gray-800 hover:underline dark:text-gray-100"
                                                >
                                                    {p.name}
                                                </Link>
                                                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                    {p.code}
                                                    {p.manager &&
                                                        ` · PM: ${p.manager.name}`}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {p.is_over_budget && (
                                                    <Badge color="red">
                                                        Melebihi anggaran
                                                    </Badge>
                                                )}
                                                <Badge
                                                    color={
                                                        p.is_active
                                                            ? 'green'
                                                            : 'gray'
                                                    }
                                                >
                                                    {p.is_active
                                                        ? 'Aktif'
                                                        : 'Nonaktif'}
                                                </Badge>
                                            </div>
                                        </div>

                                        <div className="mt-4">
                                            <div className="flex items-end justify-between gap-3">
                                                <div>
                                                    <div className="text-xs text-gray-500 dark:text-gray-400">
                                                        Sisa dana
                                                    </div>
                                                    <div
                                                        className={
                                                            'text-2xl font-bold ' +
                                                            (p.remaining_amount ===
                                                            null
                                                                ? 'text-gray-500 dark:text-gray-400'
                                                                : p.remaining_amount <
                                                                    0
                                                                  ? 'text-red-600 dark:text-red-400'
                                                                  : 'text-green-600 dark:text-green-400')
                                                        }
                                                    >
                                                        {p.remaining_amount ===
                                                        null
                                                            ? 'Tanpa anggaran'
                                                            : rupiah(
                                                                  p.remaining_amount,
                                                              )}
                                                    </div>
                                                </div>
                                                <div className="text-right text-xs text-gray-500 dark:text-gray-400">
                                                    <div>
                                                        Terpakai{' '}
                                                        {rupiah(p.used_amount)}
                                                    </div>
                                                    {p.usage_percent !==
                                                        null && (
                                                        <div>
                                                            {p.usage_percent}%
                                                            dari{' '}
                                                            {money(p.budget)}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="mt-3">
                                                <BudgetBar
                                                    budget={p.budget}
                                                    paid={p.paid_amount}
                                                    pending={p.pending_amount}
                                                />
                                            </div>
                                        </div>

                                        <div className="mt-4 flex items-center justify-between text-sm">
                                            <span className="text-gray-500 dark:text-gray-400">
                                                {p.reimbursement_count}{' '}
                                                pengajuan
                                            </span>
                                            <Link
                                                href={`/projects/${p.id}`}
                                                className="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                            >
                                                Detail →
                                            </Link>
                                        </div>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
