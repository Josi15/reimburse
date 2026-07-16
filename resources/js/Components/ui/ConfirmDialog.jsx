import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';

export default function ConfirmDialog({ show, title, message, confirmLabel = 'Ya, lanjutkan', onConfirm, onCancel, busy = false }) {
    return (
        <Modal show={show} onClose={onCancel} maxWidth="sm">
            <div className="p-6">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-100">{title}</h3>
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">{message}</p>
                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={onCancel}>Batal</SecondaryButton>
                    <DangerButton onClick={onConfirm} disabled={busy}>
                        {busy ? 'Memproses…' : confirmLabel}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    );
}
