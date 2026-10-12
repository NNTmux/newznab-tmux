<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Services\Nzb\NzbArticleFingerprint;
use App\Services\Nzb\NzbImportService;
use App\Services\Nzb\NzbService;
use App\Services\Releases\ReleaseDuplicateFinder;
use App\Support\Data\ProcessReleasesSettings;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;
use Tests\Unit\Nzb\NzbArticleFingerprintTest;

class ReleaseDuplicateFinderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['release_verification.required' => false]);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'nntmux.release_dedupe_enabled' => true,
            'nntmux.release_dedupe_size_tolerance' => 0.05,
        ]);

        DB::purge();
        DB::reconnect();

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('guid')->default('');
            $table->char('article_fingerprint', 40)->nullable()->index();
            $table->string('name')->default('');
            $table->string('searchname')->default('');
            $table->string('fromname')->nullable();
            $table->unsignedInteger('predb_id')->default(0);
            $table->unsignedBigInteger('size')->default(0);
        });

        DB::table('releases')->insert([
            'name' => 'Show.S01E01.1080p.WEB.h264-GRP',
            'searchname' => 'Show.S01E01.1080p.WEB.h264-GRP',
            'fromname' => 'poster-a@example.com',
            'predb_id' => 42,
            'size' => 1_000_000_000,
        ]);
    }

    #[Test]
    public function an_existing_release_with_the_same_searchname_and_size_is_a_duplicate_by_default(): void
    {
        [$duplicate, $reason] = (new ReleaseDuplicateFinder)->findDuplicate('Show.S01E01.1080p.WEB.h264-GRP', 'Show.S01E01.1080p.WEB.h264-GRP', 42, 1_000_000_000);

        $this->assertNotNull($duplicate);
        $this->assertSame('predb_id_match', $reason);
    }

    #[Test]
    public function disabling_release_dedupe_keeps_every_upload(): void
    {
        config(['nntmux.release_dedupe_enabled' => false]);

        [$duplicate, $reason] = (new ReleaseDuplicateFinder)->findDuplicate('Show.S01E01.1080p.WEB.h264-GRP', 'Show.S01E01.1080p.WEB.h264-GRP', 42, 1_000_000_000);

        $this->assertNull($duplicate);
        $this->assertNull($reason);
    }

    #[Test]
    public function disabling_release_dedupe_keeps_a_same_poster_repost(): void
    {
        config(['nntmux.release_dedupe_enabled' => false]);

        [$duplicate] = (new ReleaseDuplicateFinder)->findDuplicate('Show.S01E01.1080p.WEB.h264-GRP', 'Show.S01E01.1080p.WEB.h264-GRP', 42, 1_000_000_000);

        $this->assertNull($duplicate);
    }

    #[Test]
    public function nzb_import_with_dedupe_disabled_rejects_only_identical_articles(): void
    {
        config(['nntmux.release_dedupe_enabled' => false]);
        DB::table('releases')->where('id', 1)->update(['guid' => 'stored-guid']);
        $stored = NzbArticleFingerprintTest::nzb([['a1@x', 'a2@x']]);

        $nzb = $this->createMock(NzbService::class);
        $nzb->method('readNzbContents')->willReturnCallback(static fn (string $guid): string|false => $guid === 'stored-guid' ? $stored : false);
        $this->app->instance(NzbService::class, $nzb);
        $import = new NzbImportService(['Browser' => true]);
        $find = new ReflectionMethod($import, 'findIdenticalArticleUpload');

        $same = NzbArticleFingerprint::fromContents(NzbArticleFingerprintTest::nzb([['a2@x', 'a1@x']]));
        $repost = NzbArticleFingerprint::fromContents(NzbArticleFingerprintTest::nzb([['b1@y', 'b2@y']]));

        $this->assertSame(1, $find->invoke($import, 1_000_000_000, $same)?->id);
        $this->assertNull($find->invoke($import, 1_000_000_000, $repost));
        $this->assertNull($find->invoke($import, 999, $same));
    }

    #[Test]
    public function nzb_import_finds_identical_articles_beyond_the_first_candidates(): void
    {
        config(['nntmux.release_dedupe_enabled' => false]);
        DB::table('releases')->insert(array_map(static fn (int $i): array => ['guid' => 'other-'.$i, 'name' => 'repost', 'size' => 1_000_000_000], range(1, 120)));
        DB::table('releases')->insert(['guid' => 'stored-guid', 'name' => 'repost', 'size' => 1_000_000_000]);
        $stored = NzbArticleFingerprintTest::nzb([['a1@x', 'a2@x']]);
        $other = NzbArticleFingerprintTest::nzb([['o1@z']]);

        $nzb = $this->createMock(NzbService::class);
        $nzb->method('readNzbContents')->willReturnCallback(static fn (string $guid): string => $guid === 'stored-guid' ? $stored : $other);
        $this->app->instance(NzbService::class, $nzb);
        $import = new NzbImportService(['Browser' => true]);

        $match = (new ReflectionMethod($import, 'findIdenticalArticleUpload'))->invoke($import, 1_000_000_000, NzbArticleFingerprint::fromContents($stored));

        $this->assertSame('stored-guid', $match?->guid);
    }

    #[Test]
    public function nzb_import_matches_a_persisted_fingerprint_whatever_the_size(): void
    {
        config(['nntmux.release_dedupe_enabled' => false]);
        $fingerprint = NzbArticleFingerprint::fromMessageIds(['a1@x', 'a2@x']);
        DB::table('releases')->insert(['guid' => 'fp-guid', 'name' => 'x', 'size' => 5, 'article_fingerprint' => $fingerprint]);

        $nzb = $this->createMock(NzbService::class);
        $nzb->expects($this->never())->method('readNzbContents');
        $nzb->method('nzbPath')->willReturn('/stored/fp-guid.nzb.gz');
        $this->app->instance(NzbService::class, $nzb);
        $import = new NzbImportService(['Browser' => true]);
        $find = new ReflectionMethod($import, 'findIdenticalArticleUpload');

        $this->assertSame('fp-guid', $find->invoke($import, 1_000_000_000, $fingerprint)?->guid);
        $this->assertSame('fp-guid', $find->invoke($import, 0, $fingerprint)?->guid);
    }

    #[Test]
    public function a_persisted_fingerprint_without_a_stored_nzb_is_ignored(): void
    {
        config(['nntmux.release_dedupe_enabled' => false]);
        $fingerprint = NzbArticleFingerprint::fromMessageIds(['a1@x', 'a2@x']);
        DB::table('releases')->insert(['guid' => 'crashed-guid', 'name' => 'x', 'size' => 5, 'article_fingerprint' => $fingerprint]);

        $nzb = $this->createMock(NzbService::class);
        $nzb->method('nzbPath')->willReturn(false);
        $this->app->instance(NzbService::class, $nzb);
        $import = new NzbImportService(['Browser' => true]);

        $this->assertNull((new ReflectionMethod($import, 'findIdenticalArticleUpload'))->invoke($import, 0, $fingerprint));
    }

    #[Test]
    public function concurrent_imports_of_the_same_articles_are_serialised_by_a_lock(): void
    {
        config(['cache.default' => 'file', 'nntmux.release_dedupe_lock_store' => 'array']);
        $this->app->instance(NzbService::class, $this->createMock(NzbService::class));
        $import = new NzbImportService(['Browser' => true]);
        (new ReflectionProperty($import, 'identityLockWaitSeconds'))->setValue($import, 0);
        $withLock = new ReflectionMethod($import, 'withArticleIdentityLock');

        $this->assertSame('ran', $withLock->invoke($import, 'abc', static fn (): string => 'ran'));

        $store = Cache::store('array')->getStore();
        $this->assertInstanceOf(LockProvider::class, $store);
        $held = $store->lock('nzb-import-article:abc', 60);
        $this->assertTrue($held->get());
        $ran = false;
        try {
            $withLock->invoke($import, 'abc', static function () use (&$ran): void {
                $ran = true;
            });
            $this->fail('Expected the held lock to block the second import.');
        } catch (LockTimeoutException) {
            $this->assertFalse($ran);
        } finally {
            $held->release();
        }

        $this->assertSame('no-lock', $withLock->invoke($import, null, static fn (): string => 'no-lock'));
    }

    #[Test]
    public function a_failed_fingerprint_write_removes_the_inserted_release(): void
    {
        DB::statement("CREATE TRIGGER block_fingerprint BEFORE UPDATE OF article_fingerprint ON releases BEGIN SELECT RAISE(ABORT, 'boom'); END");
        $search = Search::spy();
        $this->app->instance(NzbService::class, $this->createMock(NzbService::class));
        $import = new NzbImportService(['Browser' => true]);

        try {
            (new ReflectionMethod($import, 'persistArticleFingerprint'))->invoke($import, 1, NzbArticleFingerprint::fromMessageIds(['a1@x']));
            $this->fail('Expected the fingerprint write to fail.');
        } catch (QueryException) {
            $this->assertSame(0, DB::table('releases')->where('id', 1)->count());
            $search->shouldHaveReceived('deleteRelease')->with(1)->once();
        }
    }

    #[Test]
    public function disabling_release_dedupe_also_disables_cross_post_cleanup(): void
    {
        $settings = new ProcessReleasesSettings(crossPostTime: 2);
        $this->assertTrue($settings->hasCrossPostDetection());

        config(['nntmux.release_dedupe_enabled' => false]);

        $this->assertFalse($settings->hasCrossPostDetection());
    }
}
