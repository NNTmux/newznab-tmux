<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateForumpostTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('forumpost', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('forumid')->default(1);
            $table->integer('parentid')->default(0);
            $table->unsignedInteger('users_id');
            $table->string('subject', 255);
            $table->text('message');
            $table->unsignedTinyInteger('locked')->default(0);
            $table->unsignedTinyInteger('sticky')->default(0);
            $table->integer('replies')->default(0);
            $table->dateTime('createddate');
            $table->dateTime('updateddate');
            $table->primary('id');
            $table->index('parentid');
            $table->index('users_id');
            $table->index('createddate');
            $table->index('updateddate');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('forumpost');
    }
}
