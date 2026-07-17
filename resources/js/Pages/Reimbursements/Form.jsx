import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Card from '@/Components/ui/Card';
import SelectInput from '@/Components/ui/SelectInput';
import { Loading } from '@/Components/ui/Spinner';
import TextareaInput from '@/Components/ui/TextareaInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { rupiah } from '@/lib/format';
import { toast } from '@/lib/toast';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Form({ id = null }) {
    const isEdit = id !== null;
    const [loading, setLoading] = useState(isEdit);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState({});
    const [categories, setCategories] = useState([]);
    const [accounts, setAccounts] = useState([]);
    const [existingFiles, setExistingFiles] = useState([]);
    const [deleteIds, setDeleteIds] = useState([]);
    const [form, setForm] = useState({
        title: '',
        category_id: '',
        amount: '',
        expense_date: '',
        bank_account_id: '',
        reason: '',
        description: '',
        attachments: [],
    });

    const set = (key) => (e) =>
        setForm((f) => ({ ...f, [key]: e.target.value }));

    useEffect(() => {
        api.get('/api/options/categories')
            .then((d) => setCategories(d.data))
            .catch(() => {});
        api.get('/api/bank-accounts')
            .then((d) => setAccounts(d.data.filter((a) => a.is_active)))
            .catch(() => {});

        if (isEdit) {
            api.get(`/api/reimbursements/${id}`)
                .then((d) => {
                    const r = d.data;
                    setForm((f) => ({
                        ...f,
                        title: r.title ?? '',
                        category_id: r.category_id ?? '',
                        amount: r.amount ?? '',
                        expense_date: r.expense_date ?? '',
                        bank_account_id: r.bank_account_id ?? '',
                        reason: r.reason ?? '',
                        description: r.description ?? '',
                    }));
                    setExistingFiles(r.attachments ?? []);
                })
                .catch((e) => handleApiError(e))
                .finally(() => setLoading(false));
        }
    }, [id, isEdit]);

    const selectedCategory = categories.find(
        (c) => String(c.id) === String(form.category_id),
    );

    async function submit(e) {
        e.preventDefault();
        setBusy(true);
        setErrors({});

        const fd = new FormData();
        [
            'title',
            'category_id',
            'amount',
            'expense_date',
            'bank_account_id',
            'reason',
            'description',
        ].forEach((k) => {
            if (form[k] !== '' && form[k] !== null) fd.append(k, form[k]);
        });
        [...form.attachments].forEach((file) =>
            fd.append('attachments[]', file),
        );
        deleteIds.forEach((did) => fd.append('delete_attachment_ids[]', did));

        try {
            if (isEdit) {
                fd.append('_method', 'PUT'); // method spoofing untuk multipart
                await api.post(`/api/reimbursements/${id}`, fd);
                toast('Draft berhasil diperbarui.');
                router.visit(`/reimbursements/${id}`);
            } else {
                const res = await api.post('/api/reimbursements', fd);
                toast('Draft berhasil dibuat.');
                router.visit(`/reimbursements/${res.data.id}`);
            }
        } catch (err) {
            setErrors(handleApiError(err, 'Gagal menyimpan.'));
        } finally {
            setBusy(false);
        }
    }

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {isEdit ? 'Edit Pengajuan' : 'Buat Pengajuan Reimbursement'}
                </h2>
            }
        >
            <Head title={isEdit ? 'Edit Pengajuan' : 'Buat Pengajuan'} />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <Card className="p-6">
                    {loading ? (
                        <Loading />
                    ) : (
                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <InputLabel value="Judul *" />
                                <TextInput
                                    className="mt-1 block w-full"
                                    value={form.title}
                                    onChange={set('title')}
                                    required
                                />
                                <InputError
                                    message={errors.title?.[0]}
                                    className="mt-1"
                                />
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <InputLabel value="Kategori *" />
                                    <SelectInput
                                        className="mt-1 block w-full"
                                        value={form.category_id}
                                        onChange={set('category_id')}
                                        required
                                    >
                                        <option value="">
                                            — pilih kategori —
                                        </option>
                                        {categories.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </SelectInput>
                                    {selectedCategory?.max_amount && (
                                        <p className="mt-1 text-xs text-gray-400">
                                            Plafon:{' '}
                                            {rupiah(
                                                selectedCategory.max_amount,
                                            )}
                                        </p>
                                    )}
                                    <InputError
                                        message={errors.category_id?.[0]}
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <InputLabel value="Nominal (Rp) *" />
                                    <TextInput
                                        type="number"
                                        min="1"
                                        className="mt-1 block w-full"
                                        value={form.amount}
                                        onChange={set('amount')}
                                        required
                                    />
                                    <InputError
                                        message={errors.amount?.[0]}
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <InputLabel value="Tanggal Pengeluaran" />
                                    <TextInput
                                        type="date"
                                        className="mt-1 block w-full"
                                        value={form.expense_date}
                                        onChange={set('expense_date')}
                                    />
                                    <InputError
                                        message={errors.expense_date?.[0]}
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <InputLabel value="Rekening Tujuan" />
                                    <SelectInput
                                        className="mt-1 block w-full"
                                        value={form.bank_account_id}
                                        onChange={set('bank_account_id')}
                                    >
                                        <option value="">
                                            — pilih rekening —
                                        </option>
                                        {accounts.map((a) => (
                                            <option key={a.id} value={a.id}>
                                                {a.bank?.code} ·{' '}
                                                {a.masked_number} (
                                                {a.account_holder_name})
                                            </option>
                                        ))}
                                    </SelectInput>
                                    <p className="mt-1 text-xs text-gray-400">
                                        Kelola rekening di menu Rekening Bank.
                                    </p>
                                    <InputError
                                        message={errors.bank_account_id?.[0]}
                                        className="mt-1"
                                    />
                                </div>
                            </div>

                            <div>
                                <InputLabel value="Alasan *" />
                                <TextareaInput
                                    rows={2}
                                    className="mt-1 block w-full"
                                    value={form.reason}
                                    onChange={set('reason')}
                                    required
                                />
                                <InputError
                                    message={errors.reason?.[0]}
                                    className="mt-1"
                                />
                            </div>

                            <div>
                                <InputLabel value="Deskripsi" />
                                <TextareaInput
                                    rows={3}
                                    className="mt-1 block w-full"
                                    value={form.description}
                                    onChange={set('description')}
                                />
                            </div>

                            {isEdit && existingFiles.length > 0 && (
                                <div>
                                    <InputLabel value="Lampiran Tersimpan" />
                                    <ul className="mt-2 space-y-1 text-sm">
                                        {existingFiles.map((f) => (
                                            <li
                                                key={f.id}
                                                className="flex items-center gap-2"
                                            >
                                                <input
                                                    type="checkbox"
                                                    className="rounded border-gray-300"
                                                    checked={deleteIds.includes(
                                                        f.id,
                                                    )}
                                                    onChange={(e) =>
                                                        setDeleteIds((prev) =>
                                                            e.target.checked
                                                                ? [
                                                                      ...prev,
                                                                      f.id,
                                                                  ]
                                                                : prev.filter(
                                                                      (x) =>
                                                                          x !==
                                                                          f.id,
                                                                  ),
                                                        )
                                                    }
                                                />
                                                <span
                                                    className={
                                                        deleteIds.includes(f.id)
                                                            ? 'text-gray-400 line-through'
                                                            : ''
                                                    }
                                                >
                                                    {f.file_name} (
                                                    {f.human_size})
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                    <p className="mt-1 text-xs text-gray-400">
                                        Centang untuk menghapus saat disimpan.
                                    </p>
                                </div>
                            )}

                            <div>
                                <InputLabel value="Upload Bukti (JPG/PNG/PDF, maks 5 MB per file)" />
                                <input
                                    type="file"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    className="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-indigo-700 hover:file:bg-indigo-100"
                                    onChange={(e) =>
                                        setForm((f) => ({
                                            ...f,
                                            attachments: e.target.files,
                                        }))
                                    }
                                />
                                <InputError
                                    message={
                                        errors['attachments.0']?.[0] ??
                                        errors.attachments?.[0]
                                    }
                                    className="mt-1"
                                />
                            </div>

                            <div className="flex justify-end gap-3 border-t border-gray-100 pt-5 dark:border-gray-700">
                                <SecondaryButton
                                    type="button"
                                    onClick={() => window.history.back()}
                                >
                                    Batal
                                </SecondaryButton>
                                <PrimaryButton disabled={busy}>
                                    {busy ? 'Menyimpan…' : 'Simpan Draft'}
                                </PrimaryButton>
                            </div>
                        </form>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
