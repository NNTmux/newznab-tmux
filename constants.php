<?php
// YOU SHOULD NOT EDIT ANYTHING IN THIS FILE, COPY .../nzedb/config/settings.example.php TO .../nzedb/config/settings.php AND EDIT THAT FILE!

define('NN_MINIMUM_PHP_VERSION', '5.5.0');
define('NN_MINIMUM_MYSQL_VERSION', '5.5');

define('DS', DIRECTORY_SEPARATOR);

// These are file path constants
define('NN_ROOT', realpath(__DIR__) . DS);

// Used to refer to the main lib class files.
define('NN_LIB', NN_ROOT . 'newznab' . DS);
define('NN_CORE', NN_LIB);

define('NN_CONFIGS', NN_CORE . 'config' . DS);

// Used to refer to the third party library files.
define('NN_LIBS', NN_ROOT . 'libs' . DS);

// Used to refer to the /misc class files.
define('NN_MISC', NN_ROOT . 'misc' . DS);

// /misc/update/
define('NN_UPDATE', NN_MISC . 'update_scripts' . DS);

// /misc/update/nix/
define('NN_NIX', NN_UPDATE . 'nix_scripts' . DS);

// /misc/update/nix/multiprocessing/
define('NN_MULTIPROCESSING', NN_NIX . 'multiprocessing' . DS);

// Refers to the web root for the Smarty lib
define('NN_WWW', NN_ROOT . 'www' . DS);

// Used to refer to the resources folder
define('NN_RES', NN_ROOT . 'resources' . DS);

// Used to refer to the tmp folder
define('NN_TMP', NN_RES . 'tmp' . DS);

// Full path is fs to the themes folder
define('NN_THEMES', NN_WWW . 'themes' . DS);

// Shared theme items (pictures, scripts).
define('NN_THEMES_SHARED', NN_WWW . 'themes_shared' . DS);

// Path where log files are stored.
define('NN_LOGS', NN_RES . 'logs' . DS);

define('NN_VERSIONS', NN_LIB . 'build' . DS . 'newznab.xml');
