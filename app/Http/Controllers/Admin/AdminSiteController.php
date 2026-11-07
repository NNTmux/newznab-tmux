<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Services\Admin\SiteStatsReport;

final class AdminSiteController extends BasePageController
{
    /** @throws \Exception */
    public function stats(SiteStatsReport $report): mixed
    {
        $this->viewData = array_merge($this->viewData, [
            'report' => $report->build(),
            'title' => 'Site Stats',
            'meta_title' => 'Site Stats',
        ]);

        return view('admin.site.stats', $this->viewData);
    }
}
