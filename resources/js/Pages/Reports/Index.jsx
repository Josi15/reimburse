import InputLabel from '@/Components/InputLabel';
import { StatusCell } from '@/Components/ReimbursementRow';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import ErrorState from '@/Components/ui/ErrorState';
import Pagination from '@/Components/ui/Pagination';
import SelectInput from '@/Components/ui/SelectInput';
import { TableSkeleton } from '@/Components/ui/Skeleton';
import StatCard from '@/Components/ui/StatCard';
import { Table, TBody, TD, TH, THead, TR } from '@/Components/ui/Table';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { formatDate, rupiah } from '@/lib/format';
import { REIMBURSEMENT_STATUSES } from '@/lib/statuses';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Index() {
    const [filters, setFilters] = useState({
        date_from: '',
        date_to: '',
        status: '',
        department_id: '',
        category_id: '',
        q: '',
    });
    const [departments, setDepartments] = useState([]);
    const [categories, setCategories] = useState([]);
    const [rows, setRows] = useState(null);
    const [meta, setMeta] = useState(null);
    const [summary, setSummary] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [nonce, setNonce] = useState(0);
    const [tab, setTab] = useState('reimbursements');
    const [projectRows, setProjectRows] = useState(null);
    const [projectLoading, setProjectLoading] = useState(false);
    const [projectError, setProjectError] = useState(null);
    const [accountRows, setAccountRows] = useState(null);
    const [accountLoading, setAccountLoading] = useState(false);
    const [accountError, setAccountError] = useState(null);
    const dq = useDebouncedValue(filters.q);

    const set = (key) => (e) => {
        setFilters((f) => ({ ...f, [key]: e.target.value }));
        setPage(1);
    };

    const query = (qValue = filters.q) => {
        const params = new URLSearchParams({ page });
        Object.entries({ ...filters, q: qValue }).forEach(
            ([k, v]) => v && params.append(k, v),
        );
        return params;
    };

    useEffect(() => {
        api.get('/api/options/departments')
            .then((d) => setDepartments(d.data))
            .catch(() => {});
        api.get('/api/options/categories')
            .then((d) => setCategories(d.data))
            .catch(() => {});
    }, []);

    useEffect(() => {
        let active = true;
        setLoading(true);
        setError(null);
        api.get(`/api/reports/reimbursements?${query(dq)}`)
            .then((d) => {
                if (!active) return;
                setRows(d.data);
                setMeta(d.meta);
                setSummary(d.summary);
            })
            .catch((e) => {
                if (!active) return;
                setError(true);
                handleApiError(e);
            })
            .finally(() => active && setLoading(false));
        return () => {
            active = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, JSON.stringify({ ...filters, q: dq }), nonce]);

    useEffect(() => {
        if (tab !== 'projects') return;
        let active = true;
        setProjectLoading(true);
        setProjectError(null);
        const params = query(dq);
        params.delete('page');
        api.get(`/api/reports/projects?${params}`)
            .then((d) => {
                if (!active) return;
                setProjectRows(d.data);
            })
            .catch((e) => {
                if (!active) return;
                setProjectError(true);
                handleApiError(e);
            })
            .finally(() => active && setProjectLoading(false));
        return () => {
            active = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tab, JSON.stringify({ ...filters, q: dq }), nonce]);

    useEffect(() => {
        if (tab !== 'company-accounts') return;
        let active = true;
        setAccountLoading(true);
        setAccountError(null);
        const params = query(dq);
        params.delete('page');
        api.get(`/api/reports/company-accounts?${params}`)
            .then((d) => {
                if (!active) return;
                setAccountRows(d.data);
            })
            .catch((e) => {
                if (!active) return;
                setAccountError(true);
                handleApiError(e);
            })
            .finally(() => active && setAccountLoading(false));
        return () => {
            active = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tab, JSON.stringify({ ...filters, q: dq }), nonce]);

    const reload = () => setNonce((n) => n + 1);

    function exportAs(format) {
        const params = query();
        params.set('format', format);
        params.delete('page');
        window.open(`/api/reports/reimbursements/export?${params}`, '_blank');
    }

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Laporan Reimbursement
                </h2>
            }
        >
            <Head title="Laporan" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {/* Filter */}
                <Card className="p-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                        <div>
                            <InputLabel
                                value="Dari Tanggal"
                                className="text-xs"
                            />
                            <TextInput
                                type="date"
                                className="mt-1 w-full text-sm"
                                value={filters.date_from}
                                onChange={set('date_from')}
                            />
                        </div>
                        <div>
                            <InputLabel value="Sampai" className="text-xs" />
                            <TextInput
                                type="date"
                                className="mt-1 w-full text-sm"
                                value={filters.date_to}
                                onChange={set('date_to')}
                            />
                        </div>
                        <div>
                            <InputLabel value="Status" className="text-xs" />
                            <SelectInput
                                className="mt-1 w-full text-sm"
                                value={filters.status}
                                onChange={set('status')}
                            >
                                {REIMBURSEMENT_STATUSES.map(([v, l]) => (
                                    <option key={v} value={v}>
                                        {l}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel
                                value="Department"
                                className="text-xs"
                            />
                            <SelectInput
                                className="mt-1 w-full text-sm"
                                value={filters.department_id}
                                onChange={set('department_id')}
                            >
                                <option value="">Semua</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel value="Kategori" className="text-xs" />
                            <SelectInput
                                className="mt-1 w-full text-sm"
                                value={filters.category_id}
                                onChange={set('category_id')}
                            >
                                <option value="">Semua</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel
                                value="Kata Kunci"
                                className="text-xs"
                            />
                            <TextInput
                                className="mt-1 w-full text-sm"
                                placeholder="Nomor / judul"
                                value={filters.q}
                                onChange={set('q')}
                            />
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <SecondaryButton onClick={() => exportAs('pdf')}>
                            ⬇ PDF
                        </SecondaryButton>
                        <SecondaryButton onClick={() => exportAs('xlsx')}>
                            ⬇ Excel
                        </SecondaryButton>
                        <SecondaryButton onClick={() => exportAs('csv')}>
                            ⬇ CSV
                        </SecondaryButton>
                    </div>
                </Card>

                {/* Tab navigasi rekap */}
                <div className="flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700">
                    {[
                        ['reimbursements', 'Reimbursement'],
                        ['projects', 'Rekap per Project'],
                        ['company-accounts', 'Rekap per Rekening Perusahaan'],
                    ].map(([v, l]) => (
                        <button
                            key={v}
                            type="button"
                            onClick={() => setTab(v)}
                            className={
                                'border-b-2 px-3 py-2 text-sm font-medium transition ' +
                                (tab === v
                                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200')
                            }
                        >
                            {l}
                        </button>
                    ))}
                </div>

                {/* Ringkasan */}
                {tab === 'reimbursements' && summary && (
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <StatCard
                            label="Jumlah Pengajuan"
                            value={summary.count}
                            accent="gray"
                        />
                        <StatCard
                            label="Total Nominal"
                            value={rupiah(summary.total_amount)}
                            accent="indigo"
                        />
                        <StatCard
                            label="Dibayar"
                            value={summary.by_status?.paid?.count ?? 0}
                            hint={rupiah(summary.by_status?.paid?.total ?? 0)}
                            accent="green"
                        />
                        <StatCard
                            label="Ditolak"
                            value={
                                (summary.by_status?.manager_rejected?.count ??
                                    0) +
                                (summary.by_status?.finance_rejected?.count ??
                                    0)
                            }
                            accent="red"
                        />
                    </div>
                )}

                {/* Tabel reimbursement */}
                {tab === 'reimbursements' && (
                <Card>
                    {loading ? (
                        <TableSkeleton rows={6} cols={8} />
                    ) : error ? (
                        <ErrorState onRetry={reload} />
                    ) : rows?.length === 0 ? (
                        <EmptyState title="Tidak ada data untuk filter ini" />
                    ) : (
                        <>
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Nomor</TH>
                                        <TH>Judul</TH>
                                        <TH>Pengaju</TH>
                                        <TH>Department</TH>
                                        <TH>Kategori</TH>
                                        <TH>Nominal</TH>
                                        <TH>Status</TH>
                                        <TH>Tanggal</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {(rows ?? []).map((r) => (
                                        <TR key={r.id}>
                                            <TD className="font-medium">
                                                {r.reimbursement_number}
                                            </TD>
                                            <TD>{r.title}</TD>
                                            <TD>{r.user?.name ?? '-'}</TD>
                                            <TD>{r.department?.name ?? '-'}</TD>
                                            <TD>{r.category?.name ?? '-'}</TD>
                                            <TD>{r.formatted_amount}</TD>
                                            <StatusCell status={r.status} />
                                            <TD>{formatDate(r.created_at)}</TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                            <Pagination meta={meta} onPage={setPage} />
                        </>
                    )}
                </Card>
                )}

                {/* Rekap per Project */}
                {tab === 'projects' && (
                    <Card>
                        {projectLoading ? (
                            <TableSkeleton rows={6} cols={5} />
                        ) : projectError ? (
                            <ErrorState onRetry={reload} />
                        ) : projectRows?.length === 0 ? (
                            <EmptyState title="Tidak ada data untuk filter ini" />
                        ) : (
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Project</TH>
                                        <TH>Jumlah</TH>
                                        <TH>Total Diajukan</TH>
                                        <TH>Total Dibayar</TH>
                                        <TH>Anggaran</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {(projectRows ?? []).map((r) => (
                                        <TR key={r.project_id}>
                                            <TD className="font-medium">
                                                {r.code} — {r.name}
                                            </TD>
                                            <TD>{r.count}</TD>
                                            <TD>{rupiah(r.total_amount)}</TD>
                                            <TD>{rupiah(r.paid_amount)}</TD>
                                            <TD>
                                                {r.budget != null
                                                    ? rupiah(r.budget)
                                                    : '-'}
                                            </TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        )}
                    </Card>
                )}

                {/* Rekap per Rekening Perusahaan */}
                {tab === 'company-accounts' && (
                    <Card>
                        {accountLoading ? (
                            <TableSkeleton rows={6} cols={3} />
                        ) : accountError ? (
                            <ErrorState onRetry={reload} />
                        ) : accountRows?.length === 0 ? (
                            <EmptyState title="Tidak ada data untuk filter ini" />
                        ) : (
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Rekening</TH>
                                        <TH>Jumlah Pembayaran</TH>
                                        <TH>Total</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {(accountRows ?? []).map((r) => (
                                        <TR key={r.source_account_id}>
                                            <TD className="font-medium">
                                                {r.label}
                                            </TD>
                                            <TD>{r.count}</TD>
                                            <TD>{rupiah(r.total_amount)}</TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        )}
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
