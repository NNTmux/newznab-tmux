<?php

/** @noinspection PhpUnused, SpellCheckingInspection */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCountSuggestIndexToTaggingTags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tagging_tags', function (Blueprint $table) {
            $table->index(['count', 'suggest'], 'ix_tagging_tags_count_suggest');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tagging_tags', function (Blueprint $table) {
            $table->dropIndex('ix_tagging_tags_count_suggest');
        });
    }
}
