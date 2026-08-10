# Docker Development Environment

> Docker / Sail setup for NNTmux — a Laravel 13 Usenet indexer.

## Prerequisites

- Docker & **Docker Compose v2.22+** (the Makefile uses `pull --ignore-buildable`)
- A GitHub (scopeless) token for Composer private packages
- [Laravel Sail](https://laravel.com/docs/12.x/sail) (included as a dev dependency)

## Quick Start

```bash
# 1. Copy and configure your environment
cp .env.example .env
# Edit .env — set DB credentials, COMPOSER_AUTH, and uncomment the
# "Docker service hostnames" block at the bottom.

# 2. Build and start
make build
make up

# 3. Install NNTmux (first run only)
make artisan cmd="nntmux:install"

# 4. Build frontend assets
make npm-build
```

## Runtime permission contract

NNTmux supports callers with a restrictive `umask`, including `0077`. The
global shell, agent, and Docker daemon umasks should remain restrictive. The
Sail Composer and production Vite commands publish runtime files under a
command-scoped `umask 0022`, then the build workflow verifies exact modes.

This contract exists because two separate production failures made the Web UI
unusable: Vite emitted an owner-only manifest and assets, and a focused test run
compiled owner-only Blade views into the checkout's live framework cache. Git
cannot reveal either problem because it does not record group/other read bits
and both generated paths are ignored.

| Path | Owner/group and mode contract |
|------|-------------------------------|
| Tracked runtime source | Deployment owner; directories `0755`, files `0644`, Git executables `0755` |
| `vendor` | Runtime-readable/traversable; existing package executables stay executable |
| `public/build` | Directories exactly `0755`, regular files exactly `0644` |
| `.env` | Deployment owner and PHP-FPM application group, exactly `0640` |
| `storage/framework/views` | Deployment owner/application group; directories `2775`, compiled files `0664` |
| Other `storage` and `bootstrap/cache` | Deployment owner/application group; writable setgid directories, private group-writable files |

`make fix-permissions` is the idempotent repair command. It validates the Git
root before changing anything, skips symlinks during mode normalization, and
derives the application identity from PHP-FPM configuration. `make
check-permissions` is read-only and performs decisive access checks as that
application user. Its errors name the failing path and the repair command.

Tests launched with `./sail test`, `./sail phpunit`, or `./sail artisan test`
receive unique temporary compiled-view, config, route, event, package, service,
and PHPUnit cache paths before Artisan starts. The runner removes that tree on
exit and never writes to live `storage/framework/views` or `bootstrap/cache`.

Do not substitute a plain recursive `chown` or `chmod`: ownership alone does
not repair modes, a blanket mode exposes secrets and destroys executable bits,
and neither approach protects test caches. Running tests as root only hides the
fault while allowing root-owned artifacts. In a served checkout, build frontend
assets with `make npm-build`; do not run host-side `npm run build`.

### Repairing an existing deployment

The following rollout does not rebuild the database, remove application data,
or restart background processing:

1. Run `make fix-permissions`. This normalizes tracked source (including
   `UpdateTmuxSettingsRequest.php`), `public/build`, `.env`, dependencies, live
   compiled views, storage, and bootstrap caches. Run it a second time if you
   want to confirm the idempotent `corrected 0 path(s)` result.
2. Run `make check-permissions`. This verifies `.env` remains owner/application-
   group `0640`, live views are group-writable/setgid, and PHP-FPM can read the
   source, Composer autoloader, manifest, assets, and compiled views.
3. Using the deployed base URL and an authenticated administrator cookie, verify
   `curl -f "$APP_URL/login"`, `curl -f "$APP_URL/status"`, and
   `curl -f -b admin.cookies "$APP_URL/admin/tmux-edit"` all return HTTP 200.
4. Inspect `public/build/manifest.json` and run `curl -f "$APP_URL/<asset>"`
   for every referenced JavaScript and CSS `file`/`css` entry.
5. Snapshot metadata and hashes beneath `storage/framework/views` and
   `bootstrap/cache`, then run `PERMISSION_TEST_BASE_URL="$APP_URL"
   PERMISSION_TEST_ADMIN_COOKIE_FILE=admin.cookies make
   test-focused-isolation`. It runs the issue #19 tests through Sail, compares
   content and nanosecond-resolution metadata, and repeats the authenticated
   served `/admin/tmux-edit` request after the tests. The snapshots must match
   and the request must return HTTP 200.

If a compiled Blade file cannot be repaired, it is safe to clear only compiled
views with `make artisan cmd="view:clear"`, immediately rerun `make
fix-permissions`, and warm/verify the view as the application user. Do not remove
other storage content.

## Environment Configuration

When running via Docker, uncomment the **Docker service hostnames** block
at the bottom of `.env` so the app resolves container names:

```dotenv
DB_HOST=mariadb
REDIS_HOST=redis
MANTICORESEARCH_HOST=manticore
ELASTICSEARCH_HOST=elasticsearch
MAIL_HOST=mailpit
MAIL_PORT=1025
```

### Search Engine Selection

Set `COMPOSE_PROFILES` in `.env` to match your `SEARCH_DRIVER`:

| `SEARCH_DRIVER` | `COMPOSE_PROFILES` |
|------------------|--------------------|
| `manticore`      | `manticore`        |
| `elasticsearch`  | `elasticsearch`    |

Only the selected search service container will start. The other remains
dormant and consumes no resources.

### ManticoreSearch Image Updates

Both `docker-compose.yml` and `docker-compose.yml.prod-dist` intentionally use
the unpinned `manticoresearch/manticore` image. This keeps ManticoreSearch on
the latest published Docker image whenever images are pulled, but it also means
production can receive ManticoreSearch changes without a repository diff.

Before pulling and restarting production, validate the new image in staging by
creating indexes, indexing a small batch, and running representative searches,
filters, sorting, pagination, deletes, suggestions, fuzzy searches, and any raw
SQL diagnostics/reconciliation commands.

Manticore Search 27.1.5 is part of the 25.x-27.x release line that introduced
built-in authentication/authorization, sharded tables, conversational search,
vector-search improvements, and replication layout changes. The app does not
enable Manticore auth by default, but if auth is enabled on the server set either
`MANTICORESEARCH_USERNAME`/`MANTICORESEARCH_PASSWORD` or
`MANTICORESEARCH_TOKEN` before running the app, index-creation commands, or raw
HTTP diagnostics. Roll auth out in staging first; anonymous local Docker usage
continues to work with these variables blank.

Native Ubuntu/Debian package installs are managed by `systemd`, not Docker. If
the package install fails while starting `manticore.service`, especially with
`Status: "Replaying binlogs..."` and `accept() failed ... Too many open files`,
or if `searchd` prints a backtrace during binlog replay, use
[`docs/manticore-ubuntu-package.md`](docs/manticore-ubuntu-package.md) to raise
the service file descriptor limit, preserve crash evidence, quarantine bad
binlogs if needed, and finish `dpkg` configuration.

## Make Targets

Run `make` or `make help` to see all targets, grouped by section.

### Lifecycle

| Target            | Description                                                     |
|-------------------|-----------------------------------------------------------------|
| `make up`         | Start all services (detached)                                   |
| `make down`       | Stop all services                                               |
| `make restart`    | Restart all services                                            |
| `make recreate`   | Force-recreate containers without rebuilding                    |
| `make build`      | Build the app image (cached)                                    |
| `make rebuild`    | Pull fresh base images + `--no-cache` build + force-recreate    |
| `make pull`       | Pull latest enabled-profile base images (skips buildable ones)  |
| `make update`     | Infra-only: pull + `--pull` build + restart                     |
| `make upgrade`    | App upgrade ending in permission normalization and verification  |
| `make fresh`      | **Destroy volumes**, pull, rebuild, recreate (DATA LOSS)        |

### Development

| Target              | Description                                           |
|---------------------|-------------------------------------------------------|
| `make shell`        | Bash shell in the app container                       |
| `make root-shell`   | Root bash shell in the app container                  |
| `make tinker`       | Laravel Tinker REPL                                   |
| `make artisan cmd=` | Run any artisan command                               |
| `make migrate`      | Run pending migrations                                |
| `make migrate-fresh`| Drop all tables and re-migrate (DATA LOSS)            |
| `make seed`         | Run database seeders                                  |
| `make cache-clear`  | Clear config / route / view / app caches (and stale `bootstrap/cache` files) |
| `make optimize`     | Dev-safe: warm view cache + spatie/laravel-data           |
| `make optimize-deploy` | Production: also caches config/routes (bakes absolute paths) |
| `make queue-work`   | Foreground queue worker                               |
| `make queue-restart`| Signal queue workers to restart                       |
| `make tmux-start`   | Start the NNTmux tmux processing engine               |
| `make tmux-stop`    | Stop the tmux processing engine                       |
| `make tmux-attach`  | Attach to the running tmux session                    |
| `make horizon`      | Show Horizon queue status                             |
| `make fix-permissions` | Repair runtime ownership/modes and register Git `safe.directory` |
| `make check-permissions` | Read-only application-user permission/isolation diagnostic |

### Testing & Quality

| Target           | Description                                          |
|------------------|------------------------------------------------------|
| `make test`      | PHPUnit tests (`filter=Name` optional)               |
| `make test-permissions` | Permission and isolated-cache regression tests    |
| `make test-focused-isolation` | Issue #19 Sail tests plus live-cache snapshot comparison |
| `make test-focused-cache-isolation` | CI cache snapshot check (no deployed HTTP endpoint) |
| `make pint`      | Pint formatter on dirty files                        |
| `make pint-all`  | Pint formatter on all files                          |
| `make phpstan`   | PHPStan static analysis (2G memory limit)            |
| `make rector`    | Rector dry-run (no changes)                          |
| `make rector-fix`| Apply Rector refactorings                            |

### Frontend & Types

| Target                | Description                          |
|-----------------------|--------------------------------------|
| `make npm-build`      | Safe production build plus exact asset-mode normalization |
| `make npm-dev`        | Start Vite dev server                |
| `make ts-types`       | Regenerate TypeScript types          |
| `make ts-types-check` | CI: fail if generated types drift    |
| `make data-cache`     | Cache spatie/laravel-data structures |

### Logs & Status

| Target              | Description                                                      |
|---------------------|------------------------------------------------------------------|
| `make logs`         | Tail all container logs (or `SERVICE=name` for one)              |
| `make tail-laravel` | Tail `storage/logs/laravel.log` inside the app container         |
| `make status` / `ps`| Show running containers                                          |
| `make top`          | Show processes inside each container                             |
| `make images`       | Show images used by each service                                 |
| `make health`       | Healthcheck status per service                                   |

### Cleanup

| Target        | Description                                                   |
|---------------|---------------------------------------------------------------|
| `make clean`  | Prune stopped containers / dangling images                    |
| `make nuke`   | **Remove ALL** project containers, images, volumes (DATA LOSS)|

### Flags

These can be combined with the targets above:

| Flag           | Effect                                                                |
|----------------|-----------------------------------------------------------------------|
| `FORCE=1`      | Skip confirmation prompts on `fresh`, `nuke`, `migrate-fresh` (CI use)|
| `MAINTENANCE=1`| Wrap `upgrade` migrations in `artisan down` / `artisan up`            |
| `SERVICE=name` | Restrict `logs` to a specific compose service                         |
| `CMD="…"`      | Free-form command for `artisan` (alternative to `cmd=`)               |
| `filter=Name`  | Pass `--filter=Name` to `make test`                                   |

Examples:

```bash
make fresh FORCE=1                      # non-interactive teardown + rebuild
make upgrade MAINTENANCE=1              # zero-downtime-ish upgrade with maint mode
make logs SERVICE=mariadb               # tail only mariadb
make test filter=ReleaseSearchTest      # run a single test
```

> **Note:** `make pull` / `update` / `rebuild` use `docker compose pull --ignore-buildable`,
> which only pulls images for services in the active `COMPOSE_PROFILES` and skips images
> that are built locally (e.g. `sail-8.5/app`). Requires Docker Compose v2.22+.

You can also use `./sail` directly for anything not covered above —
unknown commands are passed through to `docker compose`.

## Networking

Sail creates a Docker network called `sail` with these default port mappings:

| Service        | Container Port | Host Port (default)                      |
|----------------|----------------|------------------------------------------|
| HTTP (nginx)   | 80             | `APP_PORT` (80)                          |
| Vite           | 5173           | `VITE_PORT` (5173)                       |
| MariaDB        | 3306           | `FORWARD_DB_PORT` (3306)                 |
| Redis          | 6379           | `FORWARD_REDIS_PORT` (6379)              |
| Manticore SQL  | 9306           | 9306                                     |
| Manticore HTTP | 9308           | 9308                                     |
| Elasticsearch  | 9200           | 9200                                     |
| Mailpit SMTP   | 1025           | `FORWARD_MAILPIT_PORT` (1025)            |
| Mailpit Web    | 8025           | `FORWARD_MAILPIT_DASHBOARD_PORT` (8025)  |

If a port is already in use on your host, change the corresponding
`FORWARD_*` / `APP_PORT` variable in `.env`.

## Database

On first run MariaDB creates a user/database from your `.env`:

```dotenv
DB_USERNAME=
DB_PASSWORD=
DB_DATABASE=nntmux
```

Import an existing SQL dump or run `make artisan cmd="nntmux:install"` for
a fresh installation.

## Backups

Add this optional service to `docker-compose.yml` for automated MariaDB backups:

```yaml
    backup:
        image: fradelg/mysql-cron-backup
        depends_on:
            - mariadb
        restart: always
        volumes:
            - ./docker/backups:/backup
        environment:
            - MYSQL_USER=${DB_USERNAME}
            - MYSQL_PASS=${DB_PASSWORD}
            - MYSQL_DB=${DB_DATABASE}
            - CRON_TIME=0 3 * * *
            - MYSQL_HOST=mariadb
            - MYSQL_PORT=3306
            - TIMEOUT=10s
            - GZIP_LEVEL=9
            - MAX_BACKUPS=5
            - INIT_BACKUP=0
            - EXIT_BACKUP=1
        networks:
            - sail
```

## Production

For production deployments use `docker-compose.yml.prod-dist` as a starting
point. It includes separate `webapp`, `worker`, and `scheduler` services
and uses the root `Dockerfile` (FrankenPHP-based).

```bash
cp docker-compose.yml.prod-dist docker-compose.yml
# Edit .env for production settings
docker compose up -d
```

## Troubleshooting

| Problem                         | Solution                                                              |
|---------------------------------|-----------------------------------------------------------------------|
| Permission errors on source/build/cache | `make fix-permissions`, then `make check-permissions`         |
| `composer install` permission denied | `make fix-permissions`, then re-run `make composer-install`      |
| `npm`/`ncu` EACCES on host (`package.json`) | `make fix-permissions` chowns to host UID, not container `sail` |
| `fatal: detected dubious ownership` (git) | `make fix-permissions` registers `safe.directory` in the container |
| Container `sail` UID ≠ host UID  | Set `WWWUSER=$(id -u)` / `WWWGROUP=$(id -g)` in `.env`, then `make rebuild` |
| Host artisan/npm fails with `/var/www/html/...` paths | Stale `bootstrap/cache/config.php` from a container `config:cache`. Run `make cache-clear` and prefer `make optimize` (dev-safe) over `optimize-deploy` in development |
| Port already in use             | Change `APP_PORT`, `FORWARD_DB_PORT`, etc. in `.env`                  |
| Containers won't start          | `make logs` to inspect, or `make rebuild` to start fresh              |
| Stale images after upgrade      | `make update` (pulls base images + rebuild + restart)                 |
| Need a completely clean slate   | `make fresh` (destroys all volumes; add `FORCE=1` for non-interactive)|
| CI / scripted teardown          | `make fresh FORCE=1` or `make nuke FORCE=1` to skip prompts           |
| supervisorctl not connecting    | `make root-shell` then `supervisorctl status` to verify socket path   |

## Nginx (GetPageSpeed + Brotli)

The app container's nginx is installed from the [GetPageSpeed apt repo](https://extras.getpagespeed.com/ubuntu/)
(stable branch) instead of the Ubuntu archive. This gives us a current nginx
build with ABI-matched dynamic modules. The `nginx-module-brotli` package is
installed and the brotli directives in `docker/8.5/nginx.conf` are enabled by
default (gzip is kept as a fallback for clients without `br` support).

The repo is apt-pinned at priority `1001` via
`/etc/apt/preferences.d/getpagespeed-nginx.pref` so `nginx` and module
packages always resolve from GetPageSpeed even if Ubuntu publishes a newer
version. A `nginx -t` is run during `docker build` to fail fast on any config
drift.

To revert to stock Ubuntu nginx, remove the GetPageSpeed apt key, list, and
preferences entries from `docker/8.5/Dockerfile`, drop `nginx-module-brotli`
from the install line, re-comment the brotli block in `docker/8.5/nginx.conf`,
and run `make rebuild`.
