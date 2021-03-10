<?php

/** @noinspection PhpUnused, SpellCheckingInspection */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateIndicesOnReleases extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropIndex('ix_releases_name');
            $table->index(['name', 'adddate', 'guid'], 'ix_releases_name_adddate_guid');
            $table->dropIndex('ix_releases_passwordstatus');
            $table->index(['passwordstatus', 'postdate'], 'ix_releases_passwordstatus_postdate');
            $table->index(['categories_id', 'nzbstatus', 'musicinfo_id'], 'ix_releases_categories_id_nzbstatus_musicinfo_id');
            $table->index(['size', 'haspreview', 'passwordstatus', 'nzbstatus', 'leftguid'], 'ix_releases_size_etc');
            $table->index(['isrenamed', 'nzbstatus', 'nfostatus', 'passwordstatus', 'predb_id'], 'ix_releases_isrenamed');
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
            $table->dropIndex('ix_releases_name_adddate_guid');
            $table->index(['name'], 'ix_releases_name');
            $table->dropIndex('ix_releases_passwordstatus_postdate');
            $table->index(['name'], 'ix_releases_passwordstatus');
            $table->dropIndex('ix_releases_categories_id_nzbstatus_musicinfo_id');
            $table->dropIndex('ix_releases_size_etc');
            $table->dropIndex('ix_releases_isrenamed');
        });
    }
}
