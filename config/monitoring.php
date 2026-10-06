<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Prometheus + Grafana monitoring
    |--------------------------------------------------------------------------
    |
    | Installed on Ubuntu hosts by scripts/install-monitoring.sh (see
    | `php artisan monitoring:install`) or in Sail via docker-compose.monitoring.yml
    | (`make monitoring-up`). Grafana is reverse proxied under the main hostname and
    | trusts short-lived RS256 JWTs signed with the private key below.
    |
    */

    'enabled' => (bool) env('MONITORING_ENABLED', false),

    'grafana' => [
        // Relative path (same origin, recommended) or absolute URL.
        'url' => rtrim((string) env('GRAFANA_URL', '/grafana'), '/'),
        'org_id' => 1,
        'default_range' => 'now-6h',
        'default_refresh' => '1m',

        'jwt' => [
            // Absolute, or relative to the project root (used by the Sail setup).
            'private_key_path' => (static function (string $path): string {
                return $path === '' || str_starts_with($path, '/') ? $path : base_path($path);
            })((string) env('GRAFANA_JWT_PRIVATE_KEY_PATH', '')),
            'ttl' => max(60, (int) env('GRAFANA_JWT_TTL', 900)),
            // Must match [auth.jwt] expect_claims in grafana.ini.
            'issuer' => 'nntmux',
            'audience' => 'grafana',
            'role' => 'Viewer',
        ],

        /*
         * Dashboard UIDs and panel ids are a contract with the provisioned
         * JSON files in docker/monitoring/grafana/dashboards.
         *
         * @var array<string, array{uid: string, slug: string, title: string, icon: string}>
         */
        'dashboards' => [
            'host' => ['uid' => 'nntmux-host', 'slug' => 'nntmux-host', 'title' => 'Host', 'icon' => 'fas fa-server'],
            'services' => ['uid' => 'nntmux-services', 'slug' => 'nntmux-services', 'title' => 'Services', 'icon' => 'fas fa-database'],
            'processing' => ['uid' => 'nntmux-processing', 'slug' => 'nntmux-processing', 'title' => 'Processing', 'icon' => 'fas fa-gears'],
        ],

        /*
         * Panels embedded on /admin/index in place of the Chart.js CPU/RAM history.
         *
         * @var list<array{dashboard: string, panel: int, title: string}>
         */
        'dashboard_panels' => [
            ['dashboard' => 'host', 'panel' => 1, 'title' => 'CPU usage'],
            ['dashboard' => 'host', 'panel' => 2, 'title' => 'Memory usage'],
            ['dashboard' => 'host', 'panel' => 4, 'title' => 'Disk usage'],
            ['dashboard' => 'processing', 'panel' => 2, 'title' => 'Processing backlog'],
        ],
    ],

    'pushgateway' => [
        'url' => rtrim((string) env('MONITORING_PUSHGATEWAY_URL', 'http://127.0.0.1:9091'), '/'),
        'job' => 'nntmux',
        'timeout' => 5,
    ],

    'tmux_snapshot' => [
        'key' => 'monitoring:tmux-snapshot',
        'ttl' => 900,
    ],
];
