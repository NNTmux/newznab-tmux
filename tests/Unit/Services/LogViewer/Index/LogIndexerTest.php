<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LogViewer\Index;

use App\Models\LogIndexFile;
use App\Services\LogViewer\Index\LogIndex;
use App\Services\LogViewer\Index\LogIndexer;
use App\Services\LogViewer\LogEntryParser;
use App\Services\LogViewer\LogFile;
use App\Services\LogViewer\LogFileRepository;
use App\Services\LogViewer\LogReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeLogIndex;
use Tests\TestCase;

class LogIndexerTest extends TestCase
{
    private const string LOG = "[2026-10-01 10:00:00] local.INFO: first\n"
        ."[2026-10-01 10:01:00] security.ERROR: boom\n"
        ."#0 /app/Foo.php(12): bar()\n"
        ."[2026-10-01 10:02:00] local.WARNING: third\n";

    private string $directory;

    private FakeLogIndex $index;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();
        (require database_path('migrations/2026_10_08_224008_create_log_index_files_table.php'))->up();

        $this->directory = storage_path('framework/testing/log-indexer/'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->directory);
        $this->index = new FakeLogIndex;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function it_indexes_complete_entries_and_resumes_after_the_held_back_tail(): void
    {
        $this->write('app.log', self::LOG);

        $stats = $this->indexer()->run();

        $row = LogIndexFile::query()->sole();
        $documents = $this->index->documentsFor($row->id);
        $this->assertSame([2, 1], [$stats['entries'], $stats['files']]);
        $this->assertSame(['local', 'security'], array_column($documents, 'channel'));
        $this->assertSame(['info', 'error'], array_column($documents, 'level'));
        $this->assertSame('#0 /app/Foo.php(12): bar()', $documents[1]['body']);
        $this->assertSame('[2026-10-01 10:01:00] security.ERROR: boom', $documents[1]['first_line']);
        $this->assertSame(LogIndex::documentId($row->id, $documents[1]['byte_offset']), array_keys($this->index->documents)[1]);
        $this->assertSame(strtotime('2026-10-01 10:01:00'), $documents[1]['logged_at']);
        $this->assertSame([strpos(self::LOG, '[2026-10-01 10:02:00]'), 3], [$row->indexed_offset, $row->indexed_line]);

        File::append($this->directory.'/app.log', "[2026-10-01 10:03:00] local.INFO: fourth\n");
        $this->indexer()->run();

        $this->assertSame(['first', 'boom', 'third'], $this->messages($row->id));
        $this->assertSame([1, 2, 4], array_column($this->index->documentsFor($row->id), 'line_no'));
    }

    #[Test]
    public function an_idle_file_is_indexed_through_its_last_entry(): void
    {
        $this->write('old.log', self::LOG, time() - 600);

        $this->indexer()->run();

        $row = LogIndexFile::query()->sole();
        $this->assertSame(['first', 'boom', 'third'], $this->messages($row->id));
        $this->assertSame(strlen(self::LOG), $row->indexed_offset);
    }

    #[Test]
    public function a_size_rotated_file_keeps_its_progress_under_the_new_name(): void
    {
        $this->write('horizon.log', "  2026-10-05 21:53:20 Job A DONE\n", time() - 600);
        $this->indexer()->run();
        $writes = $this->index->writes;

        rename($this->directory.'/horizon.log', $this->directory.'/horizon.log.1');
        $this->write('horizon.log', "  2026-10-05 22:00:00 Job B DONE\n", time() - 600);
        $this->indexer()->run();

        $rows = LogIndexFile::query()->orderBy('id')->get();
        $this->assertSame(['horizon.log.1', 'horizon.log'], $rows->pluck('path')->all());
        $this->assertSame(['  2026-10-05 21:53:20 Job A DONE'], $this->messages($rows[0]->id));
        $this->assertSame(['  2026-10-05 22:00:00 Job B DONE'], $this->messages($rows[1]->id));
        $this->assertSame($writes + 1, $this->index->writes);
        $this->assertSame([], $this->index->purged);
    }

    #[Test]
    public function a_truncated_or_rewritten_file_is_indexed_again_from_the_start(): void
    {
        $path = $this->write('app.log', self::LOG, time() - 600);
        $this->indexer()->run();
        $row = LogIndexFile::query()->sole();

        file_put_contents($path, "[2026-10-02 08:00:00] local.INFO: fresh\n");
        touch($path, time() - 600);
        $this->indexer()->run();

        $this->assertSame([$row->id], $this->index->purged);
        $this->assertSame(['fresh'], $this->messages($row->id));

        file_put_contents($path, str_replace('fresh', 'other', (string) file_get_contents($path))."[2026-10-02 08:01:00] local.INFO: more\n");
        touch($path, time() - 600);
        $this->indexer()->run();

        $this->assertSame([$row->id, $row->id], $this->index->purged);
        $this->assertSame(['other', 'more'], $this->messages($row->id));
    }

