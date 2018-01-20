<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToPartsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('parts', function(Blueprint $table)
		{
			$table->foreign('binaries_id', 'FK_binaries')->references('id')->on('binaries')->onUpdate('CASCADE')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('parts', function(Blueprint $table)
		{
			$table->dropForeign('FK_binaries');
		});
	}

}
