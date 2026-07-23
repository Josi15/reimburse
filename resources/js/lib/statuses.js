// Konstanta status reimbursement — sumber tunggal untuk filter status.
// Label mengikuti label() enum di backend.

export const REIMBURSEMENT_STATUSES = [
    ['', 'Semua Status'],
    ['draft', 'Draft'],
    ['submitted', 'Menunggu Manager'],
    ['manager_approved', 'Disetujui Manager'],
    ['finance_approved', 'Disetujui Finance'],
    ['manager_rejected', 'Ditolak Manager'],
    ['finance_rejected', 'Ditolak Finance'],
    ['revision_requested', 'Perlu Revisi'],
    ['paid', 'Dibayar'],
];
