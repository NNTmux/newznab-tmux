<?php
// YOU SHOULD NOT EDIT ANYTHING IN THIS FILE, COPY settings.php.example TO settings.php AND EDIT THAT FILE!

define('NN_MINIMUM_PHP_VERSION', '5.4.0');
define('NN_MINIMUM_MYSQL_VERSION', '5.5');

define('DS', DIRECTORY_SEPARATOR);

// These are file path constants
define('NN_ROOT', realpath((dirname(__FILE__))) . DS);

// Used to refer to the main lib class files.
define('NN_LIB', NN_ROOT . 'lib' . DS);
define('NN_CORE', NN_LIB);

// Refers to the web root for the Smarty lib
define('NN_WWW', NN_ROOT);

// Refers to the covers folder
define('NN_COVERS', NN_WWW . 'covers' .DS);

// Used to refer to the resources folder
define('NN_RES', NN_ROOT . 'resources' . DS);

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
define('SMARTY_DIR', NN_LIB . 'smarty' . DS);

//
// used to refer to the /www/lib class files
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

if (is_file(__DIR__ . DS . 'settings.php')) {
	require_once(__DIR__ . DS . 'settings.php');
	// Remove this in the future, here for those not updating settings.php
	if (!defined('NN_USE_SQL_TRANSACTIONS')) {
		define('NN_USE_SQL_TRANSACTIONS', true);
	}
} else {
	define('ITEMS_PER_PAGE', '50');
	define('ITEMS_PER_COVER_PAGE', '20');
	define('ITEMS_PER_PAGE_SMALL', '15');
	define('NN_ECHOCLI', true);
	define('NN_DEBUG', false);
	define('NN_LOGGING', false);
	define('NN_LOGINFO', false);
	define('NN_LOGNOTICE', false);
	define('NN_LOGWARNING', false);
	define('NN_LOGERROR', false);
	define('NN_LOGFATAL', false);
	define('NN_LOGQUERIES', false);
	define('NN_QUERY_STRIP_WHITESPACE', false);
	define('NN_RENAME_PAR2', true);
	define('NN_RENAME_MUSIC_MEDIAINFO', true);
	define('NN_CACHE_EXPIRY_SHORT', 300);
	define('NN_CACHE_EXPIRY_MEDIUM', 600);
	define('NN_CACHE_EXPIRY_LONG', 900);
	define('NN_FLOOD_CHECK', false);
	define('NN_FLOOD_WAIT_TIME', 5);
	define('NN_FLOOD_MAX_REQUESTS_PER_SECOND', 5);
	define('NN_USE_SQL_TRANSACTIONS', true);
}
require_once(WWW_DIR . "/lib/util.php");
define('HAS_WHICH', Utility::hasWhich() ? true : false);

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
