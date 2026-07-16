export default function Pagination({ meta, onPage }) {
    if (!meta || meta.last_page <= 1) return null;

    return (
        <div className="flex items-center justify-between px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
            <span>
                {meta.from ?? 0}–{meta.to ?? 0} dari {meta.total}
            </span>
            <div className="flex gap-1">
                <button
                    disabled={meta.current_page <= 1}
                    onClick={() => onPage(meta.current_page - 1)}
                    className="rounded-md border border-gray-200 px-3 py-1 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:hover:bg-gray-700"
                >
                    ‹ Sebelumnya
                </button>
                <span className="px-3 py-1">
                    Hal {meta.current_page}/{meta.last_page}
                </span>
                <button
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => onPage(meta.current_page + 1)}
                    className="rounded-md border border-gray-200 px-3 py-1 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:hover:bg-gray-700"
                >
                    Berikutnya ›
                </button>
            </div>
        </div>
    );
}
