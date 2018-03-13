<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateReleaseUniqueTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('release_unique', function(Blueprint $table)
		{
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
		    $table->integer('releases_id')->unsigned()->comment('FK to releases.id.');
            $table->foreign('releases_id', 'FK_ru_releases')->references('id')->on('releases')->onUpdate('CASCADE')->onDelete('CASCADE');
		});

		DB::unprepared("ALTER TABLE release_unique ADD COLUMN uniqueid BINARY(16) NOT NULL DEFAULT '0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'Unique_ID from mediainfo.'");
		DB::unprepared('ALTER TABLE release_unique ADD PRIMARY KEY (releases_id, uniqueid)');
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('release_unique');
	}

}
