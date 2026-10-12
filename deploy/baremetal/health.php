<?php

declare(strict_types=1);

use App\Services\Releases\Verification\Prerequisites;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

$root = $argv[1] ?? '/srv/nntmux/current';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    (new Prerequisites)->check(
        (bool) config('release_verification.required', true),
        (string) config('release_verification.par2_binary', 'par2'),
    );
    DB::select('SELECT 1');
    Redis::connection()->ping();
    if (! app('search')->isAvailable()) {
        throw new RuntimeException('Search is unavailable.');
    }
    echo "Release verification prerequisites, database, Redis, and selected search engine are ready.\n";
} catch (Throwable) {
    fwrite(STDERR, "Deployment dependency verification failed.\n");
    exit(1);
}
