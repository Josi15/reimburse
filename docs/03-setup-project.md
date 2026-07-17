# Setup Project
# Reimbursement Management System

> **Fase:** Phase 3 — Setup Project
> **Status:** Panduan setup & strategi konfigurasi (belum membuat fitur)
> **Referensi:** [01-project-planning.md](01-project-planning.md), [02-database-design.md](02-database-design.md)
> **Tanggal:** 2026-07-16

## Status Toolchain di Mesin Ini (hasil pengecekan)

| Tool | Versi Terdeteksi | Status | Kebutuhan Laravel 12 |
|------|------------------|--------|----------------------|
| PHP | 8.2.12 (ZTS, x64) | ✅ Siap | ≥ 8.2 |
| Composer | 2.9.7 | ✅ Siap | ≥ 2.x |
| Node.js | 24.14.0 | ✅ Siap | ≥ 18 (disarankan LTS/terbaru) |
| npm | 10.9.5 | ✅ Siap | — |
| Git | 2.52.0 (Windows) | ✅ Siap | — |
| PostgreSQL | *tidak terdeteksi* | ⚠️ Perlu diinstal | ≥ 14 (disarankan 16/17) |

> Hanya PostgreSQL yang perlu dipasang. Sisanya sudah siap.

---

## Daftar Isi
1. [Prasyarat & Ekstensi PHP](#1-prasyarat--ekstensi-php)
2. [Install PostgreSQL](#2-install-postgresql)
3. [Membuat Project Laravel 12](#3-membuat-project-laravel-12)
4. [Install Laravel Breeze (React)](#4-install-laravel-breeze-react)
5. [Tailwind CSS](#5-tailwind-css)
6. [Konfigurasi Environment (local / staging / production)](#6-konfigurasi-environment-local--staging--production)
7. [Konfigurasi Storage (local disk vs S3)](#7-konfigurasi-storage-local-disk-vs-s3)
8. [Konfigurasi Queue](#8-konfigurasi-queue)
9. [Konfigurasi Mail](#9-konfigurasi-mail)
10. [Struktur Direktori Proyek](#10-struktur-direktori-proyek)
11. [Inisialisasi Git](#11-inisialisasi-git)
12. [Rencana Dasar CI/CD](#12-rencana-dasar-cicd)
13. [Verifikasi Setup](#13-verifikasi-setup)

---

## 1. Prasyarat & Ekstensi PHP

Laravel 12 + PostgreSQL memerlukan ekstensi PHP berikut aktif di `php.ini`:

```
extension=pdo_pgsql
extension=pgsql
extension=fileinfo    ; untuk deep MIME check (Phase 19)
extension=mbstring
extension=openssl
extension=curl
extension=gd          ; untuk preview/thumbnail file (opsional)
extension=zip
```

**Cek ekstensi aktif:**
```powershell
php -m | Select-String -Pattern "pgsql|pdo_pgsql|fileinfo|mbstring|openssl|gd|zip"
```

Jika `pdo_pgsql`/`pgsql` belum aktif, buka `php.ini` (lokasinya via `php --ini`), hapus tanda `;` di depan baris ekstensi tersebut, simpan, lalu ulangi pengecekan.

---

## 2. Install PostgreSQL

PostgreSQL belum terpasang. Pilih salah satu:

### Opsi A — Installer resmi (disarankan untuk dev lokal Windows)
1. Unduh dari https://www.postgresql.org/download/windows/ (EDB installer), pilih versi 16 atau 17.
2. Saat instalasi: set password superuser `postgres`, port default `5432`, sertakan **pgAdmin** dan **Command Line Tools**.
3. Tambahkan `...\PostgreSQL\<versi>\bin` ke PATH agar `psql` dikenali.

### Opsi B — Chocolatey
```powershell
choco install postgresql16 --params '/Password:postgres'
```

### Opsi C — Docker (paling bersih, tanpa mengotori host)
```powershell
docker run --name rms-postgres -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=reimbursement -p 5432:5432 -d postgres:16
```

### Buat database aplikasi
```powershell
# via psql (Opsi A/B)
psql -U postgres -c "CREATE DATABASE reimbursement;"
psql -U postgres -c "CREATE USER rms_user WITH ENCRYPTED PASSWORD 'secret';"
psql -U postgres -c "GRANT ALL PRIVILEGES ON DATABASE reimbursement TO rms_user;"
```

**Verifikasi:**
```powershell
psql --version
psql -U postgres -d reimbursement -c "\conninfo"
```

---

## 3. Membuat Project Laravel 12

Direktori kerja saat ini: `e:\magang\part1` (kosong). Buat project Laravel **di dalam** direktori ini.

```powershell
# dari e:\magang\part1
composer create-project laravel/laravel:^12.0 .
```

> Titik (`.`) memasang Laravel ke direktori aktif. Jika direktori tidak sepenuhnya kosong (mis. sudah ada folder `docs/`), Composer akan menolak. Solusi:
> ```powershell
> # buat di subfolder lalu pindahkan, atau gunakan --prefer-dist di folder baru:
> composer create-project laravel/laravel:^12.0 app
> # lalu pindahkan isi 'app/' ke root, atau jadikan 'app/' sebagai root proyek Laravel.
> ```
> **Rekomendasi:** jadikan `e:\magang\part1` sebagai root Laravel dan simpan `docs/` sebagai subfolder dokumentasi di dalamnya (Laravel mengabaikan folder non-standar).

**Verifikasi:**
```powershell
php artisan --version   # harus menampilkan Laravel Framework 12.x
php artisan serve       # buka http://127.0.0.1:8000
```

---

## 4. Install Laravel Breeze (React)

Breeze menyediakan scaffolding auth (login, register, reset password, verifikasi) berbasis React + Inertia.

```powershell
composer require laravel/breeze --dev
php artisan breeze:install react
# Breeze otomatis: pasang Inertia, React, dependency npm, dan konfigurasi Vite
npm install
npm run dev
```

Pilihan saat `breeze:install react`:
- **Dark mode**: opsional (disarankan ya, konsisten dengan UI modern Phase 17).
- **TypeScript**: opsional. Rekomendasi: **ya** untuk maintainability, atau JavaScript bila tim belum familiar.
- **SSR**: tidak perlu untuk aplikasi internal ini.

> Breeze React sudah membawa **Vite** dan **Tailwind** terkonfigurasi. Langkah 5 hanya verifikasi/penyesuaian.

---

## 5. Tailwind CSS

Breeze React sudah memasang & mengonfigurasi Tailwind. Verifikasi:

- `tailwind.config.js` memuat path `./resources/js/**/*.{js,jsx,ts,tsx}` di `content`.
- `resources/css/app.css` memuat `@tailwind base; @tailwind components; @tailwind utilities;`.
- `vite.config.js` memuat plugin React & Laravel Vite.

Penyesuaian yang disarankan untuk sistem ini (dilakukan pada Phase 17 UI):
- Tambah palet warna brand & komponen (Card, Table, Modal, Toast) sebagai class utility/komponen React.
- Tambah plugin `@tailwindcss/forms` untuk styling form konsisten:
  ```powershell
  npm install -D @tailwindcss/forms
  ```

---

## 6. Konfigurasi Environment (local / staging / production)

**Strategi:** satu `.env` per lingkungan, **tidak** di-commit ke Git. Yang di-commit hanya template `.env.example` (dibuat lengkap di Phase 5).

| Variabel kunci | Local | Staging | Production |
|----------------|-------|---------|------------|
| `APP_ENV` | `local` | `staging` | `production` |
| `APP_DEBUG` | `true` | `true` | **`false`** |
| `APP_URL` | `http://localhost:8000` | `https://staging.rms.example` | `https://rms.example` |
| `LOG_LEVEL` | `debug` | `debug` | `warning` |
| `DB_CONNECTION` | `pgsql` | `pgsql` | `pgsql` |
| `SESSION_DRIVER` | `database` | `database` | `database`/`redis` |
| `QUEUE_CONNECTION` | `database` | `database` | `redis` (disarankan) |
| `CACHE_STORE` | `database`/`file` | `redis` | `redis` |
| `FILESYSTEM_DISK` | `local` | `s3` | `s3` |
| `MAIL_MAILER` | `log`/`mailpit` | `smtp` | `smtp` |

Contoh blok koneksi DB (local) yang akan masuk `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=reimbursement
DB_USERNAME=rms_user
DB_PASSWORD=secret
```

Setelah mengubah `.env`:
```powershell
php artisan key:generate      # sekali di awal (mengisi APP_KEY)
php artisan config:clear
```

> **Prinsip keamanan:** `APP_DEBUG=false` wajib di production; kredensial hanya via env server (mis. secret manager / env panel), bukan di repo.

---

## 7. Konfigurasi Storage (local disk vs S3)

Sesuai keputusan roadmap: **local disk untuk development, S3-compatible untuk production.**

Konfigurasi ada di `config/filesystems.php` (disk `local`, `public`, `s3` sudah tersedia default Laravel). Yang perlu diatur:

**Local (dev):**
```env
FILESYSTEM_DISK=local
```
Bukti reimbursement/pembayaran disimpan di `storage/app/private/...`. Jalankan sekali:
```powershell
php artisan storage:link   # symlink storage/app/public -> public/storage untuk file yang boleh diakses publik
```

**Production (S3-compatible — AWS S3 / MinIO / Cloudflare R2):**
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=xxx
AWS_SECRET_ACCESS_KEY=xxx
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=rms-attachments
AWS_ENDPOINT=            # isi jika MinIO/R2
AWS_USE_PATH_STYLE_ENDPOINT=false
```
Paket driver S3:
```powershell
composer require league/flysystem-aws-s3-v3 "^3.0"
```

> Modul File Management (Phase 16) akan memakai abstraksi `Storage::disk(config('filesystems.default'))` sehingga kode tidak bergantung pada disk tertentu — cukup ganti `FILESYSTEM_DISK` per lingkungan. Batas ukuran & tipe file dikonfigurasi (Phase 11/16/19).

---

## 8. Konfigurasi Queue

Dipakai untuk **email notification** (Phase 13) agar tidak memblokir request.

**Local/staging (database driver — sederhana, tanpa dependensi tambahan):**
```env
QUEUE_CONNECTION=database
```
```powershell
php artisan make:queue-table    # (Laravel 12: php artisan queue:table bila tersedia)
php artisan migrate             # membuat tabel jobs & failed_jobs
php artisan queue:work          # menjalankan worker (dev)
```

**Production (Redis — disarankan untuk throughput):**
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```
Worker di production dijalankan oleh **Supervisor** (dikonfigurasi di Phase 22).

---

## 9. Konfigurasi Mail

Dipakai untuk notifikasi email & reset password.

**Local (tanpa kirim nyata):**
```env
MAIL_MAILER=log        # email tertulis ke storage/logs/laravel.log
```
Atau pakai **Mailpit** (SMTP dummy dengan UI) untuk melihat email:
```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
```

**Staging/Production (SMTP nyata — mis. Mailtrap staging, atau SES/Postmark/SMTP korporat):**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=xxx
MAIL_PASSWORD=xxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@rms.example"
MAIL_FROM_NAME="Reimbursement System"
```

---

## 10. Struktur Direktori Proyek

Target struktur setelah setup (relevan dengan fase berikutnya):

```
e:\magang\part1\
├── app/
│   ├── Http/Controllers/       # controller REST API & web (Phase 8+)
│   ├── Models/                 # model Eloquent (Phase 6)
│   ├── Policies/               # authorization (Phase 7)
│   ├── Observers/              # audit log hooks (Phase 15)
│   └── Notifications/          # notifikasi (Phase 13)
├── config/                     # filesystems, queue, mail, dll
├── database/
│   ├── migrations/             # Phase 4
│   ├── seeders/                # Phase 5
│   └── factories/              # Phase 5/6
├── docs/                       # ← dokumentasi fase (01, 02, 03, ...)
├── resources/js/               # React (Inertia + Breeze) — Phase 17
├── routes/
│   ├── web.php
│   └── api.php                 # REST API
├── storage/app/                # file lokal (dev)
├── tests/                      # Feature/API test (checklist per fase)
├── .env                        # tidak di-commit
├── .env.example                # template (Phase 5)
└── docker/ , Dockerfile        # Phase 22
```

---

## 11. Inisialisasi Git

Direktori ini belum berupa git repo. Inisialisasi:

```powershell
git init
git branch -M main
# .gitignore bawaan Laravel sudah mengabaikan .env, /vendor, /node_modules, /storage/*
git add .
git commit -m "chore: initial Laravel 12 setup with Breeze React"
```

Pastikan `.gitignore` memuat: `/vendor`, `/node_modules`, `.env`, `.env.*` (kecuali `.env.example`), `/storage/*.key`, `/public/storage`, `/public/build`.

> **Catatan:** Buat repo di GitHub lalu tambahkan remote saat siap (`git remote add origin ...`). Roadmap menempatkan Git & GitHub sebagai bagian tech stack.

---

## 12. Rencana Dasar CI/CD

Garis besar pipeline (implementasi penuh di Phase 22). Contoh **GitHub Actions** dengan 3 tahap: **lint → test → build**.

```yaml
# .github/workflows/ci.yml  (garis besar — dibuat penuh nanti)
name: CI
on:
  push: { branches: [main, develop] }
  pull_request: { branches: [main, develop] }

jobs:
  lint-test-build:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16
        env: { POSTGRES_DB: testing, POSTGRES_PASSWORD: postgres }
        ports: ['5432:5432']
        options: >-
          --health-cmd pg_isready --health-interval 10s
          --health-timeout 5s --health-retries 5
    steps:
      - uses: actions/checkout@v4
      # PHP
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2', extensions: pdo_pgsql, pgsql, mbstring, fileinfo }
      - run: composer install --no-interaction --prefer-dist
      # 1) LINT
      - run: ./vendor/bin/pint --test        # Laravel Pint (code style)
      # Node
      - uses: actions/setup-node@v4
        with: { node-version: '20', cache: 'npm' }
      - run: npm ci
      - run: npm run lint --if-present         # ESLint React
      # 2) TEST
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan migrate --force
      - run: php artisan test                  # Pest/PHPUnit — Feature & API test
      # 3) BUILD
      - run: npm run build                     # Vite production build
```

**Tiga tahap wajib:**
1. **Lint** — `./vendor/bin/pint --test` (PHP) + `npm run lint` (React/ESLint).
2. **Test** — `php artisan test` (feature & API test yang jadi checklist tiap fase fitur).
3. **Build** — `npm run build` (Vite) untuk memastikan asset production bisa dibangun.

Deploy stage (build image Docker, push, deploy) ditambahkan di Phase 22.

---

## 12b. Menjalankan via Laravel Herd (alternatif `artisan serve`)

Herd menyajikan project di domain `.test` lewat nginx + PHP-nya sendiri (mesin ini: **PHP 8.4**, sudah memuat `pdo_pgsql`/`pgsql`/`fileinfo`). Database tetap memakai PostgreSQL yang sama (127.0.0.1:5432).

```powershell
# dari root project
herd link reimburse           # → http://reimburse.test (Herd meng-update APP_URL)
herd php artisan migrate --seed
npm run build                 # asset produksi disajikan Herd (tanpa Vite dev server)
```

**Wajib untuk Sanctum SPA** (auth berbasis cookie di `.test`): pastikan `.env` memuat domain Herd di daftar stateful, jika tidak, panggilan `/api/*` dari React akan 401.
```env
APP_URL=http://reimburse.test
SANCTUM_STATEFUL_DOMAINS=reimburse.test,localhost,localhost:5173,127.0.0.1,127.0.0.1:8000
```
Lalu `php artisan config:clear`. Buka `http://reimburse.test` dan login.

Catatan:
- **HMR (Vite dev)**: untuk `npm run dev` di domain `.test`, jalankan dengan `--host`; cara paling sederhana saat demo adalah `npm run build` lalu Herd menyajikan asset statis.
- **HTTPS**: `herd secure reimburse` → `https://reimburse.test`; jika diaktifkan, ubah `APP_URL` ke `https://…` dan set `SESSION_SECURE_COOKIE=true`.
- **502 pada request pertama**: umum saat php-fpm cold-start setelah `config:clear`; request berikutnya normal.

## 13. Verifikasi Setup

Checklist sebelum lanjut ke Phase 4 (Migration):

```powershell
php artisan --version                       # Laravel 12.x
php -m | Select-String pdo_pgsql            # ekstensi PG aktif
psql -U postgres -d reimbursement -c "\dt"  # database bisa diakses (kosong = wajar)
php artisan migrate:status                  # koneksi DB OK (belum ada migration = wajar)
npm run dev                                 # Vite dev server jalan
php artisan serve                           # http://127.0.0.1:8000 tampil halaman Breeze
```

Bila semua lolos, environment siap untuk **Phase 4 — Migration** (membuat migration dari desain [02-database-design.md](02-database-design.md)).

---

## Ringkasan Keputusan Setup

| Aspek | Keputusan |
|-------|-----------|
| Root proyek | `e:\magang\part1` dengan `docs/` sebagai subfolder |
| Auth scaffolding | Laravel Breeze **React** (Inertia) |
| Bundler | Vite (bawaan Breeze) |
| Styling | Tailwind + `@tailwindcss/forms` |
| DB | PostgreSQL 16 (perlu diinstal) |
| Storage | `local` (dev) → `s3` (prod), lewat abstraksi disk |
| Queue | `database` (dev/staging) → `redis` (prod) |
| Mail | `log`/Mailpit (dev) → SMTP (prod) |
| Env | 3 lingkungan; hanya `.env.example` di Git |
| CI/CD | GitHub Actions: lint → test → build (penuh di Phase 22) |

---

*Dokumen ini adalah keluaran Phase 3 (panduan setup & strategi konfigurasi). Belum ada fitur yang dibuat sesuai instruksi fase. Perintah instalasi belum dieksekusi — lihat catatan di bawah.*
