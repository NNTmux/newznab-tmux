<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Models\Release;
use App\Services\AdditionalProcessing\DTO\ArchiveCandidate;
use App\Services\AdditionalProcessing\Enums\VideoContainer;
use App\Services\AdditionalProcessing\Media\ArchivedMediaSlicer;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use dariusiii\rarinfo\ArchiveInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ArchivedMediaSlicerTest extends TestCase
{
    use BuildsArchiveFixtures;
    use CreatesProcessingConfiguration;

    private const string MKV = "\x1A\x45\xDF\xA3";

    #[Test]
    public function it_cuts_a_stored_sample_straight_out_of_the_volume(): void
    {
        $video = self::MKV.str_repeat('S', 400);
        $context = $this->register($this->rar('Sample/movie-sample.mkv', $video));

        $this->assertCount(1, $context->archivedMedia);
        $entry = $context->archivedMedia[0];
        $this->assertSame($video, $entry->headBytes);
        $this->assertTrue($entry->isSample);
        $this->assertTrue($entry->isComplete());
        $this->assertSame(VideoContainer::Matroska, $entry->container);
        $this->assertSame(7, $entry->sourceIndex);
    }

    #[Test]
    public function it_knows_how_much_more_of_a_cut_off_video_its_volume_holds(): void
    {
        $volume = $this->rarVolume('movie.mkv', self::MKV.str_repeat('M', 496), 1500, false, true);
        $downloaded = substr($volume, 0, -200);

        $entry = $this->register($downloaded, ['mediaMainVideoMinBytes' => 1000])->archivedMedia[0];

        $this->assertSame(300, strlen($entry->headBytes));
        $this->assertFalse($entry->isSample);
        $this->assertSame(strlen($downloaded), $entry->volumeBytesDownloaded);
        $this->assertSame(strlen($volume) - 1, $entry->inVolumeEnd);
        $this->assertSame(200, $entry->continuationBytes(10000), 'the rest of this volume, not the whole file');
        $this->assertSame(50, $entry->continuationBytes(350));
    }

    #[Test]
    public function it_leaves_compressed_and_passworded_videos_alone(): void
    {
        $video = self::MKV.str_repeat('V', 100);

        $this->assertSame([], $this->register($this->rar('movie.mkv', $video, method: 0x33))->archivedMedia);
        $this->assertSame([], $this->register($this->rar('movie.mkv', $video, encryptedFile: true))->archivedMedia);
        $this->assertSame([], $this->register($this->rar('movie.nfo', $video))->archivedMedia);
        $this->assertSame([], $this->register($this->rar('movie.mkv', $video), ['mediaArchiveSlicing' => false])->archivedMedia);
    }

    #[Test]
    public function it_reads_the_chunk_of_a_video_that_a_later_volume_holds(): void
    {
        $chunk = str_repeat('C', 500);
        $volume = $this->rarVolume('Movie/movie.mkv', $chunk, 1500, true, true);

        $slicer = new ArchivedMediaSlicer($this->makeConfig());

        $this->assertSame($chunk, $slicer->chunkFromVolume($volume, 'Movie/movie.mkv'));
        $this->assertNull($slicer->chunkFromVolume($volume, 'other.mkv'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function register(string $data, array $config = []): ReleaseProcessingContext
    {
        $context = new ReleaseProcessingContext(new Release(['id' => 1, 'guid' => 'guid-1']));
        $archive = new ArchiveInfo;
        $archive->setData($data, true);

        (new ArchivedMediaSlicer($this->makeConfig(['processThumbnails' => true, 'mediaArchiveSlicing' => true, ...$config])))
            ->register($context, $data, $archive->getArchiveFileList(), new ArchiveCandidate('movie.part01.rar', ['<a>', '<b>'], true, 7));

        return $context;
    }
}
