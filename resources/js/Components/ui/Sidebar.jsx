import Dropdown from '@/Components/Dropdown';
import Logo from '@/Components/ui/Logo';
import ThemeToggle from '@/Components/ui/ThemeToggle';
import { Link } from '@inertiajs/react';

/** Ikon garis sederhana (stroke, h-5 w-5) untuk item navigasi. */
function icon(path) {
    return (
        <svg
            className="h-5 w-5 shrink-0"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {path}
        </svg>
    );
}

const ICONS = {
    dashboard: icon(
        <>
            <rect x="3" y="3" width="7" height="9" rx="1" />
            <rect x="14" y="3" width="7" height="5" rx="1" />
            <rect x="14" y="12" width="7" height="9" rx="1" />
            <rect x="3" y="16" width="7" height="5" rx="1" />
        </>,
    ),
    reimburs: icon(
        <>
            <path d="M6 2h12a1 1 0 0 1 1 1v18l-2.5-1.5L14 21l-2-1.5L10 21l-2.5-1.5L5 21V3a1 1 0 0 1 1-1Z" />
            <path d="M9 8h6M9 12h6" />
        </>,
    ),
    approval: icon(
        <>
            <path d="M9 12l2 2 4-4" />
            <path d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4Z" />
        </>,
    ),
    payment: icon(
        <>
            <rect x="2" y="5" width="20" height="14" rx="2" />
            <path d="M2 10h20M6 15h4" />
        </>,
    ),
    report: icon(
        <>
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
            <path d="M14 2v6h6M8 13h8M8 17h5" />
        </>,
    ),
    audit: icon(
        <>
            <circle cx="11" cy="11" r="7" />
            <path d="m21 21-4.3-4.3M9 11h4M11 9v4" />
        </>,
    ),
    user: icon(
        <>
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1" />
        </>,
    ),
    bank: icon(
        <>
            <path d="M3 10 12 4l9 6M4 10v9m16-9v9M4 19h16M8 10v9m4-9v9m4-9v9" />
        </>,
    ),
    default: icon(<circle cx="12" cy="12" r="8" />),
};

/** Pilih ikon berdasarkan kata kunci pada href item navigasi. */
function iconFor(href = '') {
    const h = href.toLowerCase();
    if (h.includes('dashboard')) return ICONS.dashboard;
    if (h.includes('reimburs')) return ICONS.reimburs;
    if (h.includes('approval') || h.includes('approv')) return ICONS.approval;
    if (h.includes('payment') || h.includes('bayar')) return ICONS.payment;
    if (h.includes('report') || h.includes('laporan')) return ICONS.report;
    if (h.includes('audit') || h.includes('log')) return ICONS.audit;
    if (h.includes('bank')) return ICONS.bank;
    if (h.includes('master') || h.includes('user')) return ICONS.user;
    return ICONS.default;
}

/**
 * Isi sidebar: Logo, tombol pencarian ⌘K, navigasi, dan bagian pengguna.
 * Dipakai untuk sidebar desktop maupun drawer mobile.
 */
export default function Sidebar({ navigation, user, isActive, onNavigate }) {
    return (
        <div className="flex h-full flex-col">
            <div className="flex h-16 shrink-0 items-center px-4">
                <Link href="/" onClick={onNavigate} aria-label="Beranda">
                    <Logo />
                </Link>
            </div>

            <div className="px-3 pb-2">
                <button
                    type="button"
                    onClick={() =>
                        window.dispatchEvent(
                            new CustomEvent('open-command-palette'),
                        )
                    }
                    className="flex w-full items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400 dark:hover:bg-gray-700/50"
                >
                    <svg
                        className="h-4 w-4 shrink-0"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth="1.8"
                        aria-hidden="true"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="m21 21-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z"
                        />
                    </svg>
                    <span className="flex-1 text-left">Cari…</span>
                    <kbd className="rounded border border-gray-300 bg-white px-1.5 py-0.5 text-[10px] font-medium text-gray-400 dark:border-gray-600 dark:bg-gray-800">
                        ⌘K
                    </kbd>
                </button>
            </div>

            <nav
                aria-label="Utama"
                className="flex-1 space-y-1 overflow-y-auto px-3 py-2"
            >
                {navigation.map((item) => {
                    const active = isActive(item.href);
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            onClick={onNavigate}
                            aria-current={active ? 'page' : undefined}
                            className={
                                'flex items-center gap-3 rounded-md px-3 py-2 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 ' +
                                (active
                                    ? 'bg-brand-50 font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300'
                                    : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50')
                            }
                        >
                            {iconFor(item.href)}
                            <span className="truncate">{item.label}</span>
                        </Link>
                    );
                })}
            </nav>

            <div className="border-t border-gray-100 p-3 dark:border-gray-700">
                <div className="flex items-center gap-2">
                    <div className="min-w-0 flex-1">
                        <div className="truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                            {user.name}
                        </div>
                        <div className="truncate text-xs text-gray-500 dark:text-gray-400">
                            {user.email}
                        </div>
                    </div>
                    <ThemeToggle />
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button
                                type="button"
                                aria-label="Menu pengguna"
                                className="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                            >
                                <svg
                                    className="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    strokeWidth="1.8"
                                    aria-hidden="true"
                                >
                                    <circle cx="12" cy="5" r="1.5" />
                                    <circle cx="12" cy="12" r="1.5" />
                                    <circle cx="12" cy="19" r="1.5" />
                                </svg>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content align="right" width="48" dropUp>
                            <Dropdown.Link href={route('profile.edit')}>
                                Profile
                            </Dropdown.Link>
                            <Dropdown.Link
                                href={route('logout')}
                                method="post"
                                as="button"
                            >
                                Log Out
                            </Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </div>
            </div>
        </div>
    );
}
