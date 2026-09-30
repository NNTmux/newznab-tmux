<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\GrabStat;
use App\Models\ReleaseStat;
use App\Models\RoleStat;
use App\Models\SignupStat;

final class AdminSiteController extends BasePageController
{
    /** @throws \Exception */
    public function stats(): mixed
    {
        $this->viewData = array_merge($this->viewData, [
            'topgrabs' => GrabStat::getTopGrabbers(),
            'recent' => ReleaseStat::getRecentlyAdded(),
            'usersbymonth' => SignupStat::getUsersByMonth(),
            'usersbyrole' => RoleStat::getUsersByRole(),
            'totusers' => 0,
            'totrusers' => 0,
            'title' => 'Site Stats',
            'meta_title' => 'Site Stats',
        ]);

        return view('admin.site.stats', $this->viewData);
    }
}
