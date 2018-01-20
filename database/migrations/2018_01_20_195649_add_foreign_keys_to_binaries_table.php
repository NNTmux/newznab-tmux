<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToBinariesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('binaries', function(Blueprint $table)
		{
			$table->foreign('collections_id', 'FK_Collections')->references('id')->on('collections')->onUpdate('CASCADE')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('binaries', function(Blueprint $table)
		{
			$table->dropForeign('FK_Collections');
		});
	}

}
