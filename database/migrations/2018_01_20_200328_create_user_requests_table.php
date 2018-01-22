<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserRequestsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('user_requests', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer('users_id')->unsigned()->index('userid');
			$table->string('hosthash', 50)->default('');
			$table->string('request');
			$table->dateTime('timestamp')->index('timestamp');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('user_requests');
	}

}
