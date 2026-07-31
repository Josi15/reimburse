import PrimaryButton from '@/Components/PrimaryButton';
import {
    ReimbursementNumberCell,
    StatusCell,
} from '@/Components/ReimbursementRow';
import TextInput from '@/Components/TextInput';
import Card from '@/Components/ui/Card';
import Badge from '@/Components/ui/Badge';
import EmptyState from '@/Components/ui/EmptyState';
import ErrorState from '@/Components/ui/ErrorState';
import { MobileList, MobileListItem } from '@/Components/ui/MobileList';
import Pagination from '@/Components/ui/Pagination';
import SelectInput from '@/Components/ui/SelectInput';
import { TableSkeleton } from '@/Components/ui/Skeleton';
import { Table, TBody, TD, TH, THead, TR } from '@/Components/ui/Table';
import useAuth from '@/hooks/useAuth';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { REIMBURSEMENT_STATUSES } from '@/lib/statuses';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Index() {
    const { can } = useAuth();
    const [rows, setRows] = useState(null);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [status, setStatus] = useState('');
    const [claimType, setClaimType] = useState('');
    const [claimTypes, setClaimTypes] = useState([]);
    const [q, setQ] = useState('');
    const [page, setPage] = useState(1);
    const [nonce, setNonce] = useState(0);
    const dq = useDebouncedValue(q);

    useEffect(() => {
        api.get('/api/options/claim-types')
            .then((d) => setClaimTypes(d.data))
            .catch(() => {});
    }, []);

    useEffect(() => {
        let active = true;
        setLoading(true);
        setError(null);
        const params = new URLSearchParams({
            page,
            ...(status && { status }),
            ...(claimType && { claim_type: claimType }),
            ...(dq && { q: dq }),
        });
        api.get(`/api/reimbursements?${params}`)
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
    }, [page, status, claimType, dq, nonce]);

    const reload = () => setNonce((n) => n + 1);

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
                            {REIMBURSEMENT_STATUSES.map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </SelectInput>
                        <SelectInput
                            className="text-sm"
                            aria-label="Filter jenis pengajuan"
                            value={claimType}
                            onChange={(e) => {
                                setClaimType(e.target.value);
                                setPage(1);
                            }}
                        >
                            <option value="">Semua Jenis</option>
                            {claimTypes.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.icon} {t.label}
                                </option>
                            ))}
                        </SelectInput>
                    </div>

                    {loading ? (
                        <TableSkeleton rows={6} cols={8} />
                    ) : error ? (
                        <ErrorState onRetry={reload} />
                    ) : rows?.length === 0 ? (
                        <EmptyState
                            title="Belum ada pengajuan"
                            description="Buat pengajuan reimbursement pertama Anda."
                        />
                    ) : (
                        <>
                            <div className="hidden md:block">
                                <Table>
                                    <THead>
                                        <TR>
                                            <TH>Nomor</TH>
                                            <TH>Jenis</TH>
                                            <TH>Judul</TH>
                                            <TH>Kategori</TH>
                                            <TH>Pengaju</TH>
                                            <TH>Nominal</TH>
                                            <TH>Status</TH>
                                            <TH>Dibuat</TH>
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
                                                <TD>
                                                    {r.claim_type && (
                                                        <Badge
                                                            color={
                                                                r.claim_type
                                                                    .color
                                                            }
                                                        >
                                                            {r.claim_type.icon}{' '}
                                                            {r.claim_type.label}
                                                        </Badge>
                                                    )}
                                                </TD>
                                                <TD>{r.title}</TD>
                                                <TD>
                                                    {r.category?.name ?? '-'}
                                                </TD>
                                                <TD>{r.user?.name ?? '-'}</TD>
                                                <TD>{r.formatted_amount}</TD>
                                                <StatusCell status={r.status} />
                                                <TD>
                                                    {formatDate(r.created_at)}
                                                </TD>
                                            </TR>
                                        ))}
                                    </TBody>
                                </Table>
                            </div>

                            <MobileList>
                                {(rows ?? []).map((r) => (
                                    <MobileListItem key={r.id}>
                                        <div className="flex items-center justify-between gap-3">
                                            <Link
                                                href={`/reimbursements/${r.id}`}
                                                className="inline-flex min-h-[44px] items-center font-medium text-indigo-600 hover:underline"
                                            >
                                                {r.reimbursement_number}
                                            </Link>
                                            <Badge color={r.status.color}>
                                                {r.status.label}
                                            </Badge>
                                        </div>
                                        <p className="text-sm text-gray-800 dark:text-gray-200">
                                            {r.title}
                                        </p>
                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {r.claim_type
                                                ? `${r.claim_type.icon} ${r.claim_type.label} · `
                                                : ''}
                                            {r.user?.name ?? '-'} ·{' '}
                                            {r.formatted_amount} ·{' '}
                                            {formatDate(r.created_at)}
                                        </p>
                                    </MobileListItem>
                                ))}
                            </MobileList>

                            <Pagination meta={meta} onPage={setPage} />
                        </>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
