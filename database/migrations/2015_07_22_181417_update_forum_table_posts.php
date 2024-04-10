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
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->renameColumn('parent_thread', 'thread_id');
            $table->integer('post_id')->after('content')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->renameColumn('thread_id', 'parent_thread');
        });
    }
};
