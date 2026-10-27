<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UpdateXxxCategoriesMigrationTest extends TestCase
{
    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('categories', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('title');
            $table->integer('root_categories_id');
            $table->integer('status')->default(1);
            $table->string('description')->nullable();
            $table->boolean('disablepreview')->default(false);
            $table->unsignedBigInteger('minsizetoformrelease')->default(0);
            $table->unsignedBigInteger('maxsizetoformrelease')->default(0);
        });
        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('categories_id');
        });

        $this->migration = require database_path('migrations/2026_10_27_141423_update_xxx_categories.php');
    }

    public function test_up_renames_existing_rows_and_creates_missing_targets(): void
    {
        DB::table('categories')->insert([
            ['id' => 6030, 'title' => 'XviD', 'root_categories_id' => 6000, 'status' => 0, 'minsizetoformrelease' => 100],
            ['id' => 6040, 'title' => 'x264', 'root_categories_id' => 6000, 'status' => 1, 'minsizetoformrelease' => 0],
            ['id' => 6080, 'title' => 'SD', 'root_categories_id' => 6000, 'status' => 1, 'minsizetoformrelease' => 0],
        ]);

        $this->migration->up();

        $categories = DB::table('categories')->orderBy('id')->get()->keyBy('id');
        $this->assertSame(
            [6010, 6030, 6040, 6045, 6046, 6047, 6048, 6050, 6060, 6080, 6999],
            $categories->keys()->map(fn (mixed $id): int => (int) $id)->all()
        );
        $this->assertSame('SD', $categories[6030]->title);
        $this->assertSame('Video below 720p', $categories[6030]->description);
        $this->assertSame(0, (int) $categories[6030]->status, 'Existing admin settings are kept.');
        $this->assertSame(100, (int) $categories[6030]->minsizetoformrelease);
        $this->assertSame('HD', $categories[6040]->title);
        $this->assertSame('SD (legacy)', $categories[6080]->title);
        $this->assertSame('VR', $categories[6046]->title);
        $this->assertSame('OnlyFans', $categories[6047]->title);
        $this->assertSame('JAV', $categories[6048]->title);
        $this->assertSame(1, (int) $categories[6048]->status);
    }

    public function test_up_does_not_create_the_retired_sd_row(): void
    {
        $this->migration->up();

        $this->assertFalse(DB::table('categories')->where('id', 6080)->exists());
    }

    public function test_down_restores_old_titles_and_removes_unused_jav(): void
    {
        DB::table('categories')->insert([
            ['id' => 6030, 'title' => 'XviD', 'root_categories_id' => 6000],
            ['id' => 6080, 'title' => 'SD', 'root_categories_id' => 6000],
        ]);
        $this->migration->up();

        $this->migration->down();

        $this->assertSame('XviD', DB::table('categories')->where('id', 6030)->value('title'));
        $this->assertSame('SD', DB::table('categories')->where('id', 6080)->value('title'));
        $this->assertFalse(DB::table('categories')->where('id', 6048)->exists());
    }
}
