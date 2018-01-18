<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateForumpostTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('forumpost', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer('forumid')->default(1);
			$table->integer('parentid')->default(0)->index('parentid');
			$table->integer('users_id')->unsigned()->index('userid');
			$table->string('subject');
			$table->text('message', 65535);
			$table->boolean('locked')->default(0);
			$table->boolean('sticky')->default(0);
			$table->integer('replies')->unsigned()->default(0);
			$table->timestamps();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('forumpost');
	}

}
