<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\AdditionalWorkPlanner;
use App\Services\AdditionalProcessing\DTO\ArchiveCandidate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AdditionalWorkPlannerTest extends TestCase
{
    use CreatesProcessingConfiguration;

    #[Test]
    public function it_builds_one_ordered_plan_for_direct_and_archive_candidates(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig([
            'processThumbnails' => true,
            'processJPGSample' => true,
            'processMediaInfo' => true,
            'processAudioInfo' => true,
        ]));

        $plan = $planner->plan([
            ['title' => 'release.part02.rar', 'segments' => ['<part-2>', '<shared>']],
            ['title' => '"sample.mkv" yEnc', 'segments' => ['<sample>', '<sample-2>']],
            ['title' => '"cover.jpg" yEnc', 'segments' => ['<cover>']],
            ['title' => '"track.FLAC" yEnc', 'segments' => ['<audio>']],
            ['title' => 'release.part01.rar', 'segments' => ['<part-1>', '<shared>', '<part-1>']],
        ], 'alt.binaries.test');

        $this->assertSame(['<sample>', '<sample-2>'], $plan->sampleMessageIds);
        $this->assertSame(['<cover>'], $plan->jpgMessageIds);
        $this->assertSame('<sample>', $plan->mediaInfoMessageId);
        $this->assertSame('<audio>', $plan->audioInfoMessageId);
        $this->assertSame('FLAC', $plan->audioInfoExtension);
        $this->assertTrue($plan->hasCompressedFile());
        $this->assertSame(
            ['release.part02.rar', 'release.part01.rar'],
            array_map(static fn (ArchiveCandidate $candidate): string => $candidate->title, $plan->archiveCandidates),
        );
        $this->assertFalse($plan->archiveCandidates[0]->likelyFirstVolume);
        $this->assertTrue($plan->archiveCandidates[1]->likelyFirstVolume);
        $this->assertSame(
            ['release.part01.rar', 'release.part02.rar'],
            array_map(static fn (ArchiveCandidate $candidate): string => $candidate->title, $plan->prioritizedArchiveCandidates()),
        );
        $this->assertSame(
            ['release.part01.rar', 'release.part02.rar'],
            array_map(static fn (ArchiveCandidate $candidate): string => $candidate->title, $plan->orderedArchiveCandidates(true)),
        );
        $this->assertSame(3, $plan->duplicateMessageIdCount);
        $this->assertSame([], $plan->unsupportedReasons);
    }

    #[Test]
    public function it_selects_the_first_7z_volume_with_the_last_volume_tail(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());

        $plan = $planner->plan([
            ['title' => '[02/12] - "G9AJxkjPdN0iBdyG.7z.002" yEnc (1/3)', 'segments' => ['<v2-1>', '<v2-2>', '<v2-3>']],
            ['title' => '[01/12] - "G9AJxkjPdN0iBdyG.7z.001" yEnc (1/3)', 'segments' => ['<v1-1>', '<v1-2>', '<v1-3>', '<v1-4>']],
            ['title' => '[03/12] - "G9AJxkjPdN0iBdyG.7z.003" yEnc (1/2)', 'segments' => ['<v3-1>', '<v3-2>']],
            ['title' => '[12/12] - "G9AJxkjPdN0iBdyG.vol00+01.par2" yEnc (1/1)', 'segments' => ['<par2>']],
        ], 'alt.binaries.misc');
        $single = $planner->plan([
            ['title' => '"Some.Release.7z" yEnc (1/9)', 'segments' => ['<s-1>', '<s-2>', '<s-3>', '<s-4>', '<s-5>']],
        ], 'alt.binaries.misc');

        $this->assertSame(
            ['[01/12] - "G9AJxkjPdN0iBdyG.7z.001" yEnc (1/3)'],
            array_map(static fn (ArchiveCandidate $candidate): string => $candidate->title, $plan->prioritizedArchiveCandidates()),
        );
        $this->assertTrue($plan->archiveCandidates[0]->likelyFirstVolume);
        $this->assertSame(['<v1-1>', '<v1-2>', '<v1-3>'], $plan->archiveCandidates[0]->messageIds);
        $this->assertSame(['<v3-2>'], $plan->archiveCandidates[0]->tailMessageIds);

        $this->assertTrue($single->hasCompressedFile());
        $this->assertTrue($single->archiveCandidates[0]->likelyFirstVolume);
        $this->assertSame(['<s-5>'], $single->archiveCandidates[0]->tailMessageIds);
    }

    #[Test]
    public function it_reports_book_floods_and_releases_without_supported_candidates(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());
        $contents = array_fill(0, 81, [
            'title' => 'book.epub',
            'segments' => ['<book>'],
        ]);

        $plan = $planner->plan($contents, 'alt.binaries.books');

        $this->assertSame(81, $plan->bookFileCount);
        $this->assertTrue($plan->bookFlood);
        $this->assertSame(['book-flood', 'no-supported-candidates'], $plan->unsupportedReasons);
    }

    #[Test]
    public function it_keeps_tails_separate_for_quoted_7z_names_containing_spaces(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());
        $plan = $planner->plan([
            ['title' => '"Alpha common.7z.003" yEnc', 'segments' => ['<alpha-tail>']],
            ['title' => '"Beta common.7z.002" yEnc', 'segments' => ['<beta-tail>']],
            ['title' => '"ALPHA COMMON.7z.001" yEnc', 'segments' => ['<alpha-head>']],
            ['title' => '"Beta common.7z.001" yEnc', 'segments' => ['<beta-head>']],
        ], 'alt.binaries.test');

        $candidates = $plan->prioritizedArchiveCandidates();
        $this->assertSame(['<alpha-tail>'], $candidates[0]->tailMessageIds);
        $this->assertSame(['<beta-tail>'], $candidates[1]->tailMessageIds);
        $this->assertTrue($candidates[0]->likelyFirstVolume);
        $this->assertTrue($candidates[1]->likelyFirstVolume);
    }

    #[Test]
    public function it_limits_probe_continuations_to_the_archive_segment_budget(): void
    {
        foreach ([0, 1, 2, 3] as $budget) {
            $planner = new AdditionalWorkPlanner($this->makeConfig(['maximumRarSegments' => $budget]));
            $plan = $planner->plan([
                ['title' => '"obfuscated" yEnc', 'segments' => ['<first>', '<second>', '<third>', '<last>'], 'filecount' => 1],
            ], 'alt.binaries.test');

            $this->assertSame('<first>', $plan->probeMessageId);
            $this->assertSame(array_slice(['<second>', '<third>'], 0, max(0, $budget - 1)), $plan->probeContinuationMessageIds);
            $this->assertSame(['<last>'], $plan->probeTailMessageIds);
        }
    }

    #[Test]
    public function it_probes_a_lone_file_without_an_extension(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());

        $plan = $planner->plan([
            ['title' => '"KlUC4yTqeaIpcTbOIYdzhqqWF" yEnc (1/103)', 'segments' => ['<first>', '<second>'], 'filecount' => 1],
        ], 'alt.binaries.misc');

        $this->assertSame('<first>', $plan->probeMessageId);
        $this->assertSame([], $plan->unsupportedReasons);
    }

    #[Test]
    public function it_keeps_the_last_segment_of_a_probed_file_for_a_7z_end_header(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());

        $plan = $planner->plan([
            ['title' => '"KlUC4yTqeaIpcTbOIYdzhqqWF" yEnc (1/103)', 'segments' => ['<first>', '<second>', '<last>'], 'filecount' => 1],
        ], 'alt.binaries.misc');
        $oneSegment = $planner->plan([
            ['title' => '"KlUC4yTqeaIpcTbOIYdzhqqWF" yEnc (1/1)', 'segments' => ['<only>'], 'filecount' => 1],
        ], 'alt.binaries.misc');

        $this->assertSame('<first>', $plan->probeMessageId);
        $this->assertSame(['<last>'], $plan->probeTailMessageIds);
        $this->assertSame([], $oneSegment->probeTailMessageIds);
    }

    #[Test]
    public function it_does_not_probe_an_entry_merged_from_several_nzb_files(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());

        $plan = $planner->plan([
            ['title' => '"KlUC4yTqeaIpcTbOIYdzhqqWF" yEnc (1/103)', 'segments' => ['<par2>', '<data>'], 'filecount' => 2],
        ], 'alt.binaries.misc');

        $this->assertSame('', $plan->probeMessageId);
    }

    #[Test]
    public function it_does_not_probe_named_or_multi_file_releases(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());

        $named = $planner->plan([
            ['title' => '"Show.S01E01.1080p.mkv" yEnc (1/50)', 'segments' => ['<video>']],
        ], 'alt.binaries.misc');
        $multi = $planner->plan([
            ['title' => '"aB3dE5fG7hJ9" yEnc (1/50)', 'segments' => ['<one>']],
            ['title' => '"kL2mN4pQ6rS8" yEnc (1/50)', 'segments' => ['<two>']],
        ], 'alt.binaries.misc');

        $this->assertSame('', $named->probeMessageId);
        $this->assertSame('', $multi->probeMessageId);
    }

    #[Test]
    public function it_selects_jpeg_png_and_webp_image_candidates(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig(['processJPGSample' => true]));

        foreach (['cover.jpg', 'cover.png', 'cover.webp'] as $index => $filename) {
            $messageId = '<image-'.$index.'>';
            $plan = $planner->plan([
                ['title' => '"'.$filename.'" yEnc', 'segments' => [$messageId]],
            ], 'alt.binaries.test');

            $this->assertSame([$messageId], $plan->jpgMessageIds, $filename.' should be selected');
        }
    }

    #[Test]
    public function it_keeps_a_usable_last_volume_when_the_first_volume_is_missing(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());
        $plan = $planner->plan([
            ['title' => 'release.part99.rar', 'segments' => ['<last-volume>']],
        ], 'alt.binaries.test');

        $this->assertFalse($plan->archiveCandidates[0]->likelyFirstVolume);
        $this->assertSame(['<last-volume>'], $plan->orderedArchiveCandidates(true)[0]->messageIds);
    }
}
