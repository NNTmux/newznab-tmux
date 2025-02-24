<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('forum_threads_read')) {
            Schema::create('forum_threads_read', function (Blueprint $table) {
                $table->integer('thread_id')->unsigned();
                $table->foreignIdFor(config('forum.integration.user_model'), 'user_id');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('forum_threads_read');
    }
};
