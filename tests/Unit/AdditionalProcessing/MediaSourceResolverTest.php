<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Models\Release;
use App\Services\AdditionalProcessing\AdditionalWorkPlanner;
use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\AdditionalProcessing\Enums\MappingConfidence;
use App\Services\AdditionalProcessing\Media\MediaSourceResolver;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\AdditionalProcessing\UsenetDownloadService;
use App\Services\Yenc\YencPart;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MediaSourceResolverTest extends TestCase
{
    use BuildsArchiveFixtures;
    use CreatesProcessingConfiguration;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_tells_equal_sized_volumes_apart_by_the_hash_of_their_first_article(): void
    {
        $part01 = 'Rar!'.str_repeat('1', 20000);
        $part02 = 'Rar!'.str_repeat('2', 20000);
        $context = $this->contextFor([
            $this->entry('<index>', 600),
            $this->entry('<vol-a>', 52000, 2),
            $this->entry('<vol-b>', 52000, 2),
            $this->entry('<other>', 90000, 4),
        ], ['par2VerifyFiles' => 2]);

        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldReceive('download')
            ->once()
            ->with(DownloadKind::Par2Index, ['<index>'], 'alt.binaries.test', 1, Mockery::any(), false)
            ->andReturn($this->ok($this->par2Index([
                ['Movie.part01.rar', 50000, $part01],
                ['Movie.part02.rar', 50000, $part02],
            ])));
        $download->shouldReceive('downloadArticle')
            ->once()
            ->with(DownloadKind::MediaSource, '<vol-a>.1', 'alt.binaries.test', 1)
            ->andReturn($this->ok($part02, new YencPart('a1b2', 50000, 1, 1, 25000)));
        $download->shouldReceive('downloadArticle')
            ->once()
            ->with(DownloadKind::MediaSource, '<vol-b>.1', 'alt.binaries.test', 1)
            ->andReturn($this->ok($part01, new YencPart('c3d4', 50000, 1, 1, 25000)));

        $plan = $this->resolver($context, $download, ['par2VerifyFiles' => 2])->resolve($context);

        $this->assertNotNull($plan);
        $this->assertSame('Movie.part02.rar', $plan->mappedFiles[1]->file->name);
        $this->assertSame('Movie.part01.rar', $plan->mappedFiles[2]->file->name);
        $this->assertSame(MappingConfidence::Verified, $plan->mappedFiles[2]->confidence);
        $this->assertSame(25000, $plan->articlePartSize);
    }

    #[Test]
    public function it_stops_verifying_after_the_configured_number_of_articles(): void
    {
        $context = $this->contextFor([
            $this->entry('<index>', 600),
            $this->entry('<vol-a>', 52000, 2),
            $this->entry('<vol-b>', 52000, 2),
            $this->entry('<vol-c>', 52000, 2),
        ], ['par2VerifyFiles' => 1]);

        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldReceive('download')->once()->andReturn($this->ok($this->par2Index([
            ['Movie.part01.rar', 50000, 'FIRST'],
            ['Movie.part02.rar', 50000, 'SECOND'],
            ['Movie.part03.rar', 50000, 'THIRD'],
        ])));
        $download->shouldReceive('downloadArticle')->once()->andReturn($this->ok('UNRELATED'));

        $this->assertNull($this->resolver($context, $download, ['par2VerifyFiles' => 1])->resolve($context));
    }

    #[Test]
    public function it_does_not_download_an_index_larger_than_the_byte_budget(): void
    {
        $context = $this->contextFor([
            $this->entry('<index>', 600),
            $this->entry('<a>', 5000),
            $this->entry('<b>', 9000),
        ], ['mediaMaxBytes' => 100]);

        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldNotReceive('download');

        $this->assertNull($this->resolver($context, $download, ['mediaMaxBytes' => 100])->resolve($context));
    }

    #[Test]
    public function it_marks_the_group_unavailable_when_the_index_cannot_be_fetched(): void
    {
        $context = $this->contextFor([
            $this->entry('<index>', 600),
            $this->entry('<a>', 5000),
            $this->entry('<b>', 9000),
        ]);

        $download = Mockery::mock(UsenetDownloadService::class);
        $download->shouldReceive('download')->once()->andReturn(
            ['success' => false, 'data' => null, 'groupUnavailable' => true, 'error' => 'Group unavailable'],
        );

        $this->assertNull($this->resolver($context, $download)->resolve($context));
        $this->assertTrue($context->groupUnavailable);
    }

    /**
     * @param  list<array<string, mixed>>  $nzb
     * @param  array<string, mixed>  $config
     */
    private function contextFor(array $nzb, array $config = []): ReleaseProcessingContext
    {
        $context = new ReleaseProcessingContext(new Release(['id' => 1, 'guid' => 'guid-1']));
        $context->releaseGroupName = 'alt.binaries.test';
        $context->nzbContents = $nzb;
        $context->workPlan = (new AdditionalWorkPlanner($this->makeConfig(['par2MediaMapping' => true, ...$config])))
            ->plan($nzb, 'alt.binaries.test');

        return $context;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolver(ReleaseProcessingContext $context, UsenetDownloadService $download, array $config = []): MediaSourceResolver
    {
        return new MediaSourceResolver($this->makeConfig(['par2MediaMapping' => true, ...$config]), $download);
    }

    /**
     * @return array{title: string, size: int, filecount: int, segments: list<string>}
     */
    private function entry(string $id, int $size, int $segments = 1): array
    {
        return [
            'title' => '"'.md5($id).'" yEnc (1/'.$segments.')',
            'size' => $size,
            'filecount' => 1,
            'segments' => $segments === 1
                ? [$id]
                : array_map(static fn (int $part): string => $id.'.'.$part, range(1, $segments)),
        ];
    }

    /**
     * @return array{success: true, data: string, part: YencPart|null, groupUnavailable: false, error: null}
     */
    private function ok(string $data, ?YencPart $part = null): array
    {
        return ['success' => true, 'data' => $data, 'part' => $part, 'groupUnavailable' => false, 'error' => null];
    }
}
