# Dokumen Perencanaan Proyek
# Reimbursement Management System

> **Fase:** Phase 1 — Project Planning
> **Status:** Dokumen perencanaan (belum ada kode)
> **Tanggal:** 2026-07-16
> **Tech Stack Rencana:** Laravel 12, React.js (Vite), PostgreSQL, Tailwind CSS, Laravel Breeze React, REST API

---

## Daftar Isi
1. [Latar Belakang](#1-latar-belakang)
2. [Tujuan Sistem](#2-tujuan-sistem)
3. [Identifikasi Masalah](#3-identifikasi-masalah)
4. [Solusi Sistem](#4-solusi-sistem)
5. [Target Pengguna](#5-target-pengguna)
6. [Role Pengguna](#6-role-pengguna)
7. [Fitur Utama](#7-fitur-utama)
8. [Business Flow](#8-business-flow)
9. [Use Case](#9-use-case)
10. [Activity Diagram (Teks)](#10-activity-diagram-teks)
11. [Functional Requirement](#11-functional-requirement)
12. [Non Functional Requirement](#12-non-functional-requirement)
13. [User Story](#13-user-story)
14. [Acceptance Criteria](#14-acceptance-criteria)
15. [Currency & Format Nominal](#15-currency--format-nominal)
16. [Ringkasan & Langkah Berikutnya](#16-ringkasan--langkah-berikutnya)

---

## 1. Latar Belakang

Proses reimbursement (penggantian biaya) merupakan aktivitas rutin di hampir setiap perusahaan. Karyawan mengeluarkan dana pribadi terlebih dahulu untuk keperluan operasional perusahaan — misalnya biaya perjalanan dinas, pembelian alat kerja, konsumsi rapat, atau biaya kesehatan — kemudian mengajukan penggantian ke perusahaan.

Pada banyak perusahaan, proses ini masih dijalankan secara manual: karyawan mengisi formulir kertas atau spreadsheet, melampirkan bukti fisik (struk/nota), lalu formulir diedarkan secara berurutan ke atasan (Manager) dan bagian keuangan (Finance) untuk disetujui dan dibayarkan.

Cara manual ini menimbulkan sejumlah kesulitan: bukti mudah hilang, status pengajuan tidak transparan, proses persetujuan lambat karena tergantung ketersediaan pihak yang menyetujui, serta sulitnya melakukan rekapitulasi dan audit. Ketika volume pengajuan meningkat, beban administrasi bagian keuangan menjadi sangat besar dan rawan kesalahan.

**Reimbursement Management System** hadir sebagai solusi digital yang mengelola seluruh siklus reimbursement — mulai dari pengajuan, persetujuan berjenjang, pembayaran, hingga pelaporan dan audit — dalam satu platform terpusat, transparan, dan dapat diaudit.

---

## 2. Tujuan Sistem

**Tujuan Umum:**
Menyediakan platform digital terpusat untuk mengelola proses pengajuan, persetujuan, pembayaran, dan pelaporan reimbursement secara efisien, transparan, dan akuntabel.

**Tujuan Khusus:**
1. Mendigitalkan seluruh siklus reimbursement sehingga menghilangkan proses berbasis kertas.
2. Mempercepat proses persetujuan melalui alur approval berjenjang yang otomatis.
3. Memberikan transparansi status pengajuan secara real-time kepada karyawan.
4. Menyimpan bukti pengeluaran secara digital dan terorganisir.
5. Mempermudah bagian keuangan dalam memproses dan melacak pembayaran.
6. Menyediakan pelaporan dan analitik untuk pengambilan keputusan.
7. Menjamin akuntabilitas melalui pencatatan audit log seluruh aktivitas.
8. Menerapkan kontrol akses berbasis peran (RBAC) untuk menjaga keamanan data.

---

## 3. Identifikasi Masalah

| No | Masalah | Dampak |
|----|---------|--------|
| 1 | Pengajuan berbasis kertas/spreadsheet | Bukti mudah hilang, sulit dilacak, tidak efisien |
| 2 | Status pengajuan tidak transparan | Karyawan tidak tahu posisi pengajuannya; sering menanyakan manual |
| 3 | Proses approval lambat & manual | Tergantung ketersediaan approver; dokumen bisa tertahan lama |
| 4 | Rekapitulasi & laporan sulit dibuat | Finance harus menghitung manual; rawan salah |
| 5 | Tidak ada jejak audit | Sulit menelusuri siapa menyetujui/mengubah apa dan kapan |
| 6 | Bukti pembayaran tidak terarsip rapi | Sulit verifikasi saat audit atau sengketa |
| 7 | Risiko kesalahan pembayaran | Nominal salah, rekening salah, atau pembayaran ganda |
| 8 | Tidak ada kontrol akses yang jelas | Data sensitif keuangan bisa diakses pihak tak berwenang |

---

## 4. Solusi Sistem

Reimbursement Management System menjawab masalah di atas melalui:

1. **Pengajuan Digital** — Formulir online dengan upload bukti (banyak file), validasi nominal, dan penyimpanan terpusat.
2. **Approval Berjenjang Otomatis** — State machine status yang jelas (Draft → Submitted → Manager Approved → Finance Approved → Paid) dengan alur reject dan revisi.
3. **Transparansi Real-time** — Timeline status dan notifikasi (in-app + email) di setiap perubahan status.
4. **Modul Pembayaran Realistis** — Manajemen rekening karyawan, bukti pembayaran, nomor referensi, dengan proteksi race condition via database transaction/locking.
5. **Dashboard & Analitik** — Statistik dan grafik per role untuk memantau pengeluaran dan performa proses.
6. **Pelaporan & Export** — Filter, search, dan export ke PDF/Excel/CSV.
7. **Audit Log Generik** — Pencatatan otomatis seluruh aktivitas (login, CRUD, approve, reject, payment) dengan old/new data, IP, dan browser.
8. **RBAC Penuh** — Enam role dengan hak akses, menu, dan route yang disesuaikan.
9. **Keamanan Berlapis** — CSRF, rate limiting, password policy, login attempt limit sejak awal; hardening lanjutan sebelum production.

---

## 5. Target Pengguna

**Target Organisasi:**
Perusahaan skala kecil hingga menengah-besar yang memiliki proses reimbursement rutin dan struktur persetujuan berjenjang (karyawan → atasan → keuangan).

**Target Individu (Aktor):**
- **Karyawan** yang mengajukan penggantian biaya.
- **Manager/Atasan** yang menyetujui pengajuan tim.
- **Bagian Keuangan (Finance)** yang memverifikasi dan membayar.
- **Administrator sistem** yang mengelola data master dan pengguna.
- **Auditor** yang mengawasi kepatuhan dan menelusuri jejak audit.
- **Super Admin** sebagai pemegang kontrol tertinggi sistem.

---

## 6. Role Pengguna

> Definisi role ini bersifat **kanonik** dan konsisten dipakai di seluruh fase berikutnya (RBAC di Phase 7, Master Data di Phase 8, dst).

### 6.1 Super Admin
Pemegang kontrol tertinggi atas sistem.
- Mengelola seluruh konfigurasi sistem dan data master.
- Mengelola seluruh user termasuk membuat/menonaktifkan Admin.
- Mengelola role & permission (RBAC).
- Akses penuh ke seluruh modul, termasuk audit log.
- Dapat melakukan override konfigurasi tingkat sistem (mis. batas ukuran file, kebijakan keamanan).

### 6.2 Admin
Pengelola operasional data master dan pengguna sehari-hari.
- CRUD Department, Category, User (kecuali membuat Super Admin), Master Bank.
- Mengelola data referensi sistem.
- Melihat dashboard keseluruhan dan laporan.
- **Tidak** mengubah role/permission tingkat sistem (kewenangan Super Admin).

### 6.3 Employee (Karyawan)
Pengaju reimbursement.
- Membuat, mengedit, menghapus draft, dan submit pengajuan.
- Mengelola rekening bank pribadi (satu atau lebih).
- Melihat riwayat & timeline status pengajuannya sendiri.
- Menerima notifikasi atas perubahan status.
- **Hanya** dapat melihat data miliknya sendiri.

### 6.4 Manager (Atasan)
Penyetuju tingkat pertama.
- Melihat pengajuan dari anggota tim/department-nya.
- Approve atau Reject (dengan alasan wajib) pengajuan.
- Meminta revisi (opsional, sesuai keputusan state machine).
- Melihat dashboard approval tim dan riwayat approval.

### 6.5 Finance (Keuangan)
Penyetuju tingkat kedua sekaligus pelaksana pembayaran.
- Melihat pengajuan yang telah disetujui Manager.
- Approve atau Reject dari sisi keuangan.
- Memproses pembayaran atas pengajuan berstatus "Finance Approved".
- Mengunggah bukti pembayaran, mengisi nomor referensi & metode pembayaran.
- Melihat riwayat pembayaran dan laporan keuangan.

### 6.6 Auditor
Pengawas kepatuhan, akses **read-only**.
- Akses read-only penuh ke seluruh data reimbursement, pembayaran, dan audit log.
- Tidak dapat membuat, mengubah, menyetujui, atau menghapus apa pun.
- Melihat dashboard keseluruhan dan mengekspor laporan/audit log.

### 6.7 Matriks Hak Akses (Ringkas)

| Modul / Aksi | Super Admin | Admin | Employee | Manager | Finance | Auditor |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| Kelola Role & Permission | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ |
| CRUD Master Data (Dept/Cat/Bank) | ✔ | ✔ | ✘ | ✘ | ✘ | ✘ |
| Kelola User | ✔ | ✔* | ✘ | ✘ | ✘ | ✘ |
| Buat/Submit Reimbursement | ✔ | ✘ | ✔ | ✘ | ✘ | ✘ |
| Kelola Rekening Pribadi | ✔ | ✘ | ✔ | ✔ | ✔ | ✘ |
| Approve/Reject (Manager) | ✘ | ✘ | ✘ | ✔ | ✘ | ✘ |
| Approve/Reject (Finance) | ✘ | ✘ | ✘ | ✘ | ✔ | ✘ |
| Proses Pembayaran | ✘ | ✘ | ✘ | ✘ | ✔ | ✘ |
| Lihat Dashboard Keseluruhan | ✔ | ✔ | ✘ | ✘ | ✘ | ✔ |
| Lihat Semua Data (read-only) | ✔ | ✔ | ✘ | ✘ | ✘ | ✔ |
| Export Laporan | ✔ | ✔ | ✔** | ✔** | ✔ | ✔ |
| Akses Audit Log | ✔ | ✔ | ✘ | ✘ | ✘ | ✔ (read-only) |

> \* Admin mengelola user biasa, tidak dapat membuat/mengubah Super Admin.
> \*\* Employee/Manager hanya mengekspor data dalam lingkup kewenangannya.

---

## 7. Fitur Utama

1. **Autentikasi & RBAC** — Login/logout, 6 role, menu & route dinamis per role.
2. **Master Data** — CRUD Department, Category, User, Role, Master Bank.
3. **Modul Reimbursement** — Draft, submit, edit, upload banyak bukti, timeline status.
4. **Approval Berjenjang** — Manager → Finance, dengan approve/reject/revisi + notes.
5. **Payment Management** — Rekening karyawan, proses pembayaran, bukti bayar, race-condition safe.
6. **Dashboard & Analitik** — Statistik & grafik per role.
7. **Notifikasi Multi-channel** — In-app (database) + email (queue).
8. **Laporan & Export** — Filter, search, export PDF/Excel/CSV.
9. **Audit Log Generik** — Pencatatan seluruh aktivitas sistem.
10. **File Management Terpusat** — Upload, preview, download, validasi tipe & ukuran.
11. **Keamanan** — CSRF, rate limit, password policy, login attempt limit, hardening.

---

## 8. Business Flow

### 8.1 Alur Utama (Happy Path)
```
1. Employee membuat pengajuan reimbursement (Draft)
   → mengisi kategori, nominal, alasan, memilih rekening tujuan, upload bukti
2. Employee submit pengajuan → status: Submitted
3. Sistem mengirim notifikasi ke Manager
4. Manager review → Approve → status: Manager Approved
5. Sistem mengirim notifikasi ke Finance
6. Finance review → Approve → status: Finance Approved
7. Finance memproses pembayaran (input referensi, metode, upload bukti bayar)
8. Sistem mengunci record (locking) & memvalidasi nominal
9. Pembayaran berhasil → status reimbursement: Paid
10. Sistem mengirim notifikasi "Paid" ke Employee
11. Seluruh langkah tercatat di Audit Log
```

### 8.2 Alur Alternatif — Reject oleh Manager
```
4a. Manager menolak (alasan wajib) → status: Manager Rejected
    → notifikasi ke Employee
    → Employee dapat merevisi (Revision Requested) lalu submit ulang, atau kembali ke Draft
```

### 8.3 Alur Alternatif — Reject oleh Finance
```
6a. Finance menolak (alasan wajib) → status: Finance Rejected
    → notifikasi ke Employee
    → Employee dapat merevisi lalu submit ulang
```

### 8.4 Alur Revisi (Resubmit)
```
- Setelah reject, sistem dapat memindahkan pengajuan ke status "Revision Requested".
- Employee memperbaiki data/bukti → submit ulang → kembali ke alur Submitted.
- Riwayat revisi tersimpan di timeline.
```

---

## 9. Use Case

### 9.1 Daftar Aktor
Super Admin, Admin, Employee, Manager, Finance, Auditor, System (aktor otomatis untuk notifikasi & audit).

### 9.2 Daftar Use Case per Aktor

**Employee**
- UC-01 Login / Logout
- UC-02 Membuat draft reimbursement
- UC-03 Mengedit / menghapus draft
- UC-04 Submit reimbursement
- UC-05 Upload bukti (banyak file)
- UC-06 Melihat detail & timeline status
- UC-07 Mengelola rekening bank pribadi
- UC-08 Merevisi & submit ulang pengajuan yang ditolak
- UC-09 Melihat notifikasi

**Manager**
- UC-10 Melihat daftar pengajuan tim
- UC-11 Approve pengajuan
- UC-12 Reject pengajuan (alasan wajib)
- UC-13 Meminta revisi (opsional)
- UC-14 Melihat riwayat approval

**Finance**
- UC-15 Melihat pengajuan "Manager Approved"
- UC-16 Approve / Reject dari sisi keuangan
- UC-17 Memproses pembayaran
- UC-18 Upload bukti pembayaran
- UC-19 Melihat riwayat pembayaran
- UC-20 Melihat laporan keuangan

**Admin**
- UC-21 CRUD Department & Category
- UC-22 CRUD User
- UC-23 CRUD Master Bank
- UC-24 Melihat dashboard & laporan keseluruhan

**Super Admin**
- UC-25 Semua kewenangan Admin
- UC-26 Kelola Role & Permission
- UC-27 Konfigurasi sistem

**Auditor**
- UC-28 Melihat seluruh data (read-only)
- UC-29 Melihat & mengekspor audit log

**System**
- UC-30 Mengirim notifikasi otomatis
- UC-31 Mencatat audit log otomatis

### 9.3 Contoh Deskripsi Use Case Detail

**UC-17 — Memproses Pembayaran**
- **Aktor:** Finance
- **Prasyarat:** Reimbursement berstatus "Finance Approved"; Employee memiliki rekening aktif.
- **Alur Utama:**
  1. Finance membuka pengajuan berstatus "Finance Approved".
  2. Finance mengisi nomor referensi, metode pembayaran, catatan, dan mengunggah bukti.
  3. Sistem mengunci record (database transaction/locking).
  4. Sistem memvalidasi nominal pembayaran ≤ nominal disetujui.
  5. Sistem menyimpan pembayaran & mengubah status reimbursement menjadi "Paid".
  6. Sistem mencatat audit log & mengirim notifikasi ke Employee.
- **Alur Alternatif:** Jika record sudah dibayar staff lain (lock gagal/status bukan Finance Approved), sistem menolak dan menampilkan pesan.
- **Pascakondisi:** Status "Paid", bukti tersimpan, notifikasi terkirim.

---

## 10. Activity Diagram (Teks)

### 10.1 Aktivitas: Pengajuan → Pembayaran
```
[Mulai]
   |
   v
(Employee) Isi form reimbursement + upload bukti + pilih rekening
   |
   v
<Data valid?> --Tidak--> Tampilkan error validasi --> (kembali isi form)
   | Ya
   v
(Employee) Simpan sebagai Draft
   |
   v
(Employee) Submit  ==> Status: Submitted
   |
   v
(System) Kirim notifikasi ke Manager
   |
   v
(Manager) Review pengajuan
   |
   +--< Keputusan Manager >--+
   |                          |
  Approve                   Reject (alasan wajib)
   |                          |
   v                          v
Status: Manager Approved   Status: Manager Rejected
   |                          |
   v                          v
(System) Notif ke Finance   (System) Notif ke Employee
   |                          |
   v                          v
(Finance) Review          <Revisi?> --Ya--> Status: Revision Requested --> (Employee edit) --> Submit
   |                          | Tidak
   +--< Keputusan Finance >--+  --> [Selesai - ditolak]
   |                          |
  Approve                   Reject (alasan wajib)
   |                          |
   v                          v
Status: Finance Approved   Status: Finance Rejected --> Notif Employee --> (Revisi/Selesai)
   |
   v
(Finance) Proses pembayaran
   |
   v
(System) Lock record + validasi nominal
   |
   v
<Valid & belum dibayar?> --Tidak--> Tolak pembayaran --> (kembali)
   | Ya
   v
Status: Paid + simpan bukti bayar
   |
   v
(System) Notif "Paid" ke Employee + catat Audit Log
   |
   v
[Selesai]
```

### 10.2 Aktivitas: Login dengan Kontrol Keamanan
```
[Mulai] --> Input kredensial --> <Valid?>
   |Tidak--> Increment login attempt --> <Attempt > batas?> --Ya--> Kunci sementara --> [Selesai]
   |                                                          --Tidak--> Tampilkan error --> (ulangi)
   |Ya--> Reset attempt --> Buat sesi --> Catat audit (login) --> Redirect dashboard sesuai role --> [Selesai]
```

---

## 11. Functional Requirement

| Kode | Kebutuhan Fungsional |
|------|----------------------|
| FR-01 | Sistem menyediakan login/logout dengan autentikasi aman. |
| FR-02 | Sistem menerapkan RBAC untuk 6 role dengan menu & route dinamis. |
| FR-03 | Employee dapat membuat, mengedit, menghapus draft, dan submit reimbursement. |
| FR-04 | Sistem mendukung upload banyak file bukti (JPG, PNG, PDF) dengan validasi tipe & ukuran. |
| FR-05 | Sistem memvalidasi nominal dan mewajibkan alasan pengajuan. |
| FR-06 | Sistem menampilkan timeline & riwayat status setiap pengajuan. |
| FR-07 | Manager dapat approve/reject (alasan wajib) dan meminta revisi. |
| FR-08 | Finance dapat approve/reject serta memproses pembayaran. |
| FR-09 | Sistem menegakkan state machine status reimbursement & payment. |
| FR-10 | Employee dapat mengelola satu atau lebih rekening bank & memilih rekening utama. |
| FR-11 | Sistem mewajibkan pemilihan rekening aktif saat submit. |
| FR-12 | Sistem mencegah pembayaran ganda via transaction/locking. |
| FR-13 | Sistem memvalidasi nominal bayar ≤ nominal disetujui. |
| FR-14 | Sistem mengubah status menjadi "Paid" otomatis setelah pembayaran berhasil. |
| FR-15 | Sistem mengirim notifikasi in-app & email pada event kunci. |
| FR-16 | Sistem menyediakan dashboard & analitik per role. |
| FR-17 | Sistem menyediakan laporan dengan filter, search, dan export PDF/Excel/CSV. |
| FR-18 | Sistem mencatat audit log seluruh aktivitas (old/new data, IP, browser). |
| FR-19 | Auditor memperoleh akses read-only penuh ke seluruh data & audit log. |
| FR-20 | Admin/Super Admin dapat CRUD master data (Department, Category, User, Role, Bank). |
| FR-21 | Sistem menerapkan CSRF, rate limiting, password policy, login attempt limit. |

---

## 12. Non Functional Requirement

| Kode | Kebutuhan Non-Fungsional | Target |
|------|--------------------------|--------|
| NFR-01 Performa | Waktu respons halaman utama & API umum | < 2 detik pada kondisi normal |
| NFR-02 Skalabilitas | Mendukung pertumbuhan pengguna & data | Arsitektur queue + index DB |
| NFR-03 Keamanan | Proteksi CSRF, XSS, SQL Injection, RBAC | Wajib di seluruh endpoint |
| NFR-04 Keandalan | Ketersediaan sistem | ≥ 99% uptime (produksi) |
| NFR-05 Auditability | Seluruh aksi kritis tercatat | 100% event kunci ter-log |
| NFR-06 Usability | Antarmuka modern, responsif, mudah dipakai | Mobile & desktop friendly |
| NFR-07 Maintainability | Kode terstruktur, teruji | Test wajib per fase fitur |
| NFR-08 Kompatibilitas | Berjalan di browser modern | Chrome, Firefox, Edge, Safari terbaru |
| NFR-09 Integritas Data | Konsistensi transaksi keuangan | Transaction/locking, constraint DB |
| NFR-10 Portabilitas | Deploy via Docker | Local, staging, production |
| NFR-11 Backup | Cadangan data berkala | Backup DB terjadwal |
| NFR-12 Konkurensi | Aman terhadap race condition pembayaran | Locking pessimistic/optimistic |

---

## 13. User Story

**Employee**
- US-01: *Sebagai Employee*, saya ingin membuat pengajuan reimbursement dan menyimpannya sebagai draft, agar saya bisa melengkapinya nanti sebelum submit.
- US-02: *Sebagai Employee*, saya ingin mengunggah beberapa bukti sekaligus, agar semua nota pengeluaran terlampir dalam satu pengajuan.
- US-03: *Sebagai Employee*, saya ingin melihat timeline status pengajuan saya, agar tahu posisi pengajuan tanpa bertanya manual.
- US-04: *Sebagai Employee*, saya ingin memperbaiki pengajuan yang ditolak dan mengirim ulang, agar tidak perlu membuat pengajuan dari awal.
- US-05: *Sebagai Employee*, saya ingin menyimpan beberapa rekening dan menandai rekening utama, agar pembayaran masuk ke rekening yang benar.

**Manager**
- US-06: *Sebagai Manager*, saya ingin melihat daftar pengajuan tim yang menunggu persetujuan, agar bisa memprosesnya tepat waktu.
- US-07: *Sebagai Manager*, saya ingin menolak dengan alasan wajib, agar Employee memahami kenapa pengajuannya ditolak.

**Finance**
- US-08: *Sebagai Finance*, saya ingin melihat pengajuan yang sudah disetujui Manager, agar bisa memverifikasi dari sisi keuangan.
- US-09: *Sebagai Finance*, saya ingin memproses pembayaran dan mengunggah bukti bayar, agar pembayaran terdokumentasi.
- US-10: *Sebagai Finance*, saya ingin sistem mencegah pembayaran ganda, agar tidak terjadi kesalahan pembayaran saat dua staff memproses bersamaan.

**Admin / Super Admin**
- US-11: *Sebagai Admin*, saya ingin mengelola data master (department, category, bank, user), agar data referensi selalu akurat.
- US-12: *Sebagai Super Admin*, saya ingin mengatur role & permission, agar setiap pengguna hanya mengakses yang menjadi haknya.

**Auditor**
- US-13: *Sebagai Auditor*, saya ingin melihat dan mengekspor audit log, agar dapat menelusuri seluruh aktivitas untuk kepatuhan.

---

## 14. Acceptance Criteria

Ditulis dalam format Given–When–Then untuk sebagian user story kunci.

**AC untuk US-01 (Buat Draft)**
- Given Employee sudah login, When mengisi form dengan data valid dan menekan "Simpan Draft", Then pengajuan tersimpan dengan status "Draft" dan muncul di daftar pengajuannya.
- Given form belum lengkap (nominal kosong/alasan kosong), When menekan submit, Then sistem menampilkan pesan validasi dan tidak menyimpan.

**AC untuk US-02 (Upload Bukti)**
- Given Employee membuat pengajuan, When mengunggah file berformat selain JPG/PNG/PDF atau melebihi batas ukuran, Then sistem menolak file tersebut dengan pesan jelas.
- Given file valid, When diunggah, Then file muncul di daftar lampiran dan dapat di-preview/hapus sebelum submit.

**AC untuk US-07 (Reject oleh Manager)**
- Given Manager membuka pengajuan "Submitted", When menekan "Reject" tanpa mengisi alasan, Then sistem menolak aksi dan meminta alasan.
- Given alasan diisi, When menekan "Reject", Then status berubah menjadi "Manager Rejected" dan Employee menerima notifikasi.

**AC untuk US-09 & US-10 (Pembayaran & Anti Ganda)**
- Given reimbursement berstatus "Finance Approved", When Finance memproses pembayaran valid, Then status berubah "Paid", bukti tersimpan, dan notifikasi terkirim.
- Given dua staff Finance memproses reimbursement yang sama bersamaan, When salah satu berhasil, Then upaya kedua ditolak karena status sudah bukan "Finance Approved" (locking).
- Given nominal bayar > nominal disetujui, When Finance submit, Then sistem menolak dengan pesan validasi.

**AC untuk US-13 (Audit Log Auditor)**
- Given user berperan Auditor, When membuka modul audit log, Then dapat melihat & mengekspor seluruh log namun tidak menemukan aksi create/update/delete/approve.

**AC untuk RBAC (US-12)**
- Given user berperan Employee, When mengakses URL modul master data secara langsung, Then sistem menolak dengan 403/redirect karena tidak berwenang.

---

## 15. Currency & Format Nominal

**Keputusan:** Sistem menggunakan **mata uang tunggal: Rupiah Indonesia (IDR)**.

Alasan: Target pengguna adalah perusahaan di Indonesia dengan proses reimbursement domestik. Multi-currency menambah kompleksitas (kurs, konversi, pembulatan) yang tidak diperlukan pada lingkup awal.

**Aturan Format:**
- Kode mata uang: `IDR`.
- Penyimpanan di database: nilai integer/bigint dalam **satuan rupiah penuh** (tanpa desimal sen), karena praktik reimbursement di Indonesia umumnya tidak menggunakan sen. *(Alternatif: simpan dalam satuan terkecil bila diperlukan presisi — dikonfirmasi di Phase 2 Database Design.)*
- Tampilan UI: format ribuan dengan pemisah titik, prefix "Rp". Contoh: `Rp 1.500.000`.
- Input: menerima angka polos; sistem melakukan format otomatis dan validasi (harus > 0, tidak boleh negatif).
- Validasi nominal pembayaran: `nominal_bayar ≤ nominal_disetujui`.

**Catatan Ekstensibilitas:** Struktur data akan menyertakan kolom/kode currency agar dapat dikembangkan ke multi-currency di masa depan tanpa perombakan besar, meski implementasi awal dikunci ke IDR.

---

## 16. Ringkasan & Langkah Berikutnya

Dokumen ini menetapkan fondasi Reimbursement Management System: latar belakang, tujuan, masalah, solusi, enam role kanonik (Super Admin, Admin, Employee, Manager, Finance, Auditor), fitur utama, business flow dengan state machine eksplisit, use case, activity diagram, kebutuhan fungsional & non-fungsional, user story, acceptance criteria, serta keputusan currency (IDR tunggal).

**Konsistensi lintas fase yang sudah dikunci di sini:**
- Enam role dipakai konsisten mulai RBAC (Phase 7) hingga akhir.
- State machine status: `Draft → Submitted → Manager Approved → Finance Approved → Paid`, dengan jalur reject (Manager/Finance) dan revisi (Revision Requested).
- Status pembayaran: `Pending, Processing, Paid, Failed, Cancelled`.
- Currency tunggal IDR dengan struktur yang siap ekstensibel.

**Langkah berikutnya — Phase 2 (Database Design):**
Merancang ERD, relasi tabel, PK/FK, constraint, enum (termasuk enum status reimbursement & payment dari state machine di atas), index, penjelasan tabel, dan normalisasi minimal 3NF — tanpa membuat migration.

---

*Dokumen ini adalah keluaran Phase 1. Belum ada kode yang dibuat sesuai instruksi fase.*
