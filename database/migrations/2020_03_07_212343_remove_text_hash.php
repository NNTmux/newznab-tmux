<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveTextHash extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('release_commments', function (Blueprint $table) {
            $table->dropUnique('ix_release_comments_hash_releases_id');
            $table->dropColumn('text_hash');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('release_commments', function (Blueprint $table) {
            $table->string('text_hash', 32)->default('');
            $table->unique(['text_hash', 'releases_id'], 'ix_release_comments_hash_releases_id');
        });
    }
}
