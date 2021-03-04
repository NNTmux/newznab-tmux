<?php

/** @noinspection PhpUnused, SpellCheckingInspection */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateGroupsIdIndexOnCollections extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex('groups_id');
        });
        Schema::table('collections', function (Blueprint $table) {
            $table->index(['groups_id', 'added'], 'ix_collections_groups_id_added');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex('ix_collections_groups_id_added');
        });
        Schema::table('collections', function (Blueprint $table) {
            $table->index(['groups_id'], 'groups_id');
        });
    }
}
