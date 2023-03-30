<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateContentTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('content', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->string('title', 255);
            $table->string('url', 2000)->nullable();
            $table->text('body', 65535)->nullable();
            $table->string('metadescription', 1000);
            $table->string('metakeywords', 1000);
            $table->integer('contenttype');
            $table->integer('showinmenu');
            $table->integer('status');
            $table->integer('ordinal')->nullable();
            $table->integer('role')->default(0);
            $table->index(['showinmenu', 'status', 'contenttype', 'role'], 'ix_showinmenu_status_contenttype_role');
        });

        DB::statement('ALTER TABLE content AUTO_INCREMENT = 100000;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('content');
    }
}
