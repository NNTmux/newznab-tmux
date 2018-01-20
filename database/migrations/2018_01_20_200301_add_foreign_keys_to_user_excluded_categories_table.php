<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToUserExcludedCategoriesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('user_excluded_categories', function(Blueprint $table)
		{
			$table->foreign('users_id', 'FK_users_uec')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('user_excluded_categories', function(Blueprint $table)
		{
			$table->dropForeign('FK_users_uec');
		});
	}

}
