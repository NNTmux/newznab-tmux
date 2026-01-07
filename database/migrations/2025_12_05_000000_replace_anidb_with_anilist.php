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
        // Drop anidb_episodes table (AniList doesn't support episodes)
        Schema::dropIfExists('anidb_episodes');

        // Add anilist_id, mal_id, country, and media_type columns to anidb_info
        Schema::table('anidb_info', function (Blueprint $table) {
            if (! Schema::hasColumn('anidb_info', 'anilist_id')) {
                $table->unsignedInteger('anilist_id')->nullable()->after('anidbid');
            }
            if (! Schema::hasColumn('anidb_info', 'mal_id')) {
                $table->unsignedInteger('mal_id')->nullable()->after('anilist_id');
            }
            if (! Schema::hasColumn('anidb_info', 'country')) {
                $table->string('country', 2)->nullable()->after('mal_id')->comment('ISO 3166-1 alpha-2 country code');
            }
            if (! Schema::hasColumn('anidb_info', 'media_type')) {
                $table->string('media_type', 10)->nullable()->after('country')->comment('ANIME or MANGA');
            }
            if (! Schema::hasColumn('anidb_info', 'episodes')) {
                $table->unsignedInteger('episodes')->nullable()->after('media_type');
            }
            if (! Schema::hasColumn('anidb_info', 'duration')) {
                $table->unsignedInteger('duration')->nullable()->after('episodes')->comment('Duration in minutes');
            }
            if (! Schema::hasColumn('anidb_info', 'status')) {
                $table->string('status', 20)->nullable()->after('duration')->comment('Media status (FINISHED, RELEASING, etc.)');
            }
            if (! Schema::hasColumn('anidb_info', 'source')) {
                $table->string('source', 20)->nullable()->after('status')->comment('Original source (MANGA, ORIGINAL, etc.)');
            }
            if (! Schema::hasColumn('anidb_info', 'hashtag')) {
                $table->string('hashtag', 255)->nullable()->after('source')->comment('AniList hashtag');
            }
        });

        // Add indexes for faster lookups
        Schema::table('anidb_info', function (Blueprint $table) {
            if (! $this->indexExists('anidb_info', 'ix_anidb_info_anilist_id')) {
                $table->index('anilist_id', 'ix_anidb_info_anilist_id');
            }
            if (! $this->indexExists('anidb_info', 'ix_anidb_info_mal_id')) {
                $table->index('mal_id', 'ix_anidb_info_mal_id');
            }
            if (! $this->indexExists('anidb_info', 'ix_anidb_info_country')) {
                $table->index('country', 'ix_anidb_info_country');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes
        Schema::table('anidb_info', function (Blueprint $table) {
            if ($this->indexExists('anidb_info', 'ix_anidb_info_anilist_id')) {
                $table->dropIndex('ix_anidb_info_anilist_id');
            }
            if ($this->indexExists('anidb_info', 'ix_anidb_info_mal_id')) {
                $table->dropIndex('ix_anidb_info_mal_id');
            }
            if ($this->indexExists('anidb_info', 'ix_anidb_info_country')) {
                $table->dropIndex('ix_anidb_info_country');
            }
        });

        // Remove columns
        Schema::table('anidb_info', function (Blueprint $table) {
            if (Schema::hasColumn('anidb_info', 'anilist_id')) {
                $table->dropColumn('anilist_id');
            }
            if (Schema::hasColumn('anidb_info', 'mal_id')) {
                $table->dropColumn('mal_id');
            }
            if (Schema::hasColumn('anidb_info', 'country')) {
                $table->dropColumn('country');
            }
            if (Schema::hasColumn('anidb_info', 'media_type')) {
                $table->dropColumn('media_type');
            }
            if (Schema::hasColumn('anidb_info', 'episodes')) {
                $table->dropColumn('episodes');
            }
            if (Schema::hasColumn('anidb_info', 'duration')) {
                $table->dropColumn('duration');
            }
            if (Schema::hasColumn('anidb_info', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('anidb_info', 'source')) {
                $table->dropColumn('source');
            }
            if (Schema::hasColumn('anidb_info', 'hashtag')) {
                $table->dropColumn('hashtag');
            }
        });

        // Recreate anidb_episodes table (if needed for rollback)
        if (! Schema::hasTable('anidb_episodes')) {
            Schema::create('anidb_episodes', function (Blueprint $table) {
                $table->unsignedInteger('anidbid')->comment('ID of title from AniDB');
                $table->unsignedInteger('episodeid')->default(0)->comment('anidb id for this episode');
                $table->unsignedSmallInteger('episode_no')->comment('Numeric version of episode (leave 0 for combined episodes).');
                $table->string('episode_title', 255)->comment('Title of the episode (en, x-jat)');
                $table->date('airdate');
                $table->primary(['anidbid', 'episodeid']);
            });
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        $result = $connection->select(
            'SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        return $result[0]->count > 0;
    }
};
