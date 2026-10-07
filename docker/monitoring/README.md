# Monitoring stack (Prometheus + Grafana)

Shared configuration for both ways of running NNTmux monitoring:

- **Ubuntu hosts:** `php artisan monitoring:install`, then the `sudo scripts/install-monitoring.sh …` command it prints.
- **Sail:** `php artisan monitoring:install --sail`, then `make build && make up`. While `MONITORING_ENABLED=true` the Makefile adds the `docker-compose.monitoring.yml` overlay to `make up` / `make down`.

| Path | Used by |
|---|---|
| `grafana/provisioning/datasources/prometheus.yml` | Sail and the installer. `${PROMETHEUS_URL}` comes from Grafana's environment. |
| `grafana/provisioning/dashboards/nntmux.yml` | Sail and the installer. Loads the dashboards from `/etc/grafana/dashboards/nntmux`. |
| `grafana/dashboards/*.json` | Sail and the installer. |
| `prometheus/prometheus.dev.yml` | Sail only. The installer writes its own `/etc/prometheus/prometheus.yml`. |
| `../../docker-compose.monitoring.yml` | Sail only. Prometheus, Grafana, the Pushgateway and exporters. |

## Metrics sources

- **Host:** node_exporter (job `node`). The installer reuses a node_exporter that is already running instead of installing one.
- **Services:** mysqld_exporter (`mariadb`), redis_exporter (`redis`), Manticore's own `/metrics` via Buddy (`manticore`) or elasticsearch_exporter (`elasticsearch`).
- **NNTmux:** `monitoring:export-metrics` runs every minute from the Laravel scheduler and `PUT`s the `nntmux_*` metrics to the Pushgateway (job `nntmux`). Processing counts come from the tmux monitor's latest snapshot, so they stop updating while the monitor isn't running (see the "Tmux statistics age" panel).

## Dashboard contract

`config/monitoring.php` refers to dashboards by UID and to individual panels by id (the panels embedded on `/admin/index`). Keep these stable:

| Dashboard | UID | Panels embedded on /admin/index |
|---|---|---|
| NNTmux Host | `nntmux-host` | 1 (CPU), 2 (memory), 4 (disk) |
| NNTmux Services | `nntmux-services` | — |
| NNTmux Processing | `nntmux-processing` | 2 (backlog by type) |

Every panel must use the datasource UID `nntmux-prometheus`. `tests/Unit/Monitoring/GrafanaDashboardDefinitionsTest.php` checks all of this.

## Editing dashboards

UI edits are disabled because the files are provisioned. To change a dashboard:

1. In Grafana, use **Save as** to copy it, edit the copy, then **Export → Export as JSON**.
2. Paste the panels back into the matching file here, keeping the `uid`, the panel `id`s and `"id": null`.
3. Sail picks up the change within a minute; on hosts, rerun the installer.

## Access

Grafana listens on 127.0.0.1 only (Sail: not published) and is reverse proxied at `/grafana/`. Its built-in admin is renamed to `nntmux-grafana-admin` so an NNTmux user called `admin` can't collide with it, and Grafana Live is off (dashboards poll). It has no login form: NNTmux signs a short-lived RS256 JWT (`GRAFANA_JWT_PRIVATE_KEY_PATH`) and the web server hands it to Grafana in the `X-JWT-Assertion` header. Grafana checks it against the public key and the `iss`/`aud` claims and signs the admin in as a Viewer. The token is never put in a URL (`url_login` is off), so it stays out of access logs, `Referer` headers and browser history. `GRAFANA_AUTH` picks how it gets there:

- **`proxy`** (nginx and Sail): `auth_request` sends every `/grafana/` request (except static `/grafana/public/` assets) to the internal location `/_nntmux/grafana-auth`, which runs `GET /admin/monitoring/grafana-auth` through PHP-FPM with the browser's cookies. Laravel answers 204 with the JWT in `X-NNTmux-Grafana-JWT` for a 2FA-verified admin, otherwise 401/403, so logout or losing the Admin role locks Grafana at once. The route returns 404 unless the request carries the FastCGI parameter `NNTMUX_GRAFANA_AUTH_REQUEST`, which browsers cannot set. The installer copies `fastcgi_pass` from the NNTmux site (or `--fastcgi-pass=…`).
- **`cookie`** (Apache, or nginx without `auth_request` / a detectable `fastcgi_pass`): the admin pages call `/admin/monitoring/grafana-token`, which sets the JWT as an HttpOnly, SameSite=Strict `nntmux_grafana_jwt` cookie limited to `/grafana/`, and refresh it before `GRAFANA_JWT_TTL` runs out. A token stays valid until it expires, even after logout.

The web server always overwrites any `X-JWT-Assertion` header sent by the client. Keep Grafana's port private: anyone who can reach it directly still needs a JWT signed with the NNTmux key, but bypasses the session check.

The installer holds the `grafana` package (`apt-mark hold`) so routine upgrades don't move it off the tested version. Pick up Grafana security releases by re-running the installer with `--grafana-version=…`.
