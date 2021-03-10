<?php

/** @noinspection PhpUnused, SpellCheckingInspection */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReleasesFilesJoinHashedIndexToReleases extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->index(['ishashed', 'isrenamed', 'nzbstatus', 'dehashstatus', 'categories_id', 'adddate'], 'ix_releases_rf_join_hashed');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropIndex('ix_releases_rf_join_hashed');
        });
    }
}
