<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 50);
            $table->string('firstname')->nullable()->default(null);
            $table->string('lastname')->nullable()->default(null);
            $table->string('email');
            $table->string('password');
            $table->integer('user_roles_id')->default(1)->index('ix_role')->comment('FK to user_roles.id');
            $table->string('host', 40)->nullable()->default(null);
            $table->integer('grabs')->default(0);
            $table->string('rsstoken', 64);
            $table->timestamps();
            $table->string('resetguid', 50)->nullable()->default(null);
            $table->dateTime('lastlogin')->nullable()->default(null);
            $table->dateTime('apiaccess')->nullable()->default(null);
            $table->integer('invites')->default(0);
            $table->integer('invitedby')->nullable()->default(null);
            $table->integer('movieview')->default(1);
            $table->integer('xxxview')->default(1);
            $table->integer('musicview')->default(1);
            $table->integer('consoleview')->default(1);
            $table->integer('bookview')->default(1);
            $table->integer('gameview')->default(1);
            $table->string('saburl')->nullable()->default(null);
            $table->string('sabapikey')->nullable()->default(null);
            $table->boolean('sabapikeytype')->nullable()->default(null);
            $table->boolean('sabpriority')->nullable()->default(null);
            $table->boolean('queuetype')->default(1)->comment('Type of queue, Sab or NZBGet');
            $table->string('nzbgeturl')->nullable()->default(null);
            $table->string('nzbgetusername')->nullable()->default(null);
            $table->string('nzbgetpassword')->nullable()->default(null);
            $table->string('nzbvortex_api_key', 10)->nullable()->default(null);
            $table->string('nzbvortex_server_url')->nullable()->default(null);
            $table->string('userseed', 50);
            $table->string('notes');
            $table->string('cp_url')->nullable()->default(null);
            $table->string('cp_api')->nullable()->default(null);
            $table->string('style')->nullable()->default(null);
            $table->dateTime('rolechangedate')->nullable()->default(null)->comment('When does the role expire');
            $table->string('remember_token', 100)->nullable()->default('\'NULL\'');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('users');
    }
}
