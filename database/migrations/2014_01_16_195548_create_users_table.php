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
        Schema::create('users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->string('username', 50);
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('email');
            $table->string('password');
            $table->integer('roles_id')->default(1)->index('ix_user_roles')->comment('FK to roles.id');
            $table->string('host', 40)->nullable();
            $table->integer('grabs')->default(0);
            $table->string('api_token', 64);
            $table->string('resetguid', 50)->nullable();
            $table->dateTime('lastlogin')->nullable();
            $table->dateTime('apiaccess')->nullable();
            $table->integer('invites')->default(0);
            $table->integer('invitedby')->nullable();
            $table->integer('movieview')->default(1);
            $table->integer('xxxview')->default(1);
            $table->integer('musicview')->default(1);
            $table->integer('consoleview')->default(1);
            $table->integer('bookview')->default(1);
            $table->integer('gameview')->default(1);
            $table->integer('rate_limit')->default(60);
            $table->string('userseed', 50);
            $table->string('notes')->nullable();
            $table->string('style')->nullable();
            $table->dateTime('rolechangedate')->nullable()->comment('When does the role expire');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('users');
    }
};
