# Deployment — Panduan Production
# Reimbursement Management System

> **Fase:** Phase 22 — Deployment (fase terakhir)
> **Tanggal:** 2026-07-17
> **Artefak:** `Dockerfile`, `docker-compose.yml`, `docker/nginx/`, `docker/php/`, `docker/supervisor/`, `scripts/backup-db.sh`, `.github/workflows/ci.yml`

## 1. Arsitektur Production (Docker, single host)

```
Internet → [reverse proxy / certbot :443] → nginx :80 → php-fpm (app)
                                                     ↘ queue worker (container terpisah)
                                                     ↘ scheduler
                                            postgres 16 ── volume pg-data
                                            redis 7    ── cache/queue/session
```

## 2. Langkah Deploy (pertama kali)

```bash
git clone <repo> /var/www/rms && cd /var/www/rms
cp .env.production.example .env      # isi kredensial (DB_PASSWORD, MAIL_*, AWS_*)
docker compose up -d --build

# Inisialisasi (sekali):
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force      # role/permission + master data
docker compose exec app php artisan optimize             # config+route+view cache
```

Update rilis berikutnya:
```bash
git pull
docker compose build app queue scheduler
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

## 3. Optimasi Laravel & React (sudah di-bake ke image)

- `composer install --no-dev --optimize-autoloader` (stage vendor)
- `npm run build` — asset Vite ter-minify + hash (stage assets)
- OPcache aktif dengan `validate_timestamps=0` (`docker/php/app.ini`)
- `php artisan optimize` dijalankan pasca-deploy (config/route/view/event cache)

## 4. Optimasi PostgreSQL

Untuk server ±2-4 GB RAM, set di `postgresql.conf` (atau env alpine):

| Parameter | Anjuran | Alasan |
|---|---|---|
| `shared_buffers` | 25% RAM | cache halaman utama |
| `effective_cache_size` | 50-75% RAM | estimasi planner |
| `work_mem` | 16MB | sort/aggregate laporan |
| `maintenance_work_mem` | 128MB | vacuum/index build |
| `wal_compression` | on | hemat I/O WAL |

Index aplikasi sudah dirancang sejak Phase 4/20 (FK, status, partial unique, composite). Aktifkan `pg_stat_statements` untuk memantau query lambat.

## 5. SSL

Terminasi TLS di depan nginx container — dua opsi umum:
1. **Caddy / Traefik** sebagai reverse proxy (auto-ACME, paling sederhana), atau
2. **certbot** di host + nginx host yang mem-proxy ke port 80 container.

Setelah TLS aktif: set `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`, dan `SANCTUM_STATEFUL_DOMAINS` sesuai domain.

## 6. Queue Worker & Supervisor

- **Docker**: service `queue` (queue:work) + `scheduler` (schedule:work) di compose — restart otomatis (`unless-stopped`), pengganti Supervisor.
- **VPS tanpa Docker**: pakai `docker/supervisor/rms-worker.conf` (2 proses queue + scheduler) — instruksi di dalam file.

## 7. Logging

- Production: `LOG_STACK=daily`, `LOG_LEVEL=warning` (template `.env.production.example`).
- Log container: `docker compose logs -f app queue nginx`.
- Rotasi file: bawaan channel `daily` (14 hari default).

## 8. Backup Database

- `scripts/backup-db.sh` — `pg_dump | gzip`, retensi 14 hari.
- Cron host: `0 2 * * * /var/www/rms/scripts/backup-db.sh`.
- Volume `./backups` sudah ter-mount ke container postgres; **uji restore secara berkala**: `gunzip -c backups/rms-*.sql.gz | psql -U rms_user reimbursement`.
- Simpan salinan off-site (rclone/S3) untuk disaster recovery.

## 9. Monitoring

- **Health endpoint**: `GET /up` (bawaan Laravel 12) — sambungkan ke uptime monitor (UptimeRobot/Better Stack).
- `docker compose ps` + healthcheck postgres sudah terpasang.
- Opsional: Laravel Pulse/Telescope (dev), Sentry untuk error tracking.

## 10. CI/CD (`.github/workflows/ci.yml`)

Pipeline penuh dari outline Phase 3:
1. **Lint** — Pint (PHP) + ESLint (React)
2. **Audit** — `composer audit` (blocking) + `npm audit` critical (non-blocking)
3. **Build asset** — Vite (sebelum test: feature test merender halaman Inertia yang butuh manifest)
4. **Test** — Pest penuh terhadap PostgreSQL 16 service (`reimbursement_testing`, sesuai phpunit.xml)
5. **Docker image** — build image production pada push ke `main` (push ke registry ditambahkan saat infra target ada)

## 11. Checklist Final Sebelum Go-Live

- [ ] `.env` production terisi; `APP_DEBUG=false`; `APP_KEY` baru
- [ ] TLS aktif + `SESSION_SECURE_COOKIE=true`
- [ ] `migrate --force` + seeder role/permission dijalankan
- [ ] Password seluruh akun seed diganti (bukan "password")
- [ ] `php artisan optimize` dijalankan
- [ ] Queue worker & scheduler berjalan (cek `docker compose ps`)
- [ ] Cron backup aktif + restore teruji
- [ ] Uptime monitor menunjuk ke `/up`
- [ ] Checklist keamanan Phase 19 dilalui ulang
- [ ] Storage S3 dikonfigurasi bila dipakai (`FILESYSTEM_DISK=s3`)

## 12. Catatan Verifikasi

Toolchain lokal pengembangan tidak memiliki Docker, sehingga stack container **belum dijalankan end-to-end di mesin ini**; Dockerfile/compose/nginx dirancang mengikuti pola standar Laravel-FPM dan job `docker-image` di CI akan memvalidasi build image pada setiap push ke `main`. Seluruh aplikasi di baliknya telah lulus 159 test regression.

---
*Keluaran Phase 22 — fase terakhir roadmap. 🎉*
