<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserSeriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_series', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('users_id')->unsigned();
            $table->integer('videos_id')->comment('FK to videos.id');
            $table->string('categories', 64)->nullable()->default('NULL')->comment('List of categories for user tv shows');
            $table->timestamps();
            $table->index(['users_id', 'videos_id'], 'ix_userseries_videos_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('user_series');
    }
}
