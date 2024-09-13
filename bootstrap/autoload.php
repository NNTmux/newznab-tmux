<?php

use Dotenv\Dotenv;
use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Contracts\Console\Kernel;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
require __DIR__.DIRECTORY_SEPARATOR.'app.php';
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'app/Extensions/util/PhpYenc.php';

$repository = RepositoryBuilder::createWithDefaultAdapters()->make();

$dotenv = Dotenv::create($repository, dirname(__DIR__, 1), null)->load();

/** @var Kernel $kernel */
$kernel = app(Kernel::class);
$kernel->bootstrap();
