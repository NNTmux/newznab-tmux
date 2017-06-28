<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR . 'autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

$dotenv = new Dotenv(dirname(__DIR__, 1));
$dotenv->load();

$capsule = new Capsule;
// Same as database configuration file of Laravel.
$capsule->addConnection([
	'driver' => env('DB_SYSTEM'),
	'host' => env('DB_HOST', '127.0.0.1'),
	'port' => env('DB_PORT', '3306'),
	'database' => env('DB_NAME', 'nntmux'),
	'username' => env('DB_USER', 'root'),
	'password' => env('DB_PASSWORD', ''),
	'unix_socket' => env('DB_SOCKET', ''),
	'charset' => 'utf8',
	'collation' => 'utf8_unicode_ci',
	'strict' => false
], 'default');
$capsule->bootEloquent();
$capsule->setAsGlobal();
