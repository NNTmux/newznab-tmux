# Migrations

## Keep CBP storage migration flag false after maintenance

`CBP_STORAGE_MIGRATION_EXECUTE` must stay `false` outside a deliberate maintenance window. Leaving it `true` causes the next `php artisan migrate` (including deploy/update) to re-run `cbp:optimize-storage --execute` against `collections`/`binaries`/`parts`, which can drive multi-hour `mariadbd` I/O storms and load averages of 30+. After a successful optimize, set the flag back to `false` immediately in production `.env` and clear config cache.
