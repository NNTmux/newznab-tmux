<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConfigurationDomain;
use App\Exceptions\BookProviderException;
use App\Facades\Search;
use App\Models\Category;
use App\Services\BookProcessingCandidateQuery;
use App\Services\BookService;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\GoogleBooksService;
use App\Services\IsbnDbService;
use App\Services\ItunesService;
use App\Services\OpenLibraryService;
use App\Services\Runners\PostProcessRunner;
use App\Services\Tmux\Tmux;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PDO;
use Tests\Support\ConfigurationTestBuilder;
use Tests\TestCase;

class BookProcessingRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConfigurationTestBuilder::installDefaults();
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, ['book_lookup' => 1]);
        Cache::flush();

        Schema::create('releases', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->default('Clean Code by Robert C Martin EPUB');
            $table->string('searchname')->default('Robert C Martin Clean Code');
            $table->integer('categories_id')->default(Category::BOOKS_EBOOK);
            $table->integer('bookinfo_id')->nullable();
            $table->integer('musicinfo_id')->nullable();
            $table->integer('consoleinfo_id')->nullable();
            $table->integer('gamesinfo_id')->default(0);
            $table->boolean('isrenamed')->default(true);
            $table->string('leftguid')->default('a');
            $table->dateTime('postdate')->nullable();
            $table->integer('groups_id')->default(1);
        });
        $this->stateMigration()->up();
        Search::shouldReceive('updateRelease')->zeroOrMoreTimes();
        Search::shouldReceive('isAvailable')->andReturnTrue();
        Search::shouldReceive('searchSecondary')->andReturn(['id' => []]);
    }

    public function test_provider_failure_is_deferred_and_does_not_dispatch_another_job(): void
    {
        DB::table('releases')->insert(['id' => 1]);
        $service = $this->failingService(1, 7200);
        $service->processBookReleases();
        $service->processBookReleases();

        $release = DB::table('releases')->find(1);
        $this->assertNull($release->bookinfo_id);
        $this->assertSame(1, $release->book_lookup_attempts);
        $this->assertGreaterThanOrEqual(now()->addSeconds(7190)->toDateTimeString(), $release->book_lookup_retry_at);
        $this->assertNoDispatch();
    }

    public function test_due_failures_are_bounded_and_exhausted_books_remain_out_of_the_queue(): void
    {
        DB::table('releases')->insert(['id' => 1]);
        $service = $this->failingService(5);

        foreach ([300, 900, 3600, 21600, null] as $index => $delay) {
            DB::table('releases')->where('id', 1)->update(['book_lookup_retry_at' => now()->subMinute()]);
            $service->processBookReleases();
            $release = DB::table('releases')->find(1);
            $this->assertSame($index + 1, $release->book_lookup_attempts);
            if ($delay !== null) {
                $this->assertNull($release->bookinfo_id);
                $this->assertGreaterThanOrEqual(now()->addSeconds($delay - 10)->toDateTimeString(), $release->book_lookup_retry_at);
            }
        }

        $this->assertSame(BookProcessingCandidateQuery::EXHAUSTED, $release->bookinfo_id);
        $this->assertNull($release->book_lookup_retry_at);
        $service->processBookReleases();
        $this->assertNoDispatch();
    }

    public function test_confirmed_miss_is_terminal_without_using_the_retry_budget(): void
    {
        DB::table('releases')->insert(['id' => 1]);
        $service = $this->failingService(1, null);
        $service->processBookReleases();
        $service->processBookReleases();

        $release = DB::table('releases')->find(1);
        $this->assertSame(-2, $release->bookinfo_id);
        $this->assertSame(0, $release->book_lookup_attempts);
        $this->assertNull($release->book_lookup_retry_at);
        $this->assertNoDispatch();
    }

    public function test_a_due_retry_can_match_local_metadata_and_clear_its_retry_time(): void
    {
        Schema::create('bookinfo', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('isbn')->nullable();
            $table->string('ean')->nullable();
        });
        DB::table('bookinfo')->insert(['id' => 8, 'isbn' => '9780132350884']);
        DB::table('releases')->insert([
            'id' => 1,
            'searchname' => 'Clean Code by Robert C Martin 9780132350884',
            'book_lookup_attempts' => 2,
            'book_lookup_retry_at' => now()->subMinute(),
        ]);
        $service = $this->failingService(0);
        $service->processBookReleases();
        $service->processBookReleases();

        $release = DB::table('releases')->find(1);
        $this->assertSame(8, $release->bookinfo_id);
        $this->assertNull($release->book_lookup_retry_at);
        $this->assertNoDispatch();
    }

    public function test_disabled_book_lookup_does_not_normalize_or_fetch_metadata(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, ['book_lookup' => 0]);
        DB::table('releases')->insert(['id' => 1, 'name' => 'N:/NZB', 'searchname' => 'N:/NZB']);
        app(BookService::class)->processBookReleases();

        $release = DB::table('releases')->find(1);
        $this->assertNull($release->book_name_normalized_at);
        $this->assertNull($release->bookinfo_id);
    }

    public function test_deferred_newer_books_do_not_take_the_batch_slot_from_an_older_ready_book(): void
    {
        foreach ([1, 2, 3] as $id) {
            DB::table('releases')->insert([
                'id' => $id,
                'postdate' => now(),
                'book_lookup_retry_at' => now()->addHour(),
                'book_name_normalized_at' => now(),
            ]);
        }
        DB::table('releases')->insert(['id' => 4, 'postdate' => now()->subDay()]);
        $service = $this->failingService(1);
        $service->bookqty = 1;
        $service->processBookReleases();

        $this->assertSame(1, DB::table('releases')->where('id', 4)->value('book_lookup_attempts'));
        $this->assertSame(0, DB::table('releases')->where('id', 1)->value('book_lookup_attempts'));
    }

    public function test_completed_normalization_excludes_successful_and_failed_original_obfuscated_names(): void
    {
        foreach ([123, -2, -3] as $index => $bookId) {
            DB::table('releases')->insert([
                'id' => $index + 1,
                'bookinfo_id' => $bookId,
                'name' => 'N_NZB_[1_5]_-_Clean_Code_EPUB.rar',
                'book_name_normalized_at' => now(),
            ]);
        }
        $this->assertNoDispatch();
    }

    public function test_unparseable_normalization_is_marked_complete_even_in_renamed_only_mode(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, ['book_lookup' => 2]);
        DB::table('releases')->insert([
            'id' => 1,
            'name' => 'N:/NZB',
            'searchname' => 'N:/NZB',
            'isrenamed' => 0,
            'bookinfo_id' => -2,
        ]);
        $service = app(BookService::class);
        $service->processBookReleases();
        $service->processBookReleases();

        $this->assertNotNull(DB::table('releases')->where('id', 1)->value('book_name_normalized_at'));
        $this->assertNoDispatch();
    }

    public function test_renamed_only_worker_leaves_plain_unrenamed_books_pending(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, ['book_lookup' => 2]);
        DB::table('releases')->insert(['id' => 1, 'isrenamed' => 0]);
        app(BookService::class)->processBookReleases();

        $this->assertNull(DB::table('releases')->where('id', 1)->value('bookinfo_id'));
        $this->assertNoDispatch();
    }

    public function test_requeue_command_can_preview_and_reset_exhausted_books(): void
    {
        DB::table('releases')->insert([
            'id' => 1,
            'bookinfo_id' => BookProcessingCandidateQuery::EXHAUSTED,
            'book_lookup_attempts' => 5,
            'book_lookup_retry_at' => now()->addDay(),
            'book_name_normalized_at' => now(),
        ]);
        $this->artisan('books:requeue-failed', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(-3, DB::table('releases')->where('id', 1)->value('bookinfo_id'));
        $this->artisan('books:requeue-failed')->assertSuccessful();

        $release = DB::table('releases')->find(1);
        $this->assertNull($release->bookinfo_id);
        $this->assertSame(0, $release->book_lookup_attempts);
        $this->assertNull($release->book_lookup_retry_at);
        $this->assertNull($release->book_name_normalized_at);
        $this->assertSame(1, DB::table('releases')->whereRaw(BookProcessingCandidateQuery::workCondition(1))->count());
    }

    public function test_book_reset_clears_the_retry_and_normalization_state(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'local');
        DB::table('releases')->insert([
            'id' => 1,
            'bookinfo_id' => -3,
            'book_lookup_attempts' => 5,
            'book_lookup_retry_at' => now()->addDay(),
            'book_name_normalized_at' => now(),
        ]);
        $this->artisan('nntmux:resetpp', ['--category' => ['book']])->assertSuccessful();
        $release = DB::table('releases')->find(1);
        $this->assertNull($release->bookinfo_id);
        $this->assertSame(0, $release->book_lookup_attempts);
        $this->assertNull($release->book_lookup_retry_at);
        $this->assertNull($release->book_name_normalized_at);
    }

    public function test_tmux_count_matches_ready_work_including_audiobooks_and_normalization(): void
    {
        DB::table('releases')->insert([
            ['id' => 1, 'categories_id' => Category::BOOKS_EBOOK, 'bookinfo_id' => null, 'book_lookup_retry_at' => null, 'book_name_normalized_at' => now()],
            ['id' => 2, 'categories_id' => Category::MUSIC_AUDIOBOOK, 'bookinfo_id' => null, 'book_lookup_retry_at' => null, 'book_name_normalized_at' => now()],
            ['id' => 3, 'categories_id' => Category::BOOKS_EBOOK, 'bookinfo_id' => null, 'book_lookup_retry_at' => now()->addHour(), 'book_name_normalized_at' => now()],
            ['id' => 4, 'categories_id' => Category::BOOKS_EBOOK, 'bookinfo_id' => -3, 'book_lookup_retry_at' => null, 'book_name_normalized_at' => now()],
        ]);
        DB::table('releases')->insert([
            'id' => 5, 'name' => 'N:/NZB', 'bookinfo_id' => -2,
        ]);
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('IF', static fn (int $condition, int $yes, int $no): int => $condition ? $yes : $no, 3);
        $tmux = app(Tmux::class);
        $sql = $tmux->proc_query(1, '', '');
        $lines = array_filter(explode("\n", $sql), static fn (string $line): bool => str_contains($line, 'AS processbooks'));
        $expression = rtrim(trim(array_values($lines)[0]), ',');

        $this->assertSame(3, DB::selectOne('SELECT '.$expression.' FROM releases')->processbooks);
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, ['book_lookup' => 0]);
        $this->assertSame(0, DB::table('releases')->whereRaw(BookProcessingCandidateQuery::workCondition(0))->count());
    }

    public function test_processing_state_migration_can_be_rolled_back_and_reapplied(): void
    {
        $this->stateMigration()->down();
        $this->assertFalse(Schema::hasColumn('releases', 'book_lookup_attempts'));
        $this->stateMigration()->up();
        DB::table('releases')->insert(['id' => 1]);
        $this->assertSame(0, DB::table('releases')->where('id', 1)->value('book_lookup_attempts'));
    }

    private function stateMigration(): Migration
    {
        return require database_path('migrations/2026_10_06_181329_add_book_processing_state_to_releases_table.php');
    }

    private function failingService(int $calls, ?int $retryAfter = 300): BookService
    {
        $isbn = Mockery::mock(IsbnDbService::class);
        $isbn->shouldReceive('isConfigured')->andReturnTrue();
        $request = $isbn->shouldReceive('searchBooks')->times($calls);
        if ($retryAfter === null) {
            $request->andReturn([]);
        } else {
            $request->andThrow(new BookProviderException('isbndb', 'Unavailable', 429, $retryAfter));
        }
        $google = Mockery::mock(GoogleBooksService::class);
        $google->shouldReceive('searchBooks')->times($calls)->andReturn([]);
        $library = Mockery::mock(OpenLibraryService::class);
        $library->shouldReceive('searchBooks')->times($calls)->andReturn([]);
        $itunes = Mockery::mock(ItunesService::class);
        $itunes->shouldReceive('findEbooks')->times($calls)->andReturn([]);
        $itunes->shouldReceive('lastRequestFailed')->times($calls)->andReturnFalse();

        $service = new BookService($isbn, $google, $library, $itunes);
        $service->echooutput = false;
        $service->sleeptime = 0;

        return $service;
    }

    private function assertNoDispatch(): void
    {
        $runner = new class extends PostProcessRunner
        {
            public function headerNone(): void {}

            protected function executeCommand(string|array $command): string
            {
                throw new \RuntimeException('Book work should not be dispatched.');
            }
        };
        $runner->processBooks();
        $runner->processAmazon();
        $this->assertSame(0, DB::table('releases')->whereRaw(BookProcessingCandidateQuery::workCondition(
            (int) app(ConfigurationProvider::class)->metadata()->bookLookup->value
        ))->count());
    }
}
