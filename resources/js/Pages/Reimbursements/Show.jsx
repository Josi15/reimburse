import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/ui/Badge';
import Card from '@/Components/ui/Card';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import SelectInput from '@/Components/ui/SelectInput';
import { Loading } from '@/Components/ui/Spinner';
import TextareaInput from '@/Components/ui/TextareaInput';
import useAuth from '@/hooks/useAuth';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { toast } from '@/lib/toast';
import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

/** Modal aksi approval (approve / reject / revisi). */
function ActionModal({ show, action, claimId, onClose, onDone }) {
    const [notes, setNotes] = useState('');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState({});

    const config =
        {
            approve: {
                title: 'Setujui Pengajuan',
                label: 'Catatan (opsional)',
                button: 'Setujui',
                required: false,
            },
            reject: {
                title: 'Tolak Pengajuan',
                label: 'Alasan penolakan *',
                button: 'Tolak',
                required: true,
            },
            revision: {
                title: 'Minta Revisi',
                label: 'Catatan revisi *',
                button: 'Minta Revisi',
                required: true,
            },
        }[action] ?? {};

    async function submit() {
        setBusy(true);
        setErrors({});
        try {
            await api.post(
                `/api/reimbursements/${claimId}/${action}`,
                notes ? { notes } : {},
            );
            toast(`Berhasil: ${config.button}.`);
            onDone();
        } catch (e) {
            setErrors(handleApiError(e, 'Aksi gagal.'));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                    {config.title}
                </h3>
                <div className="mt-4">
                    <InputLabel value={config.label} />
                    <TextareaInput
                        rows={3}
                        className="mt-1 block w-full"
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                    />
                    <InputError message={errors.notes?.[0]} className="mt-1" />
                </div>
                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={onClose}>Batal</SecondaryButton>
                    <PrimaryButton
                        onClick={submit}
                        disabled={busy || (config.required && !notes.trim())}
                    >
                        {busy ? 'Memproses…' : config.button}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    );
}

/** Modal pembayaran (Finance). */
function PayModal({ show, claim, onClose, onDone }) {
    const [form, setForm] = useState({
        method: 'bank_transfer',
        reference_number: '',
        notes: '',
        proof: null,
    });
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState({});

    async function submit() {
        setBusy(true);
        setErrors({});
        const fd = new FormData();
        fd.append('method', form.method);
        if (form.reference_number)
            fd.append('reference_number', form.reference_number);
        if (form.notes) fd.append('notes', form.notes);
        if (form.proof) fd.append('proof', form.proof);

        try {
            await api.post(`/api/reimbursements/${claim.id}/pay`, fd);
            toast('Pembayaran berhasil diproses.');
            onDone();
        } catch (e) {
            setErrors(handleApiError(e, 'Pembayaran gagal.'));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                    Proses Pembayaran
                </h3>
                <p className="mt-1 text-sm text-gray-500">
                    {claim?.reimbursement_number} · {claim?.formatted_amount}
                </p>

                <div className="mt-4 space-y-4">
                    <div>
                        <InputLabel value="Metode *" />
                        <SelectInput
                            className="mt-1 block w-full"
                            value={form.method}
                            onChange={(e) =>
                                setForm((f) => ({
                                    ...f,
                                    method: e.target.value,
                                }))
                            }
                        >
                            <option value="bank_transfer">Transfer Bank</option>
                            <option value="cash">Tunai</option>
                            <option value="other">Lainnya</option>
                        </SelectInput>
                    </div>
                    <div>
                        <InputLabel value="Nomor Referensi" />
                        <TextInput
                            className="mt-1 block w-full"
                            value={form.reference_number}
                            onChange={(e) =>
                                setForm((f) => ({
                                    ...f,
                                    reference_number: e.target.value,
                                }))
                            }
                        />
                    </div>
                    <div>
                        <InputLabel value="Catatan Finance" />
                        <TextareaInput
                            rows={2}
                            className="mt-1 block w-full"
                            value={form.notes}
                            onChange={(e) =>
                                setForm((f) => ({
                                    ...f,
                                    notes: e.target.value,
                                }))
                            }
                        />
                    </div>
                    <div>
                        <InputLabel value="Bukti Pembayaran (JPG/PNG/PDF)" />
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.pdf"
                            className="mt-1 block w-full text-sm text-gray-500"
                            onChange={(e) =>
                                setForm((f) => ({
                                    ...f,
                                    proof: e.target.files[0] ?? null,
                                }))
                            }
                        />
                        <InputError
                            message={errors.proof?.[0] ?? errors.payment?.[0]}
                            className="mt-1"
                        />
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={onClose}>Batal</SecondaryButton>
                    <PrimaryButton onClick={submit} disabled={busy}>
                        {busy ? 'Memproses…' : 'Bayar Sekarang'}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    );
}

export default function Show({ id }) {
    const { user, can } = useAuth();
    const [claim, setClaim] = useState(null);
    const [timeline, setTimeline] = useState([]);
    const [loading, setLoading] = useState(true);
    const [modal, setModal] = useState(null); // 'approve'|'reject'|'revision'|'pay'|'delete'|'submit'
    const [busy, setBusy] = useState(false);

    const reload = useCallback(() => {
        setLoading(true);
        api.get(`/api/reimbursements/${id}`)
            .then((d) => {
                setClaim(d.data);
                setTimeline(d.timeline ?? []);
            })
            .catch((e) => handleApiError(e))
            .finally(() => setLoading(false));
    }, [id]);

    useEffect(() => {
        reload();
    }, [reload]);

    const isOwner = claim && user && claim.user?.id === user.id;
    const status = claim?.status?.value;

    const canSubmit =
        isOwner && claim?.is_editable && can('reimbursement.submit');
    const canEdit =
        isOwner && claim?.is_editable && can('reimbursement.update');
    const canDelete =
        isOwner && status === 'draft' && can('reimbursement.delete');
    const canApprove =
        (status === 'submitted' && can('reimbursement.approve.manager')) ||
        (status === 'manager_approved' && can('reimbursement.approve.finance'));
    const canPay = status === 'finance_approved' && can('payment.process');

    async function doSubmit() {
        setBusy(true);
        try {
            await api.post(`/api/reimbursements/${id}/submit`);
            toast('Pengajuan berhasil dikirim.');
            setModal(null);
            reload();
        } catch (e) {
            handleApiError(e, 'Gagal mengirim pengajuan.');
        } finally {
            setBusy(false);
        }
    }

    async function doDelete() {
        setBusy(true);
        try {
            await api.delete(`/api/reimbursements/${id}`);
            toast('Draft dihapus.');
            router.visit('/reimbursements');
        } catch (e) {
            handleApiError(e, 'Gagal menghapus.');
            setBusy(false);
        }
    }

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Detail Reimbursement
                </h2>
            }
        >
            <Head title={claim?.reimbursement_number ?? 'Detail'} />

            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                {loading || !claim ? (
                    <Loading />
                ) : (
                    <div className="grid gap-6 lg:grid-cols-3">
                        {/* Detail utama */}
                        <div className="space-y-6 lg:col-span-2">
                            <Card className="p-6">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="text-sm text-gray-400">
                                            {claim.reimbursement_number}
                                        </div>
                                        <h3 className="mt-0.5 text-lg font-bold text-gray-800 dark:text-gray-100">
                                            {claim.title}
                                        </h3>
                                    </div>
                                    <Badge color={claim.status.color}>
                                        {claim.status.label}
                                    </Badge>
                                </div>

                                <dl className="mt-5 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                                    <div>
                                        <dt className="text-gray-400">
                                            Nominal
                                        </dt>
                                        <dd className="font-semibold text-gray-800 dark:text-gray-100">
                                            {claim.formatted_amount}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-gray-400">
                                            Kategori
                                        </dt>
                                        <dd className="text-gray-700 dark:text-gray-200">
                                            {claim.category?.name ?? '-'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-gray-400">
                                            Department
                                        </dt>
                                        <dd className="text-gray-700 dark:text-gray-200">
                                            {claim.department?.name ?? '-'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-gray-400">
                                            Pengaju
                                        </dt>
                                        <dd className="text-gray-700 dark:text-gray-200">
                                            {claim.user?.name ?? '-'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-gray-400">
                                            Tgl Pengeluaran
                                        </dt>
                                        <dd className="text-gray-700 dark:text-gray-200">
                                            {formatDate(claim.expense_date)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-gray-400">
                                            Diajukan
                                        </dt>
                                        <dd className="text-gray-700 dark:text-gray-200">
                                            {formatDate(
                                                claim.submitted_at,
                                                true,
                                            )}
                                        </dd>
                                    </div>
                                </dl>

                                <div className="mt-5 border-t border-gray-100 pt-4 dark:border-gray-700">
                                    <div className="text-sm text-gray-400">
                                        Alasan
                                    </div>
                                    <p className="mt-1 text-sm text-gray-700 dark:text-gray-200">
                                        {claim.reason}
                                    </p>
                                    {claim.description && (
                                        <>
                                            <div className="mt-3 text-sm text-gray-400">
                                                Deskripsi
                                            </div>
                                            <p className="mt-1 text-sm text-gray-700 dark:text-gray-200">
                                                {claim.description}
                                            </p>
                                        </>
                                    )}
                                </div>

                                {/* Aksi */}
                                <div className="mt-6 flex flex-wrap gap-3 border-t border-gray-100 pt-5 dark:border-gray-700">
                                    {canSubmit && (
                                        <PrimaryButton
                                            onClick={() => setModal('submit')}
                                        >
                                            Kirim Pengajuan
                                        </PrimaryButton>
                                    )}
                                    {canEdit && (
                                        <Link
                                            href={`/reimbursements/${id}/edit`}
                                        >
                                            <SecondaryButton>
                                                Edit
                                            </SecondaryButton>
                                        </Link>
                                    )}
                                    {canDelete && (
                                        <DangerButton
                                            onClick={() => setModal('delete')}
                                        >
                                            Hapus Draft
                                        </DangerButton>
                                    )}
                                    {canApprove && (
                                        <>
                                            <PrimaryButton
                                                onClick={() =>
                                                    setModal('approve')
                                                }
                                            >
                                                Setujui
                                            </PrimaryButton>
                                            <DangerButton
                                                onClick={() =>
                                                    setModal('reject')
                                                }
                                            >
                                                Tolak
                                            </DangerButton>
                                            <SecondaryButton
                                                onClick={() =>
                                                    setModal('revision')
                                                }
                                            >
                                                Minta Revisi
                                            </SecondaryButton>
                                        </>
                                    )}
                                    {canPay && (
                                        <PrimaryButton
                                            onClick={() => setModal('pay')}
                                        >
                                            💸 Proses Pembayaran
                                        </PrimaryButton>
                                    )}
                                </div>
                            </Card>

                            {/* Lampiran */}
                            <Card className="p-6">
                                <h3 className="font-semibold text-gray-700 dark:text-gray-200">
                                    Lampiran ({claim.attachments?.length ?? 0})
                                </h3>
                                {(claim.attachments ?? []).length === 0 ? (
                                    <p className="mt-2 text-sm text-gray-400">
                                        Tidak ada lampiran (pengajuan tanpa
                                        bukti).
                                    </p>
                                ) : (
                                    <ul className="mt-3 divide-y divide-gray-100 dark:divide-gray-700">
                                        {claim.attachments.map((f) => (
                                            <li
                                                key={f.id}
                                                className="flex items-center justify-between py-2 text-sm"
                                            >
                                                <span className="text-gray-700 dark:text-gray-200">
                                                    📎 {f.file_name}{' '}
                                                    <span className="text-gray-400">
                                                        ({f.human_size})
                                                    </span>
                                                </span>
                                                <span className="flex gap-3">
                                                    <a
                                                        href={`/api/attachments/${f.id}/preview`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="text-indigo-600 hover:underline"
                                                    >
                                                        Preview
                                                    </a>
                                                    <a
                                                        href={`/api/attachments/${f.id}/download`}
                                                        className="text-indigo-600 hover:underline"
                                                    >
                                                        Download
                                                    </a>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Card>
                        </div>

                        {/* Timeline */}
                        <Card className="h-fit p-6">
                            <h3 className="font-semibold text-gray-700 dark:text-gray-200">
                                Timeline Status
                            </h3>
                            <ol className="mt-4 space-y-4 border-l-2 border-gray-200 pl-4 dark:border-gray-700">
                                {timeline.map((t, i) => (
                                    <li key={i} className="relative">
                                        <span className="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full bg-indigo-500" />
                                        <div className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                            {t.label}
                                        </div>
                                        <div className="text-xs text-gray-400">
                                            {formatDate(t.at, true)}
                                            {t.by ? ` · ${t.by}` : ''}
                                        </div>
                                        {t.note && (
                                            <div className="mt-1 rounded bg-gray-50 px-2 py-1 text-xs text-gray-500 dark:bg-gray-900/40">
                                                “{t.note}”
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        </Card>
                    </div>
                )}
            </div>

            {/* Modals */}
            {['approve', 'reject', 'revision'].includes(modal) && (
                <ActionModal
                    show
                    action={modal}
                    claimId={id}
                    onClose={() => setModal(null)}
                    onDone={() => {
                        setModal(null);
                        reload();
                    }}
                />
            )}
            <PayModal
                show={modal === 'pay'}
                claim={claim}
                onClose={() => setModal(null)}
                onDone={() => {
                    setModal(null);
                    reload();
                }}
            />
            <ConfirmDialog
                show={modal === 'submit'}
                title="Kirim Pengajuan?"
                message="Setelah dikirim, pengajuan tidak dapat diedit hingga ada keputusan."
                confirmLabel="Kirim"
                busy={busy}
                onConfirm={doSubmit}
                onCancel={() => setModal(null)}
            />
            <ConfirmDialog
                show={modal === 'delete'}
                title="Hapus Draft?"
                message="Draft yang dihapus tidak dapat dikembalikan dari halaman ini."
                confirmLabel="Hapus"
                busy={busy}
                onConfirm={doDelete}
                onCancel={() => setModal(null)}
            />
        </AuthenticatedLayout>
    );
}
