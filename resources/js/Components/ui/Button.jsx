import { cn } from '@/lib/format';

const BASE_CLASSES =
    'inline-flex items-center justify-center gap-2 rounded-md font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none dark:focus-visible:ring-offset-gray-800';

const VARIANT_CLASSES = {
    primary: 'border border-transparent bg-brand-600 text-white hover:bg-brand-700 active:bg-brand-800',
    secondary:
        'border border-gray-300 bg-white text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
    danger: 'border border-transparent bg-red-600 text-white hover:bg-red-500 active:bg-red-700',
    ghost: 'border border-transparent bg-transparent text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700',
};

const SIZE_CLASSES = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2 text-sm',
};

export default function Button({
    variant = 'primary',
    size = 'md',
    type = 'button',
    className = '',
    disabled = false,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            type={type}
            disabled={disabled}
            className={cn(
                BASE_CLASSES,
                VARIANT_CLASSES[variant] ?? VARIANT_CLASSES.primary,
                SIZE_CLASSES[size] ?? SIZE_CLASSES.md,
                className,
            )}
        >
            {children}
        </button>
    );
}
