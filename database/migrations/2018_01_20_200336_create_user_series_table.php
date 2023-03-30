<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserSeriesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_series', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->integer('users_id')->unsigned();
            $table->integer('videos_id')->comment('FK to videos.id');
            $table->string('categories', 64)->nullable()->comment('List of categories for user tv shows');
            $table->timestamps();
            $table->index(['users_id', 'videos_id'], 'ix_userseries_videos_id');
            $table->foreign('users_id', 'FK_users_us')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('user_series');
    }
}
