<?php

require_once NN_LIB.'utility'.DS.'SmartyUtils.php';

use App\Models\User;
use Blacklight\db\DB;
use Blacklight\SABnzbd;
use App\Models\Settings;
use Illuminate\Support\Carbon;
use App\Models\RoleExcludedCategory;

class BasePage
{
    /**
     * @var \App\Models\Settings|null
     */
    public $settings = null;

    /**
     * @var null|\Smarty
     */
    public $smarty = null;

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
        if (session_id() === '') {
            session_start();

            $lifetime = Carbon::now()->addMinutes(config('session.lifetime'))->timestamp;
            $domain = config('session.domain');
            $http_only = config('session.http_only');
            $secure = request()->secure();

            if (empty($_SESSION['_token'])) {
                $_SESSION['_token'] = sodium_bin2hex(random_bytes(32));
            }
            setcookie('XSRF-TOKEN', $_SESSION['_token'], $lifetime, '/', $domain, $secure, false);
            setcookie(config('session.cookie'), $_SESSION['_token'], $lifetime, '/', $domain, $secure, $http_only);
        }

        if (env('FLOOD_CHECK', false)) {
            $this->floodCheck();
        }

        // Buffer settings/DB connection.
        $this->settings = new Settings();
        $this->pdo = new DB();
        $this->smarty = new Smarty();

        $this->smarty->setCompileDir(NN_SMARTY_TEMPLATES);
        $this->smarty->setConfigDir(NN_SMARTY_CONFIGS);
        $this->smarty->setCacheDir(NN_SMARTY_CACHE);
        $this->smarty->setPluginsDir(
            [
                NN_WWW.'plugins/',
                SMARTY_DIR.'plugins/',
            ]
        );
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

        $this->smarty->assign('csrf_token', $_SESSION['_token']);

        $this->page = request()->input('page') ?? 'content';

        if (User::isLoggedIn()) {
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
     * Check if the user is flooding.
     */
    public function floodCheck(): void
    {
        $waitTime = (env('FLOOD_WAIT_TIME', 5) < 1 ? 5 : env('FLOOD_WAIT_TIME', 5));
        // Check if this is not from CLI.
        if (empty($argc)) {
            // If flood wait set, the user must wait x seconds until they can access a page.
            if (isset($_SESSION['flood_wait_until']) && $_SESSION['flood_wait_until'] > microtime(true)) {
                $this->showFloodWarning($waitTime);
            } else {
                // If user not an admin, they are allowed three requests in FLOOD_THREE_REQUESTS_WITHIN_X_SECONDS seconds.
                if (! isset($_SESSION['flood_check_hits'])) {
                    $_SESSION['flood_check_hits'] = 1;
                    $_SESSION['flood_check_time'] = microtime(true);
                } else {
                    if ($_SESSION['flood_check_hits'] >= (env('FLOOD_MAX_REQUESTS_PER_SECOND', 5) < 1 ? 5 : env('FLOOD_MAX_REQUESTS_PER_SECOND', 5))) {
                        if ($_SESSION['flood_check_time'] + 1 > microtime(true)) {
                            $_SESSION['flood_wait_until'] = microtime(true) + $waitTime;
                            unset($_SESSION['flood_check_hits']);
                            $this->showFloodWarning($waitTime);
                        } else {
                            $_SESSION['flood_check_hits'] = 1;
                            $_SESSION['flood_check_time'] = microtime(true);
                        }
                    } else {
                        $_SESSION['flood_check_hits']++;
                    }
                }
            }
        }
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
        header('HTTP/1.1 404 Not Found');
        die(view('errors.404'));
    }

    /**
     * Show 403 page.
     *
     * @param bool $from_admin
     */
    public function show403($from_admin = false): void
    {
        header(
            'Location: '.
            ($from_admin ? str_replace('/admin', '', WWW_TOP) : WWW_TOP).
            '/login?redirect='.
            urlencode(request()->getRequestUri())
        );
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
        $this->smarty->display($this->page_template);
    }

    /**
     * @throws \Exception
     */
    protected function setUserPreferences(): void
    {
        $this->userdata = User::find(User::currentUserId());
        $this->userdata['categoryexclusions'] = User::getCategoryExclusion(User::currentUserId());
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
