<?php

use App\Providers\AdditionalProcessingServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\CategorizationServiceProvider;
use App\Providers\ForumServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\ProcessingServiceProvider;
use App\Providers\SearchServiceProvider;
use App\Providers\TvProcessingServiceProvider;
use App\Providers\UserServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AdditionalProcessingServiceProvider::class,
    AppServiceProvider::class,
    CategorizationServiceProvider::class,
    ForumServiceProvider::class,
    HorizonServiceProvider::class,
    ProcessingServiceProvider::class,
    SearchServiceProvider::class,
    TvProcessingServiceProvider::class,
    UserServiceProvider::class,
    VoltServiceProvider::class,
];
