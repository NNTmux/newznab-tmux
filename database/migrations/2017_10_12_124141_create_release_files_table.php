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
            $table->integer('releases_id')->unsigned()->comment('FK to releases.id');
            $table->string('name')->default('\'\'');
            $table->bigInteger('size')->unsigned()->default(0);
            $table->boolean('ishashed')->default(0)->index('ix_releasefiles_ishashed');
            $table->timestamps();
            $table->boolean('passworded')->default(0);
            $table->primary(['releases_id', 'name']);
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
