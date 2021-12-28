<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use App\Models\User;
use Blacklight\SABnzbd;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AdminSiteController extends BasePageController
{
    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Site Edit';

        // set the current action
        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                if ($request->missing('book_reqids')) {
                    $request->merge(['book_reqids' => []]);
                }
                $error = '';
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
                    $site = $ret;

                    return redirect('admin/site-edit');
                }

                $this->smarty->assign('error', $error);
                $site = (object) $request->all();
                $this->smarty->assign('site', $site);

                break;
            case 'view':
            default:
                $site = $this->settings;
                $this->smarty->assign('site', $site);
                $this->smarty->assign('settings', Settings::toTree());
                break;
        }

        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);

        $this->smarty->assign('passwd_ids', [1, 0]);
        $this->smarty->assign('passwd_names', ['Deep (requires unrar)', 'None']);

        /*0 = English, 2 = Danish, 3 = French, 1 = German*/
        $this->smarty->assign('langlist_ids', [0, 2, 3, 1]);
        $this->smarty->assign('langlist_names', ['English', 'Danish', 'French', 'German']);

        $this->smarty->assign(
            'imdblang_ids',
            [
                'en', 'da', 'nl', 'fi', 'fr', 'de', 'it', 'tlh', 'no', 'po', 'ru', 'es',
                'sv',
            ]
        );
        $this->smarty->assign(
            'imdblang_names',
            [
                'English', 'Danish', 'Dutch', 'Finnish', 'French', 'German', 'Italian',
                'Klingon', 'Norwegian', 'Polish', 'Russian', 'Spanish', 'Swedish',
            ]
        );

        $this->smarty->assign('sabintegrationtype_ids', [SABnzbd::INTEGRATION_TYPE_USER, SABnzbd::INTEGRATION_TYPE_NONE]);
        $this->smarty->assign('sabintegrationtype_names', ['User', 'None (Off)']);

        $this->smarty->assign('newgroupscan_names', ['Days', 'Posts']);

        $this->smarty->assign('registerstatus_ids', [Settings::REGISTER_STATUS_OPEN, Settings::REGISTER_STATUS_INVITE, Settings::REGISTER_STATUS_CLOSED]);
        $this->smarty->assign('registerstatus_names', ['Open', 'Invite', 'Closed']);

        $this->smarty->assign('passworded_ids', [0, 1]);
        $this->smarty->assign('passworded_names', [
            'Hide passworded',
            'Show everything',
        ]);

        $this->smarty->assign('lookuplanguage_iso', ['en', 'de', 'es', 'fr', 'it', 'nl', 'pt', 'sv']);
        $this->smarty->assign('lookuplanguage_names', ['English', 'Deutsch', 'Español', 'Français', 'Italiano', 'Nederlands', 'Português', 'Svenska']);

        $this->smarty->assign('imdb_urls', [0, 1]);
        $this->smarty->assign('imdburl_names', ['imdb.com', 'akas.imdb.com']);

        $this->smarty->assign('lookupbooks_ids', [0, 1, 2]);
        $this->smarty->assign('lookupbooks_names', ['Disabled', 'Lookup All Books', 'Lookup Renamed Books']);

        $this->smarty->assign('lookupgames_ids', [0, 1, 2]);
        $this->smarty->assign('lookupgames_names', ['Disabled', 'Lookup All Consoles', 'Lookup Renamed Consoles']);

        $this->smarty->assign('lookupmusic_ids', [0, 1, 2]);
        $this->smarty->assign('lookupmusic_names', ['Disabled', 'Lookup All Music', 'Lookup Renamed Music']);

        $this->smarty->assign('lookupmovies_ids', [0, 1, 2]);
        $this->smarty->assign('lookupmovies_names', ['Disabled', 'Lookup All Movies', 'Lookup Renamed Movies']);

        $this->smarty->assign('lookuptv_ids', [0, 1, 2]);
        $this->smarty->assign('lookuptv_names', ['Disabled', 'Lookup All TV', 'Lookup Renamed TV']);

        $this->smarty->assign('lookup_reqids_ids', [0, 1, 2]);
        $this->smarty->assign('lookup_reqids_names', ['Disabled', 'Lookup Request IDs', 'Lookup Request IDs Threaded']);

        $this->smarty->assign('coversPath', NN_COVERS);

        // return a list of audiobooks, mags, ebooks, technical and foreign books
        $result = Category::query()->whereIn('id', [Category::MUSIC_AUDIOBOOK, Category::BOOKS_MAGAZINES, Category::BOOKS_TECHNICAL, Category::BOOKS_FOREIGN])->get(['id', 'title']);

        // setup the display lists for these categories, this could have been static, but then if names changed they would be wrong
        $book_reqids_ids = [];
        $book_reqids_names = [];
        foreach ($result as $bookcategory) {
            $book_reqids_ids[] = $bookcategory['id'];
            $book_reqids_names[] = $bookcategory['title'];
        }

        // convert from a string array to an int array as we want to use int
        $book_reqids_ids = array_map(function ($value) {
            return (int) $value;
        }, $book_reqids_ids);
        $this->smarty->assign('book_reqids_ids', $book_reqids_ids);
        $this->smarty->assign('book_reqids_names', $book_reqids_names);

        // convert from a list to an array as we need to use an array, but teh Settings table only saves strings
        $books_selected = explode(',', Settings::settingValue('..book_reqids'));

        // convert from a string array to an int array
        $books_selected = array_map(function ($value) {
            return (int) $value;
        }, $books_selected);
        $this->smarty->assign('book_reqids_selected', $books_selected);

        $this->smarty->assign('themelist', Utility::getThemesList());

        if (strpos(env('NNTP_SERVER'), 'astra') === false) {
            $this->smarty->assign('compress_headers_warning', 'compress_headers_warning');
        }

        $content = $this->smarty->fetch('site-edit.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @throws \Exception
     */
    public function stats()
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Site Stats';

        $topgrabs = User::getTopGrabbers();
        $this->smarty->assign('topgrabs', $topgrabs);

        $topdownloads = Release::getTopDownloads();
        $this->smarty->assign('topdownloads', $topdownloads);

        $topcomments = Release::getTopComments();
        $this->smarty->assign('topcomments', $topcomments);

        $recent = Category::getRecentlyAdded();
        $this->smarty->assign('recent', $recent);

        $usersbymonth = User::getUsersByMonth();
        $this->smarty->assign('usersbymonth', $usersbymonth);

        $usersbyrole = Role::query()->select(['name'])->withCount('users')->groupBy('name')->having('users_count', '>', 0)->orderBy('users_count', 'desc')->get();
        $this->smarty->assign('usersbyrole', $usersbyrole);
        $this->smarty->assign('totusers', 0);
        $this->smarty->assign('totrusers', 0);

        $content = $this->smarty->fetch('site-stats.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }
}
