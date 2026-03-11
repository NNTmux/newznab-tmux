<?php

use TeamTeaTime\Forum\Http\Resources\CategoryResource;
use TeamTeaTime\Forum\Http\Resources\PostResource;
use TeamTeaTime\Forum\Http\Resources\ThreadResource;

return [

    /*
    |--------------------------------------------------------------------------
    | Enable/disable feature
    |--------------------------------------------------------------------------
    |
    | Whether or not to enable the API feature.
    |
    */

    'enable' => true,

    /*
    |--------------------------------------------------------------------------
    | Enable/disable search
    |--------------------------------------------------------------------------
    |
    | Whether or not to enable the post search endpoint.
    |
    */

    'enable_search' => true,

    /*
    |--------------------------------------------------------------------------
    | Router
    |--------------------------------------------------------------------------
    |
    | API router config.
    |
    */

    'router' => [
        'prefix' => '/forum/api',
        'as' => 'forum.api.',
        'namespace' => '\\TeamTeaTime\\Forum\\Http\\Controllers\\Api',
        'middleware' => ['api', 'auth:api'],
        'auth_middleware' => ['auth:api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Override to return your own resources for API responses.
    |
    */

    'resources' => [
        'category' => CategoryResource::class,
        'thread' => ThreadResource::class,
        'post' => PostResource::class,
    ],

];
