// Konstanta status reimbursement — sumber tunggal untuk filter status.
// Label mengikuti label() enum di backend.

export const REIMBURSEMENT_STATUSES = [
    ['', 'Semua Status'],
    ['draft', 'Draft'],
    ['submitted', 'Menunggu Manager'],
    ['manager_approved', 'Disetujui Manager'],
    ['finance_approved', 'Disetujui Finance'],
    ['director_approved', 'Disetujui Direksi'],
    ['manager_rejected', 'Ditolak Manager'],
    ['finance_rejected', 'Ditolak Finance'],
    ['director_rejected', 'Ditolak Direksi'],
    ['revision_requested', 'Perlu Revisi'],
    ['paid', 'Dibayar'],
];
