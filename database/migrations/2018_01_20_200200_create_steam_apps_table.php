<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSteamAppsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('steam_apps', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->string('name')->default('')->comment('Steam application name');
            $table->integer('appid')->unsigned()->comment('Steam application id');
            $table->primary(['appid', 'name']);
            $table->fullText('name', 'ix_name_ft');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('steam_apps');
    }
}
