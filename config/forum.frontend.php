<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | By default, the routes are loaded automatically. You can disable that
    | behaviour here if you wish to define them yourself or simply pull them
    | into your own routes file.
    |
    */

    'routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Here we specify middleware to apply to the routes. For multiple values,
    | use arrays or pipe notation.
    |
    */

    'middleware' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Controllers
    |--------------------------------------------------------------------------
    |
    | Here we specify the namespace and controllers to use for the frontend.
    | Change these if you want to override default behaviour.
    |
    */

    'controllers' => [
        'namespace' => 'Riari\Forum\Frontend\Http\Controllers',
        'category'  => 'CategoryController',
        'thread'    => 'ThreadController',
        'post'      => 'PostController',
    ],

    /*
    |--------------------------------------------------------------------------
    | Utility Class
    |--------------------------------------------------------------------------
    |
    | Here we specify the namespace of the class to use for various utility
    | methods. This is automatically aliased to 'Forum' for ease of use in
    | views.
    |
    */

    'utility_class' => Riari\Forum\Frontend\Support\Forum::class,

];
