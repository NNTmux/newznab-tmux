<?php

namespace Blacklight\http;

require_once NN_LIB.'utility/SmartyUtils.php';

use App\Models\Menu;
use App\Models\User;
use Blacklight\db\DB;
use Blacklight\SABnzbd;
use App\Models\Category;
use App\Models\Settings;
use Blacklight\Contents;
use App\Models\Forumpost;
use Ytake\LaravelSmarty\Smarty;
use App\Models\RoleExcludedCategory;
use Illuminate\Support\Facades\Auth;

class BasePage
{
    /**
     * @var \App\Models\Settings|null
     */
    public $settings = null;

    /**
     * @var \Ytake\LaravelSmarty\Smarty
     */
    public $smarty;

    public $title = '';

    public $content = '';

    public $head = '';

    public $body = '';

    public $meta_keywords = '';

    public $meta_title = '';

    public $meta_description = '';

    public $secure_connection = false;

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
     * Whether to trim white space before rendering the page or not.
     *
     * @var bool
     */
    public $trimWhiteSpace = true;

    /**
     * Is the current session HTTPS?
     *
     * @var bool
     */
    public $https = false;

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
     * Set up session / smarty / user variables.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);

        $response = $kernel->handle($request = \Illuminate\Http\Request::capture());
        $response->send();
        $kernel->terminate($request, $response);

        // Buffer settings/DB connection.
        $this->settings = new Settings();
        $this->pdo = new DB();

        app('smarty.view')->setCompileDir(config('ytake-laravel-smarty.compile_path'));
        app('smarty.view')->setConfigDir(array_get(config('ytake-laravel-smarty'), 'config_paths'));
        app('smarty.view')->setCacheDir(config('ytake-laravel-smarty.cache_path'));
        foreach (array_get(config('ytake-laravel-smarty'), 'plugins_paths', []) as $plugins) {
            app('smarty.view')->addPluginsDir($plugins);
        }
        app('smarty.view')->error_reporting = E_ALL & ~E_NOTICE;

        app('smarty.view')->assign('serverroot', url('/'));

        $this->page = request()->input('page') ?? 'content';

        if (Auth::check()) {
            $this->userdata = User::find(Auth::id());
            $this->setUserPreferences();
        } else {
            $this->theme = $this->getSettingValue('site.main.style');

            app('smarty.view')->assign('isadmin', 'false');
            app('smarty.view')->assign('ismod', 'false');
            app('smarty.view')->assign('loggedin', 'false');
        }
        if ($this->theme === 'None') {
            $this->theme = Settings::settingValue('site.main.style');
        }

        app('smarty.view')->assign('theme', $this->theme);
        app('smarty.view')->assign('site', $this->settings);
        app('smarty.view')->assign('page', $this);
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
        return request()->isMethod('POST');
    }

    /**
     * Show 404 page.
     */
    public function show404(): void
    {
        header('HTTP/1.1 404 Not Found');
        die(view('errors.404'));
    }

    /**
     * Show 403 page.
     *
     *
     * @param bool $from_admin
     */
    public function show403($from_admin = false): void
    {
        header('Location: '.($from_admin ? str_replace('/admin', '', WWW_TOP) : WWW_TOP).'/login?redirect='.urlencode(request()->getRequestUri()));
        exit();
    }

    /**
     * Show 503 page.
     */
    public function show503(): void
    {
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        die(view('errors.503'));
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
    public function showMaintenance(): void
    {
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        die(view('errors.maintenance'));
    }

    /**
     * Show Security token mismatch page.
     */
    public function showTokenError(): void
    {
        header('HTTP/1.1 419 Token Mismatch Error');
        die(view('errors.tokenError'));
    }

    /**
     * @param string $retry
     */
    public function show429($retry = ''): void
    {
        header('HTTP/1.1 429 Too Many Requests');
        if ($retry !== '') {
            header('Retry-After: '.$retry);
        }

        echo '
			<html>
			<head>
				<title>Too Many Requests</title>
			</head>

			<body>
				<h1>Too Many Requests</h1>

				<p>Wait '.(($retry !== '') ? ceil($retry / 60).' minutes ' : '').'or risk being temporarily banned.</p>

			</body>
			</html>';
        die();
    }

    public function render()
    {
        app('smarty.view')->display($this->page_template);
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

        app('smarty.view')->assign('userdata', $this->userdata);
        app('smarty.view')->assign('loggedin', 'true');

        if ($this->userdata['nzbvortex_api_key'] !== '' && $this->userdata['nzbvortex_server_url'] !== '') {
            app('smarty.view')->assign('weHasVortex', true);
        } else {
            app('smarty.view')->assign('weHasVortex', false);
        }

        $sab = new SABnzbd($this);
        app('smarty.view')->assign('sabintegrated', $sab->integratedBool);
        if ($sab->integratedBool !== false && $sab->url !== '' && $sab->apikey !== '') {
            app('smarty.view')->assign('sabapikeytype', $sab->apikeytype);
        }
        switch ((int) $this->userdata['user_roles_id']) {
            case User::ROLE_ADMIN:
                app('smarty.view')->assign('isadmin', 'true');
                break;
            case User::ROLE_MODERATOR:
                app('smarty.view')->assign('ismod', 'true');
        }
    }

    /**
     * Setup user preferences.
     *
     *
     * @throws \Exception
     */
    public function setUserPrefs()
    {
        // Tell Smarty which directories to use for templates
        app('smarty.view')->setTemplateDir([
            'user' => config('ytake-laravel-smarty.template_path').DIRECTORY_SEPARATOR.$this->theme,
            'shared' => config('ytake-laravel-smarty.template_path').'/shared',
            'default' => config('ytake-laravel-smarty.template_path').'/Gentele',
        ]);

        $role = User::ROLE_USER;
        if (! empty($this->userdata)) {
            $role = $this->userdata['user_roles_id'];
        }

        $content = new Contents();
        app('smarty.view')->assign('menulist', Menu::getMenu($role, $this->serverurl));
        app('smarty.view')->assign('usefulcontentlist', $content->getForMenuByTypeAndRole(Contents::TYPEUSEFUL, $role));
        app('smarty.view')->assign('articlecontentlist', $content->getForMenuByTypeAndRole(Contents::TYPEARTICLE, $role));
        if ($this->userdata !== null) {
            app('smarty.view')->assign('recentforumpostslist', Forumpost::getPosts(Settings::settingValue('..showrecentforumposts')));
        }

        app('smarty.view')->assign('main_menu', app('smarty.view')->fetch('mainmenu.tpl'));
        app('smarty.view')->assign('useful_menu', app('smarty.view')->fetch('usefullinksmenu.tpl'));
        app('smarty.view')->assign('article_menu', app('smarty.view')->fetch('articlesmenu.tpl'));

        if (! empty($this->userdata)) {
            $parentcatlist = Category::getForMenu($this->userdata['categoryexclusions'], $this->userdata['rolecategoryexclusions']);
        } else {
            $parentcatlist = Category::getForMenu();
        }

        app('smarty.view')->assign('parentcatlist', $parentcatlist);
        app('smarty.view')->assign('catClass', Category::class);
        $searchStr = '';
        if ($this->page === 'search' && request()->has('id')) {
            $searchStr = request()->input('id');
        }
        app('smarty.view')->assign('header_menu_search', $searchStr);

        if (request()->has('t')) {
            app('smarty.view')->assign('header_menu_cat', request()->input('t'));
        } else {
            app('smarty.view')->assign('header_menu_cat', '');
        }
        $header_menu = app('smarty.view')->fetch('headermenu.tpl');
        app('smarty.view')->assign('header_menu', $header_menu);
    }

    public function setAdminPrefs()
    {
        // Tell Smarty which directories to use for templates
        app('smarty.view')->setTemplateDir(
            [
                'admin'    => config('ytake-laravel-smarty.template_path').'/admin',
                'shared'    => config('ytake-laravel-smarty.template_path').'/shared',
                'default'    => config('ytake-laravel-smarty.template_path').'/admin',
            ]
        );

        app('smarty.view')->assign('catClass', Category::class);
    }

    /**
     * Output the page.
     */
    public function pagerender(): void
    {
        app('smarty.view')->assign('page', $this);
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
        app('smarty.view')->assign('page', $this);

        $admin_menu = app('smarty.view')->fetch('adminmenu.tpl');
        app('smarty.view')->assign('admin_menu', $admin_menu);

        $this->page_template = 'baseadminpage.tpl';

        $this->render();
    }
}
