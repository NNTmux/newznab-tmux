<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePredbTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('predb', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('nfo');
            $table->string('size');
            $table->string('category');
            $table->dateTime('predate');
            $table->string('source', 50);
            $table->unsignedInteger('request_id');
            $table->unsignedInteger('groups_id');
            $table->tinyInteger('nuked');
            $table->string('nukereason');
            $table->string('files');
            $table->string('filename');
            $table->tinyInteger('searched');
            $table->unique('title', 'ix_predb_title');
            $table->index('nfo', 'ix_predb_nfo');
            $table->index('predate', 'ix_predb_predate');
            $table->index('source', 'ix_predb_source');
            $table->index(['request_id', 'groups_id'], 'ix_predb_request_group_id');
            $table->index('filename');
            $table->index('searched', 'ix_predb_search');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('predb');
    }
}
