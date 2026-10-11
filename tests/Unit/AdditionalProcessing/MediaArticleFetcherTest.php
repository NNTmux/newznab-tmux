<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Models\Release;
use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\AdditionalProcessing\Media\MediaArticleFetcher;
use App\Services\AdditionalProcessing\State\MediaByteBudget;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\AdditionalProcessing\UsenetDownloadService;
use App\Services\Yenc\YencPart;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MediaArticleFetcherTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/fetcher-'.uniqid('', true).'.bin';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_appends_consecutive_articles_up_to_the_byte_limit(): void
    {
        $download = $this->articles([
            '<2>' => ['BBBB', 4],
            '<3>' => ['CCCC', 8],
        ]);

        $written = $this->fetch($download, fromSegment: 1, expectedOffset: 4, maxBytes: 6);

        $this->assertSame(6, $written);
        $this->assertSame('BBBBCC', file_get_contents($this->path));
    }

    #[Test]
    public function it_stops_where_an_article_does_not_continue_the_previous_one(): void
    {
        $download = $this->articles([
            '<2>' => ['BBBB', 4],
            '<3>' => ['DDDD', 12],
        ]);

        $this->assertSame(4, $this->fetch($download, fromSegment: 1, expectedOffset: 4, maxBytes: 100));
        $this->assertSame('BBBB', file_get_contents($this->path));
    }

    #[Test]
    public function it_fetches_nothing_the_budget_cannot_cover(): void
    {
        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldNotReceive('downloadArticle');

        $this->assertSame(0, $this->fetch($download, fromSegment: 0, expectedOffset: 0, maxBytes: 100, budget: new MediaByteBudget(3)));
    }

    #[Test]
    public function it_places_a_cached_first_article_at_the_start_of_the_file(): void
    {
        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldReceive('downloadArticle')->with(DownloadKind::MediaSource, '<1>', '', 1)
            ->andReturn(['success' => true, 'data' => 'AAAA', 'part' => null, 'groupUnavailable' => false, 'error' => null]);
        $download->shouldReceive('downloadArticle')->with(DownloadKind::MediaSource, '<2>', '', 1)
            ->andReturn(['success' => true, 'data' => 'BBBB', 'part' => new YencPart('f', 16, 2, 5, 8), 'groupUnavailable' => false, 'error' => null]);

        $this->assertSame(6, $this->fetch($download, fromSegment: 0, expectedOffset: 0, maxBytes: 6));
        $this->assertSame('AAAABB', file_get_contents($this->path));
    }

    /**
     * @param  array<string, array{0: string, 1: int}>  $articles  message ID => [data, 0-based offset]
     */
    private function articles(array $articles): UsenetDownloadService
    {
        $download = Mockery::mock(UsenetDownloadService::class);
        foreach ($articles as $messageId => [$data, $offset]) {
            $download->shouldReceive('downloadArticle')->with(DownloadKind::MediaSource, $messageId, '', 1)->andReturn([
                'success' => true,
                'data' => $data,
                'part' => new YencPart('movie.mkv', 16, null, $offset + 1, $offset + strlen($data)),
                'groupUnavailable' => false,
                'error' => null,
            ]);
        }

        return $download;
    }

    private function fetch(
        UsenetDownloadService $download,
        int $fromSegment,
        int $expectedOffset,
        int $maxBytes,
        ?MediaByteBudget $budget = null,
    ): int {
        return (new MediaArticleFetcher($download))->append(
            new ReleaseProcessingContext(new Release(['id' => 1, 'guid' => 'guid-1'])),
            ['<1>', '<2>', '<3>', '<4>'],
            $fromSegment,
            $expectedOffset,
            $maxBytes,
            $this->path,
            $budget ?? new MediaByteBudget(1000),
            hrtime(true) / 1e9 + 60,
            4,
        );
    }
}
