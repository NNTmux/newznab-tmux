<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

/**
 * Builds Grafana dashboard and panel URLs for the admin pages.
 *
 * URLs never contain the auth token or theme; the Alpine components append
 * both client-side so tokens stay out of server-rendered HTML.
 */
class GrafanaEmbedService
{
    public function __construct(private readonly GrafanaJwtIssuer $issuer) {}

    public function isEnabled(): bool
    {
        return (bool) config('monitoring.enabled') && $this->issuer->hasUsableKey();
    }

    /**
     * @return array<string, array{uid: string, slug: string, title: string, icon: string, url: string, open_url: string}>
     */
    public function dashboards(): array
    {
        $dashboards = [];

        foreach ($this->configuredDashboards() as $key => $dashboard) {
            $dashboards[$key] = $dashboard + [
                'url' => $this->dashboardUrl($key),
                'open_url' => $this->dashboardUrl($key, kiosk: false),
            ];
        }

        return $dashboards;
    }

    public function dashboardUrl(string $key, bool $kiosk = true): string
    {
        $dashboard = $this->dashboard($key);
        $query = [
            'orgId' => (int) config('monitoring.grafana.org_id', 1),
            'from' => (string) config('monitoring.grafana.default_range', 'now-6h'),
            'to' => 'now',
            'refresh' => (string) config('monitoring.grafana.default_refresh', '1m'),
        ];

        $url = $this->baseUrl().'/d/'.rawurlencode($dashboard['uid']).'/'.rawurlencode($dashboard['slug']).'?'.http_build_query($query);

        // Grafana treats a bare `kiosk` flag as full kiosk mode.
        return $kiosk ? $url.'&kiosk' : $url;
    }

    public function panelUrl(string $key, int $panelId): string
    {
        $dashboard = $this->dashboard($key);

        return $this->baseUrl().'/d-solo/'.rawurlencode($dashboard['uid']).'/'.rawurlencode($dashboard['slug']).'?'.http_build_query([
            'orgId' => (int) config('monitoring.grafana.org_id', 1),
            'from' => (string) config('monitoring.grafana.default_range', 'now-6h'),
            'to' => 'now',
            'refresh' => (string) config('monitoring.grafana.default_refresh', '1m'),
            'panelId' => $panelId,
        ]);
    }

    /**
     * Panels embedded on the admin dashboard; empty when monitoring is off so
     * the page falls back to the Chart.js CPU/RAM history.
     *
     * @return list<array{title: string, url: string}>
     */
    public function dashboardPanels(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $panels = [];

        /** @var list<array{dashboard: string, panel: int, title: string}> $configured */
        $configured = config('monitoring.grafana.dashboard_panels', []);

        foreach ($configured as $panel) {
            $panels[] = [
                'title' => $panel['title'],
                'url' => $this->panelUrl($panel['dashboard'], (int) $panel['panel']),
            ];
        }

        return $panels;
    }

    /**
     * Origin to allow in frame-src when Grafana is served from another host;
     * null when it shares the app origin (already covered by 'self').
     */
    public static function frameOrigin(?string $grafanaUrl, ?string $appUrl): ?string
    {
        $grafana = parse_url((string) $grafanaUrl);

        if (! is_array($grafana) || ! isset($grafana['scheme'], $grafana['host'])) {
            return null;
        }

        $origin = strtolower($grafana['scheme'].'://'.$grafana['host']).(isset($grafana['port']) ? ':'.$grafana['port'] : '');
        $app = parse_url((string) $appUrl);

        if (is_array($app) && isset($app['scheme'], $app['host'])) {
            $appOrigin = strtolower($app['scheme'].'://'.$app['host']).(isset($app['port']) ? ':'.$app['port'] : '');

            if ($appOrigin === $origin) {
                return null;
            }
        }

        return $origin;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('monitoring.grafana.url', '/grafana'), '/');
    }

    /**
     * @return array<string, array{uid: string, slug: string, title: string, icon: string}>
     */
    private function configuredDashboards(): array
    {
        /** @var array<string, array{uid: string, slug: string, title: string, icon: string}> $dashboards */
        $dashboards = config('monitoring.grafana.dashboards', []);

        return $dashboards;
    }

    /**
     * @return array{uid: string, slug: string, title: string, icon: string}
     */
    private function dashboard(string $key): array
    {
        $dashboards = $this->configuredDashboards();

        if (! isset($dashboards[$key])) {
            throw new \InvalidArgumentException("Unknown Grafana dashboard [{$key}].");
        }

        return $dashboards[$key];
    }
}
