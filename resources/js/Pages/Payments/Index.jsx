import Badge from '@/Components/ui/Badge';
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
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Index() {
    const { can } = useAuth();
    const [queue, setQueue] = useState([]);
    const [payments, setPayments] = useState(null);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [nonce, setNonce] = useState(0);

    useEffect(() => {
        let active = true;
        setLoading(true);
        setError(null);

        const tasks = [
            api.get(`/api/payments?page=${page}`).then((d) => {
                if (!active) return;
                setPayments(d.data);
                setMeta(d.meta);
            }),
        ];

        if (can('payment.process')) {
            tasks.push(
                api
                    .get(
                        '/api/reimbursements?status=finance_approved&per_page=50',
                    )
                    .then((d) => {
                        if (active) setQueue(d.data);
                    }),
            );
        }

        Promise.all(tasks)
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
    }, [page, nonce]);

    const reload = () => setNonce((n) => n + 1);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Pembayaran
                </h2>
            }
        >
            <Head title="Pembayaran" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {loading ? (
                    <Loading />
                ) : error ? (
                    <Card>
                        <ErrorState onRetry={reload} />
                    </Card>
                ) : (
                    <>
                        {can('payment.process') && (
                            <Card>
                                <h3 className="px-5 pt-5 font-semibold text-gray-700 dark:text-gray-200">
                                    Menunggu Pembayaran ({queue.length})
                                </h3>
                                {queue.length === 0 ? (
                                    <p className="px-5 pb-5 pt-2 text-sm text-gray-400">
                                        Tidak ada reimbursement yang siap
                                        dibayar.
                                    </p>
                                ) : (
                                    <div className="mt-2">
                                        <Table>
                                            <THead>
                                                <TR>
                                                    <TH>Nomor</TH>
                                                    <TH>Judul</TH>
                                                    <TH>Pengaju</TH>
                                                    <TH>Nominal</TH>
                                                    <TH>Aksi</TH>
                                                </TR>
                                            </THead>
                                            <TBody>
                                                {(queue ?? []).map((r) => (
                                                    <TR key={r.id}>
                                                        <TD>
                                                            {
                                                                r.reimbursement_number
                                                            }
                                                        </TD>
                                                        <TD>{r.title}</TD>
                                                        <TD>
                                                            {r.user?.name ??
                                                                '-'}
                                                        </TD>
                                                        <TD>
                                                            {r.formatted_amount}
                                                        </TD>
                                                        <TD>
                                                            <Link
                                                                href={`/reimbursements/${r.id}`}
                                                                className="font-medium text-indigo-600 hover:underline"
                                                            >
                                                                Bayar →
                                                            </Link>
                                                        </TD>
                                                    </TR>
                                                ))}
                                            </TBody>
                                        </Table>
                                    </div>
                                )}
                            </Card>
                        )}

                        <Card>
                            <h3 className="px-5 pt-5 font-semibold text-gray-700 dark:text-gray-200">
                                Riwayat Pembayaran
                            </h3>
                            {payments?.length === 0 ? (
                                <EmptyState title="Belum ada pembayaran" />
                            ) : (
                                <div className="mt-2">
                                    <Table>
                                        <THead>
                                            <TR>
                                                <TH>No. Pembayaran</TH>
                                                <TH>Metode</TH>
                                                <TH>Rekening Tujuan</TH>
                                                <TH>Nominal</TH>
                                                <TH>Referensi</TH>
                                                <TH>Status</TH>
                                                <TH>Dibayar</TH>
                                                <TH>Oleh</TH>
                                            </TR>
                                        </THead>
                                        <TBody>
                                            {(payments ?? []).map((p) => (
                                                <TR key={p.id}>
                                                    <TD className="font-medium">
                                                        {p.payment_number}
                                                    </TD>
                                                    <TD>{p.method.label}</TD>
                                                    <TD>
                                                        {p.bank_account
                                                            ? `${p.bank_account.bank?.code ?? ''} · ${p.bank_account.masked_number}`
                                                            : '-'}
                                                    </TD>
                                                    <TD>
                                                        {p.formatted_amount}
                                                    </TD>
                                                    <TD>
                                                        {p.reference_number ??
                                                            '-'}
                                                    </TD>
                                                    <TD>
                                                        <Badge
                                                            color={
                                                                p.status.color
                                                            }
                                                        >
                                                            {p.status.label}
                                                        </Badge>
                                                    </TD>
                                                    <TD>
                                                        {formatDate(
                                                            p.paid_at,
                                                            true,
                                                        )}
                                                    </TD>
                                                    <TD>
                                                        {p.processor?.name ??
                                                            '-'}
                                                    </TD>
                                                </TR>
                                            ))}
                                        </TBody>
                                    </Table>
                                    <Pagination meta={meta} onPage={setPage} />
                                </div>
                            )}
                        </Card>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
