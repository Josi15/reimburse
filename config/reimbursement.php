<?php

return [

    /*
     | Batas & tipe file bukti (dapat dikonfigurasi per environment via .env).
     | Dipakai modul Reimbursement (Phase 9) & Payment (Phase 11), diformalkan
     | di File Management (Phase 16).
     */
    'max_file_size_kb' => (int) env('REIMBURSEMENT_MAX_FILE_KB', 5120), // 5 MB
    'allowed_mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
    // Deep MIME check (Phase 19): tipe KONTEN asli file (finfo), bukan sekadar
    // ekstensi/klaim client. File .pdf berisi executable akan ditolak.
    'allowed_mimetypes' => ['image/jpeg', 'image/png', 'application/pdf'],
    'max_files_per_request' => (int) env('REIMBURSEMENT_MAX_FILES', 10),

];
