<?php

use App\Facades\Elasticsearch;
use App\Facades\Yenc;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Redis;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name, Environment, Debug Mode and URL
    |--------------------------------------------------------------------------
    |
    | These values are used throughout the framework, including by queued mail
    | notifications that need to generate absolute links without an HTTP request.
    |
    */

    'name' => env('APP_NAME', 'NNTmux'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    | IMPORTANT: This should always be UTC to ensure consistent date storage.
    | User-specific timezones are handled at the display layer.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    'aliases' => Facade::defaultAliases()->merge([
        'Elasticsearch' => Elasticsearch::class,
        'Google2FA' => PragmaRX\Google2FALaravel\Facade::class,
        'RedisManager' => Redis::class,
        'Yenc' => Yenc::class,
    ])->toArray(),

];
