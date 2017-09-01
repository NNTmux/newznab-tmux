<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

$dotenv = new Dotenv(dirname(__DIR__, 1));
$dotenv->load();

$capsule = new Capsule;

$capsule->addConnection([
	'driver' => env('DB_SYSTEM'),
	'host' => env('DB_HOST', 'localhost'),
	'port' => env('DB_PORT', '3306'),
	'database' => env('DB_NAME', 'nntmux'),
	'username' => env('DB_USER', 'root'),
	'password' => env('DB_PASSWORD', ''),
	'unix_socket' => env('DB_SOCKET', ''),
	'charset' => 'utf8',
	'collation' => 'utf8_unicode_ci',
	'strict' => false,
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();
