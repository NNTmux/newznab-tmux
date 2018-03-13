<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePredbTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('predb', function(Blueprint $table)
		{
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
		    $table->increments('id')->comment('Primary key');
			$table->string('title')->default('')->unique('ix_predb_title');
			$table->string('nfo')->nullable()->index('ix_predb_nfo');
			$table->string('size', 50)->nullable();
			$table->string('category')->nullable();
			$table->dateTime('predate')->nullable()->index('ix_predb_predate');
			$table->string('source', 50)->default('')->index('ix_predb_source');
			$table->integer('requestid')->unsigned()->default(0);
			$table->integer('groups_id')->unsigned()->default(0)->comment('FK to groups');
			$table->boolean('nuked')->default(0)->comment('Is this pre nuked? 0 no 2 yes 1 un nuked 3 mod nuked');
			$table->string('nukereason')->nullable()->comment('If this pre is nuked, what is the reason?');
			$table->string('files', 50)->nullable()->comment('How many files does this pre have ?');
			$table->string('filename')->default('');
			$table->boolean('searched')->default(0)->index('ix_predb_searched');
			$table->index(['requestid','groups_id'], 'ix_predb_requestid');
		});
        DB::unprepared('ALTER TABLE predb ADD FULLTEXT ft_predb_filename (filename)');
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('predb');
	}

}
