#!/bin/sh
set -eu

: "${DB_HOST:=postgres}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"
: "${BACKUP_ENCRYPTION_KEY:?BACKUP_ENCRYPTION_KEY is required}"
: "${BACKUP_RETENTION_DAYS:=14}"

TIMESTAMP=$(date -u +%Y%m%d-%H%M%S)
WORK_DIR=$(mktemp -d)
trap 'rm -rf "$WORK_DIR"' EXIT

export PGPASSWORD="$DB_PASSWORD"
pg_dump --format=custom --no-owner --no-acl --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" --dbname="$DB_DATABASE" > "$WORK_DIR/foser-$TIMESTAMP.dump"

if [ -n "${OBJECT_STORAGE_BUCKET:-}" ]; then
    export AWS_ACCESS_KEY_ID="${OBJECT_STORAGE_ACCESS_KEY_ID:?OBJECT_STORAGE_ACCESS_KEY_ID is required}"
    export AWS_SECRET_ACCESS_KEY="${OBJECT_STORAGE_SECRET_ACCESS_KEY:?OBJECT_STORAGE_SECRET_ACCESS_KEY is required}"
    export AWS_DEFAULT_REGION="${OBJECT_STORAGE_DEFAULT_REGION:-us-east-1}"
    mkdir -p "$WORK_DIR/object-storage"
    if [ -n "${OBJECT_STORAGE_ENDPOINT:-}" ]; then
        aws --endpoint-url "$OBJECT_STORAGE_ENDPOINT" s3 sync "s3://$OBJECT_STORAGE_BUCKET/" "$WORK_DIR/object-storage/"
    else
        aws s3 sync "s3://$OBJECT_STORAGE_BUCKET/" "$WORK_DIR/object-storage/"
    fi
fi

tar -czf "$WORK_DIR/foser-$TIMESTAMP-files.tar.gz" -C /var/www/html/storage app
if [ -d "$WORK_DIR/object-storage" ]; then
    tar -czf "$WORK_DIR/foser-$TIMESTAMP-object-storage.tar.gz" -C "$WORK_DIR" object-storage
fi

for FILE in "$WORK_DIR"/foser-$TIMESTAMP.*; do
    openssl enc -aes-256-cbc -pbkdf2 -salt -in "$FILE" -out "$FILE.enc" -pass env:BACKUP_ENCRYPTION_KEY
    rm -f "$FILE"
done

cp "$WORK_DIR"/*.enc /backups/
find /backups -type f -name 'foser-*.enc' -mtime +"$BACKUP_RETENTION_DAYS" -delete

if [ -n "${BACKUP_S3_BUCKET:-}" ]; then
    if [ -n "${AWS_ENDPOINT:-}" ]; then
        aws --endpoint-url "$AWS_ENDPOINT" s3 cp /backups/ "s3://$BACKUP_S3_BUCKET/" --recursive --exclude '*' --include 'foser-*.enc'
    else
        aws s3 cp /backups/ "s3://$BACKUP_S3_BUCKET/" --recursive --exclude '*' --include 'foser-*.enc'
    fi
fi

echo "Backup created: foser-$TIMESTAMP"
