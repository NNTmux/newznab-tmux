<?php

use Illuminate\Support\Facades\Facade;

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
        'RedisManager' => Illuminate\Support\Facades\Redis::class,
        'UserVerification' => Jrean\UserVerification\Facades\UserVerification::class,
        'Gravatar' => Creativeorange\Gravatar\Facades\Gravatar::class,
    ])->toArray(),

];
