import { badgeClass, cn } from '@/lib/format';

/** Warna badge per role kanonik (nama role = key di tabel roles). */
const ROLE_COLORS = {
    super_admin: 'red',
    director: 'red',
    admin: 'indigo',
    manager: 'blue',
    project_manager: 'indigo',
    supervisor: 'blue',
    finance: 'green',
    employee: 'gray',
    intern: 'gray',
    auditor: 'amber',
};

/** Cadangan label bila display_name role belum diisi di master data. */
const ROLE_LABELS = {
    super_admin: 'Super Admin',
    director: 'Direktur',
    admin: 'Admin',
    manager: 'Manager',
    project_manager: 'Project Manager',
    supervisor: 'Supervisor',
    finance: 'Finance',
    employee: 'Karyawan',
    intern: 'Staf Magang',
    auditor: 'Auditor',
};

/**
 * Label identitas "saya login sebagai role apa".
 * `role` = nama role (menentukan warna), `label` = teks yang ditampilkan.
 */
export default function RoleBadge({ role, label, className = '' }) {
    if (!role && !label) return null;

    return (
        <span
            title={`Anda login sebagai ${label ?? ROLE_LABELS[role] ?? role}`}
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                badgeClass(ROLE_COLORS[role] ?? 'gray'),
                className,
            )}
        >
            <svg
                className="h-3 w-3 shrink-0"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.2"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
            >
                <path d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4Z" />
            </svg>
            {label ?? ROLE_LABELS[role] ?? role}
        </span>
    );
}
