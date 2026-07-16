import InputLabel from '@/Components/InputLabel';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/ui/Badge';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import Pagination from '@/Components/ui/Pagination';
import SelectInput from '@/Components/ui/SelectInput';
import { Loading } from '@/Components/ui/Spinner';
import StatCard from '@/Components/ui/StatCard';
import { Table, TBody, TD, TH, THead, TR } from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { formatDate, rupiah } from '@/lib/format';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const STATUSES = [
    ['', 'Semua Status'],
    ['draft', 'Draft'],
    ['submitted', 'Diajukan'],
    ['manager_approved', 'Disetujui Manager'],
    ['finance_approved', 'Disetujui Finance'],
    ['manager_rejected', 'Ditolak Manager'],
    ['finance_rejected', 'Ditolak Finance'],
    ['revision_requested', 'Perlu Revisi'],
    ['paid', 'Dibayar'],
];

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
    const [page, setPage] = useState(1);

    const set = (key) => (e) => {
        setFilters((f) => ({ ...f, [key]: e.target.value }));
        setPage(1);
    };

    const query = () => {
        const params = new URLSearchParams({ page });
        Object.entries(filters).forEach(([k, v]) => v && params.append(k, v));
        return params;
    };

    useEffect(() => {
        api.get('/api/options/departments').then((d) => setDepartments(d.data)).catch(() => {});
        api.get('/api/options/categories').then((d) => setCategories(d.data)).catch(() => {});
    }, []);

    useEffect(() => {
        let active = true;
        setLoading(true);
        api.get(`/api/reports/reimbursements?${query()}`)
            .then((d) => {
                if (!active) return;
                setRows(d.data);
                setMeta(d.meta);
                setSummary(d.summary);
            })
            .catch((e) => handleApiError(e))
            .finally(() => active && setLoading(false));
        return () => {
            active = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, JSON.stringify(filters)]);

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
                            <InputLabel value="Dari Tanggal" className="text-xs" />
                            <TextInput type="date" className="mt-1 w-full text-sm" value={filters.date_from} onChange={set('date_from')} />
                        </div>
                        <div>
                            <InputLabel value="Sampai" className="text-xs" />
                            <TextInput type="date" className="mt-1 w-full text-sm" value={filters.date_to} onChange={set('date_to')} />
                        </div>
                        <div>
                            <InputLabel value="Status" className="text-xs" />
                            <SelectInput className="mt-1 w-full text-sm" value={filters.status} onChange={set('status')}>
                                {STATUSES.map(([v, l]) => (
                                    <option key={v} value={v}>
                                        {l}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel value="Department" className="text-xs" />
                            <SelectInput className="mt-1 w-full text-sm" value={filters.department_id} onChange={set('department_id')}>
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
                            <SelectInput className="mt-1 w-full text-sm" value={filters.category_id} onChange={set('category_id')}>
                                <option value="">Semua</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel value="Kata Kunci" className="text-xs" />
                            <TextInput className="mt-1 w-full text-sm" placeholder="Nomor / judul" value={filters.q} onChange={set('q')} />
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <SecondaryButton onClick={() => exportAs('pdf')}>⬇ PDF</SecondaryButton>
                        <SecondaryButton onClick={() => exportAs('xlsx')}>⬇ Excel</SecondaryButton>
                        <SecondaryButton onClick={() => exportAs('csv')}>⬇ CSV</SecondaryButton>
                    </div>
                </Card>

                {/* Ringkasan */}
                {summary && (
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <StatCard label="Jumlah Pengajuan" value={summary.count} accent="gray" />
                        <StatCard label="Total Nominal" value={rupiah(summary.total_amount)} accent="indigo" />
                        <StatCard
                            label="Dibayar"
                            value={summary.by_status?.paid?.count ?? 0}
                            hint={rupiah(summary.by_status?.paid?.total ?? 0)}
                            accent="green"
                        />
                        <StatCard
                            label="Ditolak"
                            value={
                                (summary.by_status?.manager_rejected?.count ?? 0) +
                                (summary.by_status?.finance_rejected?.count ?? 0)
                            }
                            accent="red"
                        />
                    </div>
                )}

                {/* Tabel */}
                <Card>
                    {loading ? (
                        <Loading />
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
                                    {rows.map((r) => (
                                        <TR key={r.id}>
                                            <TD className="font-medium">{r.reimbursement_number}</TD>
                                            <TD>{r.title}</TD>
                                            <TD>{r.user?.name ?? '-'}</TD>
                                            <TD>{r.department?.name ?? '-'}</TD>
                                            <TD>{r.category?.name ?? '-'}</TD>
                                            <TD>{r.formatted_amount}</TD>
                                            <TD>
                                                <Badge color={r.status.color}>{r.status.label}</Badge>
                                            </TD>
                                            <TD>{formatDate(r.created_at)}</TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                            <Pagination meta={meta} onPage={setPage} />
                        </>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
