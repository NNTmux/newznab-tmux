<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
require __DIR__.DIRECTORY_SEPARATOR.'app.php';
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'app/Extensions/util/PhpYenc.php';

use Dotenv\Dotenv;
use Dotenv\Repository\RepositoryBuilder;

$repository = RepositoryBuilder::createWithDefaultAdapters()
    ->make();

$dotenv = Dotenv::create($repository, dirname(__DIR__, 1), null)->load();

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
