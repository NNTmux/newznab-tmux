<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

$root = $argv[1] ?? '/srv/nntmux/current';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    DB::select('SELECT 1');
    Redis::connection()->ping();
    if (! app('search')->isAvailable()) {
        throw new RuntimeException('Search is unavailable.');
    }
    echo "Database, Redis, and selected search engine are reachable.\n";
} catch (Throwable) {
    fwrite(STDERR, "Deployment dependency verification failed.\n");
    exit(1);
}
