<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'nntmux'.DIRECTORY_SEPARATOR.'constants.php';
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'nntmux'.DIRECTORY_SEPARATOR.'bootstrap.php';
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'nntmux.php';
require __DIR__.DIRECTORY_SEPARATOR.'app.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'yenc.php';

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;

$dotenv = new Dotenv(dirname(__DIR__, 1));
$dotenv->load();

define('NNTMUX_START', microtime(true));

define('NN_APP_PATH', dirname(__DIR__).DS.'app');

if (! defined('NN_ROOT')) {
    define('NN_ROOT', dirname(NN_APP_PATH, 2));
}

$app->make(Kernel::class)->bootstrap();
