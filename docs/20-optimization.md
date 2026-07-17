# Optimization — Ringkasan
# Reimbursement Management System

> **Fase:** Phase 20 — Optimization
> **Tanggal:** 2026-07-17

## 1. Yang Dioptimalkan pada Fase Ini

| Area | Sebelum | Sesudah |
|---|---|---|
| **Cek permission (RBAC)** | `hasPermission()` memicu query `permissions` per role setiap panggilan; dipanggil berkali-kali per request (middleware, policy, menu, resource) | `permissionNames()` memuat `roles.permissions` sekali (2 query) lalu **memo per-instance** → panggilan berikutnya 0 query. Dibuktikan test: 30 panggilan ≤ 2 query |
| **Query employee** | Daftar/statistik reimbursement per user memakai index `user_id` saja | **Composite index `(user_id, status)`** — filter status & agregasi kartu dashboard personal dilayani satu index scan |
| **Opsi dropdown** | Query master data setiap kali form dibuka | `Cache::remember` TTL 60 dtk (`options.categories/departments/banks`) |

## 2. Yang Sudah Optimal Sejak Fase Sebelumnya

- **Eager loading** — semua controller `with()`/`loadMissing()` untuk relasi yang dirender (Phase 8-17); `whenLoaded()` di Resource mencegah lazy-load tak sengaja.
- **Queue** — email notification via queue `database` (Phase 13), tidak memblokir request.
- **Database index** — FK auto-index, index status/tanggal, partial unique (anti double-pay, satu rekening utama), composite `(status, department_id)` (Phase 4).
- **API Resource** — seluruh respons melalui Resource (payload terkontrol, tanpa over-fetching).
- **Lazy loading front-end** — Vite code-splitting per halaman (bawaan `resolvePageComponent`); tiap halaman = chunk terpisah (terlihat di output build).
- **Agregasi di DB** — dashboard/report memakai `GROUP BY`/`SUM` di PostgreSQL, bukan koleksi PHP.

## 3. Keputusan yang Disengaja

- **Dashboard tidak di-cache** — berisi antrean approval/pembayaran yang harus real-time; query-nya sudah agregasi ber-index dan murah.
- **`Model::preventLazyLoading()` tidak diaktifkan** — beberapa lazy-load memang disengaja (policy per-record); trade-off dicatat, N+1 diperiksa manual per fase.
- **TTL opsi 60 dtk** — master data baru terlihat maksimal 1 menit kemudian; dapat diterima untuk data referensi.

## 4. Checklist Production (dieksekusi Phase 22)

```bash
php artisan optimize          # config + route + view + event cache
composer install --no-dev --optimize-autoloader
npm run build                 # asset produksi ter-minify
```
- OPcache aktif di PHP-FPM.
- `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis` (template production).
- Monitoring query lambat via `pg_stat_statements` (opsional).

---
*Keluaran Phase 20. Bukti kinerja ada di `tests/Feature/OptimizationTest.php`.*
