<?php

namespace App\Http\Controllers;

use App\Events\UserLoggedIn;
use App\Models\Category;
use App\Models\Forumpost;
use App\Models\Settings;
use App\Models\User;
use Blacklight\Contents;
use Blacklight\SABnzbd;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class BasePageController extends Controller
{
    /**
     * @var \App\Models\Settings
     */
    public $settings;

    /**
     * @var string
     */
    public $title = '';

    /**
     * @var string
     */
    public $content = '';

    /**
     * @var string
     */
    public $meta_keywords = '';

    /**
     * @var string
     */
    public $meta_title = '';

    /**
     * @var string
     */
    public $meta_description = '';

    /**
     * Current page the user is browsing. ie browse.
     *
     * @var string
     */
    public $page = '';

    /**
     * @var string
     */
    public $page_template = '';

    /**
     * @var User
     */
    public $userdata;

    /**
     * User's theme.
     *
     * @var string
     */
    protected $theme = 'Gentele';

    /**
     * @var \Illuminate\Foundation\Application|mixed
     */
    public $smarty;

    /**
     * BasePageController constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->middleware(['auth', 'web', '2fa'])->except('api', 'contact', 'showContactForm', 'callback', 'getNzb', 'terms', 'capabilities', 'movie', 'apiSearch', 'tv', 'details', 'failed', 'showRssDesc', 'fullFeedRss', 'categoryFeedRss', 'cartRss', 'myMoviesRss', 'myShowsRss');
        // Buffer settings/DB connection.
        $this->settings = new Settings();
        $this->smarty = app('smarty.view');

        foreach (Arr::get(config('ytake-laravel-smarty'), 'plugins_paths', []) as $plugins) {
            $this->smarty->addPluginsDir($plugins);
        }
        $this->smarty->error_reporting = E_ALL & ~E_NOTICE;

        $this->smarty->assign('serverroot', url('/'));
    }

    /**
     * @param $query
     * @param $totalCount
     * @param $items
     * @param $page
     * @param $path
     * @param $reqQuery
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginate($query, $totalCount, $items, $page, $path, $reqQuery): LengthAwarePaginator
    {
        return new LengthAwarePaginator($query, $totalCount, $items, $page, ['path' => $path, 'query' => $reqQuery]);
    }

    /**
     * @throws \Exception
     */
    protected function setPrefs(): void
    {
        if (Auth::check()) {
            $this->userdata = User::find(Auth::id());
            $this->setUserPreferences();
            if ($this->theme === 'None') {
                $this->theme = Settings::settingValue('site.main.style');
            }
        } else {
            $this->theme = Settings::settingValue('site.main.style');
            // Tell Smarty which directories to use for templates
            $this->smarty->setTemplateDir([
                'user' => config('ytake-laravel-smarty.template_path').DIRECTORY_SEPARATOR.$this->theme,
                'shared' => config('ytake-laravel-smarty.template_path').'/shared',
                'default' => config('ytake-laravel-smarty.template_path').'/Gentele',
            ]);

            $this->smarty->assign(
                [
                    'isadmin' => false,
                    'ismod' => false,
                    'loggedin' => false,
                ]
            );
        }

        $this->smarty->assign(
            [
                'theme'=> $this->theme,
                'site' => $this->settings,
            ]
        );
    }

    /**
     * @return bool
     */
    public function isPostBack(): bool
    {
        return \request()->isMethod('POST');
    }

    /**
     * Show 404 page.
     *
     * @param $message
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show404($message = null)
    {
        if ($message !== null) {
            return view('errors.404')->with('Message', $message);
        }

        return view('errors.404');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show403()
    {
        return view('errors.403');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show503()
    {
        return view('errors.503')->with('Error', 'Service temporarily unavailable');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showBadBoy()
    {
        return view('errors.badboy')->with('Message', 'This is not you account.');
    }

    /**
     * Show maintenance page.
     */
    public function showMaintenance()
    {
        return view('errors.maintenance')->with('Message', 'We are performing an site maintenance.');
    }

    /**
     * Show Security token mismatch page.
     */
    public function showTokenError()
    {
        return view('errors.tokenError')->with('Error', 'Token mismatch');
    }

    /**
     * @param  string  $retry
     */
    public function show429($retry = '')
    {
        abort(429, $retry);
    }

    public function render()
    {
        $this->smarty->display($this->page_template);
    }

    /**
     * @throws \Exception
     */
    protected function setUserPreferences(): void
    {
        $this->userdata->categoryexclusions = User::getCategoryExclusionById(Auth::id());

        // Change the theme to user's selected theme if they selected one, else use the admin one.
        if ((int) Settings::settingValue('site.main.userselstyle') === 1) {
            $this->theme = $this->userdata->style ?? 'None';
            if ($this->theme === 'None') {
                $this->theme = Settings::settingValue('site.main.style');
            }
        } else {
            $this->theme = Settings::settingValue('site.main.style');
        }

        // Update last login every 15 mins.
        if (now()->subHours(3) > $this->userdata->lastlogin) {
            event(new UserLoggedIn($this->userdata));
        }

        $this->smarty->assign('userdata', $this->userdata);
        $this->smarty->assign('loggedin', 'true');

        if ($this->userdata->nzbvortex_api_key !== '' && $this->userdata->nzbvortex_server_url !== '') {
            $this->smarty->assign('weHasVortex', true);
        } else {
            $this->smarty->assign('weHasVortex', false);
        }

        $sab = new SABnzbd($this);
        $this->smarty->assign('sabintegrated', $sab->integratedBool);
        if ($sab->integratedBool && $sab->url !== '' && $sab->apikey !== '') {
            $this->smarty->assign('sabapikeytype', $sab->apikeytype);
        }
        if ($this->userdata->hasRole('Admin')) {
            $this->smarty->assign('isadmin', 'true');
        }

        if ($this->userdata->hasRole('Moderator')) {
            $this->smarty->assign('ismod', 'true');
        }

        // Tell Smarty which directories to use for templates
        $this->smarty->setTemplateDir([
            'user' => config('ytake-laravel-smarty.template_path').DIRECTORY_SEPARATOR.$this->theme,
            'shared' => config('ytake-laravel-smarty.template_path').'/shared',
            'default' => config('ytake-laravel-smarty.template_path').'/Gentele',
        ]);

        $role = User::ROLE_USER;
        if (! empty($this->userdata)) {
            $role = $this->userdata->roles_id;
        }

        $content = new Contents();
        $this->smarty->assign('usefulcontentlist', $content->getForMenuByTypeAndRole(Contents::TYPEUSEFUL, $role));
        $this->smarty->assign('articlecontentlist', $content->getForMenuByTypeAndRole(Contents::TYPEARTICLE, $role));
        if ($this->userdata !== null) {
            $this->smarty->assign('recentforumpostslist', Forumpost::getPosts(Settings::settingValue('..showrecentforumposts')));
        }

        $parentcatlist = Category::getForMenu($this->userdata->categoryexclusions);

        $this->smarty->assign('parentcatlist', $parentcatlist);
        $this->smarty->assign('catClass', Category::class);

        if (\request()->has('t')) {
            $this->smarty->assign('header_menu_cat', \request()->input('t'));
        } else {
            $this->smarty->assign('header_menu_cat', '');
        }
        $header_menu = $this->smarty->fetch('headermenu.tpl');
        $this->smarty->assign('header_menu', $header_menu);
        $notification = $this->smarty->fetch('notification.tpl');
        $this->smarty->assign('notification', $notification);
        $sidebar = $this->smarty->fetch('sidebar.tpl');
        $this->smarty->assign('sidebar', $sidebar);
        $footer = $this->smarty->fetch('footer.tpl');
        $this->smarty->assign('footer', $footer);
    }

    /**
     *  Set admin preferences.
     */
    public function setAdminPrefs()
    {
        // Tell Smarty which directories to use for templates
        $this->smarty->setTemplateDir(
            [
                'admin'    => config('ytake-laravel-smarty.template_path').'/admin',
                'shared'    => config('ytake-laravel-smarty.template_path').'/shared',
                'default'    => config('ytake-laravel-smarty.template_path').'/admin',
            ]
        );

        $this->smarty->assign('catClass', Category::class);
    }

    /**
     * Output the page.
     */
    public function pagerender(): void
    {
        $this->page_template = 'basepage.tpl';

        $this->render();
    }

    /**
     * Output a page using the admin template.
     *
     * @throws \Exception
     */
    public function adminrender(): void
    {
        $admin_menu = $this->smarty->fetch('adminmenu.tpl');
        $this->smarty->assign('admin_menu', $admin_menu);

        $this->page_template = 'baseadminpage.tpl';

        $this->render();
    }

    /**
     * @throws \Exception
     */
    public function adminBasePage(): void
    {
        $this->setAdminPrefs();
        $this->smarty->assign([
            'meta_title' => 'Admin Home',
            'meta_description' => 'Admin home page',
        ]);
        $this->adminrender();
    }
}
