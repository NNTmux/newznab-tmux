<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
require __DIR__.DIRECTORY_SEPARATOR.'app.php';
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'app/Extensions/util/PhpYenc.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::create(dirname(__DIR__, 1));
$dotenv->load();

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
