import { badgeClass, cn } from '@/lib/format';

export default function Badge({ color = 'gray', children }) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                badgeClass(color),
            )}
        >
            {children}
        </span>
    );
}
