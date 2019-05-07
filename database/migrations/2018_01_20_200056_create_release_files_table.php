<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReleaseFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('release_files', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->integer('releases_id')->unsigned()->comment('FK to releases.id');
            $table->string('name')->default('');
            $table->bigInteger('size')->unsigned()->default(0);
            $table->boolean('ishashed')->default(0)->index('ix_releasefiles_ishashed');
            $table->string('crc32')->default('');
            $table->timestamps();
            $table->boolean('passworded')->default(0);
            $table->primary(['releases_id', 'name']);
            $table->foreign('releases_id', 'FK_rf_releases')->references('id')->on('releases')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('release_files');
    }
}
