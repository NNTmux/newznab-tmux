<?php

namespace App\Providers;

use App\Extensions\util\Yenc;
use Illuminate\Support\ServiceProvider;

class YencServiceProvider extends ServiceProvider
{

	/**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		//
	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}

	public static function config(array $options = [])
	{
		$defaults = [
			['name' =>
				 [
					 'default' => [
						 'adapter' => ''
					 ],

					 'nzedb' => [
						 'adapter' => 'NzedbYenc'
					 ],

					 'php' => [
						 'adapter' => 'Php'
					 ],
				 ]
			]
		];

		$options += $defaults;

		return $options['name'];
	}
}
