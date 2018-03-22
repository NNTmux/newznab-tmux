<?php

namespace Blacklight\http;

require_once NN_LIB.'utility/SmartyUtils.php';

use App\Models\User;
use Blacklight\db\DB;
use Blacklight\SABnzbd;
use App\Models\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Ytake\LaravelSmarty\Smarty;
use App\Models\RoleExcludedCategory;

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
    public $show_desktop_mode = false;

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
        // Buffer settings/DB connection.
        $this->settings = new Settings();
        $this->pdo = new DB();
        $this->smarty = new Smarty();

        $this->smarty->setCompileDir(config('ytake-laravel-smarty.compile_path'));
        $this->smarty->setConfigDir(array_get(config('ytake-laravel-smarty'), 'config_paths'));
        $this->smarty->setCacheDir(config('ytake-laravel-smarty.cache_path'));
        foreach (array_get(config('ytake-laravel-smarty'), 'plugins_paths', []) as $plugins) {
            $this->smarty->addPluginsDir($plugins);
        }
        $this->smarty->error_reporting = E_ALL - E_NOTICE;

        $this->https = request()->secure();

        if (request()->server('SERVER_NAME')) {
            $this->serverurl = (
                ($this->https === true ? 'https://' : 'http://').request()->server('SERVER_NAME').
                (((int) request()->server('SERVER_PORT') !== 80 && (int) request()->server('SERVER_PORT') !== 443) ? ':'.request()->server('SERVER_PORT') : '').
                WWW_TOP.'/'
            );
            $this->smarty->assign('serverroot', $this->serverurl);
        }

        $this->page = request()->input('page') ?? 'content';

        if (Auth::check()) {
            $this->setUserPreferences();
        } else {
            $this->theme = $this->getSettingValue('site.main.style');

            $this->smarty->assign('isadmin', 'false');
            $this->smarty->assign('ismod', 'false');
            $this->smarty->assign('loggedin', 'false');
        }
        if ($this->theme === 'None') {
            $this->theme = Settings::settingValue('site.main.style');
        }

        $this->smarty->assign('theme', $this->theme);
        $this->smarty->assign('site', $this->settings);
        $this->smarty->assign('page', $this);
    }

    /**
     * Done in html here to reduce any smarty processing burden if a large flood is underway.
     *
     * @param int $seconds
     */
    public function showFloodWarning($seconds = 5): void
    {
        request()->header('Retry-After: '.$seconds);
        $this->show503();
    }

    /**
     * Inject content into the html head.
     *
     * @param $headcontent
     */
    public function addToHead($headcontent): void
    {
        $this->head = $this->head."\n".$headcontent;
    }

    /**
     * Inject js/attributes into the html body tag.
     *
     * @param $attr
     */
    public function addToBody($attr): void
    {
        $this->body = $this->body.' '.$attr;
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
        request()->header('HTTP/1.1 404 Not Found');
        die(view('errors.404'));
    }

    /**
     * Show 403 page.
     *
     */
    public function show403(): void
    {
        die(view('errors.403'));
    }

    /**
     * Show 503 page.
     */
    public function show503(): void
    {
        request()->header('HTTP/1.1 503 Service Temporarily Unavailable');
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
        request()->header('HTTP/1.1 503 Service Temporarily Unavailable');
        die(view('errors.maintenance'));
    }

    /**
     * Show Security token mismatch page.
     */
    public function showTokenError(): void
    {
        request()->header('HTTP/1.1 419 Token Mismatch Error');
        die(view('errors.tokenError'));
    }

    /**
     * @param string $retry
     */
    public function show429($retry = ''): void
    {
        request()->header('HTTP/1.1 429 Too Many Requests');
        if ($retry !== '') {
            request()->header('Retry-After: '.$retry);
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
        $this->smarty->display($this->page_template);
    }

    /**
     * @throws \Exception
     */
    protected function setUserPreferences(): void
    {
        $this->userdata = User::find(Auth::id());
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
        if ((strtotime($this->userdata['now']) - 900) >
            strtotime($this->userdata['lastlogin'])
        ) {
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
    }

    /**
     * Allows to fetch a value from the settings table.
     *
     * This method is deprecated, as the column it uses to select the data is due to be removed
     * from the table *soon*.
     *
     * @param $setting
     *
     * @return array|bool|mixed|null|string
     * @throws \Exception
     */
    public function getSetting($setting)
    {
        if (strpos($setting, '.') === false) {
            trigger_error(
                'You should update your template to use the newer method "$page->getSettingValue()"" of fetching values from the "settings" table! This method *will* be removed in a future version.',
                E_USER_WARNING
            );
        } else {
            return $this->getSettingValue($setting);
        }

        return $this->settings->$setting;
    }

    /**
     * @param $setting
     *
     * @return null|string
     * @throws \Exception
     */
    public function getSettingValue($setting): ?string
    {
        return Settings::settingValue($setting);
    }
}
