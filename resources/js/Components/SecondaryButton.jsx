import Button from '@/Components/ui/Button';

export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <Button
            variant="secondary"
            type={type}
            disabled={disabled}
            className={className}
            {...props}
        >
            {children}
        </Button>
    );
}
