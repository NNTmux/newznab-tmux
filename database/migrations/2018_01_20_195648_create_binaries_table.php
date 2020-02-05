<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBinariesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('binaries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->bigInteger('id', true)->unsigned();
            $table->string('name', 1000)->default('');
            $table->integer('collections_id')->unsigned()->default(0)->index('ix_binaries_collection');
            $table->integer('filenumber')->unsigned()->default(0);
            $table->integer('totalparts')->unsigned()->default(0);
            $table->integer('currentparts')->unsigned()->default(0);
            $table->boolean('partcheck')->default(0)->index('ix_binaries_partcheck');
            $table->bigInteger('partsize')->unsigned()->default(0);
            $table->foreign('collections_id', 'FK_Collections')->references('id')->on('collections')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        DB::statement("ALTER TABLE binaries ADD COLUMN binaryhash BINARY(16) NOT NULL DEFAULT '0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0'");
        DB::statement('ALTER TABLE binaries ADD UNIQUE INDEX ix_binaries_binaryhash (binaryhash)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('binaries');
    }
}
