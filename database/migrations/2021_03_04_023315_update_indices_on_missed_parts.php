<?php

/** @noinspection PhpUnused, SpellCheckingInspection */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateIndicesOnMissedParts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropIndex('ix_missed_parts_numberid_groupsid_attempts');
            $table->dropUnique('ix_missed_parts_numberid_groupsid');
            $table->unique(['numberid', 'groups_id', 'attempts'], 'ux_missed_parts_numberid_groups_id_attempts');
            $table->index(['groups_id', 'numberid'], 'ix_missed_parts_groups_id_numberid');
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
            $table->dropIndex('ix_missed_parts_groups_id_numberid');
            $table->dropUnique('ux_missed_parts_numberid_groups_id_attempts');
            $table->unique(['numberid', 'groups_id'], 'ix_missed_parts_numberid_groupsid');
            $table->index(['numberid', 'groups_id', 'attempts'], 'ix_missed_parts_numberid_groupsid_attempts');
        });
    }
}
