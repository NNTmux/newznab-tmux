<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UsenetGroup;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class AdminListPerformancePlanTest extends TestCase
{
    private string $databasePath = '';

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-admin-list-performance-test.sqlite';

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }

        parent::tearDown();
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    public function test_release_admin_list_uses_versioned_cache_keys(): void
    {
        $modelPath = app_path('Models/Release.php');

        $this->assertFileExists($modelPath);

        $content = file_get_contents($modelPath);

        $this->assertStringContainsString('adminReleasesRangeVersion', $content);
        $this->assertStringContainsString(".'_'.(\$categoryId ?? 'all')", $content);
        $this->assertStringContainsString('Cache::forever(\'adminReleasesRangeVersion\'', $content);
    }

    public function test_group_admin_list_no_longer_groups_by_id(): void
    {
        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('description')->default('');
            foreach (['first_record_postdate', 'last_record_postdate', 'last_updated'] as $field) {
                $table->dateTime($field)->nullable();
            }
            foreach (['active', 'backfill', 'minfilestoformrelease', 'minsizetoformrelease', 'backfill_target'] as $field) {
                $table->integer($field)->default(0);
            }
        });
        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('groups_id')->index();
        });
        DB::table('usenet_groups')->insert(['name' => 'alt.binaries.z']);
        $firstId = DB::table('usenet_groups')->insertGetId(['name' => 'alt.binaries.a']);
        DB::table('releases')->insert(['groups_id' => $firstId]);
        config(['nntmux.items_per_page' => 1]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $page = UsenetGroup::getGroupsRange('', null, true);
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $groupQuery = $queries->first(static fn (array $query): bool => str_contains($query['query'], 'from "usenet_groups"') && ! str_contains($query['query'], 'count(*)'));
        $countQuery = $queries->first(static fn (array $query): bool => str_contains($query['query'], 'from "releases"'));

        $this->assertSame('alt.binaries.a', $page->items()[0]->name);
        $this->assertSame(2, $page->total());
        $this->assertSame(1, $page->items()[0]->num_releases);
        $this->assertNotNull($groupQuery);
        $this->assertStringNotContainsString('group by', strtolower($groupQuery['query']));
        $this->assertNotNull($countQuery);
        $this->assertSame([$firstId], $countQuery['bindings']);
        $this->assertStringContainsString('where "groups_id" in (?)', $countQuery['query']);
    }

    public function test_admin_list_index_migration_contains_narrow_indexes(): void
    {
        $migrationPath = database_path('migrations/2026_07_13_000000_add_admin_list_performance_indexes.php');

        $this->assertFileExists($migrationPath);

        $content = file_get_contents($migrationPath);

        $this->assertStringContainsString('ix_releases_categories_postdate_admin', $content);
        $this->assertStringContainsString('ix_releases_postdate_admin', $content);
        $this->assertStringContainsString('ix_usenet_groups_active_name_admin', $content);
        $this->assertStringContainsString('ix_release_reports_status_created_admin', $content);
    }
}
