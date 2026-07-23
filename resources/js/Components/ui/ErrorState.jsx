import SecondaryButton from '@/Components/SecondaryButton';

export default function ErrorState({
    title = 'Gagal memuat data',
    description,
    onRetry,
}) {
    return (
        <div className="py-12 text-center">
            <div className="text-3xl">⚠️</div>
            <p className="mt-2 font-medium text-gray-600 dark:text-gray-300">
                {title}
            </p>
            {description && (
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {description}
                </p>
            )}
            {onRetry && (
                <div className="mt-4">
                    <SecondaryButton onClick={onRetry}>
                        Coba lagi
                    </SecondaryButton>
                </div>
            )}
        </div>
    );
}
