<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DropRedundantReleasesIndexesMigrationTest extends TestCase
{
    /**
     * Index set of a long-running production install (legacy names included).
     *
     * @var array<string, list<string>>
     */
    private const array PRODUCTION_INDEXES = [
        'ix_releases_adddate' => ['adddate', 'categories_id'],
        'ix_releases_adddate_id' => ['adddate', 'id'],
        'ix_releases_reqidstatus' => ['adddate'],
        'ix_releases_categories_postdate_password' => ['categories_id', 'postdate', 'passwordstatus'],
        'ix_releases_categories_postdate_admin' => ['categories_id', 'postdate'],
        'ix_releases_book_retry' => ['bookinfo_id', 'book_lookup_retry_at', 'categories_id'],
        'ix_releases_bookinfoid' => ['bookinfo_id'],
        'ix_releases_predb_id' => ['predb_id'],
        'ix_releases_searchname' => ['searchname'],
        'ix_releases_preid_searchname' => ['predb_id', 'searchname'],
        'ix_releases_imdbid_password_cat_postdate' => ['imdbid', 'passwordstatus', 'categories_id', 'postdate'],
        'ix_releases_imdbid_postdate_passwordstatus' => ['imdbid', 'postdate', 'passwordstatus'],
        'ix_releases_movieinfo_cat' => ['movieinfo_id', 'categories_id', 'passwordstatus', 'postdate'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge();

        Schema::create('releases', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('searchname')->default('');
            $table->integer('categories_id')->default(10);
            $table->smallInteger('passwordstatus')->default(-1);
            $table->dateTime('postdate')->nullable();
            $table->dateTime('adddate')->nullable();
            $table->unsignedInteger('predb_id')->default(0);
            $table->string('imdbid')->nullable();
            $table->integer('movieinfo_id')->nullable();
            $table->integer('bookinfo_id')->nullable();
            $table->dateTime('book_lookup_retry_at')->nullable();
        });
    }

    #[Test]
    public function it_drops_covered_indexes_and_narrows_movieinfo_on_a_production_layout(): void
    {
        $this->createIndexes(self::PRODUCTION_INDEXES);

        $this->migration()->up();

        $this->assertSame([
            'ix_releases_adddate',
            'ix_releases_book_retry',
            'ix_releases_categories_postdate_password',
            'ix_releases_imdbid_password_cat_postdate',
            'ix_releases_movieinfo_id',
            'ix_releases_predb_id',
            'ix_releases_searchname',
        ], $this->indexNames());
        $this->assertSame(['movieinfo_id'], $this->indexColumns('ix_releases_movieinfo_id'));
    }

    #[Test]
    public function running_up_twice_is_a_no_op(): void
    {
        $this->createIndexes(self::PRODUCTION_INDEXES);
        $migration = $this->migration();
        $migration->up();
        $afterFirstRun = $this->indexNames();

        $migration->up();

        $this->assertSame($afterFirstRun, $this->indexNames());
    }

    #[Test]
    public function it_keeps_an_index_whose_covering_index_is_missing(): void
    {
        // Fresh installs have ix_releases_password_categories_postdate instead of the
        // categories-first index, so the admin index is the only one serving category browse.
        $this->createIndexes([
            'ix_releases_password_categories_postdate' => ['passwordstatus', 'categories_id', 'postdate'],
            'ix_releases_categories_postdate_admin' => ['categories_id', 'postdate'],
            'ix_releases_bookinfo_id' => ['bookinfo_id'],
            'ix_releases_predb_id' => ['predb_id'],
            'ix_releases_preid_searchname' => ['predb_id', 'searchname'],
        ]);

        $this->migration()->up();

        $this->assertSame([
            'ix_releases_bookinfo_id',
            'ix_releases_categories_postdate_admin',
            'ix_releases_password_categories_postdate',
            'ix_releases_predb_id',
            'ix_releases_preid_searchname',
        ], $this->indexNames());
    }

    #[Test]
    public function down_restores_the_repository_defined_indexes(): void
    {
        $this->createIndexes(self::PRODUCTION_INDEXES);
        $migration = $this->migration();
        $migration->up();

        $migration->down();
        $migration->down();

        $this->assertSame(['adddate', 'id'], $this->indexColumns('ix_releases_adddate_id'));
        $this->assertSame(['categories_id', 'postdate'], $this->indexColumns('ix_releases_categories_postdate_admin'));
        $this->assertSame(['bookinfo_id'], $this->indexColumns('ix_releases_bookinfo_id'));
        $this->assertSame(
            ['movieinfo_id', 'categories_id', 'passwordstatus', 'postdate'],
            $this->indexColumns('ix_releases_movieinfo_cat'),
        );
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_30_115347_drop_redundant_releases_indexes.php');
    }

    /**
     * @param  array<string, list<string>>  $indexes
     */
    private function createIndexes(array $indexes): void
    {
        Schema::table('releases', static function (Blueprint $table) use ($indexes): void {
            foreach ($indexes as $name => $columns) {
                $table->index($columns, $name);
            }
        });
    }

    /**
     * @return list<string>
     */
    private function indexNames(): array
    {
        return collect(Schema::getIndexes('releases'))
            ->reject(static fn (array $index): bool => $index['primary'])
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function indexColumns(string $name): array
    {
        return collect(Schema::getIndexes('releases'))->firstWhere('name', $name)['columns'] ?? [];
    }
}
