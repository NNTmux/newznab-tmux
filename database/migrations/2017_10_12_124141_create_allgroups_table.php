<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAllgroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('allgroups', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name')->default('\'\'')->index('ix_allgroups_name');
            $table->bigInteger('first_record')->unsigned()->default(0);
            $table->bigInteger('last_record')->unsigned()->default(0);
            $table->dateTime('updated')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('allgroups');
    }
}
