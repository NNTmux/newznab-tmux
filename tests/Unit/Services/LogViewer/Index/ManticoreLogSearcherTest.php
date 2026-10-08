<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LogViewer\Index;

use App\Models\LogIndexFile;
use App\Services\LogViewer\Index\LogIndexer;
use App\Services\LogViewer\Index\ManticoreLogSearcher;
use App\Services\LogViewer\LogEntryParser;
use App\Services\LogViewer\LogFile;
use App\Services\LogViewer\LogFileRepository;
use App\Services\LogViewer\LogReader;
use App\Services\LogViewer\SearchQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeLogIndex;
use Tests\TestCase;

class ManticoreLogSearcherTest extends TestCase
{
    private const string LOG = "[2026-10-01 10:00:00] local.INFO: payment started\n"
        ."[2026-10-01 10:01:00] local.ERROR: Payment failed\n"
        ."#0 /app/Payments.php(12): charge()\n"
        ."[2026-10-01 10:02:00] local.INFO: payment retried\n";

    private string $directory;

    private FakeLogIndex $index;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();
        (require database_path('migrations/2026_10_08_224008_create_log_index_files_table.php'))->up();

        $this->directory = storage_path('framework/testing/log-searcher/'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->directory);
        $this->index = new FakeLogIndex;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function candidates_are_rechecked_so_results_keep_substring_and_case_semantics(): void
    {
        [$file, $row] = $this->indexed('app.log', self::LOG, idle: true);

        $insensitive = $this->searcher()->searchFile(new SearchQuery('PAYMENT'), $file, $row, null, 10);
        $sensitive = $this->searcher()->searchFile(new SearchQuery('Payment', caseSensitive: true), $file, $row, null, 10);
        $inTrace = $this->searcher()->searchFile(new SearchQuery('charge('), $file, $row, null, 10);

        $this->assertSame(['payment retried', 'Payment failed', 'payment started'], array_column($insensitive['entries'], 'message'));
        $this->assertSame(['Payment failed'], array_column($sensitive['entries'], 'message'));
        $this->assertSame([1, false], [$sensitive['total_matches'], $sensitive['total_is_estimate']]);
        $this->assertSame(['Payment failed'], array_column($inTrace['entries'], 'message'));
        $this->assertSame('#0 /app/Payments.php(12): charge()', $inTrace['entries'][0]['body']);
        $this->assertSame(['text' => 'charge(', 'hit' => true], $inTrace['entries'][0]['body_segments'][1]);
        $this->assertSame([1, false], [$inTrace['total_matches'], $inTrace['total_is_estimate']]);
        $this->assertSame(['equals' => ['file_id' => $row->id]], $this->index->queries[0]['bool']['must'][1]);
        $this->assertSame(['range' => ['byte_offset' => ['lt' => strlen(self::LOG)]]], $this->index->queries[0]['bool']['must'][2]);
    }

    #[Test]
    public function a_page_that_stops_before_every_candidate_was_checked_reports_an_estimate(): void
    {
        [$file, $row] = $this->indexed('app.log', self::LOG, idle: true);

        $result = $this->searcher()->searchFile(new SearchQuery('payment'), $file, $row, null, 1);

        $this->assertSame(['payment retried'], array_column($result['entries'], 'message'));
        $this->assertSame([3, true, true], [$result['total_matches'], $result['total_is_estimate'], $result['has_more']]);
    }

    #[Test]
    public function entries_written_since_the_last_run_are_found_without_the_index(): void
    {
        [$file, $row] = $this->indexed('app.log', self::LOG);
        File::append($file->absolutePath, "[2026-10-01 10:03:00] local.ERROR: payment refunded\n");
        $file = $this->repository()->find('app.log') ?? $this->fail('app.log is missing');

        $result = $this->searcher()->searchFile(new SearchQuery('payment'), $file, $row, null, 10);

        $this->assertSame(['payment refunded', 'payment retried', 'Payment failed', 'payment started'], array_column($result['entries'], 'message'));
        $this->assertSame(4, $result['total_matches']);
        $this->assertFalse($result['has_more']);
        $this->assertNull($result['before']);
    }

    #[Test]
    public function the_before_cursor_pages_through_tail_and_indexed_matches(): void
    {
        [$file, $row] = $this->indexed('app.log', self::LOG);
        $searcher = $this->searcher();
        $query = new SearchQuery('payment');
        $seen = [];
        $before = null;

        do {
            $page = $searcher->searchFile($query, $file, $row, $before, 1);
            $seen = [...$seen, ...array_column($page['entries'], 'message')];
            $before = $page['before'];
        } while ($page['has_more'] && count($seen) < 10);

        $this->assertSame(['payment retried', 'Payment failed', 'payment started'], $seen);
    }

    #[Test]
    public function filters_without_a_term_are_exact_and_need_no_recheck_of_the_text(): void
    {
        [$file, $row] = $this->indexed('app.log', self::LOG, idle: true);

        $result = $this->searcher()->searchFile(new SearchQuery('', levels: ['error']), $file, $row, null, 10);

        $this->assertSame(['Payment failed'], array_column($result['entries'], 'message'));
        $this->assertFalse($result['total_is_estimate']);
        $this->assertNull($result['entries'][0]['message_segments']);
    }

    #[Test]
    public function facets_count_levels_and_channels_of_the_given_files(): void
    {
        [, $row] = $this->indexed('app.log', self::LOG, idle: true);

        $facets = $this->searcher()->facets(new SearchQuery(''), [$row]);

        $this->assertSame(['info' => 2, 'error' => 1], $facets['levels']);
        $this->assertSame(['local' => 3], $facets['channels']);
        $this->assertSame(['levels' => [], 'channels' => []], $this->searcher()->facets(new SearchQuery(''), []));
    }

    /**
     * @return array{LogFile, LogIndexFile}
     */
    private function indexed(string $name, string $contents, bool $idle = false): array
    {
        $path = $this->directory.'/'.$name;
        file_put_contents($path, $contents);
        touch($path, $idle ? time() - 600 : time());
        clearstatcache(true, $path);

        (new LogIndexer($this->index, $this->repository(), new LogReader(new LogEntryParser)))->run();

        return [
            $this->repository()->find($name) ?? $this->fail("{$name} is missing"),
            LogIndexFile::query()->where('path', $name)->sole(),
        ];
    }

    private function searcher(): ManticoreLogSearcher
    {
        return new ManticoreLogSearcher($this->index, new LogReader(new LogEntryParser), new LogEntryParser);
    }

    private function repository(): LogFileRepository
    {
        return new LogFileRepository(new LogEntryParser, $this->directory);
    }
}
