<?php

// YOU SHOULD NOT EDIT ANYTHING IN THIS FILE, COPY .../ntmux/config/settings.example.php TO .../nntmux/config/settings.php AND EDIT THAT FILE!

define('NN_MINIMUM_PHP_VERSION', '7.1.0');
define('NN_MINIMUM_MYSQL_VERSION', '5.6');
define('NN_MINIMUM_MARIA_VERSION', '10.1');

define('DS', DIRECTORY_SEPARATOR);

// These are file path constants
define('NN_ROOT', realpath(dirname(__DIR__)).DS);

// Used to refer to the main lib class files.
define('NN_LIB', NN_ROOT.'nntmux'.DS);
define('NN_CORE', NN_LIB);

define('NN_CONFIGS', NN_CORE.'config'.DS);

// Used to refer to the third party library files.
define('NN_LIBS', NN_ROOT.'libs'.DS);

// Used to refer to the /misc class files.
define('NN_MISC', NN_ROOT.'misc'.DS);

// /misc/update/
define('NN_UPDATE', NN_MISC.'update'.DS);

// /misc/update/multiprocessing
define('NN_MULTI', NN_UPDATE.'multiprocessing'.DS);

// /misc/update/tmux/
define('NN_TMUX', NN_UPDATE.'tmux'.DS);

// /misc/update/multiprocessing/
define('NN_MULTIPROCESSING', NN_UPDATE.'multiprocessing'.DS);

// Refers to the web root for the Smarty lib
define('NN_WWW', NN_ROOT.'public'.DS);

// Used to refer to the resources folder
define('NN_RES', NN_ROOT.'resources'.DS);

// Used to refer to the covers folder
define('NN_COVERS', NN_RES.'covers'.DS);

// Smarty's cache.
define('NN_SMARTY_CACHE', NN_RES.'smarty'.DS.'cache/');

// Smarty's configuration files.
define('NN_SMARTY_CONFIGS', NN_RES.'smarty'.DS.'configs/');

// Smarty's compiled template cache.
define('NN_SMARTY_TEMPLATES', NN_RES.'smarty'.DS.'templates_c/');

// Used to refer to the tmp folder
define('NN_TMP', NN_RES.'tmp'.DS);

// Full path is fs to the themes folder
define('NN_THEMES', NN_WWW.'themes'.DS);

// Shared theme items (pictures, scripts).
define('NN_THEMES_SHARED', NN_THEMES.'shared'.DS);

// Path where log files are stored.
define('NN_LOGS', NN_RES.'logs'.DS);

define('NN_VERSIONS', NN_ROOT.'build'.DS.'nntmux.xml');
