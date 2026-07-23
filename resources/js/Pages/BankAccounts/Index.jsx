import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/ui/Badge';
import Card from '@/Components/ui/Card';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import EmptyState from '@/Components/ui/EmptyState';
import ErrorState from '@/Components/ui/ErrorState';
import SelectInput from '@/Components/ui/SelectInput';
import { Loading } from '@/Components/ui/Spinner';
import { Table, TBody, TD, TH, THead, TR } from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { toast } from '@/lib/toast';
import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const EMPTY = {
    bank_id: '',
    account_number: '',
    account_holder_name: '',
    is_primary: false,
};

export default function Index() {
    const [accounts, setAccounts] = useState(null);
    const [banks, setBanks] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [modal, setModal] = useState(null); // 'form' | id-to-delete
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(EMPTY);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const reqRef = useRef(0);

    const reload = useCallback(() => {
        const token = ++reqRef.current;
        setLoading(true);
        setError(null);
        api.get('/api/bank-accounts')
            .then((d) => {
                if (token === reqRef.current) setAccounts(d.data);
            })
            .catch((e) => {
                if (token === reqRef.current) setError(true);
                handleApiError(e);
            })
            .finally(() => {
                if (token === reqRef.current) setLoading(false);
            });
    }, []);

    useEffect(() => {
        reload();
        api.get('/api/options/banks')
            .then((d) => setBanks(d.data))
            .catch(() => {});
    }, [reload]);

    function openCreate() {
        setEditing(null);
        setForm(EMPTY);
        setErrors({});
        setModal('form');
    }

    function openEdit(account) {
        setEditing(account);
        setForm({
            bank_id: account.bank_id,
            account_number: account.account_number,
            account_holder_name: account.account_holder_name,
            is_primary: account.is_primary,
        });
        setErrors({});
        setModal('form');
    }

    async function save() {
        setBusy(true);
        setErrors({});
        try {
            if (editing) {
                await api.put(`/api/bank-accounts/${editing.id}`, form);
                toast('Rekening diperbarui.');
            } else {
                await api.post('/api/bank-accounts', form);
                toast('Rekening ditambahkan.');
            }
            setModal(null);
            reload();
        } catch (e) {
            setErrors(handleApiError(e, 'Gagal menyimpan rekening.'));
        } finally {
            setBusy(false);
        }
    }

    async function setPrimary(id) {
        try {
            await api.post(`/api/bank-accounts/${id}/primary`);
            toast('Rekening utama diperbarui.');
            reload();
        } catch (e) {
            handleApiError(e);
        }
    }

    async function remove(id) {
        setBusy(true);
        try {
            await api.delete(`/api/bank-accounts/${id}`);
            toast('Rekening dihapus.');
            setModal(null);
            reload();
        } catch (e) {
            handleApiError(e);
        } finally {
            setBusy(false);
        }
    }

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Rekening Bank Saya
                    </h2>
                    <PrimaryButton onClick={openCreate}>
                        + Tambah Rekening
                    </PrimaryButton>
                </div>
            }
        >
            <Head title="Rekening Bank" />

            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <Card>
                    {loading ? (
                        <Loading />
                    ) : error ? (
                        <ErrorState onRetry={reload} />
                    ) : accounts?.length === 0 ? (
                        <EmptyState
                            title="Belum ada rekening"
                            description="Tambahkan rekening untuk menerima pembayaran reimbursement."
                        />
                    ) : (
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Bank</TH>
                                    <TH>Nomor Rekening</TH>
                                    <TH>Pemilik</TH>
                                    <TH>Status</TH>
                                    <TH>Aksi</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {(accounts ?? []).map((a) => (
                                    <TR key={a.id}>
                                        <TD className="font-medium">
                                            {a.bank?.name} ({a.bank?.code})
                                        </TD>
                                        <TD>{a.account_number}</TD>
                                        <TD>{a.account_holder_name}</TD>
                                        <TD>
                                            <span className="flex gap-1.5">
                                                {a.is_primary && (
                                                    <Badge color="indigo">
                                                        Utama
                                                    </Badge>
                                                )}
                                                <Badge
                                                    color={
                                                        a.is_active
                                                            ? 'green'
                                                            : 'gray'
                                                    }
                                                >
                                                    {a.is_active
                                                        ? 'Aktif'
                                                        : 'Nonaktif'}
                                                </Badge>
                                            </span>
                                        </TD>
                                        <TD>
                                            <span className="flex gap-3 text-sm">
                                                {!a.is_primary && (
                                                    <button
                                                        onClick={() =>
                                                            setPrimary(a.id)
                                                        }
                                                        className="text-indigo-600 hover:underline"
                                                    >
                                                        Jadikan Utama
                                                    </button>
                                                )}
                                                <button
                                                    onClick={() => openEdit(a)}
                                                    className="text-gray-500 hover:underline"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        setModal(a.id)
                                                    }
                                                    className="text-red-600 hover:underline"
                                                >
                                                    Hapus
                                                </button>
                                            </span>
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    )}
                </Card>
            </div>

            {/* Form modal */}
            <Modal
                show={modal === 'form'}
                onClose={() => setModal(null)}
                maxWidth="md"
            >
                <div className="p-6">
                    <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                        {editing ? 'Edit Rekening' : 'Tambah Rekening'}
                    </h3>
                    <div className="mt-4 space-y-4">
                        <div>
                            <InputLabel htmlFor="bank_id" value="Bank *" />
                            <SelectInput
                                id="bank_id"
                                className="mt-1 block w-full"
                                value={form.bank_id}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        bank_id: e.target.value,
                                    }))
                                }
                            >
                                <option value="">— pilih bank —</option>
                                {banks.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {b.name} ({b.code})
                                    </option>
                                ))}
                            </SelectInput>
                            <InputError
                                message={errors.bank_id?.[0]}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel
                                htmlFor="account_number"
                                value="Nomor Rekening * (6–30 digit)"
                            />
                            <TextInput
                                id="account_number"
                                className="mt-1 block w-full"
                                value={form.account_number}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        account_number: e.target.value,
                                    }))
                                }
                            />
                            <InputError
                                message={errors.account_number?.[0]}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel
                                htmlFor="account_holder_name"
                                value="Nama Pemilik Rekening *"
                            />
                            <TextInput
                                id="account_holder_name"
                                className="mt-1 block w-full"
                                value={form.account_holder_name}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        account_holder_name: e.target.value,
                                    }))
                                }
                            />
                            <InputError
                                message={errors.account_holder_name?.[0]}
                                className="mt-1"
                            />
                        </div>
                        <label className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input
                                type="checkbox"
                                className="rounded border-gray-300"
                                checked={form.is_primary}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        is_primary: e.target.checked,
                                    }))
                                }
                            />
                            Jadikan rekening utama
                        </label>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={() => setModal(null)}>
                            Batal
                        </SecondaryButton>
                        <PrimaryButton onClick={save} disabled={busy}>
                            {busy ? 'Menyimpan…' : 'Simpan'}
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>

            <ConfirmDialog
                show={typeof modal === 'number'}
                title="Hapus Rekening?"
                message="Rekening yang dihapus tidak dapat dipilih untuk pembayaran."
                confirmLabel="Hapus"
                busy={busy}
                onConfirm={() => remove(modal)}
                onCancel={() => setModal(null)}
            />
        </AuthenticatedLayout>
    );
}
