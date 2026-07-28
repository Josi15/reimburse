import Button from '@/Components/ui/Button';

export default function PrimaryButton({
    type = 'submit',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <Button
            variant="primary"
            type={type}
            disabled={disabled}
            className={className}
            {...props}
        >
            {children}
        </Button>
    );
}
