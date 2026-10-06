<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\User;
use App\Services\Monitoring\GrafanaEmbedService;
use App\Services\Monitoring\GrafanaJwtIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminMonitoringController extends BasePageController
{
    public function index(GrafanaEmbedService $grafana): View
    {
        $this->setAdminPrefs();

        $enabled = $grafana->isEnabled();

        return view('admin.monitoring', [
            'enabled' => $enabled,
            'monitoringConfigured' => (bool) config('monitoring.enabled'),
            'dashboards' => $enabled ? $grafana->dashboards() : [],
            'defaultRange' => (string) config('monitoring.grafana.default_range', 'now-6h'),
            'defaultRefresh' => (string) config('monitoring.grafana.default_refresh', '1m'),
            'title' => 'Monitoring',
            'meta_title' => 'Monitoring',
            'meta_description' => 'Grafana dashboards for host, service and processing metrics.',
        ]);
    }

    /**
     * Short-lived Grafana login token for the embedded dashboards. Fetched by
     * the Alpine components so the token never appears in rendered HTML.
     */
    public function token(Request $request, GrafanaEmbedService $grafana, GrafanaJwtIssuer $issuer): JsonResponse
    {
        if (! $grafana->isEnabled()) {
            return response()->json(['message' => 'Monitoring is not configured.'], 404)
                ->header('Cache-Control', 'no-store');
        }

        /** @var User $user */
        $user = $request->user();

        return response()->json($issuer->issueFor($user))
            ->header('Cache-Control', 'no-store');
    }
}
