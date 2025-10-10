<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Services\UserStatsService;

class AdminPageController extends BasePageController
{
    protected UserStatsService $userStatsService;

    public function __construct(UserStatsService $userStatsService)
    {
        parent::__construct();
        $this->userStatsService = $userStatsService;
    }

    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        // Get user statistics
        $userStats = [
            'users_by_role' => $this->userStatsService->getUsersByRole(),
            'downloads_per_day' => $this->userStatsService->getDownloadsPerDay(7),
            'api_hits_per_day' => $this->userStatsService->getApiHitsPerDay(7),
            'summary' => $this->userStatsService->getSummaryStats(),
            'top_downloaders' => $this->userStatsService->getTopDownloaders(5),
        ];

        return view('admin.dashboard', array_merge($this->viewData, [
            'meta_title' => 'Admin Home',
            'meta_description' => 'Admin home page',
            'userStats' => $userStats,
            'stats' => $this->getDefaultStats(),
        ]));
    }

    /**
     * Get default dashboard statistics
     *
     * @return array
     */
    protected function getDefaultStats(): array
    {
        $today = now()->format('Y-m-d');

        return [
            'releases' => \App\Models\Release::count(),
            'releases_today' => \App\Models\Release::whereRaw('DATE(adddate) = ?', [$today])->count(),
            'users' => \App\Models\User::whereNull('deleted_at')->count(),
            'users_today' => \App\Models\User::whereRaw('DATE(created_at) = ?', [$today])->count(),
            'groups' => \App\Models\UsenetGroup::count(),
            'active_groups' => \App\Models\UsenetGroup::where('active', 1)->count(),
            'failed' => \App\Models\DnzbFailure::count(),
            'disk_free' => $this->getDiskSpace(),
        ];
    }

    /**
     * Get disk space information
     *
     * @return string
     */
    protected function getDiskSpace(): string
    {
        try {
            $bytes = disk_free_space('/');
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];

            for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                $bytes /= 1024;
            }

            return round($bytes, 2) . ' ' . $units[$i];
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
}
