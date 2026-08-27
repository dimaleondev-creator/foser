#!/bin/sh
set -eu

mkdir -p /var/www/html/storage/framework/cache/data /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/storage/logs /var/www/html/bootstrap/cache /var/www/html/public
cp -a /opt/foser-public/. /var/www/html/public/
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public

exec su-exec www-data "$@"
