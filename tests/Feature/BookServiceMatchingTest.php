<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BookProviderException;
use App\Models\BookInfo;
use App\Services\BookService;
use App\Services\GoogleBooksService;
use App\Services\IsbnDbService;
use App\Services\ItunesService;
use App\Services\OpenLibraryService;
use App\Services\ReleaseImageService;
use App\Support\BookMatchScorer;
use App\Support\Data\BookParseResult;
use App\Support\Data\ImageProcessingResult;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PDO;
use Tests\TestCase;

class BookServiceMatchingTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-book-service-matching.sqlite';
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
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('maxbooksprocessed', '50'), ('amazonsleep', '0'), ('lookupbooks', '1')");

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
        ]);
        DB::purge();
        DB::reconnect();

        Schema::create('bookinfo', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->string('author')->default('');
            $table->string('asin')->nullable()->unique();
            $table->string('isbn')->nullable()->index();
            $table->string('ean')->nullable()->index();
            $table->string('url')->nullable();
            $table->unsignedInteger('salesrank')->nullable();
            $table->string('publisher')->nullable();
            $table->dateTime('publishdate')->nullable();
            $table->string('pages')->nullable();
            $table->text('overview')->nullable();
            $table->string('genre')->default('');
            $table->boolean('cover')->default(false);
            $table->timestamps();
        });
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

    public function test_rejected_isbndb_candidates_fall_through_to_google_books(): void
    {
        $isbnDb = Mockery::mock(IsbnDbService::class);
        $isbnDb->shouldReceive('isConfigured')->andReturnTrue();
        $isbnDb->shouldReceive('searchBooks')->once()->andReturn([
            $this->candidate('Unrelated Cookbook', 'Different Author', 'isbndb:wrong'),
        ]);

        $google = Mockery::mock(GoogleBooksService::class);
        $google->shouldReceive('searchBooks')->once()->andReturn([
            $this->candidate('Clean Code', 'Robert C. Martin', 'googlebooks:clean-code'),
        ]);

        $openLibrary = Mockery::mock(OpenLibraryService::class);
        $openLibrary->shouldNotReceive('searchBooks');
        $itunes = Mockery::mock(ItunesService::class);
        $itunes->shouldNotReceive('findEbooks');

        $service = $this->makeService($isbnDb, $google, $openLibrary, $itunes);
        $service->parsedBookResult = $this->cleanCodeParseResult();

        $bookId = $service->updateBookInfo('Robert C Martin Clean Code');

        $this->assertIsInt($bookId);
        $this->assertGreaterThan(0, $bookId);
        $this->assertSame('Clean Code', BookInfo::query()->findOrFail($bookId)->title);
    }

    public function test_accepted_isbndb_candidate_stops_the_provider_cascade(): void
    {
        $isbnDb = Mockery::mock(IsbnDbService::class);
        $isbnDb->shouldReceive('isConfigured')->andReturnTrue();
        $isbnDb->shouldReceive('searchBooks')->once()->andReturn([
            $this->candidate('Clean Code', 'Robert C. Martin', 'isbndb:clean-code'),
        ]);

        $google = Mockery::mock(GoogleBooksService::class);
        $google->shouldNotReceive('searchBooks');
        $openLibrary = Mockery::mock(OpenLibraryService::class);
        $openLibrary->shouldNotReceive('searchBooks');
        $itunes = Mockery::mock(ItunesService::class);
        $itunes->shouldNotReceive('findEbooks');

        $service = $this->makeService($isbnDb, $google, $openLibrary, $itunes);
        $service->parsedBookResult = $this->cleanCodeParseResult();

        $this->assertGreaterThan(0, $service->updateBookInfo('Robert C Martin Clean Code'));
    }

    public function test_duplicate_editions_of_the_same_work_do_not_create_false_ambiguity(): void
    {
        $first = $this->candidate('Clean Code', 'Robert C. Martin', 'isbndb:edition-one');
        $second = $this->candidate('Clean Code', 'Martin, Robert C.', 'isbndb:edition-two');
        $second['publisher'] = 'Prentice Hall';

        $isbnDb = Mockery::mock(IsbnDbService::class);
        $isbnDb->shouldReceive('isConfigured')->andReturnTrue();
        $isbnDb->shouldReceive('searchBooks')->once()->andReturn([$first, $second]);

        $service = $this->makeService(
            $isbnDb,
            Mockery::mock(GoogleBooksService::class),
            Mockery::mock(OpenLibraryService::class),
            Mockery::mock(ItunesService::class),
        );
        $service->parsedBookResult = $this->cleanCodeParseResult();

        $this->assertGreaterThan(0, $service->updateBookInfo('Robert C Martin Clean Code'));
    }

    public function test_close_distinct_runner_up_prevents_a_premature_match(): void
    {
        $isbnDb = Mockery::mock(IsbnDbService::class);
        $isbnDb->shouldReceive('isConfigured')->andReturnTrue();
        $isbnDb->shouldReceive('searchBooks')->once()->andReturn([
            $this->candidate('Clean Code', 'Robert C. Martin', 'isbndb:clean-code'),
            $this->candidate('Clean Coder', 'Robert C. Martin', 'isbndb:clean-coder'),
        ]);

        $google = Mockery::mock(GoogleBooksService::class);
        $google->shouldReceive('searchBooks')->once()->andReturn([]);
        $openLibrary = Mockery::mock(OpenLibraryService::class);
        $openLibrary->shouldReceive('searchBooks')->once()->andReturn([]);
        $itunes = Mockery::mock(ItunesService::class);
        $itunes->shouldReceive('findEbooks')->once()->andReturn([]);
        $itunes->shouldReceive('lastRequestFailed')->once()->andReturnFalse();

        $service = $this->makeService($isbnDb, $google, $openLibrary, $itunes);
        $service->parsedBookResult = $this->cleanCodeParseResult();

        $this->assertSame(-2, $service->updateBookInfo('Robert C Martin Clean Code'));
    }

    public function test_ambiguous_author_dash_title_parse_retries_as_a_title_only_search(): void
    {
        $isbnDb = Mockery::mock(IsbnDbService::class);
        $isbnDb->shouldReceive('isConfigured')->andReturnTrue();
        $isbnDb->shouldReceive('searchBooks')
            ->once()
            ->with('From Journeyman to Master', 'The Pragmatic Programmer', true)
            ->andReturn([]);
        $isbnDb->shouldReceive('searchBooks')
            ->once()
            ->with('The Pragmatic Programmer - From Journeyman to Master', null, true)
            ->andReturn([
                $this->candidate(
                    'The Pragmatic Programmer: From Journeyman to Master',
                    'Andrew Hunt, David Thomas',
                    'isbndb:pragmatic-programmer',
                ),
            ]);

        $google = Mockery::mock(GoogleBooksService::class);
        $google->shouldNotReceive('searchBooks');
        $openLibrary = Mockery::mock(OpenLibraryService::class);
        $openLibrary->shouldNotReceive('searchBooks');
        $itunes = Mockery::mock(ItunesService::class);
        $itunes->shouldNotReceive('findEbooks');

        $service = $this->makeService($isbnDb, $google, $openLibrary, $itunes);
        $service->parsedBookResult = $service->parseReleaseName(
            'The Pragmatic Programmer - From Journeyman to Master EPUB'
        );

        $this->assertGreaterThan(0, $service->updateBookInfo($service->parsedBookResult->searchQuery()));
    }

    public function test_transient_provider_failure_leaves_the_match_pending(): void
    {
        $isbnDb = Mockery::mock(IsbnDbService::class);
        $isbnDb->shouldReceive('isConfigured')->andReturnTrue();
        $isbnDb->shouldReceive('searchBooks')->once()->andThrow(
            new BookProviderException('isbndb', 'Unavailable', 429, 300)
        );

        $google = Mockery::mock(GoogleBooksService::class);
        $google->shouldReceive('searchBooks')->once()->andReturn([]);
        $openLibrary = Mockery::mock(OpenLibraryService::class);
        $openLibrary->shouldReceive('searchBooks')->once()->andReturn([]);
        $itunes = Mockery::mock(ItunesService::class);
        $itunes->shouldReceive('findEbooks')->once()->andReturn([]);
        $itunes->shouldReceive('lastRequestFailed')->once()->andReturnFalse();

        $service = $this->makeService($isbnDb, $google, $openLibrary, $itunes);
        $service->parsedBookResult = $this->cleanCodeParseResult();

        $this->assertNull($service->updateBookInfo('Robert C Martin Clean Code'));
    }

    public function test_deterministic_exhaustion_returns_failed_sentinel(): void
    {
        $isbnDb = Mockery::mock(IsbnDbService::class);
        $isbnDb->shouldReceive('isConfigured')->andReturnFalse();
        $google = Mockery::mock(GoogleBooksService::class);
        $google->shouldReceive('searchBooks')->once()->andReturn([]);
        $openLibrary = Mockery::mock(OpenLibraryService::class);
        $openLibrary->shouldReceive('searchBooks')->once()->andReturn([]);
        $itunes = Mockery::mock(ItunesService::class);
        $itunes->shouldReceive('findEbooks')->once()->andReturn([]);
        $itunes->shouldReceive('lastRequestFailed')->once()->andReturnFalse();

        $service = $this->makeService($isbnDb, $google, $openLibrary, $itunes);
        $service->parsedBookResult = $this->cleanCodeParseResult();

        $this->assertSame(-2, $service->updateBookInfo('Robert C Martin Clean Code'));
    }

    public function test_exact_local_isbn_lookup_checks_both_identifier_versions(): void
    {
        $bookId = DB::table('bookinfo')->insertGetId([
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'asin' => 'existing:clean-code',
            'isbn' => '9780132350884',
            'ean' => '0132350882',
            'genre' => '',
            'cover' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = $this->makeService(
            Mockery::mock(IsbnDbService::class),
            Mockery::mock(GoogleBooksService::class),
            Mockery::mock(OpenLibraryService::class),
            Mockery::mock(ItunesService::class),
        );

        $this->assertSame($bookId, $service->getBookInfoByIsbn('0-13-235088-2')?->id);
    }

    public function test_equivalent_isbn_deduplication_preserves_metadata_and_failed_cover_state(): void
    {
        $bookId = DB::table('bookinfo')->insertGetId([
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'asin' => 'legacy:clean-code',
            'isbn' => null,
            'ean' => '0132350882',
            'publisher' => 'Prentice Hall',
            'genre' => 'Programming',
            'cover' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $candidate = $this->candidate('Clean Code', 'Robert C. Martin', 'isbndb:clean-code');
        $candidate['isbn'] = '9780132350884';
        $candidate['publisher'] = '';
        $candidate['genre'] = '';
        $candidate['coverurl'] = 'https://example.com/cover.jpg';

        $isbnDb = Mockery::mock(IsbnDbService::class);
        $isbnDb->shouldReceive('isConfigured')->andReturnTrue();
        $isbnDb->shouldReceive('findByIsbn')->once()->with('9780132350884')->andReturn($candidate);
        $isbnDb->shouldNotReceive('searchBooks');

        $service = $this->makeService(
            $isbnDb,
            Mockery::mock(GoogleBooksService::class),
            Mockery::mock(OpenLibraryService::class),
            Mockery::mock(ItunesService::class),
        );
        $service->parsedBookResult = new BookParseResult(
            rawName: 'Clean Code 9780132350884',
            title: 'Clean Code',
            author: 'Robert C Martin',
            isbn: '9780132350884',
        );

        $this->assertSame($bookId, $service->updateBookInfo('Clean Code', '9780132350884'));

        $book = BookInfo::query()->findOrFail($bookId);
        $this->assertSame('legacy:clean-code', $book->asin);
        $this->assertSame('Prentice Hall', $book->publisher);
        $this->assertSame('Programming', $book->genre);
        $this->assertFalse((bool) $book->cover);
        $this->assertSame(1, BookInfo::query()->count());
    }

    public function test_two_word_unauthored_title_reaches_external_providers_but_is_not_loosely_accepted(): void
    {
        $isbnDb = Mockery::mock(IsbnDbService::class);
        $isbnDb->shouldReceive('isConfigured')->andReturnFalse();
        $google = Mockery::mock(GoogleBooksService::class);
        $google->shouldReceive('searchBooks')->once()->andReturn([]);
        $openLibrary = Mockery::mock(OpenLibraryService::class);
        $openLibrary->shouldReceive('searchBooks')->once()->andReturn([]);
        $itunes = Mockery::mock(ItunesService::class);
        $itunes->shouldReceive('findEbooks')->once()->andReturn([]);
        $itunes->shouldReceive('lastRequestFailed')->once()->andReturnFalse();

        $service = $this->makeService($isbnDb, $google, $openLibrary, $itunes);
        $service->parsedBookResult = new BookParseResult(
            rawName: 'Atomic Habits EPUB',
            title: 'Atomic Habits',
        );

        $this->assertSame(-2, $service->updateBookInfo('Atomic Habits'));
    }

    private function makeService(
        IsbnDbService $isbnDb,
        GoogleBooksService $googleBooks,
        OpenLibraryService $openLibrary,
        ItunesService $itunes,
    ): BookService {
        $images = Mockery::mock(ReleaseImageService::class);
        $images->shouldReceive('saveRemoteImage')
            ->zeroOrMoreTimes()
            ->andReturn(ImageProcessingResult::failure('No cover URL.'));

        return new BookService(
            isbnDb: $isbnDb,
            googleBooks: $googleBooks,
            openLibrary: $openLibrary,
            itunes: $itunes,
            scorer: new BookMatchScorer,
            releaseImageService: $images,
        );
    }

    private function cleanCodeParseResult(): BookParseResult
    {
        return new BookParseResult(
            rawName: 'Clean Code by Robert C Martin',
            title: 'Clean Code',
            author: 'Robert C Martin',
            year: 2008,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function candidate(string $title, string $author, string $asin): array
    {
        return [
            'title' => $title,
            'author' => $author,
            'asin' => $asin,
            'isbn' => null,
            'ean' => null,
            'url' => '',
            'salesrank' => '',
            'publisher' => '',
            'publishdate' => null,
            'pages' => '',
            'overview' => '',
            'genre' => '',
            'coverurl' => '',
            'cover' => 0,
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
}
