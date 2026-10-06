<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LogViewer;

use App\Services\LogViewer\LogFileRepository;
use App\Services\LogViewer\LogSearcher;
use App\Services\LogViewer\SearchQuery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogSearcherTest extends TestCase
{
    private const string LOG = "[2026-10-01 10:00:00] local.INFO: needle one\n"
        ."[2026-10-01 10:01:00] local.ERROR: boom\n"
        ."#0 needle in trace\n"
        ."#1 needle again\n"
        ."[2026-10-01 10:02:00] local.INFO: unrelated\n"
        ."[2026-10-01 10:03:00] local.WARNING: Needle three\n";

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/log-searcher-'.Str::uuid()->toString());
        File::ensureDirectoryExists($this->directory);
        config(['nntmux.log_viewer.path' => $this->directory, 'nntmux.log_viewer.search_timeout' => 20]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function engineProvider(): array
    {
        return [
            'grep' => ['grep'],
            'php' => ['php'],
        ];
    }

    #[Test]
    #[DataProvider('engineProvider')]
    public function it_returns_the_newest_matching_entries_and_attributes_trace_hits_to_their_entry(string $engine): void
    {
        $this->useEngine($engine);

        $result = $this->search(new SearchQuery('needle'), 100);

        $this->assertSame($engine, $result['engine']);
        $this->assertSame(4, $result['results'][0]['total_matches']);
        $this->assertSame(['Needle three', 'boom', 'needle one'], array_column($result['results'][0]['entries'], 'message'));
        $this->assertFalse($result['results'][0]['has_more']);
        $this->assertSame(
            [['text' => '#0 ', 'hit' => false], ['text' => 'needle', 'hit' => true], ['text' => " in trace\n#1 ", 'hit' => false], ['text' => 'needle', 'hit' => true], ['text' => ' again', 'hit' => false]],
            $result['results'][0]['entries'][1]['body_segments'],
        );
    }

    #[Test]
    #[DataProvider('engineProvider')]
    public function case_sensitive_and_regex_searches_narrow_the_matches(string $engine): void
    {
        $this->useEngine($engine);

        $caseSensitive = $this->search(new SearchQuery('needle', caseSensitive: true), 100)['results'][0];
        $regex = $this->search(new SearchQuery('need\w+ (one|three)', regex: true), 100)['results'][0];

        $this->assertSame(3, $caseSensitive['total_matches']);
        $this->assertSame(['boom', 'needle one'], array_column($caseSensitive['entries'], 'message'));
        $this->assertSame(['Needle three', 'needle one'], array_column($regex['entries'], 'message'));
    }

    #[Test]
    #[DataProvider('engineProvider')]
    public function level_filters_apply_to_the_owning_entry(string $engine): void
    {
        $this->useEngine($engine);

        $withTerm = $this->search(new SearchQuery('needle', levels: ['error']), 100)['results'][0];
        $levelOnly = $this->search(new SearchQuery('', levels: ['error', 'warning']), 100)['results'][0];

        $this->assertSame(['boom'], array_column($withTerm['entries'], 'message'));
        $this->assertSame(2, $levelOnly['total_matches']);
        $this->assertSame(['Needle three', 'boom'], array_column($levelOnly['entries'], 'message'));
    }

    #[Test]
    #[DataProvider('engineProvider')]
    public function the_before_cursor_pages_through_older_matches(string $engine): void
    {
        $this->useEngine($engine);
        $messages = [];
        $before = null;

        do {
            $result = $this->search(new SearchQuery('needle'), 1, $before)['results'][0];
            $messages = [...$messages, ...array_column($result['entries'], 'message')];
            $before = $result['before'];
            $this->assertSame($before !== null, $result['has_more']);
        } while ($before !== null && count($messages) < 10);

        $this->assertSame(['Needle three', 'boom', 'needle one'], $messages);
    }

    #[Test]
    public function it_falls_back_to_php_when_grep_is_missing(): void
    {
        config(['nntmux.log_viewer.grep_binary' => '/nonexistent/grep']);

        $this->assertSame('php', app(LogSearcher::class)->engine());
    }

    private function useEngine(string $engine): void
    {
        config(['nntmux.log_viewer.grep_binary' => $engine === 'grep' ? 'grep' : '']);

        if (app(LogSearcher::class)->engine() !== $engine) {
            $this->markTestSkipped("The {$engine} search engine is not available on this host.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function search(SearchQuery $query, int $limit, ?int $before = null): array
    {
        File::put($this->directory.'/app.log', self::LOG);
        $file = app(LogFileRepository::class)->find('app.log');
        $this->assertNotNull($file);

        return app(LogSearcher::class)->search($query, [$file], $before, $limit);
    }
}