    #[Test]
    public function documents_of_vanished_files_are_purged(): void
    {
        $path = $this->write('old.log', self::LOG, time() - 600);
        $this->indexer()->run();
        $row = LogIndexFile::query()->sole();

        unlink($path);
        $stats = $this->indexer()->run();

        $this->assertSame(1, $stats['purged']);
        $this->assertSame([$row->id], $this->index->purged);
        $this->assertSame([], $this->index->documents);
        $this->assertSame(0, LogIndexFile::query()->count());
    }

    #[Test]
    public function a_wiped_index_is_rebuilt_from_scratch(): void
    {
        $this->write('old.log', self::LOG, time() - 600);
        $this->indexer()->run();

        $this->index->documents = [];
        $this->indexer()->run();

        $this->assertSame(['first', 'boom', 'third'], $this->messages(LogIndexFile::query()->sole()->id));
    }

    #[Test]
    public function the_byte_budget_spreads_a_backfill_over_several_runs(): void
    {
        config(['nntmux.log_viewer.index.max_bytes_per_file' => 50]);
        $this->write('old.log', self::LOG, time() - 600);
        $id = fn (): int => LogIndexFile::query()->sole()->id;

        $this->indexer()->run();
        $this->assertSame(['first', 'boom'], $this->messages($id()));

        $this->indexer()->run();
        $this->assertSame(['first', 'boom', 'third'], $this->messages($id()));
    }

    #[Test]
    public function status_reports_how_far_each_file_is_indexed(): void
    {
        config(['nntmux.log_viewer.index.lag_tolerance_bytes' => 10]);
        $this->write('done.log', self::LOG, time() - 600);
        $this->write('behind.log', self::LOG);
        $this->indexer()->run(['done.log', 'behind.log']);
        $this->write('new.log', self::LOG);
        $files = $this->repository()->all();

        $status = $this->indexer()->status($files);

        $this->assertSame('indexed', $status['done.log']['state']);
        $this->assertSame('partial', $status['behind.log']['state']);
        $this->assertSame('none', $status['new.log']['state']);
        $this->assertSame(strlen(self::LOG), $status['done.log']['indexed_bytes']);
    }

    #[Test]
    public function forget_purges_a_file_and_leaves_cleanup_to_the_next_run_when_manticore_fails(): void
    {
        $this->write('old.log', self::LOG, time() - 600);
        $this->write('other.log', self::LOG, time() - 600);
        $this->indexer()->run();
        $file = fn (string $path): LogFile => $this->repository()->find($path) ?? $this->fail("{$path} is missing");

        $this->indexer()->forget($file('old.log'));
        $this->assertSame(['other.log'], LogIndexFile::query()->pluck('path')->all());

        $this->index->failing = true;
        $this->indexer()->forget($file('other.log'));
        $this->assertSame(['other.log'], LogIndexFile::query()->pluck('path')->all());
    }

    #[Test]
    public function the_index_logs_command_runs_the_indexer_and_reports_an_unreachable_index(): void
    {
        config(['nntmux.log_viewer.path' => $this->directory]);
        $this->write('old.log', self::LOG, time() - 600);
        $this->app->instance(LogIndex::class, $this->index);

        $this->artisan('nntmux:index-logs')
            ->expectsOutputToContain('Indexed 3 entries')
            ->assertSuccessful();

        $this->artisan('nntmux:index-logs', ['--reset' => true])->assertSuccessful();
        $this->assertSame(1, $this->index->drops);
        $this->assertCount(3, $this->index->documents);

        $this->index->available = false;
        $this->artisan('nntmux:index-logs')->assertFailed();
    }

    private function indexer(): LogIndexer
    {
        return new LogIndexer($this->index, $this->repository(), new LogReader(new LogEntryParser));
    }

    private function repository(): LogFileRepository
    {
        return new LogFileRepository(new LogEntryParser, $this->directory);
    }

    private function write(string $name, string $contents, ?int $modifiedAt = null): string
    {
        $path = $this->directory.'/'.$name;
        file_put_contents($path, $contents);
        touch($path, $modifiedAt ?? time());
        clearstatcache(true, $path);

        return $path;
    }

    /**
     * @return list<string>
     */
    private function messages(int $fileId): array
    {
        $parser = new LogEntryParser;

        return array_map(
            static fn (array $document): string => $parser->make(0, 0, (string) $document['first_line'], '', false, (bool) $document['structured'])->message,
            $this->index->documentsFor($fileId),
        );
    }
}
