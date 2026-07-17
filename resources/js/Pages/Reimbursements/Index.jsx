import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/ui/Badge';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import Pagination from '@/Components/ui/Pagination';
import SelectInput from '@/Components/ui/SelectInput';
import { Loading } from '@/Components/ui/Spinner';
import { Table, TBody, TD, TH, THead, TR } from '@/Components/ui/Table';
import useAuth from '@/hooks/useAuth';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const STATUSES = [
    ['', 'Semua Status'],
    ['draft', 'Draft'],
    ['submitted', 'Menunggu Manager'],
    ['manager_approved', 'Disetujui Manager'],
    ['finance_approved', 'Disetujui Finance'],
    ['manager_rejected', 'Ditolak Manager'],
    ['finance_rejected', 'Ditolak Finance'],
    ['revision_requested', 'Perlu Revisi'],
    ['paid', 'Dibayar'],
];

export default function Index() {
    const { can } = useAuth();
    const [rows, setRows] = useState(null);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [status, setStatus] = useState('');
    const [q, setQ] = useState('');
    const [page, setPage] = useState(1);

    useEffect(() => {
        let active = true;
        setLoading(true);
        const params = new URLSearchParams({
            page,
            ...(status && { status }),
            ...(q && { q }),
        });
        api.get(`/api/reimbursements?${params}`)
            .then((d) => {
                if (!active) return;
                setRows(d.data);
                setMeta(d.meta);
            })
            .catch((e) => handleApiError(e))
            .finally(() => active && setLoading(false));
        return () => {
            active = false;
        };
    }, [page, status, q]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Reimbursement
                    </h2>
                    {can('reimbursement.create') && (
                        <Link href="/reimbursements/create">
                            <PrimaryButton>+ Buat Pengajuan</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Reimbursement" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <Card>
                    {/* Filter bar */}
                    <div className="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-gray-700">
                        <TextInput
                            placeholder="Cari nomor / judul…"
                            className="w-64 text-sm"
                            value={q}
                            onChange={(e) => {
                                setQ(e.target.value);
                                setPage(1);
                            }}
                        />
                        <SelectInput
                            className="text-sm"
                            value={status}
                            onChange={(e) => {
                                setStatus(e.target.value);
                                setPage(1);
                            }}
                        >
                            {STATUSES.map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </SelectInput>
                    </div>

                    {loading ? (
                        <Loading />
                    ) : rows?.length === 0 ? (
                        <EmptyState
                            title="Belum ada pengajuan"
                            description="Buat pengajuan reimbursement pertama Anda."
                        />
                    ) : (
                        <>
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Nomor</TH>
                                        <TH>Judul</TH>
                                        <TH>Kategori</TH>
                                        <TH>Pengaju</TH>
                                        <TH>Nominal</TH>
                                        <TH>Status</TH>
                                        <TH>Dibuat</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {rows.map((r) => (
                                        <TR key={r.id}>
                                            <TD>
                                                <Link
                                                    href={`/reimbursements/${r.id}`}
                                                    className="font-medium text-indigo-600 hover:underline"
                                                >
                                                    {r.reimbursement_number}
                                                </Link>
                                            </TD>
                                            <TD>{r.title}</TD>
                                            <TD>{r.category?.name ?? '-'}</TD>
                                            <TD>{r.user?.name ?? '-'}</TD>
                                            <TD>{r.formatted_amount}</TD>
                                            <TD>
                                                <Badge color={r.status.color}>
                                                    {r.status.label}
                                                </Badge>
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
