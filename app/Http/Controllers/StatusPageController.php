<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SiteStatusService;
use Illuminate\View\View;

class StatusPageController extends BasePageController
{
    public function __construct(
        protected SiteStatusService $siteStatusService
    ) {
        parent::__construct();
    }

    /**
     * Public site status page (no auth required; method name is in BasePageController auth except list).
     */
    public function showStatusPage(): View
    {
        $services = $this->siteStatusService->getAllStatuses();
        $overall = $this->siteStatusService->getOverallStatus();
        $activeIncidents = $this->siteStatusService->getActiveIncidents();
        $recentResolved = $this->siteStatusService->getRecentResolvedIncidents(30);

        $groupedResolved = $recentResolved->groupBy(function ($incident) {
            return $incident->resolved_at?->format('Y-m-d') ?? 'unknown';
        });

        $this->viewData = array_merge($this->viewData, [
            'meta_title' => 'Service status',
            'meta_description' => 'Current status of site services, incidents, and uptime.',
            'title' => 'Service status',
            'services' => $services,
            'overallStatus' => $overall,
            'activeIncidents' => $activeIncidents,
            'recentResolvedGrouped' => $groupedResolved,
        ]);

        return view('status.index', $this->viewData);
    }
}
