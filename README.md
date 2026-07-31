# FundBack — Reimbursement Management System

Aplikasi pengajuan & pencairan dana modern: pengajuan → persetujuan berjenjang (Manager → Finance) → pembayaran — lengkap dengan RBAC 6 role, notifikasi multi-channel, audit trail, laporan + export, dan REST API terdokumentasi.

**Stack:** Laravel 12 · React (Inertia + Breeze) · Vite · Tailwind CSS · PostgreSQL · Sanctum

## Fitur Utama

- **Reimbursement lifecycle** dengan state machine eksplisit: `draft → submitted → manager_approved → finance_approved → paid` (+ reject & revisi/resubmit)
- **4 jenis pengajuan** dengan form yang menyesuaikan sendiri — Reimbursement Biaya, Pengadaan Barang, Layanan/Server, dan Lembur. Nominal barang/layanan/lembur dihitung server dari detailnya (jumlah × harga satuan, jam × upah). Definisi field tunggal ada di `app/Enums/ClaimType.php`; menambah jenis atau kolom isian cukup di file itu — API `/api/options/claim-types` dan form React ikut otomatis
- **RBAC penuh** — Super Admin, Admin, Employee, Manager, Finance, Auditor; menu & aksi dinamis per role, label role tampil di sidebar
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

**Akun demo** (password: `password`): `super@`, `direktur@`, `admin@`, `finance@`, `manager@`, `supervisor@`, `employee@`, `magang@`, `auditor@` (semuanya `@fundback.test`)

## Test

```bash
php artisan test        # 196 test / 617 assertion (butuh DB reimbursement_testing)
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
