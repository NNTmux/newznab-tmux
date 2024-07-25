<?php

namespace App\Http\Controllers;

use App\Events\UserLoggedIn;
use App\Models\Category;
use App\Models\Forumpost;
use App\Models\Settings;
use App\Models\User;
use Blacklight\Contents;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BasePageController extends Controller
{
    public Settings $settings;

    public string $title = '';

    public string $content = '';

    public string $meta_keywords = '';

    public string $meta_title = '';

    public string $meta_description = '';

    /**
     * Current page the user is browsing. ie browse.
     */
    public string $page = '';

    public string $page_template = '';

    public User $userdata;

    /**
     * User's theme.
     */
    protected string $theme = 'Gentele';

    /**
     * @var \Illuminate\Foundation\Application|mixed
     */
    public mixed $smarty;

    /**
     * BasePageController constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->middleware(['auth', 'web', '2fa'])->except('api', 'contact', 'showContactForm', 'callback', 'getNzb', 'terms', 'capabilities', 'movie', 'apiSearch', 'tv', 'details', 'failed', 'showRssDesc', 'fullFeedRss', 'categoryFeedRss', 'cartRss', 'myMoviesRss', 'myShowsRss', 'release');
        // Buffer settings/DB connection.
        $this->settings = new Settings();
        $this->smarty = app('smarty.view');

        foreach (Arr::get(config('ytake-laravel-smarty'), 'plugins_paths', []) as $plugins) {
            $this->smarty->addPluginsDir($plugins);
        }

        $this->smarty->assign('serverroot', url('/'));
    }

    public function paginate($query, $totalCount, $items, $page, $path, $reqQuery): LengthAwarePaginator
    {
        return new LengthAwarePaginator($query, $totalCount, $items, $page, ['path' => $path, 'query' => $reqQuery]);
    }

    /**
     * @throws \Exception
     */
    protected function setPreferences(): void
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
                'theme' => $this->theme,
                'site' => $this->settings,
            ]
        );
    }

    public function isPostBack(Request $request): bool
    {
        return $request->isMethod('POST');
    }

    /**
     * Show 404 page.
     *
     * @param  null  $message
     */
    public function show404($message = null): View
    {
        if ($message !== null) {
            return view('errors.404')->with('Message', $message);
        }

        return view('errors.404');
    }

    public function render(): void
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
    public function setAdminPrefs(): void
    {
        // Tell Smarty which directories to use for templates
        $this->smarty->setTemplateDir(
            [
                'admin' => config('ytake-laravel-smarty.template_path').'/admin',
                'shared' => config('ytake-laravel-smarty.template_path').'/shared',
                'default' => config('ytake-laravel-smarty.template_path').'/admin',
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
