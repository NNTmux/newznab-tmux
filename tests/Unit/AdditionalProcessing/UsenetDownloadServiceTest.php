<?php

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\AdditionalProcessing\UsenetDownloadService;
use App\Services\NNTP\NNTPService;
use App\Services\Yenc\YencPart;
use DariusIII\NetNntp\Error as NntpError;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UsenetDownloadServiceTest extends TestCase
{
    use CreatesProcessingConfiguration;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_downloads_binary_payloads_through_the_enum_driven_api(): void
    {
        $nntp = Mockery::mock(NNTPService::class);
        $nntp->shouldReceive('getMessagesByMessageID')
            ->once()
            ->with(['<abc>'], false)
            ->andReturn('BINARY-DATA');

        $service = new UsenetDownloadService($this->makeConfig(), $nntp);

        $result = $service->download(DownloadKind::Compressed, ['<abc>'], 'alt.binaries', 55, 'archive.rar');

        $this->assertTrue($result['success']);
        $this->assertSame('BINARY-DATA', $result['data']);
    }

    #[Test]
    public function it_marks_group_unavailable_errors_explicitly(): void
    {
        $error = new class
        {
            public function getMessage(): string
            {
                return 'No such news group';
            }
        };

        $nntp = Mockery::mock(NNTPService::class);
        $nntp->shouldReceive('getMessagesByMessageID')
            ->once()
            ->with(['<missing>'], false)
            ->andReturn($error);

        $service = new UsenetDownloadService($this->makeConfig(), $nntp);

        $result = $service->download(DownloadKind::Sample, ['<missing>']);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['groupUnavailable']);
        $this->assertStringContainsString('Group unavailable', (string) $result['error']);
    }

    #[Test]
    public function it_reuses_exact_successful_requests_only_within_the_current_release(): void
    {
        $nntp = Mockery::mock(NNTPService::class);
        $nntp->shouldReceive('getMessagesByMessageID')
            ->twice()
            ->with(['<shared>'], false)
            ->andReturn('SHARED-DATA');

        $service = new UsenetDownloadService($this->makeConfig(), $nntp);
        $service->beginReleaseScope();

        $first = $service->download(DownloadKind::Compressed, ['<shared>'], 'alt.binaries.test', 10);
        $second = $service->download(DownloadKind::Jpg, ['<shared>'], 'alt.binaries.test', 10);
        $firstReleaseMetrics = $service->finishReleaseScope();

        $this->assertSame($first, $second);
        $this->assertSame(2, $firstReleaseMetrics->logicalRequests);
        $this->assertSame(1, $firstReleaseMetrics->networkRequests);
        $this->assertSame(1, $firstReleaseMetrics->cacheHits);
        $this->assertSame(strlen('SHARED-DATA'), $firstReleaseMetrics->bytesDownloaded);
        $this->assertSame(strlen('SHARED-DATA'), $firstReleaseMetrics->bytesReused);

        $service->beginReleaseScope();
        $service->download(DownloadKind::Compressed, ['<shared>'], 'alt.binaries.test', 11);
        $secondReleaseMetrics = $service->finishReleaseScope();

        $this->assertSame(1, $secondReleaseMetrics->logicalRequests);
        $this->assertSame(1, $secondReleaseMetrics->networkRequests);
        $this->assertSame(0, $secondReleaseMetrics->cacheHits);
    }

    #[Test]
    public function it_does_not_cache_failed_downloads(): void
    {
        $nntp = Mockery::mock(NNTPService::class);
        $nntp->shouldReceive('getMessagesByMessageID')
            ->twice()
            ->with(['<missing>'], false)
            ->andReturn('');

        $service = new UsenetDownloadService($this->makeConfig(), $nntp);
        $service->beginReleaseScope();

        $service->download(DownloadKind::Compressed, ['<missing>']);
        $service->download(DownloadKind::Compressed, ['<missing>']);
        $metrics = $service->finishReleaseScope();

        $this->assertSame(2, $metrics->networkRequests);
        $this->assertSame(0, $metrics->cacheHits);
    }

    #[Test]
    public function it_does_not_reuse_successful_downloads_outside_a_release_scope(): void
    {
        $nntp = Mockery::mock(NNTPService::class);
        $nntp->shouldReceive('getMessagesByMessageID')
            ->twice()
            ->with(['<shared>'], false)
            ->andReturn('SHARED-DATA');

        $service = new UsenetDownloadService($this->makeConfig(), $nntp);

        $first = $service->download(DownloadKind::Compressed, ['<shared>'], 'alt.binaries.test', 10);
        $second = $service->download(DownloadKind::Jpg, ['<shared>'], 'alt.binaries.test', 10);

        $this->assertSame('SHARED-DATA', $first['data']);
        $this->assertSame('SHARED-DATA', $second['data']);
    }

    #[Test]
    public function it_retries_an_exact_request_after_a_partial_provider_failure(): void
    {
        $nntp = Mockery::mock(NNTPService::class);
        $nntp->shouldReceive('getMessagesByMessageID')
            ->twice()
            ->with(['<retry>'], false)
            ->andReturn('', 'RECOVERED-DATA');

        $service = new UsenetDownloadService($this->makeConfig(), $nntp);
        $service->beginReleaseScope();

        $failed = $service->download(DownloadKind::Compressed, ['<retry>']);
        $recovered = $service->download(DownloadKind::Compressed, ['<retry>']);
        $metrics = $service->finishReleaseScope();

        $this->assertFalse($failed['success']);
        $this->assertTrue($recovered['success']);
        $this->assertSame('RECOVERED-DATA', $recovered['data']);
        $this->assertSame(2, $metrics->networkRequests);
        $this->assertSame(0, $metrics->cacheHits);
    }

    #[Test]
    public function it_checks_minimum_download_sizes(): void
    {
        $service = new UsenetDownloadService($this->makeConfig(), Mockery::mock(NNTPService::class));

        $this->assertFalse($service->meetsMinimumSize(str_repeat('a', 40)));
        $this->assertTrue($service->meetsMinimumSize(str_repeat('a', 41)));
    }

    #[Test]
    public function it_downloads_one_article_with_its_yenc_part_and_counts_it_as_preview_traffic(): void
    {
        $part = new YencPart('Movie.mkv', 5000, 2, 1001, 2000);
        $nntp = Mockery::mock(NNTPService::class);
        $nntp->shouldReceive('getArticleByMessageID')
            ->twice()
            ->with('<article>', false)
            ->andReturn(['data' => 'ARTICLE', 'part' => $part]);

        $service = new UsenetDownloadService($this->makeConfig(), $nntp);
        $service->beginReleaseScope();

        $result = $service->downloadArticle(DownloadKind::MediaSource, '<article>', 'alt.binaries.test', 10);
        $again = $service->downloadArticle(DownloadKind::MediaSource, '<article>', 'alt.binaries.test', 10);
        $metrics = $service->finishReleaseScope();

        $this->assertTrue($result['success']);
        $this->assertSame('ARTICLE', $result['data']);
        $this->assertSame($part, $result['part']);
        $this->assertSame('ARTICLE', $again['data'], 'articles are not cached, so the second call goes to the server');
        $this->assertSame(2, $metrics->networkRequests);
        $this->assertSame(14, $metrics->mediaBytesDownloaded);
    }

    #[Test]
    public function it_reuses_a_cached_download_of_the_same_single_article(): void
    {
        $nntp = Mockery::mock(NNTPService::class);
        $nntp->shouldReceive('getMessagesByMessageID')->once()->with(['<probe>'], false)->andReturn('PROBE-DATA');
        $nntp->shouldNotReceive('getArticleByMessageID');

        $service = new UsenetDownloadService($this->makeConfig(), $nntp);
        $service->beginReleaseScope();
        $service->download(DownloadKind::Compressed, ['<probe>'], 'alt.binaries.test', 10);

        $result = $service->downloadArticle(DownloadKind::MediaSource, '<probe>', 'alt.binaries.test', 10);

        $this->assertSame('PROBE-DATA', $result['data']);
        $this->assertNull($result['part']);
        $this->assertSame(1, $service->finishReleaseScope()->cacheHits);
    }

    #[Test]
    public function it_reports_an_unavailable_group_while_downloading_an_article(): void
    {
        $nntp = Mockery::mock(NNTPService::class);
        $nntp->shouldReceive('getArticleByMessageID')->once()->andReturn(new NntpError('411 No such news group'));

        $result = (new UsenetDownloadService($this->makeConfig(), $nntp))->downloadArticle(DownloadKind::Par2Index, '<par2>');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['groupUnavailable']);
    }

    #[Test]
    public function it_keeps_uncached_downloads_out_of_the_release_cache(): void
    {
        $nntp = Mockery::mock(NNTPService::class);
        $nntp->shouldReceive('getMessagesByMessageID')->twice()->with(['<mid>'], false)->andReturn('MID-DATA');

        $service = new UsenetDownloadService($this->makeConfig(), $nntp);
        $service->beginReleaseScope();
        $service->download(DownloadKind::MediaSource, ['<mid>'], cache: false);
        $service->download(DownloadKind::MediaSource, ['<mid>'], cache: false);

        $this->assertSame(0, $service->finishReleaseScope()->cacheHits);
    }
}
