export default function EmptyState({ title = 'Tidak ada data', description }) {
    return (
        <div className="py-12 text-center">
            <div className="text-3xl">🗂️</div>
            <p className="mt-2 font-medium text-gray-600 dark:text-gray-300">
                {title}
            </p>
            {description && (
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {description}
                </p>
            )}
        </div>
    );
}
