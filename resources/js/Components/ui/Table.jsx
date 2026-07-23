import { cn } from '@/lib/format';

export function Table({ children }) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                {children}
            </table>
        </div>
    );
}

export function THead({ children }) {
    return <thead className="bg-gray-50 dark:bg-gray-900/40">{children}</thead>;
}

export function TBody({ children }) {
    return (
        <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
            {children}
        </tbody>
    );
}

export function TR({ children, className, ...props }) {
    return (
        <tr
            className={cn(
                'hover:bg-gray-50 dark:hover:bg-gray-700/40',
                className,
            )}
            {...props}
        >
            {children}
        </tr>
    );
}

export function TH({ children, className }) {
    return (
        <th
            scope="col"
            className={cn(
                'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400',
                className,
            )}
        >
            {children}
        </th>
    );
}

export function TD({ children, className }) {
    return (
        <td
            className={cn(
                'px-4 py-3 text-sm text-gray-700 dark:text-gray-200',
                className,
            )}
        >
            {children}
        </td>
    );
}
