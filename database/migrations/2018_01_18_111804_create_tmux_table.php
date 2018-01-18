<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTmuxTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tmux', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('setting', 64)->unique('ix_tmux_setting');
			$table->string('value', 19000)->nullable();
			$table->timestamp('updated_at');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tmux');
	}

}
