<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AdditionalProcessing\AdditionalCandidateQuery;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class AdditionalCandidateQueryTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-additional-candidate-query-test', '.sqlite');

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('categorizeforeign', '0'), ('catwebdl', '0')");

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'nntmux_settings.check_passworded_rars' => true,
            'nntmux_settings.unrar_path' => '/usr/bin/unrar',
        ]);

        DB::purge();
        DB::reconnect();

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_bucket_chars_preserve_alphabetic_guid_buckets(): void
    {
        DB::table('categories')->insert([
            ['id' => 1, 'disablepreview' => 0],
            ['id' => 2, 'disablepreview' => 1],
        ]);

        DB::table('releases')->insert([
            $this->releaseRow(1, '0'),
            $this->releaseRow(2, '9'),
            $this->releaseRow(3, 'a'),
            $this->releaseRow(4, 'a'),
            $this->releaseRow(5, 'f'),
            $this->releaseRow(6, 'b', categoriesId: 2),
        ]);

        $chars = AdditionalCandidateQuery::bucketChars();
        sort($chars);

        $this->assertSame(['0', '9', 'a', 'f'], $chars);
    }

    public function test_bucket_chars_skip_active_claims_but_include_stale_claims(): void
    {
        DB::table('categories')->insert([
            ['id' => 1, 'disablepreview' => 0],
        ]);

        DB::table('releases')->insert([
            $this->releaseRow(1, 'a', claimedAt: now()),
            $this->releaseRow(2, 'b', claimedAt: now()->subSeconds(301)),
            $this->releaseRow(3, 'c'),
        ]);

        $chars = AdditionalCandidateQuery::bucketChars();
        sort($chars);

        $this->assertSame(['b', 'c'], $chars);
    }

    public function test_monitor_builder_can_include_claimed_releases_while_available_builder_excludes_them(): void
    {
        DB::table('categories')->insert([
            ['id' => 1, 'disablepreview' => 0],
        ]);

        DB::table('releases')->insert([
            $this->releaseRow(1, 'a', claimedAt: now()),
            $this->releaseRow(2, 'b'),
        ]);

        $this->assertSame(1, AdditionalCandidateQuery::baseBuilder()->count());
        $this->assertSame(2, AdditionalCandidateQuery::baseBuilder(includeClaimed: true)->count());
    }

    public function test_backlog_counts_are_aggregated_by_bucket_in_one_shape(): void
    {
        DB::table('categories')->insert(['id' => 1, 'disablepreview' => 0]);
        DB::table('releases')->insert([
            $this->releaseRow(1, 'a'),
            $this->releaseRow(2, 'a', claimedAt: now()),
            $this->releaseRow(3, 'b', claimedAt: now()->subSeconds(301)),
        ]);

        $this->assertSame([
            ['bucket' => 'a', 'total' => 2, 'available' => 1],
            ['bucket' => 'b', 'total' => 1, 'available' => 1],
        ], AdditionalCandidateQuery::bucketBacklog());
        $this->assertSame(['total' => 3, 'available' => 2], AdditionalCandidateQuery::backlogCounts());
        $this->assertSame([
            ['bucket' => 'a', 'count' => 1],
            ['bucket' => 'b', 'count' => 1],
        ], AdditionalCandidateQuery::availableBucketCounts());
    }

    public function test_claim_batch_excludes_active_claims_and_recovers_stale_claims(): void
    {
        DB::table('categories')->insert([
            ['id' => 1, 'disablepreview' => 0],
        ]);

        DB::table('releases')->insert([
            $this->releaseRow(1, 'a', postdate: '2026-07-12 10:00:00'),
            $this->releaseRow(2, 'a', postdate: '2026-07-12 09:00:00'),
        ]);

        $first = AdditionalCandidateQuery::claimBatch('a', 1, 'token-one', columns: ['id']);
        $this->assertSame([1], $first->pluck('id')->all());

        $second = AdditionalCandidateQuery::claimBatch('a', 10, 'token-two', columns: ['id']);
        $this->assertSame([2], $second->pluck('id')->all());

        DB::table('releases')
            ->where('id', 1)
            ->update(['additional_pp_claimed_at' => now()->subSeconds(301)]);

        $third = AdditionalCandidateQuery::claimBatch('a', 10, 'token-three', columns: ['id']);
        $this->assertSame([1], $third->pluck('id')->all());
    }

    public function test_password_inspection_enabled_selects_pending_releases(): void
    {
        config([
            'nntmux_settings.check_passworded_rars' => true,
            'nntmux_settings.unrar_path' => '/usr/bin/unrar',
        ]);

        DB::table('categories')->insert(['id' => 1, 'disablepreview' => 0]);
        DB::table('releases')->insert([
            $this->releaseRow(1, 'a', passwordStatus: -1),
            $this->releaseRow(2, 'b', passwordStatus: 0),
        ]);

        $this->assertSame([1], AdditionalCandidateQuery::baseBuilder()->pluck('r.id')->all());
    }

    public function test_password_inspection_disabled_selects_unprocessed_no_password_releases(): void
    {
        config([
            'nntmux_settings.check_passworded_rars' => false,
            'nntmux_settings.unrar_path' => false,
        ]);

        DB::table('categories')->insert(['id' => 1, 'disablepreview' => 0]);
        DB::table('releases')->insert([
            $this->releaseRow(1, 'a', passwordStatus: 0),
            $this->releaseRow(2, 'b', passwordStatus: -1),
            $this->releaseRow(3, 'c', passwordStatus: 0, hasPreview: 0),
        ]);

        $this->assertSame([1], AdditionalCandidateQuery::baseBuilder()->pluck('r.id')->all());
        $this->assertSame([
            ['bucket' => 'a', 'total' => 1, 'available' => 1],
        ], AdditionalCandidateQuery::bucketBacklog());
        $this->assertSame([1], AdditionalCandidateQuery::claimBatch('a', 25, 'worker', columns: ['id'])->pluck('id')->all());
    }

    public function test_password_inspection_without_usable_unrar_selects_no_password_state(): void
    {
        config([
            'nntmux_settings.check_passworded_rars' => true,
            'nntmux_settings.unrar_path' => '',
        ]);

        DB::table('categories')->insert(['id' => 1, 'disablepreview' => 0]);
        DB::table('releases')->insert($this->releaseRow(1, 'a', passwordStatus: 0));

        $this->assertSame([1], AdditionalCandidateQuery::baseBuilder()->pluck('r.id')->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseRow(
        int $id,
        string $leftguid,
        int $categoriesId = 1,
        ?\DateTimeInterface $claimedAt = null,
        string $postdate = '2026-07-12 00:00:00',
        int $passwordStatus = -1,
        int $hasPreview = -1,
    ): array {
        return [
            'id' => $id,
            'guid' => $leftguid.'-guid-'.$id,
            'leftguid' => $leftguid,
            'passwordstatus' => $passwordStatus,
            'haspreview' => $hasPreview,
            'nzbstatus' => 1,
            'categories_id' => $categoriesId,
            'size' => 2 * 1048576,
            'postdate' => $postdate,
            'additional_pp_claimed_at' => $claimedAt?->format('Y-m-d H:i:s'),
            'additional_pp_claim_token' => $claimedAt === null ? null : 'claimed',
        ];
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

    private function createSchema(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->text('value')->nullable();
            });
        }

        DB::table('settings')->upsert([
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
            ['name' => 'releaseprocessingtimeout', 'value' => '120'],
        ], ['name'], ['value']);

        Schema::dropIfExists('releases');
        Schema::dropIfExists('categories');

        Schema::create('categories', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->boolean('disablepreview')->default(false);
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('guid');
            $table->char('leftguid', 1);
            $table->integer('passwordstatus');
            $table->integer('haspreview');
            $table->integer('nzbstatus');
            $table->unsignedInteger('categories_id');
            $table->unsignedBigInteger('size');
            $table->dateTime('postdate')->nullable();
            $table->timestamp('additional_pp_claimed_at')->nullable();
            $table->string('additional_pp_claim_token', 64)->nullable();
        });
    }
}
