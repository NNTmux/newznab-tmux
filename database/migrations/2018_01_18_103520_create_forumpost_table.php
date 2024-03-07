<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('forumpost', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->integer('forumid')->default(1);
            $table->integer('parentid')->default(0)->index('parentid');
            $table->integer('users_id')->unsigned()->index('userid');
            $table->string('subject');
            $table->text('message', 65535);
            $table->boolean('locked')->default(0);
            $table->boolean('sticky')->default(0);
            $table->integer('replies')->unsigned()->default(0);
            $table->timestamps();
            $table->foreign('users_id', 'FK_users_fp')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('forumpost');
    }
};
