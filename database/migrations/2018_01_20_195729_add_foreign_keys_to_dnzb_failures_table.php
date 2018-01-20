<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToDnzbFailuresTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('dnzb_failures', function(Blueprint $table)
		{
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
		Schema::table('dnzb_failures', function(Blueprint $table)
		{
			$table->dropForeign('FK_users_df');
		});
	}

}
