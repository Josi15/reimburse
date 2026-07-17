import Card from './Card';

export default function StatCard({ label, value, hint, accent = 'blue' }) {
    const accents = {
        blue: 'text-blue-600 dark:text-blue-400',
        green: 'text-green-600 dark:text-green-400',
        red: 'text-red-600 dark:text-red-400',
        amber: 'text-amber-600 dark:text-amber-400',
        indigo: 'text-indigo-600 dark:text-indigo-400',
        gray: 'text-gray-700 dark:text-gray-200',
    };

    return (
        <Card className="p-5">
            <div className="text-sm text-gray-500 dark:text-gray-400">
                {label}
            </div>
            <div
                className={`mt-1 text-2xl font-bold ${accents[accent] ?? accents.blue}`}
            >
                {value}
            </div>
            {hint && <div className="mt-1 text-xs text-gray-400">{hint}</div>}
        </Card>
    );
}
