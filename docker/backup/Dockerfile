FROM alpine:3.20

RUN apk add --no-cache aws-cli openssl postgresql16-client tar gzip

COPY docker/backup/backup.sh /usr/local/bin/foser-backup
RUN chmod +x /usr/local/bin/foser-backup

ENTRYPOINT ["foser-backup"]
