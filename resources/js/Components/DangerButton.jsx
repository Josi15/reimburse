import Button from '@/Components/ui/Button';

export default function DangerButton({
    type = 'submit',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <Button
            variant="danger"
            type={type}
            disabled={disabled}
            className={className}
            {...props}
        >
            {children}
        </Button>
    );
}
