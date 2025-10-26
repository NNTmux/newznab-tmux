<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Models\GrabStat;
use App\Models\ReleaseStat;
use App\Models\RoleStat;
use App\Models\Settings;
use App\Models\SignupStat;
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

        $meta_title = $title = 'Site Edit';
        $error = '';

        // set the current action
        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                if ($request->missing('book_reqids')) {
                    $request->merge(['book_reqids' => []]);
                }
                Settings::settingsUpdate($request->all());

                return redirect()->to('admin/site-edit')->with('success', 'Settings updated successfully');

            case 'view':
            default:
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
        $bookReqidsValue = Settings::settingValue('book_reqids') ?? '';
        $books_selected = $bookReqidsValue !== '' ? explode(',', $bookReqidsValue) : [];

        // convert from a string array to an int array, filtering out empty values
        $books_selected = array_map(fn ($value) => (int) trim($value), array_filter($books_selected));

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
            'book_reqids' => [
                'ids' => $book_reqids_ids,
                'names' => $book_reqids_names,
                'selected' => $books_selected,
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
    public function stats()
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
