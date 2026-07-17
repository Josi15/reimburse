#!/usr/bin/env bash
# =========================================================================
# Backup PostgreSQL harian (Phase 22). Simpan 14 hari terakhir.
# Docker : docker compose exec -T postgres sh -c 'pg_dump -U $POSTGRES_USER $POSTGRES_DB' | gzip > backups/...
# VPS    : jalankan langsung (butuh pg_dump di PATH & kredensial via env).
# Cron   : 0 2 * * *  /var/www/rms/scripts/backup-db.sh >> /var/log/rms-backup.log 2>&1
# =========================================================================
set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_DATABASE="${DB_DATABASE:-reimbursement}"
DB_USERNAME="${DB_USERNAME:-rms_user}"
BACKUP_DIR="${BACKUP_DIR:-$(dirname "$0")/../backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

mkdir -p "$BACKUP_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
FILE="$BACKUP_DIR/rms-$STAMP.sql.gz"

pg_dump -h "$DB_HOST" -U "$DB_USERNAME" "$DB_DATABASE" | gzip > "$FILE"

# Hapus backup lebih tua dari retensi
find "$BACKUP_DIR" -name 'rms-*.sql.gz' -mtime "+$RETENTION_DAYS" -delete

echo "[$(date -Is)] backup OK: $FILE ($(du -h "$FILE" | cut -f1))"
