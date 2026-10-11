<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\PredbImportFeed;
use App\Facades\Search;
use App\Models\Predb;
use App\Services\Predb\Feeds\PredbFeedEntry;
use App\Services\Predb\Feeds\PredbFeedImporter;
use App\Services\Predb\Feeds\PredbFeedSourceFactory;
use App\Services\Predb\Feeds\Sources\PredbNetSource;
use App\Services\Predb\Feeds\Sources\PredbOvhSource;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class PredbImportFeedCommandTest extends TestCase
{
    private MockInterface $search;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'predb_feeds.enabled' => true,
            'predb_feeds.sources' => ['predb_club', 'predb_net', 'predb_me'],
            'predb_feeds.endpoints.predb_club' => 'https://predb.club/api/v1/',
            'predb_feeds.endpoints.predb_net' => 'https://api.predb.net/',
            'predb_feeds.endpoints.predb_me' => 'https://predb.me/?rss=1',
        ]);

        DB::purge();
        DB::reconnect();

        Schema::create('predb', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('')->unique();
            $table->string('nfo')->nullable();
            $table->string('size', 50)->nullable();
            $table->string('category')->nullable();
            $table->dateTime('predate')->nullable();
            $table->string('source', 50)->default('');
            $table->unsignedInteger('requestid')->default(0);
            $table->unsignedInteger('groups_id')->default(0);
            $table->tinyInteger('nuked')->default(0);
            $table->string('nukereason')->nullable();
            $table->string('files', 50)->nullable();
            $table->string('filename')->default('');
            $table->boolean('searched')->default(false);
        });

        $this->search = Search::spy();
        Http::preventStrayRequests();
    }

    #[Test]
    public function the_factory_builds_the_opt_in_predb_org_source(): void
    {
        $factory = new PredbFeedSourceFactory;

        $this->assertSame('predb_org', $factory->makeOne('predb_org')->key());
        $this->assertNotContains('predb_org', (array) config('predb_feeds.sources'));
    }

    #[Test]
    public function the_factory_builds_the_new_opt_in_sources_without_enabling_them_by_default(): void
    {
        $factory = new PredbFeedSourceFactory;

        foreach (['scnsrc_me', 'eztv', 'predb_ovh', 'apibay'] as $key) {
            $this->assertContains($key, $factory->availableKeys());
            $this->assertSame($key, $factory->makeOne($key)->key());
            $this->assertNotContains($key, (array) config('predb_feeds.sources'));
            $this->assertNotSame('', (string) config("predb_feeds.endpoints.{$key}"));
        }
    }

    #[Test]
    public function predb_ovh_sends_the_configured_page_size_and_parses_documented_rows(): void
    {
        Http::fake(['predb.ovh/*' => Http::response((string) file_get_contents($this->fixture('predb_ovh.json')), 200, ['Content-Type' => 'application/json'])]);

        $entries = (new PredbOvhSource((string) config('predb_feeds.endpoints.predb_ovh'), 100))->fetch(2);

        $this->assertCount(3, $entries);
        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), 'count=100') && str_contains($request->url(), 'page=2'));
    }

    #[Test]
    public function it_inserts_new_pres_from_every_source_and_indexes_them(): void
    {
        $this->fakeFeeds();

        $this->artisan('predb:import-feed')->assertSuccessful();

        // 3 predb.club + 1 new predb.net (the other duplicates predb.club) + 1 new predb.me RSS title.
        $this->assertSame(5, Predb::query()->count());
        $club = Predb::query()->where('title', 'WAR.2026.S01E02.FRENCH.WEB.H264-BAWLS')->firstOrFail();
        $this->assertSame('predb.club', $club->source);
        $this->assertSame('TV-SD-FR', $club->category);
        $this->assertSame('457MB', $club->size);
        $this->assertSame(Predb::PRE_NUKED, (int) Predb::query()->where('title', 'Some.Movie.2026.1080p.WEB.H264-NUKED')->value('nuked'));
        $this->assertSame('predb.me', Predb::query()->where('title', 'Elsbeth.S04E01.1080p.HDTV.x264-SYNCOPY')->value('source'));

        $this->search->shouldHaveReceived('insertPredb')->times(5);
    }

    #[Test]
    public function existing_rows_only_gain_missing_details(): void
    {
        DB::table('predb')->insert([
            'title' => 'WAR.2026.S01E02.FRENCH.WEB.H264-BAWLS',
            'source' => '#PreNNTmux',
            'category' => 'TV-IRC',
            'size' => null,
            'predate' => '2026-10-09 02:39:00',
        ]);
        $this->fakeFeeds();

        $this->artisan('predb:import-feed', ['--source' => ['predb_club']])->assertSuccessful();

        $row = Predb::query()->where('title', 'WAR.2026.S01E02.FRENCH.WEB.H264-BAWLS')->firstOrFail();
        $this->assertSame('#PreNNTmux', $row->source);
        $this->assertSame('TV-IRC', $row->category);
        $this->assertSame('457MB', $row->size);
        $this->assertSame('32', $row->files);
        $this->search->shouldHaveReceived('updatePreDb')->once();
    }

    #[Test]
    public function details_only_entries_fill_existing_rows_but_never_create_one(): void
    {
        DB::table('predb')->insert(['title' => 'Known.Release-GRP', 'source' => 'zenet', 'size' => null, 'files' => null]);
        $info = static fn (string $title): PredbFeedEntry => new PredbFeedEntry(title: $title, source: 'corrupt-net', size: '67MB', files: '14', enrichOnly: true);

        $result = app(PredbFeedImporter::class)->import([$info('Known.Release-GRP'), $info('Unknown.Release-GRP')]);

        $this->assertSame(['inserted' => 0, 'updated' => 1, 'skipped' => 1], $result);
        $row = Predb::query()->where('title', 'Known.Release-GRP')->firstOrFail();
        $this->assertSame(['zenet', '67MB', '14'], [$row->source, $row->size, $row->files]);
        $this->assertFalse(Predb::query()->where('title', 'Unknown.Release-GRP')->exists());
    }

    #[Test]
    public function predb_net_history_stops_at_its_page_cap_instead_of_failing(): void
    {
        Http::fake(['api.predb.net/*' => Http::response(['status' => 'error', 'message' => 'Invalid page number > Min: 1 > Max: 100'], 400)]);

        $this->assertSame([], app(PredbFeedSourceFactory::class)->makeOne('predb_net')->fetch(PredbNetSource::MAX_PAGE + 1));
        Http::assertNothingSent();
    }

    #[Test]
    public function a_failing_source_does_not_stop_the_others(): void
    {
        Http::fake([
            'predb.club/*' => Http::response('down', 503),
            'api.predb.net/*' => Http::response((string) file_get_contents($this->fixture('predb_net.json'))),
            'predb.me/*' => Http::response('', 500),
        ]);

        $this->artisan('predb:import-feed')->assertSuccessful();

        $this->assertSame(2, Predb::query()->count());
    }

    #[Test]
    public function it_fails_when_every_source_fails(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);

        $this->artisan('predb:import-feed')->assertFailed();
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $this->fakeFeeds();

        $this->artisan('predb:import-feed', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Predb::query()->count());
        $this->search->shouldNotHaveReceived('insertPredb');
    }

    #[Test]
    public function unknown_sources_are_rejected(): void
    {
        $this->artisan('predb:import-feed', ['--source' => ['nope']])->assertFailed();
    }

    #[Test]
    public function the_schedule_entry_follows_the_enabled_flag(): void
    {
        $event = $this->scheduledEvent('predb:import-feed');

        config(['predb_feeds.enabled' => true]);
        $this->assertTrue($event->filtersPass($this->app));

        config(['predb_feeds.enabled' => false]);
        $this->assertFalse($event->filtersPass($this->app));
    }

    #[Test]
    public function the_scheduled_tmux_health_check_follows_its_flag(): void
    {
        $event = $this->scheduledEvent('tmux:health-check --auto-restart');

        config(['tmux.scheduled_health_check' => true]);
        $this->assertTrue($event->filtersPass($this->app));

        config(['tmux.scheduled_health_check' => false]);
        $this->assertFalse($event->filtersPass($this->app));
    }

    #[Test]
    public function since_mode_pages_back_until_entries_are_older_than_the_cutoff(): void
    {
        config(['predb_feeds.request_delay_ms' => 0]);
        $requested = [];
        Http::fake(function (Request $request) use (&$requested) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);
            $requested[] = $page;
            $preAt = now()->subDays($page * 5)->getTimestamp();

            return Http::response(['data' => ['rows' => [['name' => 'Release.Page'.$page.'-GRP', 'cat' => 'TV', 'size' => 1, 'files' => 1, 'preAt' => $preAt, 'nuke' => null]]]]);
        });

        $this->artisan('predb:import-feed', ['--source' => ['predb_club'], '--since' => '14d'])->assertSuccessful();

        // Pages 1-2 are newer than 14 days; page 3 (15 days old) crosses the cutoff and is the last one fetched.
        $this->assertSame([1, 2, 3], $requested);
        $this->assertSame(3, Predb::query()->count());
    }

    #[Test]
    public function rate_limited_pages_are_retried_after_backing_off(): void
    {
        config(['predb_feeds.request_delay_ms' => 0, 'predb_feeds.rate_limit_wait_seconds' => 0]);
        Http::fakeSequence('predb.club/*')
            ->push(['message' => 'rate limit exceeded'], 429)
            ->push((string) file_get_contents($this->fixture('predb_club.json')));

        $this->artisan('predb:import-feed', ['--source' => ['predb_club']])->assertSuccessful();

        $this->assertSame(3, Predb::query()->count());
    }

    #[Test]
    public function an_invalid_since_value_is_rejected(): void
    {
        $this->artisan('predb:import-feed', ['--since' => 'soon'])->assertFailed();
    }

    #[Test]
    public function a_429_is_left_to_the_backoff_loop_instead_of_being_retried_immediately(): void
    {
        config(['predb_feeds.request_delay_ms' => 0, 'predb_feeds.rate_limit_retries' => 0]);
        Http::fakeSequence('predb.club/*')
            ->push(['message' => 'rate limit exceeded'], 429)
            ->push((string) file_get_contents($this->fixture('predb_club.json')));

        $this->artisan('predb:import-feed', ['--source' => ['predb_club']])->assertFailed();

        Http::assertSentCount(1);
    }

    #[Test]
    public function client_errors_are_not_retried_but_server_errors_are(): void
    {
        Http::fakeSequence('predb.club/*')->push('missing', 404);
        $this->artisan('predb:import-feed', ['--source' => ['predb_club']])->assertFailed();
        Http::assertSentCount(1);

        Http::fakeSequence('api.predb.net/*')
            ->push('oops', 503)
            ->push((string) file_get_contents($this->fixture('predb_net.json')));
        $this->artisan('predb:import-feed', ['--source' => ['predb_net']])->assertSuccessful();
        $this->assertSame(2, Predb::query()->count());
    }

    #[Test]
    public function an_api_error_envelope_fails_the_source(): void
    {
        Http::fake(['predb.club/*' => Http::response(['status' => 'error', 'message' => 'maintenance', 'data' => null])]);

        $this->artisan('predb:import-feed', ['--source' => ['predb_club']])->assertFailed();
    }

    #[Test]
    public function an_unknown_predate_is_stored_as_null_and_filled_later(): void
    {
        Http::fake(['predb.me/*' => Http::response((string) file_get_contents($this->fixture('predb_me.xml')), 200, ['Content-Type' => 'application/xml'])]);
        $this->artisan('predb:import-feed', ['--source' => ['predb_me']])->assertSuccessful();
        $this->assertNull(Predb::query()->where('title', 'Nybble_and_Nibble_CD32_AMIGA-bADkARMA')->value('predate'));

        Http::fake(['predb.club/*' => Http::response((string) file_get_contents($this->fixture('predb_club.json')))]);
        $this->artisan('predb:import-feed', ['--source' => ['predb_club']])->assertSuccessful();
        $this->assertNotNull(Predb::query()->where('title', 'Nybble_and_Nibble_CD32_AMIGA-bADkARMA')->value('predate'));
    }

    #[Test]
    public function database_failures_fail_the_run_instead_of_being_counted_as_skipped(): void
    {
        Schema::drop('predb');
        $this->fakeFeeds();

        $this->artisan('predb:import-feed', ['--source' => ['predb_club']])->assertFailed();
    }

    #[Test]
    public function filling_missing_fields_never_overwrites_a_value_written_meanwhile(): void
    {
        DB::table('predb')->insert(['title' => 'Race-GRP', 'source' => '#PreNNTmux', 'size' => '5MB', 'category' => null]);
        $id = (int) DB::table('predb')->where('title', 'Race-GRP')->value('id');

        $changed = (new ReflectionMethod(PredbFeedImporter::class, 'applyChanges'))
            ->invoke(app(PredbFeedImporter::class), $id, ['size' => '1MB', 'category' => 'TV']);

        $this->assertTrue($changed);
        $this->assertSame('5MB', DB::table('predb')->where('id', $id)->value('size'));
        $this->assertSame('TV', DB::table('predb')->where('id', $id)->value('category'));
    }

    #[Test]
    public function a_nuke_change_only_applies_while_the_observed_status_is_unchanged(): void
    {
        DB::table('predb')->insert(['title' => 'Nuke-GRP', 'source' => '#PreNNTmux', 'nuked' => Predb::PRE_UNNUKED]);
        $id = (int) DB::table('predb')->where('title', 'Nuke-GRP')->value('id');

        // The feed planned NONUKE -> NUKED, but IRC has since recorded UNNUKED.
        $changed = (new ReflectionMethod(PredbFeedImporter::class, 'applyChanges'))
            ->invoke(app(PredbFeedImporter::class), $id, ['nuked' => Predb::PRE_NUKED, 'nukereason' => 'dupe', 'nuked_from' => Predb::PRE_NONUKE]);

        $this->assertFalse($changed);
        $this->assertSame(Predb::PRE_UNNUKED, (int) DB::table('predb')->where('id', $id)->value('nuked'));
    }

    #[Test]
    public function a_missing_nuke_reason_is_filled_when_the_status_already_matches(): void
    {
        DB::table('predb')->insert(['title' => 'Some.Movie.2026.1080p.WEB.H264-NUKED', 'source' => '#PreNNTmux', 'nuked' => Predb::PRE_NUKED, 'nukereason' => null]);
        Http::fake(['predb.club/*' => Http::response((string) file_get_contents($this->fixture('predb_club.json')))]);

        $this->artisan('predb:import-feed', ['--source' => ['predb_club']])->assertSuccessful();

        $this->assertSame('dupe.2026-10-01', Predb::query()->where('title', 'Some.Movie.2026.1080p.WEB.H264-NUKED')->value('nukereason'));
    }

    #[Test]
    public function rate_limit_waits_use_retry_after_or_the_reset_header_without_shortening(): void
    {
        $wait = new ReflectionMethod(PredbImportFeed::class, 'rateLimitWaitSeconds');
        $instance = app(PredbImportFeed::class);

        $this->assertSame(1200, $wait->invoke($instance, '1200', null, 60));
        $this->assertEqualsWithDelta(300, $wait->invoke($instance, now()->addSeconds(300)->toRfc7231String(), null, 60), 2);
        $this->assertEqualsWithDelta(120, $wait->invoke($instance, null, (string) now()->addSeconds(120)->getTimestamp(), 60), 2);
        $this->assertSame(60, $wait->invoke($instance, null, null, 60));
        $this->assertSame(60, $wait->invoke($instance, 'not-a-date', null, 60));
    }

    #[Test]
    public function a_rate_limit_longer_than_the_max_wait_fails_the_source_without_retrying_early(): void
    {
        config(['predb_feeds.request_delay_ms' => 0, 'predb_feeds.rate_limit_max_wait_seconds' => 900]);
        Http::fake(['predb.club/*' => Http::response(['message' => 'rate limit exceeded'], 429, ['Retry-After' => '3600'])]);

        $this->artisan('predb:import-feed', ['--source' => ['predb_club']])->assertFailed();

        Http::assertSentCount(1);
    }

    #[Test]
    public function impossible_or_trailing_since_dates_are_rejected(): void
    {
        $this->artisan('predb:import-feed', ['--since' => '2026-02-31'])->assertFailed();
        $this->artisan('predb:import-feed', ['--since' => '2026-09-25garbage'])->assertFailed();
    }

    #[Test]
    public function a_dry_run_counts_a_title_seen_twice_as_one_insert(): void
    {
        $importer = app(PredbFeedImporter::class);
        $first = new PredbFeedEntry(title: 'Dup.Title-GRP', source: 'predb.club', category: 'TV');
        $second = new PredbFeedEntry(title: 'Dup.Title-GRP', source: 'predb.net', category: 'TV-HD', size: '5MB');

        $result = $importer->import([$first, $second, $first], dryRun: true);

        $this->assertSame(['inserted' => 1, 'updated' => 1, 'skipped' => 1], $result);
        $this->assertSame(0, Predb::query()->count());
    }

    #[Test]
    public function the_search_index_is_refreshed_from_the_current_row(): void
    {
        DB::table('predb')->insert(['title' => 'Index-GRP', 'source' => '#PreNNTmux', 'filename' => 'old.name', 'size' => null]);
        $id = (int) DB::table('predb')->where('title', 'Index-GRP')->value('id');
        // Simulate the IRC scraper renaming the file after the importer read the row.
        Predb::retrieved(static function (Predb $predb) use ($id): void {
            if ((int) $predb->id === $id) {
                DB::table('predb')->where('id', $id)->update(['filename' => 'new.name']);
            }
        });

        app(PredbFeedImporter::class)->import([new PredbFeedEntry(title: 'Index-GRP', source: 'predb.club', size: '5MB')]);

        $this->search->shouldHaveReceived('updatePreDb')->with(Mockery::on(static fn (array $doc): bool => $doc['filename'] === 'new.name'))->once();
    }

    #[Test]
    public function a_history_import_that_ends_before_the_cutoff_is_reported_incomplete(): void
    {
        config(['predb_feeds.request_delay_ms' => 0]);
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $rows = (int) ($query['page'] ?? 1) === 1
                ? [['name' => 'Recent-GRP', 'cat' => 'TV', 'size' => 1, 'files' => 1, 'preAt' => now()->subDay()->getTimestamp(), 'nuke' => null]]
                : [];

            return Http::response(['data' => ['rows' => $rows]]);
        });

        $this->artisan('predb:import-feed', ['--source' => ['predb_club'], '--since' => '14d'])
            ->expectsOutputToContain('incomplete')
            ->assertSuccessful();
    }

    #[Test]
    public function a_reason_update_keeps_a_reason_written_meanwhile(): void
    {
        DB::table('predb')->insert(['title' => 'Reason-GRP', 'source' => '#PreNNTmux', 'nuked' => Predb::PRE_NUKED, 'nukereason' => 'irc.newer']);
        $id = (int) DB::table('predb')->where('title', 'Reason-GRP')->value('id');

        $changed = (new ReflectionMethod(PredbFeedImporter::class, 'applyChanges'))
            ->invoke(app(PredbFeedImporter::class), $id, ['nukereason' => 'feed.reason', 'nuked_status' => Predb::PRE_NUKED, 'nukereason_from' => null]);

        $this->assertFalse($changed);
        $this->assertSame('irc.newer', DB::table('predb')->where('id', $id)->value('nukereason'));
    }

    #[Test]
    public function a_successful_response_without_the_expected_structure_fails_the_source(): void
    {
        Http::fake([
            'predb.club/*' => Http::response('<html>proxy error</html>'),
            'api.predb.net/*' => Http::response(['status' => 'success', 'unexpected' => true]),
        ]);

        $this->artisan('predb:import-feed', ['--source' => ['predb_club', 'predb_net']])->assertFailed();
    }

    #[Test]
    public function an_application_rate_limit_envelope_is_backed_off_and_retried(): void
    {
        config(['predb_feeds.request_delay_ms' => 0, 'predb_feeds.rate_limit_wait_seconds' => 0]);
        Http::fakeSequence('predb.club/*')
            ->push(['status' => 'error', 'message' => 'Rate limit exceeded, try again later', 'data' => null])
            ->push((string) file_get_contents($this->fixture('predb_club.json')));

        $this->artisan('predb:import-feed', ['--source' => ['predb_club']])->assertSuccessful();

        $this->assertSame(3, Predb::query()->count());
    }

    #[Test]
    public function xrel_requests_are_clamped_to_its_minimum_page_size(): void
    {
        config(['predb_feeds.page_size' => 2]);
        Http::fake(['api.xrel.to/*' => Http::response(['pagination' => ['total_pages' => 1], 'list' => []])]);

        $this->artisan('predb:import-feed', ['--source' => ['xrel']])->assertSuccessful();

        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), 'per_page=5'));
    }

    #[Test]
    public function malformed_collections_fail_every_source(): void
    {
        Http::fake([
            'predb.club/*' => Http::response(['status' => 'success', 'data' => ['rows' => ['unexpected' => 'object']]]),
            'api.predb.net/*' => Http::response(['status' => 'success', 'data' => ['a' => 1]]),
            'api.srrdb.com/*' => Http::response('<html>blocked</html>'),
            'api.xrel.to/*' => Http::response(['pagination' => ['total_pages' => 3]]),
        ]);

        $this->artisan('predb:import-feed', ['--source' => ['predb_club', 'predb_net', 'srrdb', 'xrel']])->assertFailed();
    }

    #[Test]
    public function undated_history_pages_are_reported_incomplete(): void
    {
        Http::fake(['predb.me/*' => Http::response('<?xml version="1.0"?><rss version="2.0"><channel><item><title>No.Date-GRP</title></item></channel></rss>', 200, ['Content-Type' => 'application/xml'])]);

        $this->artisan('predb:import-feed', ['--source' => ['predb_me'], '--since' => '14d'])
            ->expectsOutputToContain('incomplete')
            ->assertSuccessful();
    }

    #[Test]
    public function a_dry_run_matches_titles_case_insensitively_like_the_database(): void
    {
        $result = app(PredbFeedImporter::class)->import([
            new PredbFeedEntry(title: 'Release-GRP', source: 'predb.club'),
            new PredbFeedEntry(title: 'release-grp', source: 'predb.net'),
        ], dryRun: true);

        $this->assertSame(1, $result['inserted']);
    }

    #[Test]
    public function since_mode_pages_by_listing_time_when_a_source_has_no_pre_time(): void
    {
        // srrDB is single-page by default; lift that here to exercise listedAt-based paging.
        config(['predb_feeds.request_delay_ms' => 0, 'predb_feeds.single_page_sources' => []]);
        $requested = [];
        Http::fake(function (Request $request) use (&$requested) {
            $requested[] = $request->url();
            $skip = preg_match('/skip:(\d+)/', $request->url(), $m) === 1 ? (int) $m[1] : 0;
            $days = $skip === 0 ? 1 : 20;

            return Http::response(['results' => [['release' => 'Srr.Page'.$skip.'-GRP', 'date' => now('Europe/Brussels')->subDays($days)->format('Y-m-d H:i:s'), 'size' => 1]]]);
        });

        $this->artisan('predb:import-feed', ['--source' => ['srrdb'], '--since' => '14d'])->assertSuccessful();

        $this->assertCount(2, $requested);
        $this->assertNull(Predb::query()->where('title', 'Srr.Page0-GRP')->value('predate'));
    }

    #[Test]
    public function an_unchanged_existing_entry_is_re_indexed_from_the_current_row(): void
    {
        DB::table('predb')->insert(['title' => 'Known-GRP', 'source' => '#PreNNTmux', 'category' => 'TV', 'filename' => 'old.mkv']);
        $id = (int) DB::table('predb')->where('title', 'Known-GRP')->value('id');
        // Simulate the IRC scraper renaming the file right after the importer's lookup.
        Predb::retrieved(static function (Predb $predb) use ($id): void {
            if ((int) $predb->id === $id) {
                DB::table('predb')->where('id', $id)->update(['filename' => 'known.mkv']);
            }
        });

        $result = app(PredbFeedImporter::class)->import([new PredbFeedEntry(title: 'Known-GRP', source: 'predb.club', category: 'TV')]);

        $this->assertSame(1, $result['skipped']);
        $this->search->shouldHaveReceived('updatePreDb')->with(Mockery::on(static fn (array $doc): bool => $doc['title'] === 'Known-GRP' && $doc['filename'] === 'known.mkv'))->once();
    }

    #[Test]
    public function srrdb_is_never_paged_deeply_and_is_not_a_default_source(): void
    {
        config(['predb_feeds.request_delay_ms' => 0]);
        Http::fake(['api.srrdb.com/*' => Http::response(['results' => [['release' => 'Srr.Only-GRP', 'date' => now('Europe/Brussels')->subDay()->format('Y-m-d H:i:s')]]])]);

        $this->artisan('predb:import-feed', ['--source' => ['srrdb'], '--since' => '14d'])
            ->expectsOutputToContain('incomplete')
            ->assertSuccessful();
        $this->artisan('predb:import-feed', ['--source' => ['srrdb'], '--pages' => 5])->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertNotContains('srrdb', (require base_path('config/predb_feeds.php'))['sources']);
    }

    private function fakeFeeds(): void
    {
        Http::fake([
            'predb.club/*' => Http::response((string) file_get_contents($this->fixture('predb_club.json'))),
            'api.predb.net/*' => Http::response((string) file_get_contents($this->fixture('predb_net.json'))),
            'predb.me/*' => Http::response((string) file_get_contents($this->fixture('predb_me.xml')), 200, ['Content-Type' => 'application/xml']),
        ]);
    }

    #[Test]
    public function the_predb_full_text_name_pass_runs_on_its_own_schedule(): void
    {
        $event = $this->scheduledEvent('multiprocessing:fixrelnames predbft');

        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);

        // One launcher only: the scheduled run stops when the tmux cycle runs the pass instead, whatever a
        // legacy TMUX_FIX_NAMES_PREDBFT=true says.
        config(['tmux.fix_names.predbft' => true, 'tmux.fix_names.predbft_scheduled' => true]);
        $this->assertTrue($event->filtersPass($this->app));
        config(['tmux.fix_names.predbft_scheduled' => false]);
        $this->assertFalse($event->filtersPass($this->app));

        // An operator who turned the pass off keeps it off.
        config(['tmux.fix_names.predbft' => false, 'tmux.fix_names.predbft_scheduled' => true]);
        $this->assertFalse($event->filtersPass($this->app));
    }

    #[Test]
    public function releases_are_linked_to_late_arriving_predb_titles_on_a_schedule(): void
    {
        $event = $this->scheduledEvent('predb:check 3');

        $this->assertSame('*/10 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    private function scheduledEvent(string $command): Event
    {
        foreach ($this->app->make(Schedule::class)->events() as $event) {
            if (str_contains((string) $event->command, $command)) {
                return $event;
            }
        }

        $this->fail("No scheduled event for [{$command}].");
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__).'/Fixtures/predb/'.$name;
    }
}
