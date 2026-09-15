<?php

declare(strict_types=1);

use App\Services\Search\Support\ManticoreClientFactory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    DB::select('SELECT 1');
    Redis::connection()->ping();
    ManticoreClientFactory::make(config('search.drivers.manticore', []))->nodes()->status();
    echo "Database, Redis, and Manticore are reachable.\n";
} catch (Throwable) {
    fwrite(STDERR, "Deployment dependency verification failed.\n");
    exit(1);
}
