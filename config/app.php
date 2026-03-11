<?php

use App\Facades\Elasticsearch;
use App\Facades\Yenc;
use Creativeorange\Gravatar\Facades\Gravatar;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Redis;
use Jrean\UserVerification\Facades\UserVerification;

return [

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
        'Gravatar' => Gravatar::class,
        'RedisManager' => Redis::class,
        'UserVerification' => UserVerification::class,
        'Yenc' => Yenc::class,
    ])->toArray(),

];
