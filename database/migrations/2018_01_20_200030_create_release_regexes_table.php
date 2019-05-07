<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReleaseRegexesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('release_regexes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->integer('releases_id')->unsigned()->default(0);
            $table->integer('collection_regex_id')->default(0);
            $table->integer('naming_regex_id')->default(0);
            $table->primary(['releases_id', 'collection_regex_id', 'naming_regex_id'], 'ix_rel_coll_name_reg_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('release_regexes');
    }
}
