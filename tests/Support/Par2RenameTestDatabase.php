<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Category;
use App\Models\Release;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait Par2RenameTestDatabase
{
    private function createPar2RenameSchema(): void
    {
        DB::connection()->getPdo()->sqliteCreateFunction('UNCOMPRESS', static fn (?string $value): ?string => $value);
        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            foreach (['name', 'searchname', 'fromname', 'guid', 'leftguid', 'postdate', 'adddate'] as $column) {
                $table->string($column)->nullable();
            }
            foreach (['groups_id', 'categories_id', 'nzbstatus', 'passwordstatus', 'isrenamed', 'proc_par2', 'rarinnerfilecount', 'predb_id', 'nfostatus', 'proc_nfo', 'proc_files', 'proc_uid', 'proc_srr', 'proc_crc32', 'proc_hash16k', 'size'] as $column) {
                $table->integer($column)->default(0);
            }
            foreach (['videos_id', 'tv_episodes_id', 'book_lookup_attempts', 'iscategorized'] as $column) {
                $table->integer($column)->default(0);
            }
            foreach (['imdbid', 'musicinfo_id', 'consoleinfo_id', 'bookinfo_id', 'book_lookup_retry_at', 'book_name_normalized_at', 'anidbid'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        $migration = require base_path('database/migrations/2026_10_11_215821_create_par2_rename_progress_table.php');
        $migration->up();
        Schema::create('release_nfos', function (Blueprint $table): void {
            $table->integer('releases_id');
            $table->binary('nfo')->nullable();
        });
        Schema::create('release_files', function (Blueprint $table): void {
            $table->integer('releases_id');
            $table->string('name');
            $table->string('crc32')->nullable();
        });
        Schema::create('media_infos', function (Blueprint $table): void {
            $table->integer('releases_id');
            $table->string('unique_id')->nullable();
            $table->string('movie_name')->nullable();
            $table->string('file_name')->nullable();
        });
        Schema::create('par_hashes', function (Blueprint $table): void {
            $table->integer('releases_id');
            $table->string('hash');
        });
        Schema::create('predb', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.binaries.test']);
    }

    /** @param array<string, mixed> $attributes */
    private function pendingPar2Release(array $attributes = []): Release
    {
        return Release::withoutEvents(fn (): Release => Release::factory()->create(array_merge([
            'groups_id' => 1, 'categories_id' => Category::OTHER_HASHED, 'isrenamed' => 0,
            'adddate' => now()->toDateTimeString(),
        ], $attributes)));
    }
}
