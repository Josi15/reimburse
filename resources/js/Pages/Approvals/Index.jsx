import {
    ReimbursementNumberCell,
    StatusCell,
} from '@/Components/ReimbursementRow';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import ErrorState from '@/Components/ui/ErrorState';
import Pagination from '@/Components/ui/Pagination';
import { Loading } from '@/Components/ui/Spinner';
import { Table, TBody, TD, TH, THead, TR } from '@/Components/ui/Table';
import useAuth from '@/hooks/useAuth';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Index() {
    const { can } = useAuth();
    const [rows, setRows] = useState(null);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [nonce, setNonce] = useState(0);

    // Manager memproses "submitted"; Finance memproses "manager_approved".
    const status = can('reimbursement.approve.finance')
        ? 'manager_approved'
        : 'submitted';

    useEffect(() => {
        let active = true;
        setLoading(true);
        setError(null);
        api.get(
            `/api/reimbursements?status=${status}&page=${page}&sort=submitted_at&direction=asc`,
        )
            .then((d) => {
                if (!active) return;
                setRows(d.data);
                setMeta(d.meta);
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
    }, [status, page, nonce]);

    const reload = () => setNonce((n) => n + 1);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Persetujuan
                </h2>
            }
        >
            <Head title="Persetujuan" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <Card>
                    <div className="border-b border-gray-100 p-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Pengajuan yang menunggu keputusan Anda. Klik nomor untuk
                        melihat detail & mengambil aksi.
                    </div>

                    {loading ? (
                        <Loading />
                    ) : error ? (
                        <ErrorState onRetry={reload} />
                    ) : rows?.length === 0 ? (
                        <EmptyState
                            title="Tidak ada antrean"
                            description="Semua pengajuan sudah diproses. 🎉"
                        />
                    ) : (
                        <>
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Nomor</TH>
                                        <TH>Judul</TH>
                                        <TH>Pengaju</TH>
                                        <TH>Kategori</TH>
                                        <TH>Nominal</TH>
                                        <TH>Status</TH>
                                        <TH>Diajukan</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {(rows ?? []).map((r) => (
                                        <TR key={r.id}>
                                            <ReimbursementNumberCell
                                                id={r.id}
                                                number={r.reimbursement_number}
                                            />
                                            <TD>{r.title}</TD>
                                            <TD>{r.user?.name ?? '-'}</TD>
                                            <TD>{r.category?.name ?? '-'}</TD>
                                            <TD>{r.formatted_amount}</TD>
                                            <StatusCell status={r.status} />
                                            <TD>
                                                {formatDate(
                                                    r.submitted_at,
                                                    true,
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
            </div>
        </AuthenticatedLayout>
    );
}
