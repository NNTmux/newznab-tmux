<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Models\DownloadStat;
use App\Models\GrabStat;
use App\Models\ReleaseStat;
use App\Models\RoleStat;
use App\Models\Settings;
use App\Models\SignupStat;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;

class AdminSiteController extends BasePageController
{
    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Site Edit';
        $error = '';

        // set the current action
        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                if ($request->missing('book_reqids')) {
                    $request->merge(['book_reqids' => []]);
                }
                $ret = Settings::settingsUpdate($request->all());
                if (\is_int($ret)) {
                    if ($ret === Settings::ERR_BADUNRARPATH) {
                        $error = 'The unrar path does not point to a valid binary';
                    } elseif ($ret === Settings::ERR_BADFFMPEGPATH) {
                        $error = 'The ffmpeg path does not point to a valid binary';
                    } elseif ($ret === Settings::ERR_BADMEDIAINFOPATH) {
                        $error = 'The mediainfo path does not point to a valid binary';
                    } elseif ($ret === Settings::ERR_BADNZBPATH) {
                        $error = 'The nzb path does not point to a valid directory';
                    } elseif ($ret === Settings::ERR_DEEPNOUNRAR) {
                        $error = 'Deep password check requires a valid path to unrar binary';
                    } elseif ($ret === Settings::ERR_BADTMPUNRARPATH) {
                        $error = 'The temp unrar path is not a valid directory';
                    } elseif ($ret === Settings::ERR_BADLAMEPATH) {
                        $error = 'The lame path is not a valid directory';
                    } elseif ($ret === Settings::ERR_SABCOMPLETEPATH) {
                        $error = 'The sab complete path is not a valid directory';
                    }
                }

                if ($error === '') {
                    return redirect()->to('admin/site-edit')->with('success', 'Settings updated successfully');
                }

                $site = (object) $request->all();
                break;
            case 'view':
            default:
                // Load all settings from database into an object
                $allSettings = Settings::all();
                $site = new \stdClass;
                foreach ($allSettings as $setting) {
                    $site->{$setting->name} = $setting->value;
                }
                break;
        }

        // return a list of audiobooks, mags, ebooks, technical and foreign books
        $result = Category::query()->whereIn('id', [Category::MUSIC_AUDIOBOOK, Category::BOOKS_MAGAZINES, Category::BOOKS_TECHNICAL, Category::BOOKS_FOREIGN])->get(['id', 'title']);

        // setup the display lists for these categories
        $book_reqids_ids = [];
        $book_reqids_names = [];
        foreach ($result as $bookcategory) {
            $book_reqids_ids[] = $bookcategory['id'];
            $book_reqids_names[] = $bookcategory['title'];
        }

        // convert from a string array to an int array as we want to use int
        $book_reqids_ids = array_map(fn ($value) => (int) $value, $book_reqids_ids);

        // convert from a list to an array as we need to use an array, but the Settings table only saves strings
        $books_selected = explode(',', Settings::settingValue('book_reqids'));

        // convert from a string array to an int array
        $books_selected = array_map(fn ($value) => (int) $value, $books_selected);

        $compress_headers_warning = ! str_contains(config('settings.nntp_server'), 'astra') ? 'compress_headers_warning' : '';

        $this->viewData = array_merge($this->viewData, [
            'site' => $site,
            'settings' => Settings::toTree(),
            'error' => $error,
            'yesno_ids' => [1, 0],
            'yesno_names' => ['Yes', 'No'],
            'passwd_ids' => [1, 0],
            'passwd_names' => ['Deep (requires unrar)', 'None'],
            'langlist_ids' => [0, 2, 3, 1],
            'langlist_names' => ['English', 'Danish', 'French', 'German'],
            'imdblang_ids' => ['en', 'da', 'nl', 'fi', 'fr', 'de', 'it', 'tlh', 'no', 'po', 'ru', 'es', 'sv'],
            'imdblang_names' => ['English', 'Danish', 'Dutch', 'Finnish', 'French', 'German', 'Italian', 'Klingon', 'Norwegian', 'Polish', 'Russian', 'Spanish', 'Swedish'],
            'newgroupscan_names' => ['Days', 'Posts'],
            'registerstatus_ids' => [Settings::REGISTER_STATUS_OPEN, Settings::REGISTER_STATUS_INVITE, Settings::REGISTER_STATUS_CLOSED],
            'registerstatus_names' => ['Open', 'Invite', 'Closed'],
            'passworded_ids' => [0, 1],
            'passworded_names' => ['Hide passworded', 'Show everything'],
            'lookuplanguage_iso' => ['en', 'de', 'es', 'fr', 'it', 'nl', 'pt', 'sv'],
            'lookuplanguage_names' => ['English', 'Deutsch', 'Español', 'Français', 'Italiano', 'Nederlands', 'Português', 'Svenska'],
            'imdb_urls' => [0, 1],
            'imdburl_names' => ['imdb.com', 'akas.imdb.com'],
            'lookupbooks_ids' => [0, 1, 2],
            'lookupbooks_names' => ['Disabled', 'Lookup All Books', 'Lookup Renamed Books'],
            'lookupgames_ids' => [0, 1, 2],
            'lookupgames_names' => ['Disabled', 'Lookup All Consoles', 'Lookup Renamed Consoles'],
            'lookupmusic_ids' => [0, 1, 2],
            'lookupmusic_names' => ['Disabled', 'Lookup All Music', 'Lookup Renamed Music'],
            'lookupmovies_ids' => [0, 1, 2],
            'lookupmovies_names' => ['Disabled', 'Lookup All Movies', 'Lookup Renamed Movies'],
            'lookuptv_ids' => [0, 1, 2],
            'lookuptv_names' => ['Disabled', 'Lookup All TV', 'Lookup Renamed TV'],
            'lookup_reqids_ids' => [0, 1, 2],
            'lookup_reqids_names' => ['Disabled', 'Lookup Request IDs', 'Lookup Request IDs Threaded'],
            'coversPath' => config('nntmux_settings.covers_path'),
            'book_reqids_ids' => $book_reqids_ids,
            'book_reqids_names' => $book_reqids_names,
            'book_reqids_selected' => $books_selected,
            'themelist' => Utility::getThemesList(),
            'compress_headers_warning' => $compress_headers_warning,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.site.edit', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function stats()
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Site Stats';

        $topGrabs = GrabStat::getTopGrabbers();
        $topDownloads = DownloadStat::getTopDownloads();
        $recent = ReleaseStat::getRecentlyAdded();
        $usersByMonth = SignupStat::getUsersByMonth();
        $usersByRole = RoleStat::getUsersByRole();

        $this->viewData = array_merge($this->viewData, [
            'topgrabs' => $topGrabs,
            'topdownloads' => $topDownloads,
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
