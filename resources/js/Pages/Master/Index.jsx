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
import Pagination from '@/Components/ui/Pagination';
import SelectInput from '@/Components/ui/SelectInput';
import { Loading } from '@/Components/ui/Spinner';
import { Table, TBody, TD, TH, THead, TR } from '@/Components/ui/Table';
import useAuth from '@/hooks/useAuth';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { cn, rupiah } from '@/lib/format';
import { toast } from '@/lib/toast';
import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

/* ------------------------------------------------------------------ */
/* CRUD generik — dipakai Department, Category, Bank, User             */
/* ------------------------------------------------------------------ */
function CrudSection({ endpoint, columns, fields, emptyTitle, transform }) {
    const [rows, setRows] = useState(null);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [q, setQ] = useState('');
    const [modal, setModal] = useState(null); // 'form' | {deleteId}
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({});
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const reqRef = useRef(0);
    const dq = useDebouncedValue(q);

    const reload = useCallback(() => {
        const token = ++reqRef.current;
        setLoading(true);
        setError(null);
        const params = new URLSearchParams({ page, ...(dq && { q: dq }) });
        api.get(`${endpoint}?${params}`)
            .then((d) => {
                if (token !== reqRef.current) return;
                setRows(d.data);
                setMeta(d.meta);
            })
            .catch((e) => {
                if (token === reqRef.current) setError(true);
                handleApiError(e);
            })
            .finally(() => {
                if (token === reqRef.current) setLoading(false);
            });
    }, [endpoint, page, dq]);

    useEffect(() => {
        reload();
    }, [reload]);

    function blank() {
        const f = {};
        fields.forEach(
            (fd) => (f[fd.key] = fd.type === 'checkbox' ? true : ''),
        );
        return f;
    }

    function openCreate() {
        setEditing(null);
        setForm(blank());
        setErrors({});
        setModal('form');
    }

    function openEdit(row) {
        setEditing(row);
        const f = {};
        fields.forEach((fd) => {
            if (fd.createOnly) return;
            f[fd.key] = fd.fromRow
                ? fd.fromRow(row)
                : (row[fd.key] ?? (fd.type === 'checkbox' ? false : ''));
        });
        setForm(f);
        setErrors({});
        setModal('form');
    }

    async function save() {
        setBusy(true);
        setErrors({});
        const payload = transform
            ? transform({ ...form }, !!editing)
            : { ...form };
        try {
            if (editing) {
                await api.put(`${endpoint}/${editing.id}`, payload);
                toast('Data diperbarui.');
            } else {
                await api.post(endpoint, payload);
                toast('Data ditambahkan.');
            }
            setModal(null);
            reload();
        } catch (e) {
            setErrors(handleApiError(e, 'Gagal menyimpan.'));
        } finally {
            setBusy(false);
        }
    }

    async function remove(rowId) {
        setBusy(true);
        try {
            await api.delete(`${endpoint}/${rowId}`);
            toast('Data dihapus.');
            setModal(null);
            reload();
        } catch (e) {
            handleApiError(e, 'Gagal menghapus.');
            setModal(null);
        } finally {
            setBusy(false);
        }
    }

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4 dark:border-gray-700">
                <TextInput
                    placeholder="Cari…"
                    className="w-56 text-sm"
                    value={q}
                    onChange={(e) => {
                        setQ(e.target.value);
                        setPage(1);
                    }}
                />
                <PrimaryButton onClick={openCreate}>+ Tambah</PrimaryButton>
            </div>

            {loading ? (
                <Loading />
            ) : error ? (
                <ErrorState onRetry={reload} />
            ) : rows?.length === 0 ? (
                <EmptyState title={emptyTitle} />
            ) : (
                <>
                    <Table>
                        <THead>
                            <TR>
                                {columns.map((c) => (
                                    <TH key={c.key}>{c.label}</TH>
                                ))}
                                <TH>Aksi</TH>
                            </TR>
                        </THead>
                        <TBody>
                            {(rows ?? []).map((row) => (
                                <TR key={row.id}>
                                    {columns.map((c) => (
                                        <TD key={c.key}>
                                            {c.render
                                                ? c.render(row)
                                                : (row[c.key] ?? '-')}
                                        </TD>
                                    ))}
                                    <TD>
                                        <span className="flex gap-3 text-sm">
                                            <button
                                                onClick={() => openEdit(row)}
                                                className="text-indigo-600 hover:underline"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                onClick={() =>
                                                    setModal({
                                                        deleteId: row.id,
                                                    })
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
                    <Pagination meta={meta} onPage={setPage} />
                </>
            )}

            {/* Modal form */}
            <Modal
                show={modal === 'form'}
                onClose={() => setModal(null)}
                maxWidth="md"
            >
                <div className="p-6">
                    <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                        {editing ? 'Edit Data' : 'Tambah Data'}
                    </h3>
                    <div className="mt-4 space-y-4">
                        {fields
                            .filter((fd) => !(editing && fd.createOnly))
                            .map((fd) => (
                                <div key={fd.key}>
                                    {fd.type === 'checkbox' ? (
                                        <label className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                className="rounded border-gray-300"
                                                checked={!!form[fd.key]}
                                                onChange={(e) =>
                                                    setForm((f) => ({
                                                        ...f,
                                                        [fd.key]:
                                                            e.target.checked,
                                                    }))
                                                }
                                            />
                                            {fd.label}
                                        </label>
                                    ) : fd.type === 'select' ? (
                                        <>
                                            <InputLabel
                                                htmlFor={`field-${fd.key}`}
                                                value={fd.label}
                                            />
                                            <SelectInput
                                                id={`field-${fd.key}`}
                                                className="mt-1 block w-full"
                                                value={form[fd.key] ?? ''}
                                                onChange={(e) =>
                                                    setForm((f) => ({
                                                        ...f,
                                                        [fd.key]:
                                                            e.target.value,
                                                    }))
                                                }
                                            >
                                                <option value="">
                                                    — pilih —
                                                </option>
                                                {(fd.options ?? []).map((o) => (
                                                    <option
                                                        key={o.value}
                                                        value={o.value}
                                                    >
                                                        {o.label}
                                                    </option>
                                                ))}
                                            </SelectInput>
                                        </>
                                    ) : (
                                        <>
                                            <InputLabel
                                                htmlFor={`field-${fd.key}`}
                                                value={fd.label}
                                            />
                                            <TextInput
                                                id={`field-${fd.key}`}
                                                type={fd.type ?? 'text'}
                                                className="mt-1 block w-full"
                                                value={form[fd.key] ?? ''}
                                                onChange={(e) =>
                                                    setForm((f) => ({
                                                        ...f,
                                                        [fd.key]:
                                                            e.target.value,
                                                    }))
                                                }
                                            />
                                        </>
                                    )}
                                    <InputError
                                        message={errors[fd.key]?.[0]}
                                        className="mt-1"
                                    />
                                </div>
                            ))}
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
                show={!!modal?.deleteId}
                title="Hapus data ini?"
                message="Data akan di-soft-delete dan bisa dipulihkan oleh administrator."
                confirmLabel="Hapus"
                busy={busy}
                onConfirm={() => remove(modal.deleteId)}
                onCancel={() => setModal(null)}
            />
        </Card>
    );
}

/* ------------------------------------------------------------------ */
/* Roles — form khusus dengan checkbox permission                      */
/* ------------------------------------------------------------------ */
function RolesSection() {
    const [roles, setRoles] = useState(null);
    const [permissions, setPermissions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [modal, setModal] = useState(null);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({
        name: '',
        display_name: '',
        description: '',
        permission_ids: [],
    });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const reqRef = useRef(0);

    const reload = useCallback(() => {
        const token = ++reqRef.current;
        setLoading(true);
        setError(null);
        api.get('/api/roles?per_page=100')
            .then((d) => {
                if (token === reqRef.current) setRoles(d.data);
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
        api.get('/api/options/permissions')
            .then((d) => setPermissions(d.data))
            .catch(() => {});
    }, [reload]);

    function openCreate() {
        setEditing(null);
        setForm({
            name: '',
            display_name: '',
            description: '',
            permission_ids: [],
        });
        setErrors({});
        setModal('form');
    }

    function openEdit(role) {
        setEditing(role);
        const permIds = permissions
            .filter((p) => role.permissions?.includes(p.name))
            .map((p) => p.id);
        setForm({
            name: role.name,
            display_name: role.display_name,
            description: role.description ?? '',
            permission_ids: permIds,
        });
        setErrors({});
        setModal('form');
    }

    async function save() {
        setBusy(true);
        setErrors({});
        try {
            if (editing) {
                await api.put(`/api/roles/${editing.id}`, form);
                toast('Role diperbarui.');
            } else {
                await api.post('/api/roles', form);
                toast('Role ditambahkan.');
            }
            setModal(null);
            reload();
        } catch (e) {
            setErrors(handleApiError(e, 'Gagal menyimpan role.'));
        } finally {
            setBusy(false);
        }
    }

    async function remove(roleId) {
        setBusy(true);
        try {
            await api.delete(`/api/roles/${roleId}`);
            toast('Role dihapus.');
            setModal(null);
            reload();
        } catch (e) {
            handleApiError(e, 'Gagal menghapus role.');
            setModal(null);
        } finally {
            setBusy(false);
        }
    }

    return (
        <Card>
            <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                <p className="text-sm text-gray-500">
                    Role inti sistem tidak dapat dihapus.
                </p>
                <PrimaryButton onClick={openCreate}>
                    + Tambah Role
                </PrimaryButton>
            </div>

            {loading ? (
                <Loading />
            ) : error ? (
                <ErrorState onRetry={reload} />
            ) : (
                <Table>
                    <THead>
                        <TR>
                            <TH>Slug</TH>
                            <TH>Nama</TH>
                            <TH>Users</TH>
                            <TH>Permissions</TH>
                            <TH>Aksi</TH>
                        </TR>
                    </THead>
                    <TBody>
                        {(roles ?? []).map((r) => (
                            <TR key={r.id}>
                                <TD className="font-mono text-xs">{r.name}</TD>
                                <TD>{r.display_name}</TD>
                                <TD>{r.users_count ?? 0}</TD>
                                <TD>
                                    <span className="text-xs text-gray-400">
                                        {(r.permissions ?? []).length} izin
                                    </span>
                                </TD>
                                <TD>
                                    <span className="flex gap-3 text-sm">
                                        <button
                                            onClick={() => openEdit(r)}
                                            className="text-indigo-600 hover:underline"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            onClick={() =>
                                                setModal({ deleteId: r.id })
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

            <Modal
                show={modal === 'form'}
                onClose={() => setModal(null)}
                maxWidth="2xl"
            >
                <div className="p-6">
                    <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                        {editing
                            ? `Edit Role: ${editing.display_name}`
                            : 'Tambah Role'}
                    </h3>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel
                                htmlFor="role_name"
                                value="Slug * (huruf kecil & underscore)"
                            />
                            <TextInput
                                id="role_name"
                                className="mt-1 block w-full"
                                value={form.name}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        name: e.target.value,
                                    }))
                                }
                            />
                            <InputError
                                message={errors.name?.[0]}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel
                                htmlFor="role_display_name"
                                value="Nama Tampilan *"
                            />
                            <TextInput
                                id="role_display_name"
                                className="mt-1 block w-full"
                                value={form.display_name}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        display_name: e.target.value,
                                    }))
                                }
                            />
                            <InputError
                                message={errors.display_name?.[0]}
                                className="mt-1"
                            />
                        </div>
                    </div>
                    <div className="mt-4">
                        <InputLabel value="Permissions" />
                        <div className="mt-2 grid max-h-64 grid-cols-2 gap-1 overflow-y-auto rounded-md border border-gray-200 p-3 text-sm sm:grid-cols-3 dark:border-gray-700">
                            {permissions.map((p) => (
                                <label
                                    key={p.id}
                                    className="flex items-center gap-2 text-gray-600 dark:text-gray-300"
                                >
                                    <input
                                        type="checkbox"
                                        className="rounded border-gray-300"
                                        checked={form.permission_ids.includes(
                                            p.id,
                                        )}
                                        onChange={(e) =>
                                            setForm((f) => ({
                                                ...f,
                                                permission_ids: e.target.checked
                                                    ? [
                                                          ...f.permission_ids,
                                                          p.id,
                                                      ]
                                                    : f.permission_ids.filter(
                                                          (x) => x !== p.id,
                                                      ),
                                            }))
                                        }
                                    />
                                    <span className="truncate" title={p.name}>
                                        {p.name}
                                    </span>
                                </label>
                            ))}
                        </div>
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
                show={!!modal?.deleteId}
                title="Hapus role?"
                message="Role inti atau yang masih dipakai user akan ditolak sistem."
                confirmLabel="Hapus"
                busy={busy}
                onConfirm={() => remove(modal.deleteId)}
                onCancel={() => setModal(null)}
            />
        </Card>
    );
}

/* ------------------------------------------------------------------ */
/* Halaman utama dengan tab                                            */
/* ------------------------------------------------------------------ */
export default function Index() {
    const { can } = useAuth();
    const [tab, setTab] = useState(null);
    const [departments, setDepartments] = useState([]);
    const [roleOptions, setRoleOptions] = useState([]);

    const tabs = [
        can('department.manage') && { key: 'departments', label: 'Department' },
        can('category.manage') && { key: 'categories', label: 'Kategori' },
        can('bank.manage') && { key: 'banks', label: 'Bank' },
        can('user.view') && { key: 'users', label: 'User' },
        can('role.manage') && { key: 'roles', label: 'Role' },
    ].filter(Boolean);

    useEffect(() => {
        if (!tab && tabs.length) setTab(tabs[0].key);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (can('user.view')) {
            api.get('/api/options/departments')
                .then((d) => setDepartments(d.data))
                .catch(() => {});
            api.get('/api/options/roles')
                .then((d) => setRoleOptions(d.data))
                .catch(() => {});
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const activeBadge = (row) => (
        <Badge color={row.is_active ? 'green' : 'gray'}>
            {row.is_active ? 'Aktif' : 'Nonaktif'}
        </Badge>
    );

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Master Data
                </h2>
            }
        >
            <Head title="Master Data" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {/* Tab bar */}
                <div className="mb-5 flex flex-wrap gap-1 rounded-lg bg-gray-200/70 p-1 dark:bg-gray-800">
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            onClick={() => setTab(t.key)}
                            className={cn(
                                'rounded-md px-4 py-1.5 text-sm font-medium transition',
                                tab === t.key
                                    ? 'bg-white text-gray-800 shadow dark:bg-gray-700 dark:text-gray-100'
                                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400',
                            )}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                {tab === 'departments' && (
                    <CrudSection
                        endpoint="/api/departments"
                        emptyTitle="Belum ada department"
                        columns={[
                            { key: 'code', label: 'Kode' },
                            { key: 'name', label: 'Nama' },
                            { key: 'users_count', label: 'Users' },
                            {
                                key: 'is_active',
                                label: 'Status',
                                render: activeBadge,
                            },
                        ]}
                        fields={[
                            { key: 'name', label: 'Nama *' },
                            { key: 'code', label: 'Kode *' },
                            { key: 'description', label: 'Deskripsi' },
                            {
                                key: 'is_active',
                                label: 'Aktif',
                                type: 'checkbox',
                            },
                        ]}
                    />
                )}

                {tab === 'categories' && (
                    <CrudSection
                        endpoint="/api/categories"
                        emptyTitle="Belum ada kategori"
                        columns={[
                            { key: 'code', label: 'Kode' },
                            { key: 'name', label: 'Nama' },
                            {
                                key: 'max_amount',
                                label: 'Plafon',
                                render: (r) =>
                                    r.max_amount
                                        ? rupiah(r.max_amount)
                                        : 'Tanpa batas',
                            },
                            {
                                key: 'is_active',
                                label: 'Status',
                                render: activeBadge,
                            },
                        ]}
                        fields={[
                            { key: 'name', label: 'Nama *' },
                            { key: 'code', label: 'Kode *' },
                            { key: 'description', label: 'Deskripsi' },
                            {
                                key: 'max_amount',
                                label: 'Plafon (Rp, kosongkan bila tanpa batas)',
                                type: 'number',
                            },
                            {
                                key: 'is_active',
                                label: 'Aktif',
                                type: 'checkbox',
                            },
                        ]}
                        transform={(f) => ({
                            ...f,
                            max_amount:
                                f.max_amount === '' ? null : f.max_amount,
                        })}
                    />
                )}

                {tab === 'banks' && (
                    <CrudSection
                        endpoint="/api/banks"
                        emptyTitle="Belum ada bank"
                        columns={[
                            { key: 'code', label: 'Kode' },
                            { key: 'name', label: 'Nama' },
                            { key: 'swift_code', label: 'SWIFT' },
                            { key: 'bank_accounts_count', label: 'Rekening' },
                            {
                                key: 'is_active',
                                label: 'Status',
                                render: activeBadge,
                            },
                        ]}
                        fields={[
                            { key: 'name', label: 'Nama *' },
                            { key: 'code', label: 'Kode *' },
                            { key: 'swift_code', label: 'SWIFT Code' },
                            {
                                key: 'is_active',
                                label: 'Aktif',
                                type: 'checkbox',
                            },
                        ]}
                    />
                )}

                {tab === 'users' && (
                    <CrudSection
                        endpoint="/api/users"
                        emptyTitle="Belum ada user"
                        columns={[
                            { key: 'name', label: 'Nama' },
                            { key: 'email', label: 'Email' },
                            {
                                key: 'roles',
                                label: 'Role',
                                render: (r) =>
                                    (r.roles ?? []).join(', ') || '-',
                            },
                            {
                                key: 'department',
                                label: 'Department',
                                render: (r) => r.department?.name ?? '-',
                            },
                            {
                                key: 'is_active',
                                label: 'Status',
                                render: activeBadge,
                            },
                        ]}
                        fields={[
                            { key: 'name', label: 'Nama *' },
                            { key: 'email', label: 'Email *' },
                            {
                                key: 'password',
                                label: 'Password *',
                                type: 'password',
                                createOnly: true,
                            },
                            {
                                key: 'password_confirmation',
                                label: 'Konfirmasi Password *',
                                type: 'password',
                                createOnly: true,
                            },
                            { key: 'phone', label: 'Telepon' },
                            {
                                key: 'department_id',
                                label: 'Department',
                                type: 'select',
                                options: departments.map((d) => ({
                                    value: d.id,
                                    label: d.name,
                                })),
                            },
                            {
                                key: 'role_id',
                                label: 'Role *',
                                type: 'select',
                                options: roleOptions.map((r) => ({
                                    value: r.id,
                                    label: r.display_name,
                                })),
                                fromRow: () => '',
                            },
                            {
                                key: 'is_active',
                                label: 'Aktif',
                                type: 'checkbox',
                            },
                        ]}
                        transform={(f, isEdit) => {
                            const payload = { ...f };
                            if (payload.role_id)
                                payload.role_ids = [Number(payload.role_id)];
                            delete payload.role_id;
                            if (payload.department_id === '')
                                payload.department_id = null;
                            if (isEdit && !payload.role_ids)
                                delete payload.role_ids;
                            return payload;
                        }}
                    />
                )}

                {tab === 'roles' && <RolesSection />}
            </div>
        </AuthenticatedLayout>
    );
}
