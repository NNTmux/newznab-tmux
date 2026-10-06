<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LogViewer;

use App\Services\LogViewer\LogEntryParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LogEntryParserTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string, string}>
     */
    public static function headerProvider(): array
    {
        return [
            'laravel default' => ['[2026-10-06 18:11:29] NNTmux.ERROR: Something broke {"id":1}', '2026-10-06 18:11:29', 'NNTmux', 'error', 'Something broke {"id":1}'],
            'iso with microseconds and zone' => ['[2026-10-06T18:11:29.123456+02:00] local.DEBUG: tick', '2026-10-06T18:11:29.123456+02:00', 'local', 'debug', 'tick'],
            'dotted channel' => ['[2026-10-06 18:11:29] app.jobs.WARNING: slow', '2026-10-06 18:11:29', 'app.jobs', 'warning', 'slow'],
            'emergency level' => ['[2026-10-06 18:11:29] production.EMERGENCY: down', '2026-10-06 18:11:29', 'production', 'emergency', 'down'],
        ];
    }

    #[Test]
    #[DataProvider('headerProvider')]
    public function it_parses_monolog_headers(string $line, string $timestamp, string $channel, string $level, string $message): void
    {
        $header = (new LogEntryParser)->parseHeader($line);

        $this->assertSame(['timestamp' => $timestamp, 'channel' => $channel, 'level' => $level, 'message' => $message], $header);
    }

    #[Test]
    public function it_rejects_lines_that_are_not_headers(): void
    {
        $parser = new LogEntryParser;

        $this->assertNull($parser->parseHeader('#0 /var/www/app/Foo.php(12): bar()'));
        $this->assertNull($parser->parseHeader('[stacktrace]'));
        $this->assertFalse($parser->isHeader('  2026-10-05 21:53:21 App\Jobs\ReindexReleaseJob ... DONE'));
    }

    #[Test]
    public function it_extracts_timestamps_from_plain_console_lines(): void
    {
        $parser = new LogEntryParser;

        $this->assertSame('2026-10-05 21:53:21', $parser->plainTimestamp('  2026-10-05 21:53:21 App\Jobs\ReindexReleaseJob ... DONE'));
        $this->assertNull($parser->plainTimestamp('no timestamp here'));
    }

    #[Test]
    public function it_builds_plain_entries_for_unstructured_files(): void
    {
        $entry = (new LogEntryParser)->make(10, 50, '[2026-10-06 18:11:29] local.INFO: looks structured', '', false, false);

        $this->assertNull($entry->level);
        $this->assertSame('[2026-10-06 18:11:29] local.INFO: looks structured', $entry->message);
    }
}
