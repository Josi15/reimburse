# Integration, Performance & Final Testing — Ringkasan
# Reimbursement Management System

> **Fase:** Phase 21
> **Tanggal:** 2026-07-17
> **Hasil akhir:** **159 test, 483 assertion — semua lulus** (regression penuh)

## 1. Piramida Pengujian

| Lapisan | File | Cakupan |
|---|---|---|
| **Integrasi end-to-end** | `EndToEndFlowTest` | Siklus penuh via API nyata: draft+bukti → submit → manager approve → finance approve → bayar+bukti → paid. Di-assert sekaligus: status, approvals (2), payment (1), lampiran, timeline, **notifikasi tepat sasaran** (owner 3, manager 1, finance 1), **audit log semantik** (approve×2, payment×1), dan **pembayaran ganda ditolak**. Plus alur revisi: revisi → perbaiki → resubmit → approve → finance reject (3 baris riwayat) |
| **Performa (volume 300 klaim)** | `PerformanceSanityTest` | Dashboard: total benar & **≤ 25 query** (agregasi di DB); index 50 baris **≤ 12 query** (bebas N+1); report summary 300 klaim benar. Timing hanya batas longgar (<3 dtk) agar tidak flaky |
| **Feature/API per modul** (Phase 8-16) | `Api/*` (9 file) | CRUD master data, reimbursement, approval, payment, bank account, dashboard, notifikasi, report/export, audit log, attachment — 84 kasus |
| **Keamanan** | `SecurityTest`, `SecurityHardeningTest`, `RbacTest` | Password policy, lockout, akun nonaktif, role/permission/gate/policy, deep MIME, rate limit payment, security headers — 18 kasus |
| **Model & unit** | `ModelTest`, `OptimizationTest`, `ApiDocsTest` | State machine, observer nomor, accessor, memoisasi query, index, docs — 13 kasus |
| **Auth bawaan (Breeze)** | `Auth/*`, `ProfileTest` | Login/register/reset/verify/profile — 24 kasus |

## 2. Prinsip yang Dipakai

- **Test = checklist wajib per fase** (sesuai roadmap) — setiap fase fitur dites saat dibangun, bukan retrofit di akhir.
- **Assertion deterministik untuk performa** — jumlah query (penjaga N+1) sebagai assertion utama; waktu hanya sanity longgar.
- **DB test terpisah** (`reimbursement_testing`) — regression tidak menyentuh data dev.
- **PostgreSQL asli di test** — fitur khusus (ILIKE, jsonb, partial index, lockForUpdate) teruji di engine yang sama dengan produksi.

## 3. Cara Menjalankan

```bash
php artisan test                         # regression penuh
php artisan test --filter=EndToEndFlow   # integrasi e2e saja
php artisan test tests/Feature/Api       # API per modul
```

---
*Keluaran Phase 21. Load test eksternal (mis. k6/JMeter terhadap server produksi) dapat ditambahkan pasca-deploy Phase 22 bila dibutuhkan.*
