# NNTmux Docker Dev Environment

**Author:** [Fossil01](https://github.com/Fossil01)

The development environment contains the: App, MariaDB, Redis, Manticore and Mailpit containers. The app container is where nginx and PHP-FPM run and has a bind mount to the root directory of this project. Supervisord is used to run it all in the background. Manticore can be switched out for Elasticsearch by commenting/uncommenting the corresponding lines in `docker-compose.yml`. Elasticsearch has security disabled by default.

This container also houses several tools to run the indexing backend: tmux, ffmpeg, mediainfo, unrar etc. It also has MariaDB & Postgres clients, NodeJS & Yarn.

nginx runs on port 80 and is mapped to port 80 on the host machine too. If you're already using port 80 then change the `docker-compose.yml` file accordingly.

Mailpit is used as a fake mailserver which catches all e-mails in one inbox. You can acess this via [this link](http://localhost:8025). Mailpit's SMTP server runs on port `1025`.

>This project currently uses PHP 8.2 and MariaDB 10.x

## Prerequisites

- Docker & Docker Compose (Duh)
- Github (scopeless) Token
- [Laravel Sail](https://laravel.com/docs/10.x/sail)

## Setup

- Edit `vars.env` with your Github (scopeless) token
  - See: https://github.com/settings/tokens/new?scopes=&description=Laravel+Dev+Env and set the expiration to **No expiration**
  - Make sure your `.gitignore` contains this line: `docker/8.2/var.ens`
- `sail build --no-cache`
- `sail up -d`

### You might have to edit your Laravel `.env` file:

```
DB_HOST=mariadb

MAIL_MAILER=smtp
MAIL_DRIVER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025

MANTICORESEARCH_HOST=manticore
ELASTICSEARCH_HOST=elasticsearch
```

## Database
On first run MariaDB will create a user and password based on your projects .env file:

```
DB_USERNAME=
DB_PASSWORD=
```

You will need to manually import a SQL dump or run `sail artisan nntmux:install` if this is a new installation.

## Networking
Sail will create a Docker network called `sail` with these ports mapped to your host:
- HTTP: `80`
- Vite: `5173`
- Manticore: `9306`(SQL) & `9308`(HTTP)
- Mailpit: `1025`(SMTP) & `8025`(WebUI)

## Backups

Optionally you can add this config to `docker-compose.yml` to have automated MariaDB backups. Just replace `DATABASE_NAME` with your own NNTmux database name.

```yaml
    backup:
        image: fradelg/mysql-cron-backup
        depends_on:
        - mariadb
        restart: always
        volumes:
        - ./docker/mariadb/backups:/backup
        environment:
        - MYSQL_USER=${DB_USERNAME}
        - MYSQL_PASS=${DB_PASSWORD}
        - MYSQL_DB=${DB_DATABASE}
        - MYSQLDUMP_OPTS=--ignore-table=DATABASE_NAME.telescope_entries --ignore-table=DATABASE_NAME.telescope_entries_tags
        - CRON_TIME=0 0 31 2 * # (To never run with cron use Feb 31st: 0 0 31 2 *)
        - MYSQL_HOST=mariadb
        - MYSQL_PORT=3306
        - TIMEOUT=10s
        - GZIP_LEVEL=9
        - MAX_BACKUPS=5 # The number of backups to keep. When reaching the limit, the old backup will be discarded.
        - INIT_BACKUP=0
        - EXIT_BACKUP=1 # Make a backup whenever this container is gracefully stopped.
        networks:
            - sail
```