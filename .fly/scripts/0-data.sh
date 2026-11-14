#!/usr/bin/env bash
# The fly volume at /data holds everything that must survive a deploy:
# the SQLite database and storage/app (public images + private medical files).
set -e
data=/data
mkdir -p "$data/app/public" "$data/app/private"
rm -rf /var/www/html/storage/app
ln -s "$data/app" /var/www/html/storage/app
ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage

db="${DB_DATABASE:-$data/database.sqlite}"
[ -f "$db" ] || touch "$db"
# SQLite writes WAL/journal files next to the database, so the folder must be writable too.
chown -R www-data:www-data "$data"
su -s /bin/sh www-data -c "php /var/www/html/artisan migrate --force --no-interaction"
