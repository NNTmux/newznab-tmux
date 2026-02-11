<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop index and column from release_files table
        Schema::table('release_files', function (Blueprint $table) {
            if ($this->hasIndex('release_files', 'ix_releasefiles_ishashed')) {
                $table->dropIndex('ix_releasefiles_ishashed');
            }

            if (Schema::hasColumn('release_files', 'ishashed')) {
                $table->dropColumn('ishashed');
            }
        });

        // Drop index and columns from releases table
        Schema::table('releases', function (Blueprint $table) {
            if ($this->hasIndex('releases', 'ix_releases_dehashstatus')) {
                $table->dropIndex('ix_releases_dehashstatus');
            }

            if (Schema::hasColumn('releases', 'ishashed')) {
                $table->dropColumn('ishashed');
            }

            if (Schema::hasColumn('releases', 'dehashstatus')) {
                $table->dropColumn('dehashstatus');
            }
        });

        // Remove dehash settings from settings table
        DB::table('settings')->whereIn('name', ['dehash', 'dehash_timer'])->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore columns to release_files table
        Schema::table('release_files', function (Blueprint $table) {
            if (! Schema::hasColumn('release_files', 'ishashed')) {
                $table->boolean('ishashed')->default(false)->after('size');
            }

            if (! $this->hasIndex('release_files', 'ix_releasefiles_ishashed')) {
                $table->index('ishashed', 'ix_releasefiles_ishashed');
            }
        });

        // Restore columns to releases table
        Schema::table('releases', function (Blueprint $table) {
            if (! Schema::hasColumn('releases', 'dehashstatus')) {
                $table->tinyInteger('dehashstatus')->default(0)->after('audiostatus');
            }

            if (! Schema::hasColumn('releases', 'ishashed')) {
                $table->boolean('ishashed')->default(false)->after('isrenamed');
            }

            if (! $this->hasIndex('releases', 'ix_releases_dehashstatus')) {
                $table->index(['dehashstatus', 'ishashed'], 'ix_releases_dehashstatus');
            }
        });

        // Restore dehash settings
        DB::table('settings')->insert([
            ['name' => 'dehash', 'value' => '0', 'section' => 'site', 'subsection' => '', 'hint' => 'dehash'],
            ['name' => 'dehash_timer', 'value' => '30', 'section' => 'site', 'subsection' => '', 'hint' => 'dehash_timer'],
        ]);
    }

    /**
     * Check if an index exists on a table.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};
