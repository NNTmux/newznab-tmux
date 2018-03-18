<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
require __DIR__.DIRECTORY_SEPARATOR.'app.php';
require_once 'constants.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'yenc.php';

use Dotenv\Dotenv;
use Blacklight\utility\Utility;

$dotenv = new Dotenv(dirname(__DIR__, 1));
$dotenv->load();

if (! defined('HAS_WHICH')) {
    define('HAS_WHICH', Utility::hasWhich() ? true : false);
}

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);

