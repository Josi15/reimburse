import RoleBadge from '@/Components/ui/RoleBadge';
import { rupiah } from '@/lib/format';

/** Satu baris keterangan: label kecil di atas, isi di bawahnya. */
function Field({ label, hint, children }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {label}
            </dt>
            <dd className="mt-1 text-sm text-gray-800 dark:text-gray-100">
                {children}
            </dd>
            {hint && (
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {hint}
                </p>
            )}
        </div>
    );
}

/** Nilai yang belum terisi — ditandai jelas agar bisa ditindaklanjuti. */
function Missing({ children }) {
    return <span className="text-red-600 dark:text-red-400">{children}</span>;
}

/**
 * Identitas kerja pengguna: departemen, jabatan, atasan, plafon, dan tarif
 * lembur. Ditampilkan (bukan diubah) di sini karena ketiga hal itu menentukan
 * ke mana biaya reimbursement dibebankan, siapa yang menyetujuinya, dan berapa
 * upah lembur dihitung — perubahannya lewat Admin, bukan swalayan.
 */
export default function WorkIdentityCard({ workIdentity, className = '' }) {
    const {
        department,
        roles = [],
        manager,
        phone,
        reimbursement_limit: limit,
        overtime_rate: overtimeRate,
        sees_all_departments: seesAll,
    } = workIdentity ?? {};

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Identitas Kerja
                </h2>

                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Dipakai otomatis saat Anda mengajukan reimbursement, lembur,
                    dan pengajuan lainnya. Hubungi Admin bila ada yang keliru.
                </p>
            </header>

            <dl className="mt-6 grid gap-6 sm:grid-cols-2">
                <Field
                    label="Departemen"
                    hint={
                        department
                            ? 'Biaya pengajuan Anda dibebankan ke departemen ini.'
                            : 'Tanpa departemen, pengajuan tidak bisa dibebankan ke unit mana pun.'
                    }
                >
                    {department ? (
                        <span className="inline-flex items-center gap-2">
                            <span className="font-medium">
                                {department.name}
                            </span>
                            <span className="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                {department.code}
                            </span>
                        </span>
                    ) : (
                        <Missing>
                            Belum terdaftar di departemen mana pun
                        </Missing>
                    )}
                </Field>

                <Field
                    label="Jabatan"
                    hint={
                        seesAll
                            ? 'Jabatan Anda melihat data seluruh departemen.'
                            : 'Cakupan data Anda terbatas pada departemen sendiri.'
                    }
                >
                    {roles.length ? (
                        <span className="flex flex-wrap gap-1.5">
                            {roles.map((role) => (
                                <RoleBadge
                                    key={role.name}
                                    role={role.name}
                                    label={role.label}
                                />
                            ))}
                        </span>
                    ) : (
                        <Missing>Belum punya jabatan</Missing>
                    )}
                </Field>

                <Field
                    label="Atasan Langsung"
                    hint="Penyetuju tahap pertama pengajuan Anda."
                >
                    {manager ? (
                        <>
                            <span className="font-medium">{manager.name}</span>
                            {manager.role_label && (
                                <span className="text-gray-500 dark:text-gray-400">
                                    {' '}
                                    — {manager.role_label}
                                </span>
                            )}
                        </>
                    ) : (
                        <span className="text-gray-500 dark:text-gray-400">
                            Tidak ada — pengajuan Anda langsung masuk ke antrean
                            Manager
                        </span>
                    )}
                </Field>

                <Field label="Nomor Telepon">
                    {phone || (
                        <span className="text-gray-500 dark:text-gray-400">
                            Belum diisi
                        </span>
                    )}
                </Field>

                <Field
                    label="Plafon Reimbursement"
                    hint="Batas nominal per pengajuan menurut jabatan."
                >
                    {limit === null ? 'Tanpa batas' : rupiah(limit)}
                </Field>

                <Field
                    label="Upah Lembur"
                    hint="Tarif per jam yang dipakai saat mengajukan lembur."
                >
                    {overtimeRate ? (
                        `${rupiah(overtimeRate)} / jam`
                    ) : (
                        <span className="text-gray-500 dark:text-gray-400">
                            Jabatan Anda tidak berhak mengajukan lembur
                        </span>
                    )}
                </Field>
            </dl>
        </section>
    );
}
