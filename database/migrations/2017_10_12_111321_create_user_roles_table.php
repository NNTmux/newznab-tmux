<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 32);
            $table->unsignedInteger('apirequests');
            $table->unsignedInteger('downloadrequests');
            $table->unsignedInteger('defaultinvites');
            $table->tinyInteger('isdefault');
            $table->tinyInteger('cabpreview');
            $table->tinyInteger('hideads');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_roles');
    }
}
