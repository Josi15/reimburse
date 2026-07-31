import ClaimTypeFields from '@/Components/ClaimTypeFields';
import ClaimTypePicker from '@/Components/ClaimTypePicker';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Breadcrumb from '@/Components/ui/Breadcrumb';
import Card from '@/Components/ui/Card';
import SelectInput from '@/Components/ui/SelectInput';
import { Loading } from '@/Components/ui/Spinner';
import TextareaInput from '@/Components/ui/TextareaInput';
import useAuth from '@/hooks/useAuth';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { rupiah } from '@/lib/format';
import { toast } from '@/lib/toast';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

// Selaras dengan config reimbursement.max_files_per_request (backend).
const MAX_FILES = 10;

export default function Form({ id = null }) {
    const isEdit = id !== null;
    const { user } = useAuth();
    const [loading, setLoading] = useState(isEdit);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState({});
    const [categories, setCategories] = useState([]);
    const [accounts, setAccounts] = useState([]);
    const [projects, setProjects] = useState([]);
    const [claimTypes, setClaimTypes] = useState([]);
    const [departments, setDepartments] = useState([]);
    const [quota, setQuota] = useState(null);
    const [existingFiles, setExistingFiles] = useState([]);
    const [deleteIds, setDeleteIds] = useState([]);
    const [form, setForm] = useState({
        claim_type: 'expense',
        title: '',
        category_id: '',
        amount: '',
        expense_date: '',
        bank_account_id: '',
        project_id: '',
        department_id: '',
        reason: '',
        description: '',
        details: {},
        attachments: [],
    });

    const set = (key) => (e) => {
        setForm((f) => ({ ...f, [key]: e.target.value }));
        // Bersihkan error field ini begitu user memperbaikinya.
        setErrors((prev) => (prev[key] ? { ...prev, [key]: undefined } : prev));
    };

    // Ganti jenis pengajuan → detail lama dikosongkan (field-nya berbeda).
    const setClaimType = (value) => {
        setForm((f) => ({ ...f, claim_type: value, details: {} }));
        setErrors({});
    };

    const setDetail = (key, value) => {
        setForm((f) => ({ ...f, details: { ...f.details, [key]: value } }));
        setErrors((prev) =>
            prev[`details.${key}`]
                ? { ...prev, [`details.${key}`]: undefined }
                : prev,
        );
    };

    // Tambah bukti secara bertahap (menumpuk, bukan menimpa) — bisa banyak struk.
    const addFiles = (fileList) => {
        setForm((f) => {
            const incoming = Array.from(fileList).filter(
                (nf) =>
                    !f.attachments.some(
                        (ef) => ef.name === nf.name && ef.size === nf.size,
                    ),
            );
            return {
                ...f,
                attachments: [...f.attachments, ...incoming].slice(
                    0,
                    MAX_FILES,
                ),
            };
        });
        setErrors((prev) => ({
            ...prev,
            attachments: undefined,
            'attachments.0': undefined,
        }));
    };

    const removeFile = (index) =>
        setForm((f) => ({
            ...f,
            attachments: f.attachments.filter((_, i) => i !== index),
        }));

    useEffect(() => {
        api.get('/api/options/categories')
            .then((d) => setCategories(d.data))
            .catch(() => {});
        api.get('/api/bank-accounts')
            .then((d) => setAccounts(d.data.filter((a) => a.is_active)))
            .catch(() => {});
        api.get('/api/options/projects')
            .then((d) => setProjects(d.data))
            .catch(() => {});
        api.get('/api/reimbursements/quota')
            .then((d) => setQuota(d))
            .catch(() => {});
        api.get('/api/options/claim-types')
            .then((d) => setClaimTypes(d.data))
            .catch(() => {});
        api.get('/api/options/departments')
            .then((d) => setDepartments(d.data))
            .catch(() => {});

        if (isEdit) {
            api.get(`/api/reimbursements/${id}`)
                .then((d) => {
                    const r = d.data;
                    setForm((f) => ({
                        ...f,
                        claim_type: r.claim_type?.value ?? 'expense',
                        details: r.details ?? {},
                        title: r.title ?? '',
                        category_id: r.category_id ?? '',
                        amount: r.amount ?? '',
                        expense_date: r.expense_date ?? '',
                        bank_account_id: r.bank_account_id ?? '',
                        project_id: r.project_id ?? '',
                        department_id: r.department_id ?? '',
                        reason: r.reason ?? '',
                        description: r.description ?? '',
                    }));
                    setExistingFiles(r.attachments ?? []);
                })
                .catch((e) => handleApiError(e))
                .finally(() => setLoading(false));
        }
    }, [id, isEdit]);

    // Saat membuat baru, departemen pengaju dipakai sebagai default (tetap
    // bisa diganti bila biayanya ditanggung unit lain).
    useEffect(() => {
        if (isEdit || !user?.department_id) return;
        setForm((f) =>
            f.department_id ? f : { ...f, department_id: user.department_id },
        );
    }, [isEdit, user?.department_id]);

    const selectedCategory = categories.find(
        (c) => String(c.id) === String(form.category_id),
    );

    const selectedType = claimTypes.find((t) => t.value === form.claim_type);

    // Jenis tertentu nominalnya hasil perkalian dua field (mis. jumlah × harga
    // satuan, jam × upah). Backend menghitung ulang; ini hanya pratinjau.
    const formula = selectedType?.amount_formula ?? null;
    const computedAmount = formula
        ? Math.round(
              (Number(form.details[formula[0]]) || 0) *
                  (Number(form.details[formula[1]]) || 0),
          )
        : null;
    const effectiveAmount = formula ? computedAmount : form.amount;

    async function submit(e) {
        e.preventDefault();
        setBusy(true);
        setErrors({});

        const fd = new FormData();
        [
            'claim_type',
            'title',
            'category_id',
            'expense_date',
            'bank_account_id',
            'project_id',
            'department_id',
            'reason',
            'description',
        ].forEach((k) => {
            if (form[k] !== '' && form[k] !== null) fd.append(k, form[k]);
        });

        // Nominal: manual untuk biaya, hasil hitung untuk barang/layanan/lembur.
        if (effectiveAmount !== '' && effectiveAmount !== null)
            fd.append('amount', effectiveAmount);

        Object.entries(form.details).forEach(([k, v]) => {
            if (v !== '' && v !== null && v !== undefined)
                fd.append(`details[${k}]`, v);
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
                <div>
                    <Breadcrumb
                        items={[
                            { label: 'Dashboard', href: '/dashboard' },
                            { label: 'Reimbursement', href: '/reimbursements' },
                            { label: isEdit ? 'Edit' : 'Buat' },
                        ]}
                    />
                    <h2 className="mt-1 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {isEdit ? 'Edit Pengajuan' : 'Buat Pengajuan Baru'}
                    </h2>
                </div>
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
                                <InputLabel value="Jenis Pengajuan *" />
                                <p className="mb-2 mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    Pilih jenis dulu — kolom isian menyesuaikan
                                    otomatis.
                                </p>
                                <ClaimTypePicker
                                    types={claimTypes}
                                    value={form.claim_type}
                                    onChange={setClaimType}
                                />
                                <InputError
                                    message={errors.claim_type?.[0]}
                                    className="mt-1"
                                />
                            </div>

                            <div>
                                <InputLabel htmlFor="title" value="Judul *" />
                                <TextInput
                                    id="title"
                                    className="mt-1 block w-full"
                                    value={form.title}
                                    onChange={set('title')}
                                    autoFocus={!isEdit}
                                    required
                                />
                                <InputError
                                    message={errors.title?.[0]}
                                    className="mt-1"
                                />
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <InputLabel
                                        htmlFor="category_id"
                                        value="Kategori *"
                                    />
                                    <SelectInput
                                        id="category_id"
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
                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
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
                                    <InputLabel
                                        htmlFor="amount"
                                        value={
                                            formula
                                                ? 'Nominal (Rp) — dihitung otomatis'
                                                : 'Nominal (Rp) *'
                                        }
                                    />
                                    <TextInput
                                        id="amount"
                                        type="number"
                                        min="1"
                                        className="mt-1 block w-full disabled:bg-gray-100 disabled:text-gray-500 dark:disabled:bg-gray-900/50"
                                        value={
                                            formula
                                                ? (computedAmount ?? '')
                                                : form.amount
                                        }
                                        onChange={set('amount')}
                                        disabled={!!formula}
                                        required={!formula}
                                    />
                                    {formula && (
                                        <p className="mt-1 text-xs text-brand-600 dark:text-brand-400">
                                            {rupiah(computedAmount || 0)} ={' '}
                                            {form.details[formula[0]] || 0} ×{' '}
                                            {rupiah(
                                                Number(
                                                    form.details[formula[1]],
                                                ) || 0,
                                            )}
                                        </p>
                                    )}
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {quota && quota.limit !== null ? (
                                            <>
                                                Plafon bulan ini ({quota.month}
                                                ): sisa{' '}
                                                <span className="font-medium text-gray-700 dark:text-gray-200">
                                                    {rupiah(quota.remaining)}
                                                </span>{' '}
                                                dari {rupiah(quota.limit)}{' '}
                                                (terpakai {rupiah(quota.used)})
                                            </>
                                        ) : (
                                            'Plafon jabatan: tanpa batas'
                                        )}
                                    </p>
                                    <InputError
                                        message={errors.amount?.[0]}
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        htmlFor="expense_date"
                                        value="Tanggal Pengeluaran"
                                    />
                                    <TextInput
                                        id="expense_date"
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
                                    <InputLabel
                                        htmlFor="bank_account_id"
                                        value="Rekening Tujuan"
                                    />
                                    <SelectInput
                                        id="bank_account_id"
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
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Kelola rekening di menu Rekening Bank.
                                    </p>
                                    <InputError
                                        message={errors.bank_account_id?.[0]}
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        htmlFor="department_id"
                                        value="Departemen Pengaju *"
                                    />
                                    <SelectInput
                                        id="department_id"
                                        className="mt-1 block w-full"
                                        value={form.department_id}
                                        onChange={set('department_id')}
                                        required
                                    >
                                        <option value="">
                                            — pilih departemen —
                                        </option>
                                        {departments.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.code
                                                    ? `${d.code} — ${d.name}`
                                                    : d.name}
                                            </option>
                                        ))}
                                    </SelectInput>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Departemen yang menanggung biaya ini —
                                        dasar rekap Finance. Ganti bila biayanya
                                        untuk unit lain.
                                    </p>
                                    <InputError
                                        message={errors.department_id?.[0]}
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        htmlFor="project_id"
                                        value="Project"
                                    />
                                    <SelectInput
                                        id="project_id"
                                        className="mt-1 block w-full"
                                        value={form.project_id}
                                        onChange={set('project_id')}
                                    >
                                        <option value="">
                                            — tanpa project —
                                        </option>
                                        {projects.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.code
                                                    ? `${p.code} — ${p.name}`
                                                    : p.name}
                                            </option>
                                        ))}
                                    </SelectInput>
                                    <InputError
                                        message={errors.project_id?.[0]}
                                        className="mt-1"
                                    />
                                </div>
                            </div>

                            {/* Field khusus jenis pengajuan (barang, layanan, lembur) */}
                            <ClaimTypeFields
                                type={selectedType}
                                values={form.details}
                                onChange={setDetail}
                                errors={errors}
                            />

                            <div>
                                <InputLabel htmlFor="reason" value="Alasan *" />
                                <TextareaInput
                                    id="reason"
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
                                <InputLabel
                                    htmlFor="description"
                                    value="Deskripsi"
                                />
                                <TextareaInput
                                    id="description"
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
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Centang untuk menghapus saat disimpan.
                                    </p>
                                </div>
                            )}

                            <div>
                                <InputLabel
                                    htmlFor="attachments"
                                    value={`Upload Bukti (JPG/PNG/PDF, maks 5 MB/file, sampai ${MAX_FILES} file)`}
                                />
                                <input
                                    id="attachments"
                                    type="file"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    disabled={
                                        form.attachments.length >= MAX_FILES
                                    }
                                    className="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-indigo-700 hover:file:bg-indigo-100 disabled:opacity-50 dark:text-gray-400 dark:file:bg-indigo-900/40 dark:file:text-indigo-300 dark:hover:file:bg-indigo-900/60"
                                    onChange={(e) => {
                                        addFiles(e.target.files);
                                        e.target.value = ''; // reset agar bisa menambah lagi / file sama
                                    }}
                                />
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Bisa pilih beberapa sekaligus, atau tambah
                                    satu per satu (menumpuk).
                                </p>

                                {/* Daftar bukti yang akan diunggah */}
                                {form.attachments.length > 0 && (
                                    <ul className="mt-2 space-y-1 text-sm">
                                        {form.attachments.map((file, i) => (
                                            <li
                                                key={`${file.name}-${file.size}-${i}`}
                                                className="flex items-center justify-between gap-3 rounded-md border border-gray-200 px-3 py-1.5 dark:border-gray-700"
                                            >
                                                <span className="min-w-0 truncate text-gray-700 dark:text-gray-200">
                                                    {file.name}{' '}
                                                    <span className="text-xs text-gray-400">
                                                        (
                                                        {Math.max(
                                                            1,
                                                            Math.round(
                                                                file.size /
                                                                    1024,
                                                            ),
                                                        )}{' '}
                                                        KB)
                                                    </span>
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        removeFile(i)
                                                    }
                                                    className="shrink-0 text-red-600 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                                                >
                                                    Hapus
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {form.attachments.length > 0 && (
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {form.attachments.length}/{MAX_FILES}{' '}
                                        file dipilih
                                    </p>
                                )}

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
