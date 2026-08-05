import { usePage } from '@inertiajs/react';

/**
 * Pembagian modul pengajuan (Reimbursement / Pengadaan Barang / Layanan &
 * Server). Definisinya tinggal di PHP (App\Support\ClaimSection) dan dibagikan
 * lewat props Inertia, supaya menu, alamat, dan label di frontend tidak pernah
 * berbeda dari aturan backend.
 */
export function useClaimSections() {
    return usePage().props.claimSections ?? [];
}

/** Bagian tempat sebuah jenis pengajuan bernaung; fallback ke bagian pertama. */
export function useSectionForClaimType(claimType) {
    const sections = useClaimSections();

    if (!claimType) return sections[0] ?? null;

    return (
        sections.find((s) => s.claim_types.includes(claimType)) ??
        sections[0] ??
        null
    );
}
