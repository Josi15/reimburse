import Badge from '@/Components/ui/Badge';
import { TD } from '@/Components/ui/Table';
import { Link } from '@inertiajs/react';

/**
 * Sel nomor pengajuan dengan tautan ke halaman detail.
 *
 * `basePath` menentukan bagian mana yang membuka detailnya (/reimbursements,
 * /goods, /services) supaya breadcrumb & tombol kembali tetap berada di menu
 * yang sedang dipakai. Antrean lintas jenis (Persetujuan, Pembayaran) memakai
 * default /reimbursements.
 */
export function ReimbursementNumberCell({
    id,
    number,
    basePath = '/reimbursements',
}) {
    return (
        <TD>
            <Link
                href={`${basePath}/${id}`}
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
