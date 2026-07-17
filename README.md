# Reimbursement Management System

Aplikasi manajemen reimbursement modern: pengajuan → persetujuan berjenjang (Manager → Finance) → pembayaran — lengkap dengan RBAC 6 role, notifikasi multi-channel, audit trail, laporan + export, dan REST API terdokumentasi.

**Stack:** Laravel 12 · React (Inertia + Breeze) · Vite · Tailwind CSS · PostgreSQL · Sanctum

## Fitur Utama

- **Reimbursement lifecycle** dengan state machine eksplisit: `draft → submitted → manager_approved → finance_approved → paid` (+ reject & revisi/resubmit)
- **RBAC penuh** — Super Admin, Admin, Employee, Manager, Finance, Auditor; menu & aksi dinamis per role
- **Payment management** — master bank, rekening karyawan (satu rekening utama), pembayaran race-safe (`lockForUpdate` + partial unique index anti double-pay), bukti transfer
- **Notifikasi** in-app + email (queue) pada submit/approve/reject/revisi/paid
- **Audit log generik** — login/logout/CRUD/approve/reject/payment dengan old/new data, IP, browser; read-only untuk Auditor
- **Laporan & export** PDF/Excel/CSV, global search, dashboard analitik per role
- **File management** terpusat — multi-upload, preview, download, replace, deep MIME check
- **API docs** OpenAPI 3.1 auto-generated di `/docs/api` (49 endpoint)

## Quickstart (Development)

```bash
composer install && npm install
cp .env.example .env               # isi kredensial PostgreSQL
php artisan key:generate
php artisan migrate --seed         # role, permission, master data, akun demo
npm run dev                        # terminal 1 — Vite
php artisan serve                  # terminal 2 — http://127.0.0.1:8000
php artisan queue:work             # terminal 3 — email notifikasi
```

**Akun demo** (password: `password`): `super@rms.test`, `admin@rms.test`, `manager@rms.test`, `employee@rms.test`, `finance@rms.test`, `auditor@rms.test`

## Test

```bash
php artisan test        # 159 test / 483 assertion (butuh DB reimbursement_testing)
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
