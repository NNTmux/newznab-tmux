<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Extensions\util\yenc\adapter\Php;

class YencServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * @param array $options
     *
     * @return mixed
     * @internal param $option
     */
    public static function config(array $options = [])
    {
        $defaults = [
            ['name' => [
                'default' => 'Php',
                ],
            ],
        ];

        $options += $defaults;

        $namespace = '\App\Extensions\util\yenc\adapter\\';

        $class = $namespace.$options[0]['name']['default'];

        return new $class;
    }
}
