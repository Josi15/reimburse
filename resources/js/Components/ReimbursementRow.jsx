import Badge from '@/Components/ui/Badge';
import { TD } from '@/Components/ui/Table';
import { Link } from '@inertiajs/react';

/** Sel nomor reimbursement dengan tautan ke halaman detail. */
export function ReimbursementNumberCell({ id, number }) {
    return (
        <TD>
            <Link
                href={`/reimbursements/${id}`}
                className="font-medium text-indigo-600 hover:underline"
            >
                {number}
            </Link>
        </TD>
    );
}

/** Sel badge status reimbursement (status = objek { color, label }). */
export function StatusCell({ status }) {
    return (
        <TD>
            <Badge color={status.color}>{status.label}</Badge>
        </TD>
    );
}
