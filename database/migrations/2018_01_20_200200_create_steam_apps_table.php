<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSteamAppsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('steam_apps', function(Blueprint $table)
		{
			$table->string('name')->default('\'\'')->index('ix_name_ft')->comment('Steam application name');
			$table->integer('appid')->unsigned()->comment('Steam application id');
			$table->primary(['appid','name']);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('steam_apps');
	}

}
