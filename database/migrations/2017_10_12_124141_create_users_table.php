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
            $table->string('firstname')->nullable()->default('NULL');
            $table->string('lastname')->nullable()->default('NULL');
            $table->string('email');
            $table->string('password');
            $table->integer('user_roles_id')->default(1)->index('ix_role')->comment('FK to user_roles.id');
            $table->string('host', 40)->nullable()->default('NULL');
            $table->integer('grabs')->default(0);
            $table->string('rsstoken', 64);
            $table->timestamps();
            $table->string('resetguid', 50)->nullable()->default('NULL');
            $table->dateTime('lastlogin')->nullable()->default('NULL');
            $table->dateTime('apiaccess')->nullable()->default('NULL');
            $table->integer('invites')->default(0);
            $table->integer('invitedby')->nullable()->default('NULL');
            $table->integer('movieview')->default(1);
            $table->integer('xxxview')->default(1);
            $table->integer('musicview')->default(1);
            $table->integer('consoleview')->default(1);
            $table->integer('bookview')->default(1);
            $table->integer('gameview')->default(1);
            $table->string('saburl')->nullable()->default('NULL');
            $table->string('sabapikey')->nullable()->default('NULL');
            $table->boolean('sabapikeytype')->nullable()->default('NULL');
            $table->boolean('sabpriority')->nullable()->default('NULL');
            $table->boolean('queuetype')->default(1)->comment('Type of queue, Sab or NZBGet');
            $table->string('nzbgeturl')->nullable()->default('NULL');
            $table->string('nzbgetusername')->nullable()->default('NULL');
            $table->string('nzbgetpassword')->nullable()->default('NULL');
            $table->string('nzbvortex_api_key', 10)->nullable()->default('NULL');
            $table->string('nzbvortex_server_url')->nullable()->default('NULL');
            $table->string('userseed', 50);
            $table->string('notes');
            $table->string('cp_url')->nullable()->default('NULL');
            $table->string('cp_api')->nullable()->default('NULL');
            $table->string('style')->nullable()->default('NULL');
            $table->dateTime('rolechangedate')->nullable()->default('NULL')->comment('When does the role expire');
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
