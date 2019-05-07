<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTagGroupsTable extends Migration
{
    public function up()
    {
        Schema::create('tagging_tag_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug', 125)->index();
            $table->string('name', 125);
        });
    }

    public function down()
    {
        Schema::drop('tagging_tag_groups');
    }
}
