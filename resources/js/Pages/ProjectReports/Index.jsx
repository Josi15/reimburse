import InputLabel from '@/Components/InputLabel';
import {
    ReimbursementNumberCell,
    StatusCell,
} from '@/Components/ReimbursementRow';
import TextInput from '@/Components/TextInput';
import Breadcrumb from '@/Components/ui/Breadcrumb';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import ErrorState from '@/Components/ui/ErrorState';
import Pagination from '@/Components/ui/Pagination';
import SelectInput from '@/Components/ui/SelectInput';
import { TableSkeleton } from '@/Components/ui/Skeleton';
import StatCard from '@/Components/ui/StatCard';
import { Table, TBody, TD, TH, THead, TR } from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { formatDate, rupiah } from '@/lib/format';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Index() {
    const [projects, setProjects] = useState([]);
    const [projectId, setProjectId] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    // Rekap semua project (saat tidak ada project dipilih).
    const [recap, setRecap] = useState(null);
    const [recapLoading, setRecapLoading] = useState(true);
    const [recapError, setRecapError] = useState(false);

    // Detail satu project (saat project dipilih).
    const [rows, setRows] = useState(null);
    const [meta, setMeta] = useState(null);
    const [page, setPage] = useState(1);
    const [detailLoading, setDetailLoading] = useState(false);
    const [detailError, setDetailError] = useState(false);

    const [nonce, setNonce] = useState(0);
    const reload = () => setNonce((n) => n + 1);

    // Ambil opsi project saat mount.
    useEffect(() => {
        api.get('/api/options/projects')
            .then((d) => setProjects(d.data))
            .catch(() => {});
    }, []);

    // Rekap seluruh project (filter tanggal). Selalu di-fetch agar StatCard
    // project terpilih dapat diambil dari daftar rekap.
    useEffect(() => {
        let active = true;
        setRecapLoading(true);
        setRecapError(false);
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        api.get(`/api/reports/projects?${params}`)
            .then((d) => {
                if (!active) return;
                setRecap(d.data);
            })
            .catch((e) => {
                if (!active) return;
                setRecapError(true);
                handleApiError(e);
            })
            .finally(() => active && setRecapLoading(false));
        return () => {
            active = false;
        };
    }, [dateFrom, dateTo, nonce]);

    // Detail reimbursement satu project.
    useEffect(() => {
        if (!projectId) return;
        let active = true;
        setDetailLoading(true);
        setDetailError(false);
        const params = new URLSearchParams({ project_id: projectId, page });
        api.get(`/api/reimbursements?${params}`)
            .then((d) => {
                if (!active) return;
                setRows(d.data);
                setMeta(d.meta);
            })
            .catch((e) => {
                if (!active) return;
                setDetailError(true);
                handleApiError(e);
            })
            .finally(() => active && setDetailLoading(false));
        return () => {
            active = false;
        };
    }, [projectId, page, nonce]);

    const selectProject = (id) => {
        setProjectId(id);
        setPage(1);
    };

    // Entri rekap untuk project yang sedang dipilih.
    const current = (recap ?? []).find(
        (r) => String(r.project_id) === String(projectId),
    );

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <Breadcrumb
                        items={[
                            { label: 'Dashboard', href: '/dashboard' },
                            { label: 'Laporan Project' },
                        ]}
                    />
                    <h2 className="mt-1 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Laporan Project
                    </h2>
                </div>
            }
        >
            <Head title="Laporan Project" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {/* Filter */}
                <Card className="p-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <InputLabel value="Project" className="text-xs" />
                            <SelectInput
                                className="mt-1 w-full text-sm"
                                value={projectId}
                                onChange={(e) => selectProject(e.target.value)}
                            >
                                <option value="">Semua Project</option>
                                {projects.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.code} — {p.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel value="Dari" className="text-xs" />
                            <TextInput
                                type="date"
                                className="mt-1 w-full text-sm"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                            />
                        </div>
                        <div>
                            <InputLabel value="Sampai" className="text-xs" />
                            <TextInput
                                type="date"
                                className="mt-1 w-full text-sm"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                            />
                        </div>
                    </div>
                </Card>

                {/* Rekap semua project */}
                {!projectId && (
                    <Card>
                        {recapLoading ? (
                            <TableSkeleton rows={6} cols={6} />
                        ) : recapError ? (
                            <ErrorState onRetry={reload} />
                        ) : recap?.length === 0 ? (
                            <EmptyState title="Belum ada pengeluaran project" />
                        ) : (
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Project</TH>
                                        <TH>Jumlah</TH>
                                        <TH>Total Diajukan</TH>
                                        <TH>Total Dibayar</TH>
                                        <TH>Anggaran</TH>
                                        <TH>Sisa Anggaran</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {(recap ?? []).map((r) => {
                                        const remaining =
                                            r.budget != null
                                                ? r.budget - r.paid_amount
                                                : null;
                                        return (
                                            <TR
                                                key={r.project_id}
                                                className="cursor-pointer"
                                                onClick={() =>
                                                    selectProject(
                                                        String(r.project_id),
                                                    )
                                                }
                                            >
                                                <TD className="font-medium">
                                                    {r.code} — {r.name}
                                                </TD>
                                                <TD>{r.count}</TD>
                                                <TD>
                                                    {rupiah(r.total_amount)}
                                                </TD>
                                                <TD>{rupiah(r.paid_amount)}</TD>
                                                <TD>
                                                    {r.budget != null
                                                        ? rupiah(r.budget)
                                                        : '-'}
                                                </TD>
                                                <TD
                                                    className={
                                                        remaining != null &&
                                                        remaining < 0
                                                            ? 'text-red-600 dark:text-red-400'
                                                            : undefined
                                                    }
                                                >
                                                    {remaining != null
                                                        ? rupiah(remaining)
                                                        : '-'}
                                                </TD>
                                            </TR>
                                        );
                                    })}
                                </TBody>
                            </Table>
                        )}
                    </Card>
                )}

                {/* Detail satu project */}
                {projectId && (
                    <>
                        {/* StatCards dari entri rekap */}
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <StatCard
                                label="Anggaran"
                                value={
                                    current?.budget != null
                                        ? rupiah(current.budget)
                                        : 'Tanpa anggaran'
                                }
                                accent="blue"
                            />
                            <StatCard
                                label="Total Diajukan"
                                value={rupiah(current?.total_amount ?? 0)}
                                accent="indigo"
                            />
                            <StatCard
                                label="Total Dibayar"
                                value={rupiah(current?.paid_amount ?? 0)}
                                accent="green"
                            />
                            <StatCard
                                label="Sisa Anggaran"
                                value={
                                    current?.budget != null
                                        ? rupiah(
                                              current.budget -
                                                  current.paid_amount,
                                          )
                                        : '-'
                                }
                                accent={
                                    current?.budget != null &&
                                    current.budget - current.paid_amount < 0
                                        ? 'red'
                                        : 'gray'
                                }
                            />
                        </div>

                        {/* Daftar reimbursement project */}
                        <Card>
                            {detailLoading ? (
                                <TableSkeleton rows={6} cols={6} />
                            ) : detailError ? (
                                <ErrorState onRetry={reload} />
                            ) : rows?.length === 0 ? (
                                <EmptyState title="Belum ada pengajuan untuk project ini" />
                            ) : (
                                <>
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
                                            {(rows ?? []).map((r) => (
                                                <TR key={r.id}>
                                                    <ReimbursementNumberCell
                                                        id={r.id}
                                                        number={
                                                            r.reimbursement_number
                                                        }
                                                    />
                                                    <TD>{r.title}</TD>
                                                    <TD>
                                                        {r.user?.name ?? '-'}
                                                    </TD>
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
                                    <Pagination meta={meta} onPage={setPage} />
                                </>
                            )}
                        </Card>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
