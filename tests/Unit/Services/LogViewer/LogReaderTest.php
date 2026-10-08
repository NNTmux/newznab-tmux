<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LogViewer;

use App\Services\LogViewer\LogEntry;
use App\Services\LogViewer\LogEntryParser;
use App\Services\LogViewer\LogFile;
use App\Services\LogViewer\LogReader;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LogReaderTest extends TestCase
{
    private const string STACK_TRACE_LOG = "[2026-10-01 10:00:00] local.INFO: first\n"
        ."[2026-10-01 10:01:00] local.ERROR: boom\n"
        ."#0 /app/Foo.php(12): bar()\n"
        ."#1 {main}\n"
        ."[2026-10-01 10:02:00] local.WARNING: third\n";

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/log-reader-test-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function it_groups_stack_traces_into_their_entry_newest_first(): void
    {
        $file = $this->logFile('app.log', self::STACK_TRACE_LOG);

        $result = $this->reader()->latest($file, null, 10);

        $this->assertSame(['third', 'boom', 'first'], $this->messages($result['entries']));
        $this->assertSame(['warning', 'error', 'info'], array_map(static fn (LogEntry $entry): ?string => $entry->level, $result['entries']));
        $this->assertSame("#0 /app/Foo.php(12): bar()\n#1 {main}", $result['entries'][1]->body);
        $this->assertSame('2026-10-01 10:01:00', $result['entries'][1]->timestamp);
        $this->assertNull($result['before']);
    }

    #[Test]
    public function the_before_cursor_pages_through_the_whole_file(): void
    {
        $file = $this->logFile('app.log', self::STACK_TRACE_LOG);
        $reader = $this->reader();
        $seen = [];
        $before = null;

        do {
            $page = $reader->latest($file, $before, 1);
            $seen = [...$seen, ...$this->messages($page['entries'])];
            $before = $page['before'];
        } while ($before !== null && count($seen) < 10);

        $this->assertSame(['third', 'boom', 'first'], $seen);
    }

    #[Test]
    public function it_filters_by_level_while_scanning(): void
    {
        $file = $this->logFile('app.log', self::STACK_TRACE_LOG);

        $result = $this->reader()->latest($file, null, 10, ['error', 'info']);

        $this->assertSame(['boom', 'first'], $this->messages($result['entries']));
    }

    #[Test]
    public function it_handles_crlf_and_a_missing_trailing_newline(): void
    {
        $file = $this->logFile('crlf.log', "[2026-10-01 10:00:00] local.INFO: a\r\n[2026-10-01 10:01:00] local.INFO: b");

        $result = $this->reader()->latest($file, null, 10);

        $this->assertSame(['b', 'a'], $this->messages($result['entries']));
    }

    #[Test]
    public function an_empty_file_has_no_entries(): void
    {
        $result = $this->reader()->latest($this->logFile('empty.log', ''), null, 10);

        $this->assertSame([], $result['entries']);
        $this->assertNull($result['before']);
    }

    #[Test]
    public function continuation_lines_before_the_first_header_become_an_orphan_entry(): void
    {
        $file = $this->logFile('rotated.log', "#5 orphan frame\n#6 {main}\n[2026-10-01 10:00:00] local.INFO: next\n");

        $entries = $this->reader()->latest($file, null, 10)['entries'];

        $this->assertSame(['next', '#5 orphan frame'], $this->messages($entries));
        $this->assertNull($entries[1]->level);
        $this->assertSame('#6 {main}', $entries[1]->body);
        $this->assertSame(0, $entries[1]->offset);
    }

    #[Test]
    public function plain_files_yield_one_entry_per_non_blank_line(): void
    {
        $file = $this->logFile('horizon.log', "  2026-10-05 21:53:20 App\\Jobs\\A ... DONE\n\n  2026-10-05 21:53:21 App\\Jobs\\B ... FAIL\n", structured: false);

        $entries = $this->reader()->latest($file, null, 10)['entries'];

        $this->assertCount(2, $entries);
        $this->assertSame('2026-10-05 21:53:21', $entries[0]->timestamp);
        $this->assertStringContainsString('App\\Jobs\\B', $entries[0]->message);
    }

    #[Test]
    public function oversized_bodies_keep_the_start_of_the_trace(): void
    {
        $file = $this->logFile('big.log', "[2026-10-01 10:01:00] local.ERROR: boom\n"
            .'#0 '.str_repeat('a', 30)."\n"
            .'#1 '.str_repeat('b', 30)."\n"
            .'#2 '.str_repeat('c', 30)."\n");

        $entry = (new LogReader(new LogEntryParser, chunkSize: 16, maxEntryBytes: 40))->latest($file, null, 10)['entries'][0];

        $this->assertTrue($entry->truncated);
        $this->assertSame('#0 '.str_repeat('a', 30), $entry->body);
    }

    #[Test]
    public function the_scan_budget_stops_early_and_resumes_further_back(): void
    {
        $file = $this->logFile('app.log', str_repeat(self::STACK_TRACE_LOG, 20));
        $reader = new LogReader(new LogEntryParser, chunkSize: 32, scanBudget: 200);

        $first = $reader->latest($file, null, 10, ['critical']);

        $this->assertTrue($first['budget_exhausted']);
        $this->assertSame([], $first['entries']);
        $this->assertNotNull($first['before']);
        $this->assertLessThan($file->size, $first['before']);

        $before = $first['before'];
        $calls = 1;
        while ($before !== null && $calls < 100) {
            $before = $reader->latest($file, $before, 10, ['critical'])['before'];
            $calls++;
        }

        $this->assertNull($before, 'Repeated calls must eventually reach the start of the file.');
    }

    #[Test]
    public function a_cursor_past_the_end_of_the_file_is_clamped(): void
    {
        $file = $this->logFile('app.log', self::STACK_TRACE_LOG);

        $result = $this->reader()->latest($file, $file->size + 1000, 1);

        $this->assertSame(['third'], $this->messages($result['entries']));
    }

    #[Test]
    public function entry_at_resolves_the_owning_entry_from_inside_a_stack_trace(): void
    {
        $file = $this->logFile('app.log', self::STACK_TRACE_LOG);
        $traceOffset = strpos(self::STACK_TRACE_LOG, '#1 {main}');
        $boomOffset = strpos(self::STACK_TRACE_LOG, '[2026-10-01 10:01:00]');
        $thirdOffset = strpos(self::STACK_TRACE_LOG, '[2026-10-01 10:02:00]');

        $entry = $this->reader()->entryAt($file, (int) $traceOffset + 3);

        $this->assertNotNull($entry);
        $this->assertSame('boom', $entry->message);
        $this->assertSame($boomOffset, $entry->offset);
        $this->assertSame($thirdOffset, $entry->end);
        $this->assertSame("#0 /app/Foo.php(12): bar()\n#1 {main}", $entry->body);
        $this->assertNull($this->reader()->entryAt($file, $file->size));
    }

    #[Test]
    public function entries_forward_holds_back_the_open_tail_and_resumes_from_it(): void
    {
        $file = $this->logFile('app.log', self::STACK_TRACE_LOG);
        $reader = $this->reader();

        $first = $reader->entriesForward($file, 0, 0, PHP_INT_MAX, false);
        $entries = iterator_to_array($first, false);
        $resume = $first->getReturn();

        $this->assertSame(['first', 'boom'], array_map(static fn (array $item): string => $item[0]->message, $entries));
        $this->assertSame("#0 /app/Foo.php(12): bar()\n#1 {main}", $entries[1][0]->body);
        $this->assertSame('[2026-10-01 10:01:00] local.ERROR: boom', $entries[1][1]);
        $this->assertSame([1, 2], [$entries[0][0]->lineNumber, $entries[1][0]->lineNumber]);
        $this->assertSame([1, 4], [$entries[0][2], $entries[1][2]]);
        $this->assertSame(['offset' => strpos(self::STACK_TRACE_LOG, '[2026-10-01 10:02:00]'), 'line' => 4], $resume);

        $tail = $reader->entriesForward($file, $resume['offset'], $resume['line'], PHP_INT_MAX, true);
        $remaining = iterator_to_array($tail, false);

        $this->assertSame(['third'], array_map(static fn (array $item): string => $item[0]->message, $remaining));
        $this->assertSame(5, $remaining[0][0]->lineNumber);
        $this->assertSame(['offset' => strlen(self::STACK_TRACE_LOG), 'line' => 5], $tail->getReturn());
    }

    #[Test]
    public function entries_forward_stops_at_an_entry_boundary_once_the_budget_is_spent(): void
    {
        $file = $this->logFile('app.log', self::STACK_TRACE_LOG);

        $entries = $this->reader()->entriesForward($file, 0, 0, 1, true);

        $this->assertSame(['first'], array_map(static fn (array $item): string => $item[0]->message, iterator_to_array($entries, false)));
        $this->assertSame(['offset' => strpos(self::STACK_TRACE_LOG, '[2026-10-01 10:01:00]'), 'line' => 1], $entries->getReturn());
    }

    #[Test]
    public function entries_forward_waits_for_a_line_still_being_written_in_plain_files(): void
    {
        $file = $this->logFile('horizon.log', "  2026-10-05 21:53:20 Job A DONE\n\n  2026-10-05 21:53:21 Job B RUN", false);
        $reader = $this->reader();

        $live = $reader->entriesForward($file, 0, 0, PHP_INT_MAX, false);
        $this->assertSame(['  2026-10-05 21:53:20 Job A DONE'], array_map(static fn (array $item): string => $item[0]->message, iterator_to_array($live, false)));
        $this->assertSame(['offset' => 34, 'line' => 2], $live->getReturn());

        $flushed = $reader->entriesForward($file, 0, 0, PHP_INT_MAX, true);
        $entries = iterator_to_array($flushed, false);

        $this->assertSame([null, null], [$entries[0][0]->level, $entries[1][0]->level]);
        $this->assertSame('2026-10-05 21:53:21', $entries[1][0]->timestamp);
        $this->assertSame(3, $entries[1][0]->lineNumber);
        $this->assertSame(['offset' => $file->size, 'line' => 3], $flushed->getReturn());
    }

    #[Test]
    public function entries_forward_caps_oversized_bodies_and_keeps_orphan_blocks(): void
    {
        $contents = "orphan continuation\n[2026-10-01 10:00:00] local.ERROR: big\n".str_repeat("#0 frame\n", 10)."[2026-10-01 10:01:00] local.INFO: next\n";
        $file = $this->logFile('app.log', $contents);
        $reader = new LogReader(new LogEntryParser, chunkSize: 64, maxEntryBytes: 60);

        $entries = iterator_to_array($reader->entriesForward($file, 0, 0, PHP_INT_MAX, true), false);

        $this->assertSame(['orphan continuation', 'big', 'next'], array_map(static fn (array $item): string => $item[0]->message, $entries));
        $this->assertNull($entries[0][0]->level);
        $this->assertSame(implode("\n", array_fill(0, 6, '#0 frame')), $entries[1][0]->body);
        $this->assertTrue($entries[1][0]->truncated);
        $this->assertSame(13, $entries[2][0]->lineNumber);
    }

    private function reader(): LogReader
    {
        return new LogReader(new LogEntryParser, chunkSize: 64);
    }

    private function logFile(string $name, string $contents, bool $structured = true): LogFile
    {
        $path = $this->directory.'/'.$name;
        file_put_contents($path, $contents);

        return new LogFile($name, $name, null, $path, strlen($contents), CarbonImmutable::now(), $structured);
    }

    /**
     * @param  list<LogEntry>  $entries
     * @return list<string>
     */
    private function messages(array $entries): array
    {
        return array_map(static fn (LogEntry $entry): string => $entry->message, $entries);
    }
}
