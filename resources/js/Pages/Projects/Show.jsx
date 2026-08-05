import BudgetBar from '@/Components/BudgetBar';
import Badge from '@/Components/ui/Badge';
import Breadcrumb from '@/Components/ui/Breadcrumb';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import ErrorState from '@/Components/ui/ErrorState';
import { CardSkeleton } from '@/Components/ui/Skeleton';
import StatCard from '@/Components/ui/StatCard';
import { Table, TBody, TD, TH, THead, TR } from '@/Components/ui/Table';
import useAuth from '@/hooks/useAuth';
import useFetch from '@/hooks/useFetch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate, rupiah } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';

const money = (v) => (v === null || v === undefined ? '—' : rupiah(v));

export default function Show({ id }) {
    const { can } = useAuth();
    const { data, loading, error, reload } = useFetch(
        `/api/project-budgets/${id}`,
    );
    const p = data?.data;

    // Pemegang proyek melihat ringkasan pengajuan, tetapi hanya yang berhak
    // melihat detail klaim orang lain yang mendapat tautan ke halamannya.
    const canOpenClaim = can('reimbursement.viewAny');

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {p?.name ?? 'Detail Proyek'}
                </h2>
            }
        >
            <Head title={p?.name ?? 'Detail Proyek'} />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <Breadcrumb
                    items={[
                        { label: 'Anggaran Proyek', href: '/projects' },
                        { label: p?.code ?? '…' },
                    ]}
                />

                {loading ? (
                    <CardSkeleton lines={5} />
                ) : error || !p ? (
                    <Card>
                        <ErrorState onRetry={reload} />
                    </Card>
                ) : (
                    <>
                        <Card className="p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 className="font-semibold text-gray-800 dark:text-gray-100">
                                        {p.name}{' '}
                                        <span className="font-normal text-gray-400">
                                            · {p.code}
                                        </span>
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Pemegang proyek:{' '}
                                        {p.manager?.name ?? 'Belum ditugaskan'}
                                        {(p.start_date || p.end_date) &&
                                            ` · ${formatDate(p.start_date)} – ${formatDate(p.end_date)}`}
                                    </p>
                                    {p.description && (
                                        <p className="mt-2 max-w-2xl text-sm text-gray-600 dark:text-gray-300">
                                            {p.description}
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    {p.is_over_budget && (
                                        <Badge color="red">
                                            Melebihi anggaran
                                        </Badge>
                                    )}
                                    <Badge
                                        color={p.is_active ? 'green' : 'gray'}
                                    >
                                        {p.is_active ? 'Aktif' : 'Nonaktif'}
                                    </Badge>
                                </div>
                            </div>

                            <div className="mt-5">
                                <BudgetBar
                                    budget={p.budget}
                                    paid={p.paid_amount}
                                    pending={p.pending_amount}
                                />
                            </div>
                        </Card>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard
                                label="Anggaran"
                                value={money(p.budget)}
                                hint={
                                    p.usage_percent !== null
                                        ? `Terserap ${p.usage_percent}%`
                                        : 'Tidak dianggarkan'
                                }
                                accent="indigo"
                            />
                            <StatCard
                                label="Sudah Dibayar"
                                value={rupiah(p.paid_amount)}
                                accent="green"
                            />
                            <StatCard
                                label="Dalam Proses"
                                value={rupiah(p.pending_amount)}
                                hint="Menahan anggaran"
                                accent="amber"
                            />
                            <StatCard
                                label="Sisa Dana"
                                value={money(p.remaining_amount)}
                                hint={`Terpakai ${rupiah(p.used_amount)}`}
                                accent={
                                    p.remaining_amount !== null &&
                                    p.remaining_amount < 0
                                        ? 'red'
                                        : 'blue'
                                }
                            />
                        </div>

                        <Card>
                            <h3 className="px-5 pt-5 font-semibold text-gray-700 dark:text-gray-200">
                                Anggota Proyek
                            </h3>
                            <p className="px-5 pt-1 text-sm text-gray-500 dark:text-gray-400">
                                Hanya anggota berikut yang bisa membebankan
                                pengajuan ke proyek ini.
                            </p>
                            {(p.members ?? []).length === 0 ? (
                                <EmptyState title="Belum ada anggota ditugaskan" />
                            ) : (
                                <ul className="flex flex-wrap gap-2 p-5">
                                    {p.members.map((m) => (
                                        <li
                                            key={m.id}
                                            className="rounded-full border border-gray-200 px-3 py-1 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200"
                                        >
                                            {m.name}
                                            {m.department && (
                                                <span className="ml-1 text-xs text-gray-400">
                                                    · {m.department}
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>

                        <Card>
                            <h3 className="px-5 pt-5 font-semibold text-gray-700 dark:text-gray-200">
                                Rincian per Status
                            </h3>
                            {p.by_status.length === 0 ? (
                                <EmptyState title="Belum ada pengajuan pada proyek ini" />
                            ) : (
                                <div className="mt-2">
                                    <Table>
                                        <THead>
                                            <TR>
                                                <TH>Status</TH>
                                                <TH>Jumlah</TH>
                                                <TH>Nominal</TH>
                                            </TR>
                                        </THead>
                                        <TBody>
                                            {p.by_status.map((s) => (
                                                <TR key={s.status.value}>
                                                    <TD>
                                                        <Badge
                                                            color={
                                                                s.status.color
                                                            }
                                                        >
                                                            {s.status.label}
                                                        </Badge>
                                                    </TD>
                                                    <TD>{s.count}</TD>
                                                    <TD>
                                                        {rupiah(s.total_amount)}
                                                    </TD>
                                                </TR>
                                            ))}
                                        </TBody>
                                    </Table>
                                </div>
                            )}
                        </Card>

                        <Card>
                            <h3 className="px-5 pt-5 font-semibold text-gray-700 dark:text-gray-200">
                                Pengajuan Terbaru
                            </h3>
                            {p.recent_reimbursements.length === 0 ? (
                                <EmptyState title="Belum ada pengajuan" />
                            ) : (
                                <div className="mt-2 overflow-x-auto">
                                    <Table>
                                        <THead>
                                            <TR>
                                                <TH>Nomor</TH>
                                                <TH>Judul</TH>
                                                <TH>Pengaju</TH>
                                                <TH>Kategori</TH>
                                                <TH>Nominal</TH>
                                                <TH>Status</TH>
                                                <TH>Tanggal</TH>
                                            </TR>
                                        </THead>
                                        <TBody>
                                            {p.recent_reimbursements.map(
                                                (r) => (
                                                    <TR key={r.id}>
                                                        <TD className="font-medium">
                                                            {canOpenClaim ? (
                                                                <Link
                                                                    href={`/reimbursements/${r.id}`}
                                                                    className="text-indigo-600 hover:underline dark:text-indigo-400"
                                                                >
                                                                    {r.number}
                                                                </Link>
                                                            ) : (
                                                                r.number
                                                            )}
                                                        </TD>
                                                        <TD>{r.title}</TD>
                                                        <TD>{r.user ?? '-'}</TD>
                                                        <TD>
                                                            {r.category ?? '-'}
                                                        </TD>
                                                        <TD>
                                                            {r.formatted_amount}
                                                        </TD>
                                                        <TD>
                                                            <Badge
                                                                color={
                                                                    r.status
                                                                        .color
                                                                }
                                                            >
                                                                {r.status.label}
                                                            </Badge>
                                                        </TD>
                                                        <TD>
                                                            {formatDate(
                                                                r.created_at,
                                                            )}
                                                        </TD>
                                                    </TR>
                                                ),
                                            )}
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
