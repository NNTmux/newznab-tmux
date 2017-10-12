<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('content', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('title');
            $table->string('url', 2000)->nullable()->default('NULL');
            $table->text('body', 65535)->nullable()->default('NULL');
            $table->string('metadescription', 1000);
            $table->string('metakeywords', 1000);
            $table->integer('contenttype');
            $table->integer('showinmenu');
            $table->integer('status');
            $table->integer('ordinal')->nullable()->default('NULL');
            $table->integer('role')->default(0);
            $table->index(['showinmenu', 'status', 'contenttype', 'role'], 'ix_showinmenu_status_contenttype_role');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('content');
    }
}
