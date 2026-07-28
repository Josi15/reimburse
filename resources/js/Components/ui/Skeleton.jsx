import { cn } from '@/lib/format';

/** Blok shimmer dasar. */
export default function Skeleton({ className, ...props }) {
    return (
        <div
            className={cn(
                'animate-pulse rounded bg-gray-200 dark:bg-gray-700',
                className,
            )}
            {...props}
        />
    );
}

/** Placeholder tabel: header + baris shimmer, meniru bentuk <Table>. */
export function TableSkeleton({ rows = 6, cols = 5 }) {
    return (
        <div className="overflow-x-auto">
            <div className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                {/* Header */}
                <div className="flex gap-4 bg-gray-50 px-4 py-3 dark:bg-gray-900/40">
                    {Array.from({ length: cols }).map((_, i) => (
                        <Skeleton key={i} className="h-3 flex-1" />
                    ))}
                </div>
                {/* Baris */}
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    {Array.from({ length: rows }).map((_, r) => (
                        <div key={r} className="flex gap-4 px-4 py-4">
                            {Array.from({ length: cols }).map((_, c) => (
                                <Skeleton
                                    key={c}
                                    className="h-4 flex-1"
                                    style={{ opacity: 1 - r * 0.06 }}
                                />
                            ))}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

/** Placeholder kartu generik. */
export function CardSkeleton({ className, lines = 3 }) {
    return (
        <div
            className={cn(
                'rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800',
                className,
            )}
        >
            <Skeleton className="h-4 w-1/3" />
            <div className="mt-4 space-y-2.5">
                {Array.from({ length: lines }).map((_, i) => (
                    <Skeleton key={i} className="h-3 w-full" />
                ))}
            </div>
        </div>
    );
}

/** Placeholder kartu statistik. */
export function StatCardSkeleton() {
    return (
        <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <Skeleton className="h-3 w-2/3" />
            <Skeleton className="mt-3 h-6 w-1/2" />
        </div>
    );
}

/** Placeholder untuk keseluruhan dashboard. */
export function DashboardSkeleton() {
    return (
        <div className="space-y-6">
            {/* Baris kartu statistik */}
            <div className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                {Array.from({ length: 6 }).map((_, i) => (
                    <StatCardSkeleton key={i} />
                ))}
            </div>

            {/* Grafik + daftar */}
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:col-span-2">
                    <Skeleton className="h-4 w-1/3" />
                    <Skeleton className="mt-4 h-40 w-full" />
                </div>
                <div className="space-y-6">
                    <CardSkeleton lines={4} />
                    <CardSkeleton lines={4} />
                </div>
            </div>

            {/* Tabel aktivitas */}
            <div className="rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div className="p-5">
                    <Skeleton className="h-4 w-40" />
                </div>
                <TableSkeleton rows={4} cols={6} />
            </div>
        </div>
    );
}
