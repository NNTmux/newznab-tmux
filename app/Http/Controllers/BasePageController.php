<?php

namespace App\Http\Controllers;

require_once NN_LIB.'utility/SmartyUtils.php';

use App\Models\Menu;
use App\Models\User;
use Blacklight\db\DB;
use Blacklight\SABnzbd;
use App\Models\Category;
use App\Models\Settings;
use Blacklight\Contents;
use App\Models\Forumpost;
use Illuminate\Http\Request;
use App\Models\RoleExcludedCategory;
use Illuminate\Support\Facades\Auth;

class BasePageController extends Controller
{
    /**
     * @var \App\Models\Settings
     */
    public $settings;

    public $title = '';

    public $content = '';

    public $meta_keywords = '';

    public $meta_title = '';

    public $meta_description = '';

    /**
     * Current page the user is browsing. ie browse.
     *
     * @var string
     */
    public $page = '';

    public $page_template = '';

    /**
     * User settings from the MySQL DB.
     *
     * @var array|bool
     */
    public $userdata = [];

    /**
     * URL of the server. ie http://localhost/.
     *
     * @var string
     */
    public $serverurl = '';

    /**
     * Public access to Captcha object for error checking.
     *
     * @var \Blacklight\Captcha
     */
    public $captcha;

    /**
     * User's theme.
     *
     * @var string
     */
    protected $theme = 'Gentele';

    /**
     * @var string
     */
    public $token;

    /**
     * @var \Blacklight\db\DB
     */
    public $pdo;

    /**
     * @var \Illuminate\Foundation\Application|mixed
     */
    public $smarty;

    /**
     * BasePageController constructor.
     *
     * @param \Illuminate\Http\Request         $request
     *
     * @throws \Exception
     */
    public function __construct(Request $request)
    {
        $this->middleware('auth')->except('api', 'rss', 'contact', 'showContactForm', 'callback');
        // Buffer settings/DB connection.
        $this->settings = new Settings();
        $this->pdo = new DB();
        $this->smarty = app('smarty.view');

        foreach (array_get(config('ytake-laravel-smarty'), 'plugins_paths', []) as $plugins) {
            $this->smarty->addPluginsDir($plugins);
        }
        $this->smarty->error_reporting = E_ALL & ~E_NOTICE;

        $this->smarty->assign('serverroot', url('/'));
    }

    /**
     * @throws \Exception
     */
    protected function setPrefs()
    {
        if (Auth::check()) {
            $this->userdata = Auth::user();
            $this->setUserPreferences();
        } else {
            $this->theme = Settings::settingValue('site.main.style');

            $this->smarty->assign('isadmin', 'false');
            $this->smarty->assign('ismod', 'false');
            $this->smarty->assign('loggedin', 'false');
        }

        if ($this->theme === 'None') {
            $this->theme = Settings::settingValue('site.main.style');
        }

        $this->smarty->assign('theme', $this->theme);
        $this->smarty->assign('site', $this->settings);
    }

    /**
     * Done in html here to reduce any smarty processing burden if a large flood is underway.
     *
     * @param int $seconds
     */
    public function showFloodWarning($seconds = 5): void
    {
        header('Retry-After: '.$seconds);
        $this->show503();
    }

    /**
     * @return bool
     */
    public function isPostBack()
    {
        return \request()->isMethod('POST');
    }

    /**
     * Show 404 page.
     *
     * @param $message
     *
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
     * Show 503 page.
     */
    public function show503()
    {
        return view('errors.503')->with('Error', 'Service temporarily unavailable');
    }

    /**
     * Show 503 page.
     */
    public function showBadBoy(): void
    {
        die(view('errors.badboy'));
    }

    /**
     * Show maintenance page.
     */
    public function showMaintenance()
    {
        return view('errors.maintenance')->with('Message', 'Service Temporarily Unavailable');
    }

    /**
     * Show Security token mismatch page.
     */
    public function showTokenError()
    {
        return view('errors.tokenError')->with('Error', 'Token mismatch');
    }

    /**
     * @param string $retry
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
        $this->userdata['categoryexclusions'] = User::getCategoryExclusion(Auth::id());
        $this->userdata['rolecategoryexclusions'] = RoleExcludedCategory::getRoleCategoryExclusion($this->userdata['user_roles_id']);

        // Change the theme to user's selected theme if they selected one, else use the admin one.
        if ((int) Settings::settingValue('site.main.userselstyle') === 1) {
            $this->theme = $this->userdata['style'] ?? 'None';
            if ($this->theme === 'None') {
                $this->theme = Settings::settingValue('site.main.style');
            }
        } else {
            $this->theme = Settings::settingValue('site.main.style');
        }

        // Update last login every 15 mins.
        if ((strtotime($this->userdata['now']) - 900) > strtotime($this->userdata['lastlogin'])) {
            User::updateSiteAccessed($this->userdata['id']);
        }

        $this->smarty->assign('userdata', $this->userdata);
        $this->smarty->assign('loggedin', 'true');

        if ($this->userdata['nzbvortex_api_key'] !== '' && $this->userdata['nzbvortex_server_url'] !== '') {
            $this->smarty->assign('weHasVortex', true);
        } else {
            $this->smarty->assign('weHasVortex', false);
        }

        $sab = new SABnzbd($this);
        $this->smarty->assign('sabintegrated', $sab->integratedBool);
        if ($sab->integratedBool !== false && $sab->url !== '' && $sab->apikey !== '') {
            $this->smarty->assign('sabapikeytype', $sab->apikeytype);
        }
        switch ((int) $this->userdata['user_roles_id']) {
            case User::ROLE_ADMIN:
                $this->smarty->assign('isadmin', 'true');
                break;
            case User::ROLE_MODERATOR:
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
            $role = $this->userdata['user_roles_id'];
        }

        $content = new Contents();
        $this->smarty->assign('menulist', Menu::getMenu($role, $this->serverurl));
        $this->smarty->assign('usefulcontentlist', $content->getForMenuByTypeAndRole(Contents::TYPEUSEFUL, $role));
        $this->smarty->assign('articlecontentlist', $content->getForMenuByTypeAndRole(Contents::TYPEARTICLE, $role));
        if ($this->userdata !== null) {
            $this->smarty->assign('recentforumpostslist', Forumpost::getPosts(Settings::settingValue('..showrecentforumposts')));
        }

        if (! empty($this->userdata)) {
            $parentcatlist = Category::getForMenu($this->userdata['categoryexclusions'], $this->userdata['rolecategoryexclusions']);
        } else {
            $parentcatlist = Category::getForMenu();
        }

        $this->smarty->assign('parentcatlist', $parentcatlist);
        $this->smarty->assign('catClass', Category::class);

        if (\request()->has('t')) {
            $this->smarty->assign('header_menu_cat', \request()->input('t'));
        } else {
            $this->smarty->assign('header_menu_cat', '');
        }
        $header_menu = $this->smarty->fetch('headermenu.tpl');
        $this->smarty->assign('header_menu', $header_menu);
    }

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
        $this->smarty->assign('page', $this);
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
        $this->smarty->assign('page', $this);

        $admin_menu = $this->smarty->fetch('adminmenu.tpl');
        $this->smarty->assign('admin_menu', $admin_menu);

        $this->page_template = 'baseadminpage.tpl';

        $this->render();
    }

    /**
     * @throws \Exception
     */
    public function basePage()
    {
        $this->setPrefs();
        $this->pagerender();
    }

    /**
     * @throws \Exception
     */
    public function adminBasePage()
    {
        $this->setAdminPrefs();
        $this->adminrender();
    }
}
