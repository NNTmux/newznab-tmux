<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Laravel Framework Service Providers...
         */

        /*
         * Package Service Providers...
         */
        Laravel\Tinker\TinkerServiceProvider::class,

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\HorizonServiceProvider::class,
        App\Providers\TelescopeServiceProvider::class,
        Jrean\UserVerification\UserVerificationServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\UserServiceProvider::class,
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
        'RedisManager' => Illuminate\Support\Facades\Redis::class,
        'UserVerification' => Jrean\UserVerification\Facades\UserVerification::class,
    ])->toArray(),

];
