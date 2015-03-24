<?php
require_once(SMARTY_DIR . 'Smarty.class.php');
require_once(NN_LIB . 'framework' . DS . 'db.php');
require_once(WWW_DIR . "/lib/users.php");
require_once(WWW_DIR . "/lib/site.php");
require_once(WWW_DIR . "/lib/sabnzbd.php");
require_once (NN_LIB . 'SmartyUtils.php');

class BasePage
{
	/**
	 * @var DB
	 */
	public $settings = null;

	/**
	 * @var Users
	 */
	public $users = null;

	public $title = '';
	public $content = '';
	public $head = '';
	public $body = '';
	public $meta_keywords = '';
	public $meta_title = '';
	public $meta_description = '';
	public $page = '';
	public $page_template = '';
	public $smarty = '';
	public $userdata = array();
	public $serverurl = '';
	public $site = '';
	public $secure_connection = false;


	function BasePage()
	{
		@session_start();

		if (NN_FLOOD_CHECK) {
			$this->floodCheck();
		}

		if ((function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) || ini_get('magic_quotes_sybase'))
		{
			foreach ($_GET as $k => $v) $_GET[$k] = (is_array($v)) ? array_map("stripslashes", $v) : stripslashes($v);
			foreach ($_POST as $k => $v) $_POST[$k] = (is_array($v)) ? array_map("stripslashes", $v) : stripslashes($v);
			foreach ($_REQUEST as $k => $v) $_REQUEST[$k] = (is_array($v)) ? array_map("stripslashes", $v) : stripslashes($v);
			foreach ($_COOKIE as $k => $v) $_COOKIE[$k] = (is_array($v)) ? array_map("stripslashes", $v) : stripslashes($v);
		}

		// set site variable
		$s = new Sites();
		$this->site = $s->get();

		// Buffer settings/DB connection.
		$this->settings = new DB();
		$this->smarty = new Smarty();

		if ($this->site->style != "default")
			$this->smarty->addTemplateDir(WWW_DIR . 'templates/' . $this->site->style . '/views/frontend', 'style_frontend');
		$this->smarty->addTemplateDir(WWW_DIR . 'templates/default/views/frontend', 'frontend');
		$this->smarty->setCompileDir(SMARTY_DIR . 'templates_c' . DIRECTORY_SEPARATOR);
		$this->smarty->setConfigDir(SMARTY_DIR . 'configs' . DIRECTORY_SEPARATOR);
		$this->smarty->setCacheDir(SMARTY_DIR . 'cache' . DIRECTORY_SEPARATOR);
		$this->smarty->error_reporting = (E_ALL - E_NOTICE);
		$this->secure_connection = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443));

		if (file_exists(WWW_DIR . 'templates/' . $this->site->style . '/theme.php'))
			require_once(WWW_DIR . 'templates/' . $this->site->style . '/theme.php');
		$this->smarty->assign('themevars', (isset($themevars) ? $themevars : null));

		$servername = null;
		if (defined('EXTERNAL_PROXY_IP') && defined('EXTERNAL_HOST_NAME') && isset($_SERVER["REMOTE_ADDR"]) && $_SERVER["REMOTE_ADDR"] == EXTERNAL_PROXY_IP)
			$servername = EXTERNAL_HOST_NAME;
		elseif (isset($_SERVER["SERVER_NAME"]))
			$servername = $_SERVER["SERVER_NAME"];

		if ($servername != "")
		{
			$this->serverurl = ($this->secure_connection ? "https://" : "http://") . $servername . (($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") ? ":" . $_SERVER["SERVER_PORT"] : "") . WWW_TOP . '/';
			$this->smarty->assign('serverroot', $this->serverurl);
		}

		$this->page = (isset($_GET['page'])) ? $_GET['page'] : 'content';

		$users = new Users();
		if ($users->isLoggedIn()) {
			$this->userdata = $users->getById($users->currentUserId());

			//
			// user can still be logged in but have been disabled by admin, so if they are, log them off
			//
			if ($this->userdata["role"] == Users::ROLE_DISABLED) {
				$users->logout();
				$this->show403();
			}

			$this->userdata["categoryexclusions"] = $users->getCategoryExclusion($users->currentUserId());

			//update lastlogin every 15 mins
			if (strtotime($this->userdata['now']) - 900 > strtotime($this->userdata['lastlogin']))
				$users->updateSiteAccessed($this->userdata['id']);

			$this->smarty->assign('userdata', $this->userdata);
			$this->smarty->assign('loggedin', "true");

			if (!empty($this->userdata['nzbvortex_api_key']) && (!empty($this->userdata['nzbvortex_server_url'])))
				$this->smarty->assign('weHasVortex', true);

			$sab = new SABnzbd($this);
			if ($sab->integrated !== false && $sab->url != '' && $sab->apikey != '')
			{
				$this->smarty->assign('sabintegrated', $sab->integrated);
				$this->smarty->assign('sabapikeytype', $sab->apikeytype);
			}
			if ($this->userdata["role"] == Users::ROLE_ADMIN)
				$this->smarty->assign('isadmin', "true");

			if ($this->userdata["hideads"] == "1")
			{
				$this->site->adheader = "";
				$this->site->adbrowse = "";
				$this->site->addetail = "";
			}

			$this->floodCheck($this->userdata["role"]);
		}
		else
		{
			$this->smarty->assign('isadmin', "false");
			$this->smarty->assign('loggedin', "false");
			$this->floodCheck();
		}

		$this->smarty->assign('site', $this->site);
		$this->smarty->assign('page', $this);
	}

	/**
	 * Check if the user is flooding.
	 */
	public function floodCheck()
	{
		$waitTime = (NN_FLOOD_WAIT_TIME < 1 ? 5 : NN_FLOOD_WAIT_TIME);
		// Check if this is not from CLI.
		if (empty($argc)) {
			// If flood wait set, the user must wait x seconds until they can access a page.
			if (isset($_SESSION['flood_wait_until']) && $_SESSION['flood_wait_until'] > microtime(true)) {
				$this->showFloodWarning($waitTime);
			} else {
				// If user not an admin, they are allowed three requests in FLOOD_THREE_REQUESTS_WITHIN_X_SECONDS seconds.
				if (!isset($_SESSION['flood_check_hits'])) {
					$_SESSION['flood_check_hits'] = 1;
					$_SESSION['flood_check_time'] = microtime(true);
				} else {
					if ($_SESSION['flood_check_hits'] >= (NN_FLOOD_MAX_REQUESTS_PER_SECOND < 1 ? 5 : NN_FLOOD_MAX_REQUESTS_PER_SECOND)) {
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
	 */
	public function showFloodWarning($seconds = 5)
	{
		header('Retry-After: ' . $seconds);
		$this->show503(
			sprintf(
				'Too many requests!</p><p>You must wait <b>%s seconds</b> before trying again.',
				$seconds
			)
		);
	}

	//
	// Inject content into the html head
	//
	public function addToHead($headcontent)
	{
		$this->head = $this->head . "\n" . $headcontent;
	}

	//
	// Inject js/attributes into the html body tag
	//
	public function addToBody($attr)
	{
		$this->body = $this->body . " " . $attr;
	}

	public function isPostBack()
	{
		return (strtoupper($_SERVER["REQUEST_METHOD"]) === "POST");
	}

	public function show404()
	{
		header("HTTP/1.1 404 Not Found");
		die();
	}

	public function show403($from_admin = false)
	{
		$redirect_path = ($from_admin) ? str_replace('/admin', '', WWW_TOP) : WWW_TOP;
		header("Location: $redirect_path/login?redirect=" . urlencode($_SERVER["REQUEST_URI"]));
		die();
	}

	public function show503($retry = '')
	{
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		header('Status: 503 Service Temporarily Unavailable');
		if ($retry != '')
			header('Retry-After: ' . $retry);

		echo "
			<html>
			<head>
				<title>Service Unavailable</title>
			</head>

			<body>
				<h1>Service Unavailable</h1>

				<p>Your maximum api or download limit has been reached for the day</p>

			</body>
			</html>";
		die();
	}

	public function show429($retry = '')
	{
		header('HTTP/1.1 429 Too Many Requests');
		if ($retry != '')
			header('Retry-After: ' . $retry);

		echo "
			<html>
			<head>
				<title>Too Many Requests</title>
			</head>

			<body>
				<h1>Too Many Requests</h1>

				<p>Wait ".(($retry != '') ? ceil($retry / 60) . ' minutes ' : '') . "or risk being temporarily banned.</p>

			</body>
			</html>";
		die();
	}

	public function render()
	{
		$this->smarty->display($this->page_template);
	}
}