#!/bin/sh
set -eu
cd /app
for directory in storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs; do
    mkdir -p "$directory"
done
# CLI operations must work before initialization and must never initialize implicitly.
if [ "$1" != php ]; then
    test -f /app/_install/install.lock || { echo "Initialize the database explicitly before starting services." >&2; exit 1; }
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    php artisan data:cache-structures
fi
exec "$@"
