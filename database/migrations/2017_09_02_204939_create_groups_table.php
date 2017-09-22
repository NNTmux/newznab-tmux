<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 255);
            $table->unsignedBigInteger('first_record')->default('0');
            $table->dateTime('fisrt_record_postdate');
            $table->unsignedBigInteger('last_record')->default('0');
            $table->dateTime('last_record_postdate');
            $table->dateTime('last_updated');
            $table->integer('minfilestoformrelease')->nullable();
            $table->bigInteger('minsizetoformrelease')->nullable();
            $table->tinyInteger('active')->default('0');
            $table->tinyInteger('backfill')->default('0');
            $table->string('description', 255)->default('');
            $table->primary('id');
            $table->index('active', 'ix_active');
            $table->unique('name', 'ix_groups_name');
            DB::update('ALTER TABLE groups AUTO_INCREMENT = 1000001');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('groups');
    }
}
