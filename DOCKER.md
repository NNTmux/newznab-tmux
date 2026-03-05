# Docker Development Environment

> Docker / Sail setup for NNTmux — a Laravel 12 Usenet indexer.

## Prerequisites

- Docker & Docker Compose v2+
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

## Make Targets

Run `make` or `make help` to see all targets:

| Target              | Description                                           |
|---------------------|-------------------------------------------------------|
| `make up`           | Start all services (detached)                         |
| `make down`         | Stop all services                                     |
| `make restart`      | Restart all services                                  |
| `make build`        | Build the app image                                   |
| `make rebuild`      | Build from scratch (no cache)                         |
| `make pull`         | Pull latest base images                               |
| `make update`       | Pull + rebuild + restart                              |
| `make fresh`        | **Destroy volumes**, rebuild, start clean (DATA LOSS) |
| `make shell`        | Bash shell in the app container                       |
| `make root-shell`   | Root bash shell in the app container                  |
| `make artisan cmd=` | Run any artisan command                               |
| `make tmux-start`   | Start the NNTmux tmux processing engine               |
| `make tmux-stop`    | Stop the tmux processing engine                       |
| `make tmux-attach`  | Attach to the running tmux session                    |
| `make horizon`      | Show Horizon queue status                             |
| `make test`         | Run PHPUnit tests (`filter=Name` optional)            |
| `make pint`         | Pint formatter on dirty files                         |
| `make npm-build`    | `npm install` + `npm run build`                       |
| `make npm-dev`      | Start Vite dev server                                 |
| `make db`           | MariaDB CLI session                                   |
| `make redis-cli`    | Redis CLI session                                     |
| `make logs`         | Tail all container logs                               |
| `make status`       | Show container status                                 |
| `make clean`        | Prune stopped containers / dangling images            |
| `make nuke`         | **Remove ALL** project containers, images, volumes    |

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
| Permission errors on storage/   | `make root-shell` then `chown -R sail:sail storage bootstrap/cache`   |
| Port already in use             | Change `APP_PORT`, `FORWARD_DB_PORT`, etc. in `.env`                  |
| Containers won't start          | `make logs` to inspect, or `make rebuild` to start fresh              |
| Stale images after upgrade      | `make update` (pulls + rebuilds + restarts)                           |
| Need a completely clean slate   | `make fresh` (destroys all volumes!)                                  |
| supervisorctl not connecting    | `make root-shell` then `supervisorctl status` to verify socket path   |

