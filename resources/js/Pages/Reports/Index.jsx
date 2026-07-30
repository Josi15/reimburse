import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import { StatusCell } from '@/Components/ReimbursementRow';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import ErrorState from '@/Components/ui/ErrorState';
import Pagination from '@/Components/ui/Pagination';
import SelectInput from '@/Components/ui/SelectInput';
import { TableSkeleton } from '@/Components/ui/Skeleton';
import StatCard from '@/Components/ui/StatCard';
import { Table, TBody, TD, TH, THead, TR } from '@/Components/ui/Table';
import useAuth from '@/hooks/useAuth';
import useDebouncedValue from '@/hooks/useDebouncedValue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { api, handleApiError } from '@/lib/api';
import { formatDate, rupiah } from '@/lib/format';
import { REIMBURSEMENT_STATUSES } from '@/lib/statuses';
import { toast } from '@/lib/toast';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const today = () => new Date().toISOString().slice(0, 10);

export default function Index() {
    const [filters, setFilters] = useState({
        date_from: '',
        date_to: '',
        status: '',
        department_id: '',
        project_id: '',
        category_id: '',
        q: '',
    });
    const [departments, setDepartments] = useState([]);
    const [categories, setCategories] = useState([]);
    const [projects, setProjects] = useState([]);
    const [rows, setRows] = useState(null);
    const [meta, setMeta] = useState(null);
    const [summary, setSummary] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [nonce, setNonce] = useState(0);
    const [tab, setTab] = useState('reimbursements');
    const [projectRows, setProjectRows] = useState(null);
    const [projectLoading, setProjectLoading] = useState(false);
    const [projectError, setProjectError] = useState(null);
    const [accountRows, setAccountRows] = useState(null);
    const [accountLoading, setAccountLoading] = useState(false);
    const [accountError, setAccountError] = useState(null);
    const [cashflow, setCashflow] = useState(null);
    const [cashflowLoading, setCashflowLoading] = useState(false);
    const [cashflowError, setCashflowError] = useState(null);
    const [depositOpen, setDepositOpen] = useState(false);
    const [deposit, setDeposit] = useState({
        company_bank_account_id: '',
        amount: '',
        deposited_at: today(),
        note: '',
    });
    const [depositErrors, setDepositErrors] = useState({});
    const [savingDeposit, setSavingDeposit] = useState(false);
    const { can } = useAuth();
    const dq = useDebouncedValue(filters.q);

    const set = (key) => (e) => {
        setFilters((f) => ({ ...f, [key]: e.target.value }));
        setPage(1);
    };

    const query = (qValue = filters.q) => {
        const params = new URLSearchParams({ page });
        Object.entries({ ...filters, q: qValue }).forEach(
            ([k, v]) => v && params.append(k, v),
        );
        return params;
    };

    useEffect(() => {
        api.get('/api/options/departments')
            .then((d) => setDepartments(d.data))
            .catch(() => {});
        api.get('/api/options/categories')
            .then((d) => setCategories(d.data))
            .catch(() => {});
        api.get('/api/options/projects')
            .then((d) => setProjects(d.data))
            .catch(() => {});
    }, []);

    useEffect(() => {
        let active = true;
        setLoading(true);
        setError(null);
        api.get(`/api/reports/reimbursements?${query(dq)}`)
            .then((d) => {
                if (!active) return;
                setRows(d.data);
                setMeta(d.meta);
                setSummary(d.summary);
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
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, JSON.stringify({ ...filters, q: dq }), nonce]);

    useEffect(() => {
        if (tab !== 'projects') return;
        let active = true;
        setProjectLoading(true);
        setProjectError(null);
        const params = query(dq);
        params.delete('page');
        api.get(`/api/reports/projects?${params}`)
            .then((d) => {
                if (!active) return;
                setProjectRows(d.data);
            })
            .catch((e) => {
                if (!active) return;
                setProjectError(true);
                handleApiError(e);
            })
            .finally(() => active && setProjectLoading(false));
        return () => {
            active = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tab, JSON.stringify({ ...filters, q: dq }), nonce]);

    useEffect(() => {
        if (tab !== 'company-accounts') return;
        let active = true;
        setAccountLoading(true);
        setAccountError(null);
        const params = query(dq);
        params.delete('page');
        api.get(`/api/reports/company-accounts?${params}`)
            .then((d) => {
                if (!active) return;
                setAccountRows(d.data);
            })
            .catch((e) => {
                if (!active) return;
                setAccountError(true);
                handleApiError(e);
            })
            .finally(() => active && setAccountLoading(false));
        return () => {
            active = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tab, JSON.stringify({ ...filters, q: dq }), nonce]);

    useEffect(() => {
        if (tab !== 'cashflow') return;
        let active = true;
        setCashflowLoading(true);
        setCashflowError(null);
        const params = query(dq);
        params.delete('page');
        api.get(`/api/reports/cashflow?${params}`)
            .then((d) => {
                if (!active) return;
                setCashflow(d);
            })
            .catch((e) => {
                if (!active) return;
                setCashflowError(true);
                handleApiError(e);
            })
            .finally(() => active && setCashflowLoading(false));
        return () => {
            active = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tab, JSON.stringify({ ...filters, q: dq }), nonce]);

    const reload = () => setNonce((n) => n + 1);

    const closeDeposit = () => setDepositOpen(false);

    const setDepositField = (key) => (e) =>
        setDeposit((d) => ({ ...d, [key]: e.target.value }));

    function submitDeposit(e) {
        e.preventDefault();
        setSavingDeposit(true);
        setDepositErrors({});
        api.post('/api/company-deposits', {
            company_bank_account_id: deposit.company_bank_account_id,
            amount: deposit.amount,
            deposited_at: deposit.deposited_at,
            note: deposit.note,
        })
            .then(() => {
                toast('Pemasukan dicatat.');
                setDepositOpen(false);
                setDeposit({
                    company_bank_account_id: '',
                    amount: '',
                    deposited_at: today(),
                    note: '',
                });
                setNonce((n) => n + 1);
            })
            .catch((err) => setDepositErrors(handleApiError(err)))
            .finally(() => setSavingDeposit(false));
    }

    function exportAs(format) {
        const params = query();
        params.set('format', format);
        params.delete('page');
        window.open(`/api/reports/reimbursements/export?${params}`, '_blank');
    }

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Laporan Reimbursement
                </h2>
            }
        >
            <Head title="Laporan" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {/* Filter */}
                <Card className="p-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                        <div>
                            <InputLabel
                                value="Dari Tanggal"
                                className="text-xs"
                            />
                            <TextInput
                                type="date"
                                className="mt-1 w-full text-sm"
                                value={filters.date_from}
                                onChange={set('date_from')}
                            />
                        </div>
                        <div>
                            <InputLabel value="Sampai" className="text-xs" />
                            <TextInput
                                type="date"
                                className="mt-1 w-full text-sm"
                                value={filters.date_to}
                                onChange={set('date_to')}
                            />
                        </div>
                        <div>
                            <InputLabel value="Status" className="text-xs" />
                            <SelectInput
                                className="mt-1 w-full text-sm"
                                value={filters.status}
                                onChange={set('status')}
                            >
                                {REIMBURSEMENT_STATUSES.map(([v, l]) => (
                                    <option key={v} value={v}>
                                        {l}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel
                                value="Department"
                                className="text-xs"
                            />
                            <SelectInput
                                className="mt-1 w-full text-sm"
                                value={filters.department_id}
                                onChange={set('department_id')}
                            >
                                <option value="">Semua</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel value="Project" className="text-xs" />
                            <SelectInput
                                className="mt-1 w-full text-sm"
                                value={filters.project_id}
                                onChange={set('project_id')}
                            >
                                <option value="">Semua</option>
                                {projects.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.code ? `${p.code} — ${p.name}` : p.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel value="Kategori" className="text-xs" />
                            <SelectInput
                                className="mt-1 w-full text-sm"
                                value={filters.category_id}
                                onChange={set('category_id')}
                            >
                                <option value="">Semua</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel
                                value="Kata Kunci"
                                className="text-xs"
                            />
                            <TextInput
                                className="mt-1 w-full text-sm"
                                placeholder="Nomor / judul"
                                value={filters.q}
                                onChange={set('q')}
                            />
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <SecondaryButton onClick={() => exportAs('pdf')}>
                            ⬇ PDF
                        </SecondaryButton>
                        <SecondaryButton onClick={() => exportAs('xlsx')}>
                            ⬇ Excel
                        </SecondaryButton>
                        <SecondaryButton onClick={() => exportAs('csv')}>
                            ⬇ CSV
                        </SecondaryButton>
                    </div>
                </Card>

                {/* Tab navigasi rekap */}
                <div className="flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700">
                    {[
                        ['reimbursements', 'Reimbursement'],
                        ['projects', 'Rekap per Project'],
                        ['company-accounts', 'Rekap per Rekening Perusahaan'],
                        ...(can('company_account.manage')
                            ? [['cashflow', 'Arus Kas']]
                            : []),
                    ].map(([v, l]) => (
                        <button
                            key={v}
                            type="button"
                            onClick={() => setTab(v)}
                            className={
                                'border-b-2 px-3 py-2 text-sm font-medium transition ' +
                                (tab === v
                                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200')
                            }
                        >
                            {l}
                        </button>
                    ))}
                </div>

                {/* Ringkasan */}
                {tab === 'reimbursements' && summary && (
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <StatCard
                            label="Jumlah Pengajuan"
                            value={summary.count}
                            accent="gray"
                        />
                        <StatCard
                            label="Total Nominal"
                            value={rupiah(summary.total_amount)}
                            accent="indigo"
                        />
                        <StatCard
                            label="Dibayar"
                            value={summary.by_status?.paid?.count ?? 0}
                            hint={rupiah(summary.by_status?.paid?.total ?? 0)}
                            accent="green"
                        />
                        <StatCard
                            label="Ditolak"
                            value={
                                (summary.by_status?.manager_rejected?.count ??
                                    0) +
                                (summary.by_status?.finance_rejected?.count ??
                                    0)
                            }
                            accent="red"
                        />
                    </div>
                )}

                {/* Tabel reimbursement */}
                {tab === 'reimbursements' && (
                <Card>
                    {loading ? (
                        <TableSkeleton rows={6} cols={8} />
                    ) : error ? (
                        <ErrorState onRetry={reload} />
                    ) : rows?.length === 0 ? (
                        <EmptyState title="Tidak ada data untuk filter ini" />
                    ) : (
                        <>
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Nomor</TH>
                                        <TH>Judul</TH>
                                        <TH>Pengaju</TH>
                                        <TH>Department</TH>
                                        <TH>Kategori</TH>
                                        <TH>Nominal</TH>
                                        <TH>Status</TH>
                                        <TH>Tanggal</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {(rows ?? []).map((r) => (
                                        <TR key={r.id}>
                                            <TD className="font-medium">
                                                {r.reimbursement_number}
                                            </TD>
                                            <TD>{r.title}</TD>
                                            <TD>{r.user?.name ?? '-'}</TD>
                                            <TD>{r.department?.name ?? '-'}</TD>
                                            <TD>{r.category?.name ?? '-'}</TD>
                                            <TD>{r.formatted_amount}</TD>
                                            <StatusCell status={r.status} />
                                            <TD>{formatDate(r.created_at)}</TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                            <Pagination meta={meta} onPage={setPage} />
                        </>
                    )}
                </Card>
                )}

                {/* Rekap per Project */}
                {tab === 'projects' && (
                    <Card>
                        {projectLoading ? (
                            <TableSkeleton rows={6} cols={5} />
                        ) : projectError ? (
                            <ErrorState onRetry={reload} />
                        ) : projectRows?.length === 0 ? (
                            <EmptyState title="Tidak ada data untuk filter ini" />
                        ) : (
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Project</TH>
                                        <TH>Jumlah</TH>
                                        <TH>Total Diajukan</TH>
                                        <TH>Total Dibayar</TH>
                                        <TH>Anggaran</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {(projectRows ?? []).map((r) => (
                                        <TR key={r.project_id}>
                                            <TD className="font-medium">
                                                {r.code} — {r.name}
                                            </TD>
                                            <TD>{r.count}</TD>
                                            <TD>{rupiah(r.total_amount)}</TD>
                                            <TD>{rupiah(r.paid_amount)}</TD>
                                            <TD>
                                                {r.budget != null
                                                    ? rupiah(r.budget)
                                                    : '-'}
                                            </TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        )}
                    </Card>
                )}

                {/* Rekap per Rekening Perusahaan */}
                {tab === 'company-accounts' && (
                    <Card>
                        {accountLoading ? (
                            <TableSkeleton rows={6} cols={3} />
                        ) : accountError ? (
                            <ErrorState onRetry={reload} />
                        ) : accountRows?.length === 0 ? (
                            <EmptyState title="Tidak ada data untuk filter ini" />
                        ) : (
                            <Table>
                                <THead>
                                    <TR>
                                        <TH>Rekening</TH>
                                        <TH>Jumlah Pembayaran</TH>
                                        <TH>Total</TH>
                                    </TR>
                                </THead>
                                <TBody>
                                    {(accountRows ?? []).map((r) => (
                                        <TR key={r.source_account_id}>
                                            <TD className="font-medium">
                                                {r.label}
                                            </TD>
                                            <TD>{r.count}</TD>
                                            <TD>{rupiah(r.total_amount)}</TD>
                                        </TR>
                                    ))}
                                </TBody>
                            </Table>
                        )}
                    </Card>
                )}

                {/* Arus Kas */}
                {tab === 'cashflow' && (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                {cashflow?.period
                                    ? `Periode: ${cashflow.period.from} s/d ${cashflow.period.to}`
                                    : 'Periode: —'}
                            </p>
                            <PrimaryButton
                                type="button"
                                onClick={() => setDepositOpen(true)}
                            >
                                + Catat Pemasukan
                            </PrimaryButton>
                        </div>

                        <Card>
                            {cashflowLoading ? (
                                <TableSkeleton rows={6} cols={5} />
                            ) : cashflowError ? (
                                <ErrorState onRetry={reload} />
                            ) : (cashflow?.accounts ?? []).length === 0 ? (
                                <EmptyState title="Belum ada rekening perusahaan" />
                            ) : (
                                <Table>
                                    <THead>
                                        <TR>
                                            <TH>Rekening</TH>
                                            <TH>Saldo Awal</TH>
                                            <TH>Pemasukan</TH>
                                            <TH>Pengeluaran</TH>
                                            <TH>Saldo Akhir</TH>
                                        </TR>
                                    </THead>
                                    <TBody>
                                        {(cashflow?.accounts ?? []).map((a) => (
                                            <TR key={a.account_id}>
                                                <TD className="font-medium">
                                                    {a.label} · {a.bank_code}{' '}
                                                    {a.masked_number}
                                                </TD>
                                                <TD>
                                                    {rupiah(a.opening_balance)}
                                                </TD>
                                                <TD className="text-green-600 dark:text-green-400">
                                                    {rupiah(a.pemasukan)}
                                                </TD>
                                                <TD className="text-red-600 dark:text-red-400">
                                                    {rupiah(a.pengeluaran)}
                                                </TD>
                                                <TD className="font-semibold">
                                                    {rupiah(a.ending_balance)}
                                                </TD>
                                            </TR>
                                        ))}
                                        {cashflow?.totals && (
                                            <TR className="bg-gray-50 font-semibold dark:bg-gray-900/40">
                                                <TD>Total</TD>
                                                <TD>
                                                    {rupiah(
                                                        cashflow.totals
                                                            .opening_balance,
                                                    )}
                                                </TD>
                                                <TD className="text-green-600 dark:text-green-400">
                                                    {rupiah(
                                                        cashflow.totals.pemasukan,
                                                    )}
                                                </TD>
                                                <TD className="text-red-600 dark:text-red-400">
                                                    {rupiah(
                                                        cashflow.totals
                                                            .pengeluaran,
                                                    )}
                                                </TD>
                                                <TD>
                                                    {rupiah(
                                                        cashflow.totals
                                                            .ending_balance,
                                                    )}
                                                </TD>
                                            </TR>
                                        )}
                                    </TBody>
                                </Table>
                            )}
                        </Card>
                    </div>
                )}
            </div>

            {/* Modal Catat Pemasukan */}
            <Modal show={depositOpen} onClose={closeDeposit} maxWidth="md">
                <form onSubmit={submitDeposit} className="p-6">
                    <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                        Catat Pemasukan
                    </h3>

                    <div className="mt-4 space-y-4">
                        <div>
                            <InputLabel
                                htmlFor="deposit-account"
                                value="Rekening Perusahaan"
                            />
                            <SelectInput
                                id="deposit-account"
                                required
                                className="mt-1 block w-full"
                                value={deposit.company_bank_account_id}
                                onChange={setDepositField(
                                    'company_bank_account_id',
                                )}
                            >
                                <option value="">— pilih rekening —</option>
                                {(cashflow?.accounts ?? []).map((a) => (
                                    <option
                                        key={a.account_id}
                                        value={a.account_id}
                                    >
                                        {a.label} · {a.bank_code}{' '}
                                        {a.masked_number}
                                    </option>
                                ))}
                            </SelectInput>
                            <InputError
                                message={
                                    depositErrors.company_bank_account_id?.[0]
                                }
                                className="mt-1"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="deposit-amount"
                                value="Nominal (Rp)"
                            />
                            <TextInput
                                id="deposit-amount"
                                type="number"
                                min="1"
                                required
                                className="mt-1 block w-full"
                                value={deposit.amount}
                                onChange={setDepositField('amount')}
                            />
                            <InputError
                                message={depositErrors.amount?.[0]}
                                className="mt-1"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="deposit-date"
                                value="Tanggal"
                            />
                            <TextInput
                                id="deposit-date"
                                type="date"
                                required
                                className="mt-1 block w-full"
                                value={deposit.deposited_at}
                                onChange={setDepositField('deposited_at')}
                            />
                            <InputError
                                message={depositErrors.deposited_at?.[0]}
                                className="mt-1"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="deposit-note"
                                value="Catatan"
                            />
                            <TextInput
                                id="deposit-note"
                                className="mt-1 block w-full"
                                value={deposit.note}
                                onChange={setDepositField('note')}
                            />
                            <InputError
                                message={depositErrors.note?.[0]}
                                className="mt-1"
                            />
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" onClick={closeDeposit}>
                            Batal
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={savingDeposit}>
                            {savingDeposit ? 'Menyimpan…' : 'Simpan'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
