<?php

use Illuminate\Support\Facades\Facade;

return [

    'aliases' => Facade::defaultAliases()->merge([
        'RedisManager' => Illuminate\Support\Facades\Redis::class,
        'UserVerification' => Jrean\UserVerification\Facades\UserVerification::class,
    ])->toArray(),

];
