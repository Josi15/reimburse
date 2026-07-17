# Security Hardening — Review & Checklist
# Reimbursement Management System

> **Fase:** Phase 19 — Security Hardening (lanjutan; dasar sudah di Phase 7)
> **Tanggal:** 2026-07-17

## 1. Ringkasan Kontrol Keamanan Aktif

| Kontrol | Sejak | Implementasi |
|---|---|---|
| CSRF Protection | Phase 7 | Middleware `web` bawaan Laravel + XSRF-TOKEN untuk SPA (Sanctum) |
| Password Policy | Phase 7 | `Password::defaults()`: min 8, huruf besar-kecil, angka, simbol; `uncompromised()` di production |
| Login Attempt Limit | Phase 7 | RateLimiter (5/menit per email+IP) + lockout DB (`locked_until` 15 menit setelah 5 gagal) |
| Akun nonaktif/terkunci | Phase 7 | Ditolak saat login + middleware `active` memutus sesi berjalan |
| RBAC penuh | Phase 7 | Gate + Policy + middleware `role`/`permission`; Super Admin via `Gate::before` |
| Otorisasi per-record | Phase 9-16 | Policy cek kepemilikan + status state machine |
| Rate limit login/api | Phase 7 | Limiter `login` (5/mnt), `api` (60/mnt) |
| **Rate limit payment** | **Phase 19** | `throttle:payment` (10/mnt per user) kini ter-attach di `POST /reimbursements/{id}/pay` |
| **Deep MIME check** | **Phase 19** | Rule `mimetypes:` (finfo pada konten asli) di semua upload + MIME asli yang disimpan, bukan klaim client |
| **Security headers** | **Phase 19** | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `Permissions-Policy` |
| Audit trail | Phase 15 | Semua event kunci tercatat (login/logout/CRUD/approve/reject/payment) append-only |
| Anti double-payment | Phase 11 | `lockForUpdate` + partial unique index |

## 2. Review XSS

- **React** meng-escape seluruh output secara default; **tidak ada** penggunaan `dangerouslySetInnerHTML` di codebase.
- **Blade** (PDF report, email) memakai `{{ }}` (escaped); tidak ada `{!! !!}`.
- Payload notifikasi & audit log dirender sebagai teks/JSON, bukan HTML.
- File upload: tipe dibatasi (jpg/png/pdf) dengan validasi konten; `nosniff` mencegah browser mengeksekusi file sebagai tipe lain saat preview.
- Kesimpulan: **tidak ditemukan vektor XSS**; kontrol berlapis aktif.

## 3. Review SQL Injection

Seluruh akses DB memakai Eloquent/Query Builder dengan parameter binding. Titik `raw` yang diaudit:

| Lokasi | Penggunaan | Status |
|---|---|---|
| `HandlesResourceQuery` | `ILIKE` dengan nilai ter-bind (`%{q}%` sebagai parameter) | ✅ Aman |
| `HandlesResourceQuery` | Kolom sort/filter dari **whitelist**, bukan input mentah | ✅ Aman |
| `ReportService`, `SearchController`, `AuditLogController` | `ILIKE` ter-bind | ✅ Aman |
| `DashboardService`, `ReportService` | `selectRaw`/`groupByRaw` berisi **konstanta** (tanpa input user) | ✅ Aman |
| Partial index / CHECK di migration | DDL statis | ✅ Aman |

Kesimpulan: **tidak ada konkatenasi input user ke SQL**.

## 4. Validasi File Lanjutan (Phase 19)

1. `mimes:jpg,jpeg,png,pdf` — ekstensi berdasarkan konten.
2. **`mimetypes:image/jpeg,image/png,application/pdf`** — MIME asli via finfo; file `.pdf` berisi executable ditolak (ada test-nya).
3. Ukuran maks dikonfigurasi (`REIMBURSEMENT_MAX_FILE_KB`, default 5 MB), maks 10 file/request.
4. MIME yang disimpan di DB = hasil deteksi konten (`getMimeType()`), bukan `getClientMimeType()`.
5. File disimpan di disk privat (bukan `public/`); akses hanya via endpoint ber-otorisasi (policy induk).

## 5. Checklist Pra-Production

- [x] `APP_DEBUG=false` di template production (`.env.production.example`)
- [x] Kredensial hanya via env; `.env` di-gitignore
- [x] HTTPS/SSL — dikonfigurasi saat deployment (Phase 22)
- [x] Session: `database` driver; `SESSION_ENCRYPT=true` di production template
- [x] Password hashing bcrypt (12 rounds)
- [x] API docs dibatasi (super_admin di production)
- [x] Error tidak membocorkan stack trace (APP_DEBUG=false)
- [x] Rate limiting: login, api, payment
- [x] Semua mutasi tercatat di audit log
- [ ] Review dependensi berkala (`composer audit`, `npm audit`) — masuk pipeline CI (Phase 22)
- [ ] Backup DB terjadwal — Phase 22
- [ ] CSP ketat (opsional; perlu penyesuaian Vite) — dievaluasi di Phase 22

---
*Keluaran Phase 19. Item terbuka dieksekusi pada Phase 22 (Deployment).*
