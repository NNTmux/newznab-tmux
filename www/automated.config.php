<?php
// YOU SHOULD NOT EDIT ANYTHING IN THIS FILE, COPY settings.php.example TO settings.php AND EDIT THAT FILE!

define('NN_MINIMUM_PHP_VERSION', '5.5.0');
define('NN_MINIMUM_MYSQL_VERSION', '5.5');

define('DS', DIRECTORY_SEPARATOR);

// These are file path constants
define('NN_ROOT', realpath(dirname(dirname(__FILE__))) . DS);

// Used to refer to the main lib class files.
define('NN_LIB', NN_ROOT . 'newznab' . DS);
define('NN_CORE', NN_LIB);

// Used to refer to the third party library files.
define('NN_LIBS', NN_ROOT . 'libs' . DS);

// Refers to the web root for the Smarty lib
define('NN_WWW', NN_ROOT . 'www' . DS);

//Refers to the misc folder
define('NN_MISC', NN_ROOT . 'misc' . DS);

//Refers to update_scripts folder
define('NN_UPDATE', NN_MISC . 'update_scripts' . DS);

//Refers to nix_scripts folder
define('NN_NIX', NN_UPDATE . 'nix_scripts' . DS );

//Refers to multiprocessing folder
define('NN_MULTI', NN_NIX . 'multiprocessing' . DS);

//refers to tmux folder
define('NN_TMUX', NN_NIX . 'tmux' . DS);

// Used to refer to the resources folder
define('NN_RES', NN_ROOT . 'resources' . DS);

// Refers to the covers folder
define('NN_COVERS', NN_RES . 'covers' .DS);

// Used to refer to the tmp folder
define('NN_TMP', NN_RES . 'tmp' . DS);

// Full path is fs to the themes folder
define('NN_THEMES', NN_WWW . 'templates' . DS);

// Path where log files are stored.
define('NN_LOGS', NN_RES . 'logs' . DS);

if (function_exists('ini_set') && function_exists('ini_get')) {
	$ps = (strtolower(PHP_OS) == 'windows') ? ';' : ':';
	ini_set('include_path', NN_WWW . $ps . ini_get('include_path'));
}

// Path to smarty files. (not prefixed with NN as the name is needed in smarty files).
define('SMARTY_DIR', NN_LIBS . 'smarty' . DS);

//
// used to refer to the /www/ class files
//
define('WWW_DIR', NN_WWW);

// These are site constants
$www_top = str_replace("\\", "/", dirname($_SERVER['PHP_SELF']));
if (strlen($www_top) == 1) {
	$www_top = "";
}

// Used everywhere an href is output, includes the full path to the NN install.
define('WWW_TOP', $www_top);

define('NN_VERSIONS', NN_LIB . 'build' . DS . 'newznab.xml');

$settings_file = __DIR__ . DS . 'settings.php';
if (is_file($settings_file)) {
	require_once($settings_file);
	if (php_sapi_name() == 'cli') {
		$current_settings_file_version = 1; // Update this when updating settings.php.example
		if (!defined('NN_SETTINGS_FILE_VERSION') || NN_SETTINGS_FILE_VERSION != $current_settings_file_version) {
			echo ("\033[0;31mNotice: Your $settings_file file is either out of date or you have not updated" .
				" NN_SETTINGS_FILE_VERSION to $current_settings_file_version in that file.\033[0m" . PHP_EOL
			);
		}
		unset($current_settings_file_version);
	}
} else {
	define('ITEMS_PER_PAGE', '50');
	define('ITEMS_PER_COVER_PAGE', '20');
	define('NN_ECHOCLI', true);
	define('NN_DEBUG', false);
	define('NN_LOGGING', false);
	define('NN_LOGINFO', false);
	define('NN_LOGNOTICE', false);
	define('NN_LOGWARNING', false);
	define('NN_LOGERROR', false);
	define('NN_LOGFATAL', false);
	define('NN_LOGQUERIES', false);
	define('NN_LOGAUTOLOADER', false);
	define('NN_QUERY_STRIP_WHITESPACE', false);
	define('NN_RENAME_PAR2', true);
	define('NN_RENAME_MUSIC_MEDIAINFO', true);
	define('NN_CACHE_EXPIRY_SHORT', 300);
	define('NN_CACHE_EXPIRY_MEDIUM', 600);
	define('NN_CACHE_EXPIRY_LONG', 900);
	define('NN_PREINFO_OPEN', false);
	define('NN_FLOOD_CHECK', false);
	define('NN_FLOOD_WAIT_TIME', 5);
	define('NN_FLOOD_MAX_REQUESTS_PER_SECOND', 5);
	define('NN_USE_SQL_TRANSACTIONS', true);
	define('NN_RELEASE_SEARCH_TYPE', 0);
	define('NN_MAX_PAGER_RESULTS', '125000');
}
unset($settings_file);
require_once NN_CORE . 'autoloader.php';
require_once NN_LIBS . 'autoloader.php';
require_once SMARTY_DIR . 'autoloader.php';

define('HAS_WHICH', newznab\utility\Utility::hasWhich() ? true : false);

if (file_exists(__DIR__ . DS . 'config.php')) {
	require_once __DIR__ . DS . 'config.php';
}

// Check if they updated config.php for the openssl changes. Only check 1 to save speed.
if (!defined('NN_SSL_VERIFY_PEER')) {
	define('NN_SSL_CAFILE', '');
	define('NN_SSL_CAPATH', '');
	define('NN_SSL_VERIFY_PEER', '0');
	define('NN_SSL_VERIFY_HOST', '0');
	define('NN_SSL_ALLOW_SELF_SIGNED', '1');
}
