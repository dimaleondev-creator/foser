#!/bin/sh
set -eu

FILE=${1:?Usage: ./scripts/restore.sh backups/foser-YYYYMMDD-HHMMSS.dump.enc}
test -f "$FILE"
openssl enc -d -aes-256-cbc -pbkdf2 -in "$FILE" -pass env:BACKUP_ENCRYPTION_KEY | docker compose exec -T postgres pg_restore --clean --if-exists --no-owner --dbname="$DB_DATABASE" --username="$DB_USERNAME"