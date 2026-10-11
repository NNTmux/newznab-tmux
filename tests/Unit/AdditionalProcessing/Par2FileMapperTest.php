<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\DTO\Par2FileEntry;
use App\Services\AdditionalProcessing\Enums\MappingConfidence;
use App\Services\AdditionalProcessing\Media\Par2FileMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class Par2FileMapperTest extends TestCase
{
    #[Test]
    public function it_names_files_whose_posted_size_fits_only_one_par2_entry(): void
    {
        $mapped = (new Par2FileMapper)->map(
            [$this->entry(3000), $this->entry(50000), $this->entry(60000)],
            [new Par2FileEntry('Movie.sample.mkv', 2900), new Par2FileEntry('Movie.mkv', 48500)],
        );

        $this->assertSame([0, 1], array_keys($mapped));
        $this->assertSame('Movie.sample.mkv', $mapped[0]->file->name);
        $this->assertSame('Movie.mkv', $mapped[1]->file->name);
        $this->assertSame(MappingConfidence::UniqueSize, $mapped[1]->confidence);
    }

    #[Test]
    public function it_leaves_equal_sized_volumes_unnamed(): void
    {
        $mapped = (new Par2FileMapper)->map(
            [$this->entry(51000), $this->entry(51000)],
            [new Par2FileEntry('Movie.part01.rar', 50000), new Par2FileEntry('Movie.part02.rar', 50000)],
        );

        $this->assertSame([], $mapped);
    }

    #[Test]
    public function it_names_the_remaining_volume_once_the_others_were_verified(): void
    {
        $part01 = new Par2FileEntry('Movie.part01.rar', 50000);
        $part02 = new Par2FileEntry('Movie.part02.rar', 50000);

        $mapped = (new Par2FileMapper)->map(
            [$this->entry(51000), $this->entry(51000)],
            [$part01, $part02],
            verified: [0 => $part02],
        );

        $this->assertSame($part02, $mapped[0]->file);
        $this->assertSame(MappingConfidence::Verified, $mapped[0]->confidence);
        $this->assertSame($part01, $mapped[1]->file);
        $this->assertSame(MappingConfidence::UniqueSize, $mapped[1]->confidence);
    }

    #[Test]
    public function it_requires_the_segment_count_to_fit_when_the_article_size_is_known(): void
    {
        $nzb = [$this->entry(10400, segments: 3), $this->entry(10400, segments: 2)];
        $file = new Par2FileEntry('Movie.sample.mkv', 10000);

        $this->assertSame([], (new Par2FileMapper)->map($nzb, [$file]));
        $this->assertSame([1], array_keys((new Par2FileMapper)->map($nzb, [$file], articlePartSize: 5000)));
    }

    #[Test]
    public function it_ignores_entries_that_merge_several_posted_files(): void
    {
        $mapped = (new Par2FileMapper)->map(
            [$this->entry(3000, fileCount: 2)],
            [new Par2FileEntry('Movie.sample.mkv', 2900)],
        );

        $this->assertSame([], $mapped);
    }

    /**
     * @return array{title: string, size: int, filecount: int, segments: list<string>}
     */
    private function entry(int $size, int $segments = 1, int $fileCount = 1): array
    {
        static $counter = 0;
        $counter++;

        return [
            'title' => '"'.md5((string) $counter).'" yEnc (1/'.$segments.')',
            'size' => $size,
            'filecount' => $fileCount,
            'segments' => array_map(static fn (int $part): string => '<'.$counter.'.'.$part.'>', range(1, $segments)),
        ];
    }
}
