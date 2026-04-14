<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\GrabStat;
use App\Models\ReleaseStat;
use App\Models\RoleStat;
use App\Models\Settings;
use App\Models\SignupStat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSiteController extends BasePageController
{
    /**
     * @return RedirectResponse|View
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {

        $meta_title = $title = 'Site Edit';
        $error = '';

        // set the current action
        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                Settings::settingsUpdate($request->all());

                return redirect()->to('admin/site-edit')->with('success', 'Settings updated successfully');

            case 'view':
            default:
                break;
        }

        $compress_headers_warning = ! str_contains(config('settings.nntp_server'), 'astra') ? 'compress_headers_warning' : '';

        $this->viewData = array_merge($this->viewData, [
            'error' => $error,
            'yesno' => [
                'ids' => [1, 0],
                'names' => ['Yes', 'No'],
            ],
            'passwd' => [
                'ids' => [1, 0],
                'names' => ['Deep (requires unrar)', 'None'],
            ],
            'langlist' => [
                'ids' => [0, 2, 3, 1],
                'names' => ['English', 'Danish', 'French', 'German'],
            ],
            'imdblang' => [
                'ids' => ['en', 'da', 'nl', 'fi', 'fr', 'de', 'it', 'tlh', 'no', 'po', 'ru', 'es', 'sv'],
                'names' => ['English', 'Danish', 'Dutch', 'Finnish', 'French', 'German', 'Italian', 'Klingon', 'Norwegian', 'Polish', 'Russian', 'Spanish', 'Swedish'],
            ],
            'newgroupscan_names' => ['Days', 'Posts'],
            'registerstatus' => [
                'ids' => [Settings::REGISTER_STATUS_OPEN, Settings::REGISTER_STATUS_INVITE, Settings::REGISTER_STATUS_CLOSED],
                'names' => ['Open', 'Invite', 'Closed'],
            ],
            'passworded' => [
                'ids' => [0, 1],
                'names' => ['Hide passworded', 'Show everything'],
            ],
            'lookuplanguage' => [
                'iso' => ['en', 'de', 'es', 'fr', 'it', 'nl', 'pt', 'sv'],
                'names' => ['English', 'Deutsch', 'Español', 'Français', 'Italiano', 'Nederlands', 'Português', 'Svenska'],
            ],
            'imdb_urls' => [
                'ids' => [0, 1],
                'names' => ['imdb.com', 'akas.imdb.com'],
            ],
            'lookupbooks' => [
                'ids' => [0, 1, 2],
                'names' => ['Disabled', 'Lookup All Books', 'Lookup Renamed Books'],
            ],
            'lookupgames' => [
                'ids' => [0, 1, 2],
                'names' => ['Disabled', 'Lookup All Consoles', 'Lookup Renamed Consoles'],
            ],
            'lookupmusic' => [
                'ids' => [0, 1, 2],
                'names' => ['Disabled', 'Lookup All Music', 'Lookup Renamed Music'],
            ],
            'lookupmovies' => [
                'ids' => [0, 1, 2],
                'names' => ['Disabled', 'Lookup All Movies', 'Lookup Renamed Movies'],
            ],
            'lookuptv' => [
                'ids' => [0, 1, 2],
                'names' => ['Disabled', 'Lookup All TV', 'Lookup Renamed TV'],
            ],
            'lookup_reqids' => [
                'ids' => [0, 1, 2],
                'names' => ['Disabled', 'Lookup Request IDs', 'Lookup Request IDs Threaded'],
            ],
            'compress_headers_warning' => $compress_headers_warning,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.site.edit', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function stats(): mixed
    {

        $meta_title = $title = 'Site Stats';

        $topGrabs = GrabStat::getTopGrabbers();
        $recent = ReleaseStat::getRecentlyAdded();
        $usersByMonth = SignupStat::getUsersByMonth();
        $usersByRole = RoleStat::getUsersByRole();

        $this->viewData = array_merge($this->viewData, [
            'topgrabs' => $topGrabs,
            'recent' => $recent,
            'usersbymonth' => $usersByMonth,
            'usersbyrole' => $usersByRole,
            'totusers' => 0,
            'totrusers' => 0,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.site.stats', $this->viewData);
    }
}
