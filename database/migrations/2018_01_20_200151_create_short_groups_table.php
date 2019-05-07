<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShortGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('short_groups', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->integer('id', true);
            $table->string('name')->default('')->index('ix_shortgroups_name');
            $table->bigInteger('first_record')->unsigned()->default(0);
            $table->bigInteger('last_record')->unsigned()->default(0);
            $table->dateTime('updated')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('short_groups');
    }
}
