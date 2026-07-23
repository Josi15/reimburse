import SecondaryButton from '@/Components/SecondaryButton';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import ErrorState from '@/Components/ui/ErrorState';
import Pagination from '@/Components/ui/Pagination';
import { Loading } from '@/Components/ui/Spinner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { cn, formatDate } from '@/lib/format';
import { toast } from '@/lib/toast';
import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

export default function Index() {
    const [items, setItems] = useState(null);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const reqRef = useRef(0);

    const reload = useCallback(() => {
        const token = ++reqRef.current;
        setLoading(true);
        setError(null);
        api.get(`/api/notifications?page=${page}`)
            .then((d) => {
                if (token !== reqRef.current) return;
                setItems(d.data);
                setMeta({
                    current_page: d.current_page,
                    last_page: d.last_page,
                    from: d.from,
                    to: d.to,
                    total: d.total,
                });
            })
            .catch((e) => {
                if (token === reqRef.current) setError(true);
                handleApiError(e);
            })
            .finally(() => {
                if (token === reqRef.current) setLoading(false);
            });
    }, [page]);

    useEffect(() => {
        reload();
    }, [reload]);

    async function markRead(id) {
        try {
            await api.post(`/api/notifications/${id}/read`);
            window.dispatchEvent(new CustomEvent('notifications-read'));
            reload();
        } catch (e) {
            handleApiError(e);
        }
    }

    async function markAll() {
        try {
            await api.post('/api/notifications/read-all');
            toast('Semua notifikasi ditandai dibaca.');
            window.dispatchEvent(new CustomEvent('notifications-read'));
            reload();
        } catch (e) {
            handleApiError(e);
        }
    }

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Notifikasi
                    </h2>
                    <SecondaryButton onClick={markAll}>
                        Tandai semua dibaca
                    </SecondaryButton>
                </div>
            }
        >
            <Head title="Notifikasi" />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <Card>
                    {loading ? (
                        <Loading />
                    ) : error ? (
                        <ErrorState onRetry={reload} />
                    ) : items?.length === 0 ? (
                        <EmptyState title="Tidak ada notifikasi" />
                    ) : (
                        <>
                            <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                                {(items ?? []).map((n) => (
                                    <li
                                        key={n.id}
                                        className={cn(
                                            'flex items-start gap-3 px-5 py-4',
                                            !n.read_at &&
                                                'bg-indigo-50/50 dark:bg-indigo-900/10',
                                        )}
                                    >
                                        <span className="mt-1 text-lg">
                                            {n.data?.type ===
                                            'reimbursement_paid'
                                                ? '💸'
                                                : n.data?.type ===
                                                    'reimbursement_submitted'
                                                  ? '📥'
                                                  : '🔔'}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm text-gray-700 dark:text-gray-200">
                                                {n.data?.message ??
                                                    'Notifikasi'}
                                            </p>
                                            <p className="mt-0.5 text-xs text-gray-400">
                                                {formatDate(n.created_at, true)}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1 text-xs">
                                            {n.data?.reimbursement_id && (
                                                <Link
                                                    href={`/reimbursements/${n.data.reimbursement_id}`}
                                                    className="text-indigo-600 hover:underline"
                                                >
                                                    Lihat →
                                                </Link>
                                            )}
                                            {!n.read_at && (
                                                <button
                                                    onClick={() =>
                                                        markRead(n.id)
                                                    }
                                                    className="text-gray-400 hover:underline"
                                                >
                                                    Tandai dibaca
                                                </button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            <Pagination meta={meta} onPage={setPage} />
                        </>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
