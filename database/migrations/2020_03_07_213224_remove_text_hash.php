<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('release_comments', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('release_comments');

            if (array_key_exists('ix_release_comments_hash_releases_id', $indexesFound)) {
                $table->dropUnique('ix_release_comments_hash_releases_id');
            }

            $table->dropColumn('text_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('release_comments', function (Blueprint $table) {
            $table->string('text_hash', 32)->default('');
            $table->unique(['text_hash', 'releases_id'], 'ix_release_comments_hash_releases_id');
        });
    }
};
