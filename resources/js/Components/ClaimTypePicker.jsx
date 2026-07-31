import { cn } from '@/lib/format';

/** Pemilih jenis pengajuan berbentuk kartu radio. */
export default function ClaimTypePicker({ types, value, onChange, disabled }) {
    return (
        <div
            role="radiogroup"
            aria-label="Jenis pengajuan"
            className="grid gap-3 sm:grid-cols-2"
        >
            {types.map((t) => {
                const active = t.value === value;
                return (
                    <button
                        key={t.value}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        disabled={disabled}
                        onClick={() => onChange(t.value)}
                        className={cn(
                            'flex items-start gap-3 rounded-lg border p-3 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-60',
                            active
                                ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500 dark:bg-brand-900/20'
                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/40',
                        )}
                    >
                        <span
                            className="text-xl leading-none"
                            aria-hidden="true"
                        >
                            {t.icon}
                        </span>
                        <span className="min-w-0">
                            <span
                                className={cn(
                                    'block text-sm font-medium',
                                    active
                                        ? 'text-brand-700 dark:text-brand-300'
                                        : 'text-gray-800 dark:text-gray-100',
                                )}
                            >
                                {t.label}
                            </span>
                            <span className="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                {t.description}
                            </span>
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
