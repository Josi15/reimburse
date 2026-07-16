import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/ui/Badge';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import Pagination from '@/Components/ui/Pagination';
import SelectInput from '@/Components/ui/SelectInput';
import { Loading } from '@/Components/ui/Spinner';
import { Table, TBody, TD, TH, THead, TR } from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const EVENTS = [
    ['', 'Semua Event'],
    ['login', 'Login'],
    ['logout', 'Logout'],
    ['create', 'Create'],
    ['update', 'Update'],
    ['delete', 'Delete'],
    ['approve', 'Approve'],
    ['reject', 'Reject'],
    ['payment', 'Payment'],
];

const EVENT_COLORS = {
    login: 'blue',
    logout: 'gray',
    create: 'green',
    update: 'amber',
    delete: 'red',
    approve: 'indigo',
    reject: 'red',
    payment: 'green',
};

export default function Index() {
    const [filters, setFilters] = useState({ event: '', date_from: '', date_to: '', q: '' });
    const [rows, setRows] = useState(null);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [detail, setDetail] = useState(null);

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
        let active = true;
        setLoading(true);
        api.get(`/api/audit-logs?${query()}`)
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
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, JSON.stringify(filters)]);

    function exportAs(format) {
        const params = query();
        params.set('format', format);
        params.delete('page');
        window.open(`/api/audit-logs/export?${params}`, '_blank');
    }

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Activity Log
                </h2>
            }
        >
            <Head title="Activity Log" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <Card className="p-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <InputLabel value="Event" className="text-xs" />
                            <SelectInput className="mt-1 w-full text-sm" value={filters.event} onChange={set('event')}>
                                {EVENTS.map(([v, l]) => (
                                    <option key={v} value={v}>
                                        {l}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel value="Dari Tanggal" className="text-xs" />
                            <TextInput type="date" className="mt-1 w-full text-sm" value={filters.date_from} onChange={set('date_from')} />
                        </div>
                        <div>
                            <InputLabel value="Sampai" className="text-xs" />
                            <TextInput type="date" className="mt-1 w-full text-sm" value={filters.date_to} onChange={set('date_to')} />
                        </div>
                        <div>
                            <InputLabel value="Kata Kunci" className="text-xs" />
                            <TextInput className="mt-1 w-full text-sm" placeholder="Deskripsi / IP / URL" value={filters.q} onChange={set('q')} />
                        </div>
                    </div>
                    <div className="mt-4 flex gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <SecondaryButton onClick={() => exportAs('xlsx')}>⬇ Excel</SecondaryButton>
                        <SecondaryButton onClick={() => exportAs('csv')}>⬇ CSV</SecondaryButton>
                    </div>
                </Card>

                <Card>
                    {loading ? (
                        <Loading />
                    ) : rows?.length === 0 ? (
                        <EmptyState title="Tidak ada aktivitas" />
                    ) : (
                        <>
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Waktu</TH>
                                        <TH>User</TH>
                                        <TH>Event</TH>
                                        <TH>Entitas</TH>
                                        <TH>Deskripsi</TH>
                                        <TH>IP</TH>
                                        <TH>Detail</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {rows.map((l) => (
                                        <TR key={l.id}>
                                            <TD className="whitespace-nowrap">{formatDate(l.created_at, true)}</TD>
                                            <TD>{l.user?.name ?? 'Sistem'}</TD>
                                            <TD>
                                                <Badge color={EVENT_COLORS[l.event] ?? 'gray'}>{l.event_label}</Badge>
                                            </TD>
                                            <TD>
                                                {l.auditable_type ? `${l.auditable_type} #${l.auditable_id}` : '-'}
                                            </TD>
                                            <TD className="max-w-xs truncate">{l.description ?? '-'}</TD>
                                            <TD>{l.ip_address ?? '-'}</TD>
                                            <TD>
                                                <button
                                                    onClick={() => setDetail(l)}
                                                    className="text-sm text-indigo-600 hover:underline"
                                                >
                                                    Lihat
                                                </button>
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

            {/* Detail modal */}
            <Modal show={!!detail} onClose={() => setDetail(null)} maxWidth="2xl">
                {detail && (
                    <div className="p-6">
                        <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                            Detail Aktivitas #{detail.id}
                        </h3>
                        <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                            <div>
                                <dt className="text-gray-400">Waktu</dt>
                                <dd>{formatDate(detail.created_at, true)}</dd>
                            </div>
                            <div>
                                <dt className="text-gray-400">User</dt>
                                <dd>{detail.user?.name ?? 'Sistem'}</dd>
                            </div>
                            <div>
                                <dt className="text-gray-400">Event</dt>
                                <dd>{detail.event_label}</dd>
                            </div>
                            <div>
                                <dt className="text-gray-400">IP / Browser</dt>
                                <dd className="truncate" title={detail.user_agent}>
                                    {detail.ip_address ?? '-'}
                                </dd>
                            </div>
                            <div className="col-span-2">
                                <dt className="text-gray-400">URL</dt>
                                <dd className="truncate">{detail.url ?? '-'}</dd>
                            </div>
                        </dl>

                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <div className="text-xs font-semibold uppercase text-gray-400">Data Lama</div>
                                <pre className="mt-1 max-h-48 overflow-auto rounded bg-gray-50 p-3 text-xs dark:bg-gray-900/50">
                                    {detail.old_values ? JSON.stringify(detail.old_values, null, 2) : '—'}
                                </pre>
                            </div>
                            <div>
                                <div className="text-xs font-semibold uppercase text-gray-400">Data Baru</div>
                                <pre className="mt-1 max-h-48 overflow-auto rounded bg-gray-50 p-3 text-xs dark:bg-gray-900/50">
                                    {detail.new_values ? JSON.stringify(detail.new_values, null, 2) : '—'}
                                </pre>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end">
                            <SecondaryButton onClick={() => setDetail(null)}>Tutup</SecondaryButton>
                        </div>
                    </div>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
