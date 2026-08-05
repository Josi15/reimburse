# FundBack — Reimbursement Management System

Aplikasi pengajuan & pencairan dana modern: pengajuan → persetujuan berjenjang (Manager → Finance) → pembayaran — lengkap dengan RBAC berlapis, notifikasi multi-channel, audit trail, laporan + export, dan REST API terdokumentasi.

**Stack:** Laravel 12 · React (Inertia + Breeze) · Vite · Tailwind CSS · PostgreSQL · Sanctum

## Fitur Utama

- **Reimbursement lifecycle** dengan state machine eksplisit: `draft → submitted → manager_approved → finance_approved → paid` (+ reject & revisi/resubmit)
- **4 jenis pengajuan** dengan form yang menyesuaikan sendiri — Reimbursement Biaya, Pengadaan Barang, Layanan/Server, dan Lembur. Nominal barang/layanan/lembur dihitung server dari detailnya (jumlah × harga satuan, jam × upah). Definisi field tunggal ada di `app/Enums/ClaimType.php`; menambah jenis atau kolom isian cukup di file itu — API `/api/options/claim-types` dan form React ikut otomatis
- **RBAC penuh** — Super Admin, Direktur, Admin, Finance, Manager, Supervisor, Project Manager, Employee, Staf Magang, Auditor; menu & aksi dinamis per role, label role tampil di sidebar
- **Anggaran proyek** — tiap proyek dipegang seorang Project Manager yang bisa memantau sisa dana proyeknya (anggaran − yang sudah dibayar − yang masih berjalan) di halaman **Anggaran Proyek**; Direksi/Admin/Finance/Auditor melihat seluruh proyek
- **Payment management** — master bank, rekening karyawan (satu rekening utama), pembayaran race-safe (`lockForUpdate` + partial unique index anti double-pay), bukti transfer
- **Notifikasi rantai penuh** — pengaju dapat tanda terima saat mengirim, dikabari tiap keputusan, sampai dana cair; approver Manager → Finance → petugas pembayaran dikabari saat gilirannya tiba (lihat `ReimbursementNotifier`)
- **Audit log generik** — login/logout/CRUD/approve/reject/payment dengan old/new data, IP, browser; read-only untuk Auditor
- **Laporan & export** PDF/Excel/CSV, global search, dashboard analitik per role
- **File management** terpusat — multi-upload, preview, download, replace, deep MIME check
- **API docs** OpenAPI 3.1 auto-generated di `/docs/api` (92 endpoint)

## Quickstart (Development)

```bash
composer install && npm install
cp .env.example .env               # isi kredensial PostgreSQL
php artisan key:generate
php artisan migrate --seed         # role, permission, master data, akun demo
npm run dev                        # terminal 1 — Vite
php artisan serve                  # terminal 2 — http://127.0.0.1:8000
php artisan queue:work             # terminal 3 — OPSIONAL, hanya untuk email
```

> **Notifikasi in-app tidak butuh queue worker.** Channel `database` dijalankan
> pada koneksi `sync` (lihat `App\Notifications\Concerns\DeliversInAppImmediately`),
> jadi lonceng notifikasi langsung terisi begitu aksi dilakukan. Hanya channel
> `mail` yang mengantre — jalankan `queue:work` bila ingin email ikut terkirim.

**Akun demo** (password: `password`, semuanya `@fundback.test`)

Seeder membentuk organisasi lengkap: **47 user di 5 departemen** (IT, FIN, HR, MKT, OPS). Tiap departemen berdiri sendiri — punya **Manager, Supervisor, Admin, Project Manager**, beberapa Karyawan & Staf Magang — sehingga alur submit → approve → bayar bisa diuji di departemen mana pun (Admin/Manager/Supervisor hanya melihat departemennya sendiri). Rantai atasan: Direktur → Manager → Supervisor → Karyawan/Magang, dan tiap penerima pembayaran sudah punya rekening utama.

| Departemen | Manager | Supervisor | Admin | Project Manager |
| --- | --- | --- | --- | --- |
| IT | `manager@` | `supervisor@` | `admin@` | `pm@` |
| FIN | `dimas.fin@` | `nadia.fin@` | `bayu.fin@` | `vera.fin@` |
| HR | `sari.hr@` | `rendi.hr@` | `putri.hr@` | `iqbal.hr@` |
| MKT | `reza.mkt@` | `alya.mkt@` | `fikri.mkt@` | `nabila.mkt@` |
| OPS | `agus.ops@` | `lina.ops@` | `teguh.ops@` | `yuni.ops@` |

Lintas departemen: `super@` (Super Admin), `direktur@` (Direksi), `finance@` (Finance), `auditor@` (read-only). Pengaju harian: `employee@`, `magang@`. Tiap departemen dipasangkan dengan **2 proyek** (10 proyek, `PRJ-2026-001` … `-010`) — pemegangnya Project Manager departemen itu, anggotanya seluruh isi departemen.

## Test

```bash
php artisan test        # 277 test / 896 assertion (butuh DB reimbursement_testing)
./vendor/bin/pint       # code style
```

## Deployment

Docker single-host (app, nginx, postgres, redis, queue, scheduler) — lihat [docs/22-deployment.md](docs/22-deployment.md). CI/CD: `.github/workflows/ci.yml` (lint → audit → build → test → docker image).

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [docs/01-project-planning.md](docs/01-project-planning.md) | Perencanaan: role, use case, requirement, state machine |
| [docs/02-database-design.md](docs/02-database-design.md) | ERD, relasi, constraint, index, normalisasi 3NF |
| [docs/03-setup-project.md](docs/03-setup-project.md) | Setup environment & strategi konfigurasi |
| [docs/19-security-checklist.md](docs/19-security-checklist.md) | Review keamanan & checklist pra-production |
| [docs/20-optimization.md](docs/20-optimization.md) | Optimasi query, index, cache |
| [docs/21-testing-summary.md](docs/21-testing-summary.md) | Piramida pengujian |
| [docs/22-deployment.md](docs/22-deployment.md) | Panduan production |
| [docs/openapi.json](docs/openapi.json) | Spesifikasi OpenAPI (bisa diimpor ke Postman) |

---
Dibangun mengikuti roadmap 22 fase — planning → database → auth/RBAC → modul inti → notifikasi → laporan → audit → UI → docs → hardening → optimasi → testing → deployment.
