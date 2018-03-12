<?php

// YOU SHOULD NOT EDIT ANYTHING IN THIS FILE!

define('NN_MINIMUM_PHP_VERSION', '7.2.0');
define('NN_MINIMUM_MYSQL_VERSION', '5.6');
define('NN_MINIMUM_MARIA_VERSION', '10.1');

define('DS', DIRECTORY_SEPARATOR);

// These are file path constants
define('NN_ROOT', base_path().'/');

// Used to refer to the main lib class files.
define('NN_LIB', NN_ROOT.'Blacklight/');

// Used to refer to the /misc class files.
define('NN_MISC', NN_ROOT.'misc/');

// /misc/update/
define('NN_UPDATE', NN_MISC.'update/');

// /misc/update/multiprocessing
define('NN_MULTI', NN_UPDATE.'multiprocessing/');

// /misc/update/tmux/
define('NN_TMUX', NN_UPDATE.'tmux/');

// /misc/update/multiprocessing/
define('NN_MULTIPROCESSING', NN_UPDATE.'multiprocessing/');

// Refers to the web root for the Smarty lib
define('NN_WWW', public_path().'/');

// Used to refer to the resources folder
define('NN_RES', resource_path().'/');

// Used to refer to the covers folder
define('NN_COVERS', NN_RES.'covers/');

// Used to refer to the tmp folder
define('NN_TMP', NN_RES.'tmp/');

// Path where log files are stored.
define('NN_LOGS', storage_path().'/logs/');

define('NN_VERSIONS', NN_ROOT.'build/nntmux.xml');
