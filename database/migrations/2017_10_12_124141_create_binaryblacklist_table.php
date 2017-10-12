<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBinaryblacklistTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('binaryblacklist', function (Blueprint $table) {
            $table->increments('id');
            $table->string('groupname')->nullable()->default(null)->index('ix_binaryblacklist_groupname');
            $table->string('regex', 2000);
            $table->integer('msgcol')->unsigned()->default(1);
            $table->integer('optype')->unsigned()->default(1);
            $table->integer('status')->unsigned()->default(1)->index('ix_binaryblacklist_status');
            $table->string('description', 1000)->nullable()->default(null);
            $table->date('last_activity')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('binaryblacklist');
    }
}
