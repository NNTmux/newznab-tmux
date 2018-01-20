<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToMultigroupBinariesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('multigroup_binaries', function(Blueprint $table)
		{
			$table->foreign('collections_id', 'FK_MGR_Collections')->references('id')->on('multigroup_collections')->onUpdate('CASCADE')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('multigroup_binaries', function(Blueprint $table)
		{
			$table->dropForeign('FK_MGR_Collections');
		});
	}

}
