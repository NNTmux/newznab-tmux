<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'nntmux' . DIRECTORY_SEPARATOR . 'constants.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'configuration' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'nntmux' . DIRECTORY_SEPARATOR . 'bootstrap.php';

define('NN_APP_PATH', dirname(__DIR__) . DS . 'app');

if (!defined('NN_ROOT')) {
	define('NN_ROOT', dirname(NN_APP_PATH, 2));
}

require_once NN_APP_PATH . DS . 'libraries' . DS . 'autoload.php';
