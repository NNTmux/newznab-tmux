<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDnzbFailuresTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dnzb_failures', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->integer('release_id')->unsigned();
            $table->integer('users_id')->unsigned()->index('FK_users_df');
            $table->integer('failed')->unsigned()->default(0);
            $table->primary(['release_id', 'users_id']);
            $table->foreign('users_id', 'FK_users_df')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('release_id', 'FK_df_releases')->references('id')->on('releases')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('dnzb_failures');
    }
}
