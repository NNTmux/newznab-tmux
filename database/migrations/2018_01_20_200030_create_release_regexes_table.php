<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateReleaseRegexesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('release_regexes', function(Blueprint $table)
		{
			$table->integer('releases_id')->unsigned()->default(0);
			$table->integer('collection_regex_id')->default(0);
			$table->integer('naming_regex_id')->default(0);
			$table->primary(['releases_id','collection_regex_id','naming_regex_id']);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('release_regexes');
	}

}
