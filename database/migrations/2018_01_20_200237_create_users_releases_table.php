<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUsersReleasesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('users_releases', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer('users_id')->unsigned();
			$table->integer('releases_id')->unsigned()->comment('FK to releases.id');
			$table->timestamps();
			$table->unique(['users_id','releases_id'], 'ix_usercart_userrelease');
            $table->foreign('users_id', 'FK_users_ur')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('users_releases');
	}

}
