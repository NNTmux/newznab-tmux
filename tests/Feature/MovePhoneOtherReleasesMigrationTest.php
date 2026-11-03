<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MovePhoneOtherReleasesMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge();

        Schema::create('categories', static function (Blueprint $table): void {
            $table->integer('id')->primary();
        });
        Schema::create('releases', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('categories_id');
        });
        DB::table('releases')->insert([
            ['id' => 1, 'categories_id' => 4040],
            ['id' => 2, 'categories_id' => 4070],
        ]);
    }

    #[Test]
    public function it_moves_releases_from_the_missing_4040_category_to_phone_other(): void
    {
        DB::table('categories')->insert([['id' => 4070], ['id' => 4999]]);

        $this->runMigration();

        $this->assertSame([1 => 4999, 2 => 4070], $this->categoriesByRelease());
    }

    #[Test]
    public function it_leaves_releases_alone_when_4040_is_a_real_category(): void
    {
        DB::table('categories')->insert([['id' => 4040], ['id' => 4070], ['id' => 4999]]);

        $this->runMigration();

        $this->assertSame([1 => 4040, 2 => 4070], $this->categoriesByRelease());
    }

    private function runMigration(): void
    {
        (require database_path('migrations/2026_10_30_115348_move_phone_other_releases_to_4999.php'))->up();
    }

    /**
     * @return array<int, int>
     */
    private function categoriesByRelease(): array
    {
        return DB::table('releases')->orderBy('id')->pluck('categories_id', 'id')
            ->map(static fn (mixed $category): int => (int) $category)
            ->all();
    }
}
