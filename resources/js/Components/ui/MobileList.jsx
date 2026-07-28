import { cn } from '@/lib/format';

/**
 * Daftar kartu untuk tampilan mobile (pengganti tabel yang menggulir
 * horizontal di layar kecil). Sembunyi di md+ karena tabel yang dipakai.
 */
export function MobileList({ className, children }) {
    return (
        <div className={cn('space-y-3 p-4 md:hidden', className)}>
            {children}
        </div>
    );
}

/** Satu kartu baris dengan padding & border yang konsisten. */
export function MobileListItem({ className, children }) {
    return (
        <div
            className={cn(
                'rounded-lg border border-gray-100 p-4 dark:border-gray-700',
                className,
            )}
        >
            {children}
        </div>
    );
}
