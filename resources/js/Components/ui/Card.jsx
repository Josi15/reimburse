import { cn } from '@/lib/format';

export default function Card({ className, children, ...props }) {
    return (
        <div
            className={cn(
                'rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800',
                className,
            )}
            {...props}
        >
            {children}
        </div>
    );
}
