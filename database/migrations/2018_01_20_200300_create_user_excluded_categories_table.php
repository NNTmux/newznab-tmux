<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserExcludedCategoriesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('user_excluded_categories', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer('users_id')->unsigned();
			$table->integer('categories_id');
			$table->timestamps();
			$table->unique(['users_id','categories_id'], 'ix_userexcat_usercat');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('user_excluded_categories');
	}

}
