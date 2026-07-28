import { cn } from '@/lib/format';

/**
 * Wordmark RMS (Reimbursement Management System).
 * Ikon rounded-square brand + glyph receipt/checkmark, teks "RMS".
 */
export default function Logo({ className = '', showText = true }) {
    return (
        <span className={cn('inline-flex items-center gap-2', className)}>
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white shadow-sm">
                <svg
                    className="h-5 w-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                >
                    <path d="M6 2h12a1 1 0 0 1 1 1v18l-2.5-1.5L14 21l-2-1.5L10 21l-2.5-1.5L5 21V3a1 1 0 0 1 1-1Z" />
                    <path d="m9 11 2 2 4-4" />
                </svg>
            </span>

            {showText && (
                <span className="flex flex-col leading-none">
                    <span className="text-lg font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        RMS
                    </span>
                    <span className="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Reimbursement
                    </span>
                </span>
            )}
        </span>
    );
}
