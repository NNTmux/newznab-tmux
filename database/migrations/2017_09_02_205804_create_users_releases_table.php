<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersReleasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users_releases', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('users_id');
            $table->integer('releases_id');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->unique(['users_id', 'releases_id'], 'ix_usercart_userrelease');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users_releases');
    }
}
