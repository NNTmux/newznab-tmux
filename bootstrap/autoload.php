<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
require_once 'constants.php';
require __DIR__.DIRECTORY_SEPARATOR.'app.php';

use Dotenv\Dotenv;
use Blacklight\utility\Utility;
use Blacklight\config\Configure;

$dotenv = new Dotenv(dirname(__DIR__, 1));
$dotenv->load();

define('NNTMUX_START', microtime(true));

define('NN_APP_PATH', dirname(__DIR__).DS.'app');

if (! defined('NN_ROOT')) {
    define('NN_ROOT', dirname(NN_APP_PATH, 2));
}

if (! defined('HAS_WHICH')) {
    define('HAS_WHICH', Utility::hasWhich() ? true : false);
}

new Configure('indexer');

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$response = $app->handle($request = Illuminate\Http\Request::capture());
require_once __DIR__.DIRECTORY_SEPARATOR.'yenc.php';
