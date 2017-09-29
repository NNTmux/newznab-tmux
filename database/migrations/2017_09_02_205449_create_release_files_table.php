<?php

use Illuminate\Support\Facades\Schema;
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
            $table->unsignedInteger('releases_id');
            $table->string('name', 255)->default('');
            $table->unsignedBigInteger('size')->default(0);
            $table->tinyInteger('ishashed')->default(0);
            $table->unsignedTinyInteger('passworded')->default(0);
            $table->timestamps();
            $table->primary(['releases_id', 'name']);
            $table->index('ishashed', 'ix_releasefiles_ishashed');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('release_files');
    }
}
