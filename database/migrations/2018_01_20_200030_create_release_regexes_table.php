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
     */
    public function down(): void
    {
        Schema::drop('release_regexes');
    }
};
