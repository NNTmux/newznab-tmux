<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDnzbFailuresTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('dnzb_failures', function(Blueprint $table)
		{
			$table->integer('release_id')->unsigned();
			$table->integer('users_id')->unsigned()->index('FK_users_df');
			$table->integer('failed')->unsigned()->default(0);
			$table->primary(['release_id','users_id']);
            $table->foreign('users_id', 'FK_users_df')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('dnzb_failures');
	}

}
