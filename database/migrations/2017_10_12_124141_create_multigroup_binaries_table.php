<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMultigroupBinariesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('multigroup_binaries', function (Blueprint $table) {
            $table->bigInteger('id', true)->unsigned();
            $table->string('name', 1000)->default('\'\'');
            $table->integer('collections_id')->unsigned()->default(0)->index('ix_binaries_collection');
            $table->integer('filenumber')->unsigned()->default(0);
            $table->integer('totalparts')->unsigned()->default(0);
            $table->integer('currentparts')->unsigned()->default(0);
            $table->binary('binaryhash', 16)->default('\'0$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$\'')->unique('ix_binaries_binaryhash');
            $table->boolean('partcheck')->default(0)->index('ix_binaries_partcheck');
            $table->bigInteger('partsize')->unsigned()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('multigroup_binaries');
    }
}
