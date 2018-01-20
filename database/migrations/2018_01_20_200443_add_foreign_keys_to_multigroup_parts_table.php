<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToMultigroupPartsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('multigroup_parts', function(Blueprint $table)
		{
			$table->foreign('binaries_id', 'FK_MGR_binaries')->references('id')->on('multigroup_binaries')->onUpdate('CASCADE')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('multigroup_parts', function(Blueprint $table)
		{
			$table->dropForeign('FK_MGR_binaries');
		});
	}

}
