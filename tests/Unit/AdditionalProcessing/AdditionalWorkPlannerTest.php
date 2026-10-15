<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\AdditionalWorkPlanner;
use App\Services\AdditionalProcessing\DTO\ArchiveCandidate;
use PHPUnit\Framework\Attributes\DataProvider;
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

            $this->assertSame(['<first>', ...array_slice(['<second>', '<third>'], 0, max(0, $budget - 1))], $plan->probeCandidates[0]->messageIds);
            $this->assertSame(['<last>'], $plan->probeCandidates[0]->tailMessageIds);
        }
    }

    #[Test]
    public function it_probes_a_lone_file_without_an_extension(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());

        $plan = $planner->plan([
            ['title' => '"KlUC4yTqeaIpcTbOIYdzhqqWF" yEnc (1/103)', 'segments' => ['<first>', '<second>'], 'filecount' => 1],
        ], 'alt.binaries.misc');

        $this->assertSame('<first>', $plan->probeCandidates[0]->messageIds[0]);
        $this->assertTrue($plan->probeIsOnlyFile);
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

        $this->assertSame('<first>', $plan->probeCandidates[0]->messageIds[0]);
        $this->assertSame(['<last>'], $plan->probeCandidates[0]->tailMessageIds);
        $this->assertSame([], $oneSegment->probeCandidates[0]->tailMessageIds);
    }

    #[Test]
    public function it_does_not_probe_an_entry_merged_from_several_nzb_files(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());

        $plan = $planner->plan([
            ['title' => '"KlUC4yTqeaIpcTbOIYdzhqqWF" yEnc (1/103)', 'segments' => ['<par2>', '<data>'], 'filecount' => 2],
        ], 'alt.binaries.misc');

        $this->assertSame([], $plan->probeCandidates);
    }

    #[Test]
    public function it_does_not_probe_named_files_or_releases_with_a_named_archive(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());

        $named = $planner->plan([
            ['title' => '"Show.S01E01.1080p.mkv" yEnc (1/50)', 'segments' => ['<video>'], 'filecount' => 1],
        ], 'alt.binaries.misc');
        $withArchive = $planner->plan([
            ['title' => '"Show.S01E01.part01.rar" yEnc (1/50)', 'segments' => ['<rar>'], 'filecount' => 1],
            ['title' => '"aB3dE5fG7hJ9" yEnc (1/50)', 'segments' => ['<one>'], 'filecount' => 1],
        ], 'alt.binaries.misc');

        $this->assertSame([], $named->probeCandidates);
        $this->assertSame([], $withArchive->probeCandidates);
    }

    #[Test]
    public function it_probes_the_largest_unnamed_files_of_a_multi_file_release_up_to_the_cap(): void
    {
        $contents = [
            ['title' => '"aB3dE5fG7hJ9" yEnc (1/2)', 'segments' => ['<small-1>', '<small-2>'], 'size' => 10, 'filecount' => 1],
            ['title' => '"kL2mN4pQ6rS8" yEnc (1/3)', 'segments' => ['<big-1>', '<big-2>', '<big-3>'], 'size' => 500, 'filecount' => 1],
            ['title' => '"release.nfo" yEnc (1/1)', 'segments' => ['<nfo>'], 'size' => 900, 'filecount' => 1],
            ['title' => '"zZ9yY8xX7wW6" yEnc (1/50)', 'segments' => ['<merged-1>', '<merged-2>'], 'size' => 900, 'filecount' => 2],
            ['title' => '"tT1uU2vV3wW4" yEnc (1/3)', 'segments' => ['<equal-1>', '<equal-2>'], 'size' => 500, 'filecount' => 1],
            ['title' => '"qQ5rR6sS7tT8" yEnc (1/1)', 'segments' => ['<mid>'], 'size' => 100, 'filecount' => 1],
        ];

        $plan = (new AdditionalWorkPlanner($this->makeConfig()))->plan($contents, 'alt.binaries.misc');

        $this->assertFalse($plan->probeIsOnlyFile);
        $this->assertSame(
            [['<big-1>', '<big-2>', '<big-3>'], ['<equal-1>', '<equal-2>'], ['<mid>']],
            array_map(static fn ($candidate): array => $candidate->messageIds, $plan->probeCandidates),
        );
        $this->assertSame([['<big-3>'], ['<equal-2>'], []], array_map(static fn ($candidate): array => $candidate->tailMessageIds, $plan->probeCandidates));
        $this->assertSame([1, 4, 5], array_map(static fn ($candidate): int => $candidate->sourceIndex, $plan->probeCandidates));

        $one = (new AdditionalWorkPlanner($this->makeConfig(['archiveProbeFiles' => 1])))->plan($contents, 'alt.binaries.misc');
        $none = (new AdditionalWorkPlanner($this->makeConfig(['archiveProbeFiles' => 0])))->plan($contents, 'alt.binaries.misc');
        $this->assertCount(1, $one->probeCandidates);
        $this->assertSame([], $none->probeCandidates);
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

    #[Test]
    public function it_picks_the_par2_index_rather_than_a_recovery_volume(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig(['par2MediaMapping' => true, 'par2IndexMaxSegments' => 1]));

        $plan = $planner->plan([
            ['title' => '"movie.vol00+01.par2" yEnc (1/2)', 'segments' => ['<vol>', '<vol-2>'], 'size' => 500, 'filecount' => 1],
            ['title' => '"movie.par2" yEnc (1/2)', 'segments' => ['<index>', '<index-2>'], 'size' => 800, 'filecount' => 1],
            ['title' => '"movie.part01.rar" yEnc (1/9)', 'segments' => ['<rar>'], 'size' => 9000, 'filecount' => 1],
        ], 'alt.binaries.test');

        $this->assertCount(1, $plan->par2IndexCandidates);
        $this->assertSame(['<index>'], $plan->par2IndexCandidates[0]->messageIds);
        $this->assertSame(1, $plan->par2IndexCandidates[0]->sourceIndex);
    }

    #[Test]
    public function it_takes_the_smallest_unnamed_files_as_the_index_of_an_obfuscated_release(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig(['par2MediaMapping' => true]));
        $nzb = [
            ['title' => '"8f3a1c" yEnc (1/90)', 'segments' => ['<big>'], 'size' => 90000, 'filecount' => 1],
            ['title' => '"0c71de" yEnc (1/1)', 'segments' => ['<small>'], 'size' => 700, 'filecount' => 1],
            ['title' => '"55be02" yEnc (1/5)', 'segments' => ['<mid>'], 'size' => 5000, 'filecount' => 1],
        ];

        $this->assertSame(
            [1, 2],
            array_map(static fn (ArchiveCandidate $candidate): int => $candidate->sourceIndex, $planner->plan($nzb, 'alt.binaries.test')->par2IndexCandidates),
        );
        $this->assertSame([], $planner->plan(array_slice($nzb, 0, 2), 'alt.binaries.test')->par2IndexCandidates);
        $this->assertSame([], (new AdditionalWorkPlanner($this->makeConfig()))->plan($nzb, 'alt.binaries.test')->par2IndexCandidates);
    }

    #[Test]
    public function it_lists_full_length_videos_largest_first_without_samples_or_archives(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig([
            'processThumbnails' => true,
            'mediaMainVideoFallback' => true,
            'mediaMainVideoMinBytes' => 1000,
        ]));

        $plan = $planner->plan([
            ['title' => '"Movie.Part1.mkv" yEnc (1/9)', 'segments' => ['<a>'], 'size' => 5000, 'filecount' => 1],
            ['title' => '"Movie.sample.mkv" yEnc (1/1)', 'segments' => ['<s>'], 'size' => 9000, 'filecount' => 1],
            ['title' => '"Movie.Part2.mkv" yEnc (1/9)', 'segments' => ['<b>'], 'size' => 8000, 'filecount' => 1],
            ['title' => '"Movie.mkv.part01.rar" yEnc (1/9)', 'segments' => ['<r>'], 'size' => 9900, 'filecount' => 1],
            ['title' => '"Extras.mkv" yEnc (1/1)', 'segments' => ['<e>'], 'size' => 500, 'filecount' => 1],
        ], 'alt.binaries.test');

        $this->assertSame([2, 0], $plan->mainVideoSourceIndexes);
    }

    #[Test]
    public function it_does_not_take_an_archived_sample_for_a_video_sample(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig(['processThumbnails' => true]));

        $plan = $planner->plan([
            ['title' => '"movie-sample.part01.rar" yEnc (1/3)', 'segments' => ['<rar>']],
        ], 'alt.binaries.test');

        $this->assertSame([], $plan->sampleMessageIds);
        $this->assertTrue($plan->hasCompressedFile());
    }

    #[Test]
    public function it_plans_whole_downloads_of_the_best_few_images(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig(['processJPGSample' => true, 'mediaImageMaxBytes' => 3000000]));

        $plan = $planner->plan([
            ['title' => '"movie.back.jpg" yEnc (1/2)', 'segments' => ['<back-1>', '<back-2>'], 'size' => 900000, 'filecount' => 1],
            ['title' => '"movie.cover.jpg" yEnc (1/3)', 'segments' => ['<cover-1>', '<cover-2>', '<cover-3>'], 'size' => 1900000, 'filecount' => 1],
            ['title' => '"movie.screens.jpg" yEnc (1/4)', 'segments' => ['<s-1>', '<s-2>', '<s-3>', '<s-4>'], 'size' => 2600000, 'filecount' => 1],
            ['title' => '"movie.huge.screens.jpg" yEnc (1/9)', 'segments' => ['<h-1>'], 'size' => 6000000, 'filecount' => 1],
            ['title' => '"logo.png" yEnc (1/1)', 'segments' => ['<logo>'], 'size' => 4000, 'filecount' => 1],
            ['title' => '"movie.jpg" yEnc (1/1)', 'segments' => ['<plain>'], 'size' => 300000, 'filecount' => 1],
        ], 'alt.binaries.erotica');

        $this->assertSame(['<s-1>', '<s-2>', '<s-3>', '<s-4>'], $plan->jpgMessageIds);
        $this->assertSame(
            ['"movie.screens.jpg" yEnc (1/4)', '"movie.cover.jpg" yEnc (1/3)', '"movie.jpg" yEnc (1/1)'],
            array_map(static fn ($candidate): string => $candidate->title, $plan->imageCandidates),
        );
    }

    #[Test]
    public function it_inspects_the_earliest_rar_volume_that_has_its_first_segment(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());
        $titles = static fn (array $candidates): array => array_map(
            static fn (ArchiveCandidate $candidate): string => $candidate->title,
            $candidates,
        );

        $plan = $planner->plan([
            ['title' => '"release.part01.rar" yEnc (2/50)', 'segments' => ['<p1-2>', '<p1-3>'], 'firstsegment' => false],
            ['title' => '"release.part02.rar" yEnc (2/50)', 'segments' => ['<p2-2>'], 'firstsegment' => false],
            ['title' => '"release.part03.rar" yEnc (1/50)', 'segments' => ['<p3-1>', '<p3-2>'], 'firstsegment' => true],
            ['title' => '"release.part04.rar" yEnc (1/50)', 'segments' => ['<p4-1>'], 'firstsegment' => true],
        ], 'alt.binaries.test');
        $oldStyle = $planner->plan([
            ['title' => 'release.rar yEnc (2/50)', 'segments' => ['<rar-2>'], 'firstsegment' => false],
            ['title' => 'release.r00 yEnc (1/50)', 'segments' => ['<r00-1>'], 'firstsegment' => true],
        ], 'alt.binaries.test');

        $this->assertSame(
            ['"release.part03.rar" yEnc (1/50)', '"release.part01.rar" yEnc (2/50)', '"release.part02.rar" yEnc (2/50)', '"release.part04.rar" yEnc (1/50)'],
            $titles($plan->prioritizedArchiveCandidates()),
        );
        $this->assertSame(['<p3-1>', '<p3-2>'], $plan->prioritizedArchiveCandidates()[0]->messageIds);
        $this->assertSame(['release.r00 yEnc (1/50)', 'release.rar yEnc (2/50)'], $titles($oldStyle->prioritizedArchiveCandidates()));
    }

    #[Test]
    public function it_keeps_the_first_rar_volume_when_it_has_its_first_segment_or_no_volume_does(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());

        $complete = $planner->plan([
            ['title' => '"release.part01.rar" yEnc (1/50)', 'segments' => ['<p1-1>'], 'firstsegment' => true],
            ['title' => '"release.part02.rar" yEnc (1/50)', 'segments' => ['<p2-1>'], 'firstsegment' => true],
        ], 'alt.binaries.test');
        $noneComplete = $planner->plan([
            ['title' => '"release.part01.rar" yEnc (2/50)', 'segments' => ['<p1-2>'], 'firstsegment' => false],
            ['title' => '"release.part02.rar" yEnc (2/50)', 'segments' => ['<p2-2>'], 'firstsegment' => false],
        ], 'alt.binaries.test');

        foreach ([$complete, $noneComplete] as $plan) {
            [$first, $second] = $plan->prioritizedArchiveCandidates();
            $this->assertStringStartsWith('"release.part01.rar"', $first->title);
            $this->assertTrue($first->likelyFirstVolume);
            $this->assertFalse($second->likelyFirstVolume);
        }
    }

    #[Test]
    public function it_groups_archive_volumes_into_sets(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());
        $keys = static fn (array $candidates): array => array_map(
            static fn (ArchiveCandidate $candidate): string => $candidate->setKey,
            $candidates,
        );

        $episodes = $planner->plan([
            ['title' => '"Show.S01E01.1080p-GRP.part01.rar" yEnc (1/50)', 'segments' => ['<e1-1>']],
            ['title' => '"Show.S01E01.1080p-GRP.part02.rar" yEnc (1/50)', 'segments' => ['<e1-2>']],
            ['title' => '"Show.S01E02.1080p-GRP.rar" yEnc (1/50)', 'segments' => ['<e2-1>']],
            ['title' => '"Show.S01E02.1080p-GRP.r00" yEnc (1/50)', 'segments' => ['<e2-2>']],
            ['title' => '"Show.S01E03.1080p-GRP.7z.001" yEnc (1/50)', 'segments' => ['<e3-1>']],
            ['title' => '"Subs.zip" yEnc (1/1)', 'segments' => ['<subs>']],
        ], 'alt.binaries.test');
        $hashed = $planner->plan([
            ['title' => '"0123456789abcdef0123456789abcdef.10" yEnc (1/50)', 'segments' => ['<h-1>']],
            ['title' => '"0123456789abcdef0123456789abcdef.11" yEnc (1/50)', 'segments' => ['<h-2>']],
        ], 'alt.binaries.test');

        $this->assertSame(
            ['rar:show.s01e01.1080p-grp', 'rar:show.s01e01.1080p-grp', 'rar:show.s01e02.1080p-grp', 'rar:show.s01e02.1080p-grp', '7z:show.s01e03.1080p-grp', 'zip:subs'],
            $keys($episodes->archiveCandidates),
        );
        $this->assertSame(4, $episodes->archiveSetCount());
        $this->assertSame(['split:0123456789abcdef0123456789abcdef', 'split:0123456789abcdef0123456789abcdef'], $keys($hashed->archiveCandidates));
        $this->assertSame(1, $hashed->archiveSetCount());
    }

    #[Test]
    public function it_keeps_the_set_when_a_later_rar_volume_replaces_the_first(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());

        $plan = $planner->plan([
            ['title' => '"Show.S01E01.part01.rar" yEnc (2/50)', 'segments' => ['<p1-2>'], 'firstsegment' => false],
            ['title' => '"Show.S01E01.part02.rar" yEnc (1/50)', 'segments' => ['<p2-1>'], 'firstsegment' => true],
        ], 'alt.binaries.test');

        foreach ($plan->prioritizedArchiveCandidates() as $candidate) {
            $this->assertSame('rar:show.s01e01', $candidate->setKey);
        }
    }

    #[Test]
    public function it_checks_only_the_first_article_of_a_split_video_and_keeps_numbered_archives(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig(['processMediaInfo' => true]));

        $split = $planner->plan([
            ['title' => '"Movie.2026.mkv.002" yEnc (1/80)', 'segments' => ['<m2-1>']],
            ['title' => '"Movie.2026.mkv.001" yEnc (1/80)', 'segments' => ['<m1-1>', '<m1-2>']],
        ], 'alt.binaries.test');
        $archives = $planner->plan([
            ['title' => '"Movie.2026.7z.001" yEnc (1/80)', 'segments' => ['<z1>']],
            ['title' => '"Movie.2026.001" yEnc (1/80)', 'segments' => ['<n1>']],
        ], 'alt.binaries.test');

        $this->assertCount(1, $split->archiveCandidates);
        $this->assertSame('"Movie.2026.mkv.001" yEnc (1/80)', $split->archiveCandidates[0]->title);
        $this->assertSame(['<m1-1>'], $split->archiveCandidates[0]->messageIds);
        $this->assertSame('split:movie.2026.mkv', $split->archiveCandidates[0]->volumeSet());
        $this->assertSame('', $split->mediaInfoMessageId);
        $this->assertSame(
            ['"Movie.2026.7z.001" yEnc (1/80)', '"Movie.2026.001" yEnc (1/80)'],
            array_map(static fn (ArchiveCandidate $candidate): string => $candidate->title, $archives->archiveCandidates),
        );
        $this->assertSame('', $archives->mediaInfoMessageId);
    }

    #[Test]
    public function it_checks_a_split_video_when_a_sample_has_the_mediainfo_or_only_passwords_are_checked(): void
    {
        $contents = [
            ['title' => '"Movie.2026.sample.mkv" yEnc (1/5)', 'segments' => ['<s-1>']],
            ['title' => '"Movie.2026.mkv.001" yEnc (1/80)', 'segments' => ['<m1-1>', '<m1-2>']],
        ];

        foreach ([['processMediaInfo' => true], ['processPasswords' => true]] as $settings) {
            $plan = (new AdditionalWorkPlanner($this->makeConfig($settings)))->plan($contents, 'alt.binaries.test');

            $this->assertTrue($plan->hasCompressedFile());
            $this->assertSame(['<m1-1>'], $plan->archiveCandidates[0]->messageIds);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function volumeNames(): array
    {
        return [
            'RAR part' => ['Movie.2026.part01.rar', 'rar:movie.2026'],
            'RAR old-style' => ['Movie.2026.r00', 'rar:movie.2026'],
            '7z volume' => ['Movie.2026.7z.002', '7z:movie.2026'],
            'ZIP' => ['Movie.2026.zip', 'zip:movie.2026'],
            'ZIP volume' => ['Movie.2026.z01', 'zip:movie.2026'],
            'split video' => ['Movie.2026.mkv.001', 'split:movie.2026.mkv'],
            'spaced RAR part' => ['First Movie.part01.rar', 'rar:first movie'],
            'spaced ZIP volume' => ['First Movie.z01', 'zip:first movie'],
            'spaced 7z volume' => ['First Movie.7z.001', '7z:first movie'],
            'spaced split video' => ['First Movie.mkv.001', 'split:first movie.mkv'],
        ];
    }

    #[Test]
    #[DataProvider('volumeNames')]
    public function it_keys_quoted_and_unquoted_subjects_alike(string $name, string $key): void
    {
        $subjects = [
            '"'.$name.'" yEnc (1/50)',
            $name.' yEnc (1/50)',
            $name.' (1/50)',
            strtoupper($name).' - yEnc (01/50)',
            '[01/10] - '.$name.' yEnc',
            '[01/10] - "'.$name.'" yEnc (1/50)',
        ];

        foreach ($subjects as $subject) {
            $this->assertSame(strtolower($name), ArchiveCandidate::fileName($subject), $subject);
            $this->assertSame($key, ArchiveCandidate::volumeSetKey($subject), $subject);
        }
    }

    #[Test]
    public function it_keeps_unquoted_names_that_differ_before_their_last_space_apart(): void
    {
        $this->assertSame('first movie.rar', ArchiveCandidate::fileName('First Movie.rar yEnc (1/50)'));
        $this->assertSame('rar:first movie', ArchiveCandidate::volumeSetKey('First Movie.rar yEnc (1/50)'));
        $this->assertSame('rar:second movie', ArchiveCandidate::volumeSetKey('Second Movie.rar yEnc (1/50)'));
        $this->assertSame('rar:other post - movie.2026', ArchiveCandidate::volumeSetKey('Other Post - Movie.2026.part01.rar - yEnc (01/50)'));
        $this->assertSame('rar:movie (1 of 2) extras', ArchiveCandidate::volumeSetKey('Movie (1 of 2) Extras.rar yEnc (1/50)'));
        $this->assertSame('rar:movie (2 of 2) extras', ArchiveCandidate::volumeSetKey('Movie (2 of 2) Extras.rar yEnc (1/50)'));
    }

    #[Test]
    public function it_groups_unquoted_volumes_whose_subjects_differ_only_in_post_counters(): void
    {
        $subjects = [
            'Group Post - Movie.2026.part01.rar - yEnc (01/50)',
            'Group Post - Movie.2026.part02.rar - yEnc (02/50)',
            'Group Post [01/10] - Movie.2026.part01.rar yEnc (1/50)',
            'Group Post [02/10] - Movie.2026.part02.rar yEnc (1/50)',
        ];

        $this->assertSame(
            ['rar:group post - movie.2026', 'rar:group post - movie.2026', 'rar:group post movie.2026', 'rar:group post movie.2026'],
            array_map(ArchiveCandidate::volumeSetKey(...), $subjects),
        );
    }

    #[Test]
    public function it_groups_only_genuine_sibling_volumes_of_one_archive_family(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig());
        $titles = [
            'Movie.2026.part01.rar yEnc (1/50)',
            '"Movie.2026.part02.rar" yEnc (1/50)',
            '"Movie.2026.rar" yEnc (1/50)',
            'Movie.2026.r00 yEnc (1/50)',
            '"Movie.2026.zip" yEnc (1/50)',
            'Movie.2026.z01 yEnc (1/50)',
            '"Movie.2026.7z.001" yEnc (1/50)',
            'Movie.2026.mkv.001 yEnc (1/80)',
        ];

        $plan = $planner->plan(array_map(
            static fn (string $title, int $index): array => ['title' => $title, 'segments' => ['<a'.$index.'>']],
            $titles,
            array_keys($titles),
        ), 'alt.binaries.test');
        $keys = [];
        foreach ($plan->archiveCandidates as $candidate) {
            $keys[$candidate->title] = $candidate->setKey;
            $this->assertSame($candidate->setKey, $candidate->volumeSet());
        }

        $this->assertSame([
            'Movie.2026.part01.rar yEnc (1/50)' => 'rar:movie.2026',
            '"Movie.2026.part02.rar" yEnc (1/50)' => 'rar:movie.2026',
            '"Movie.2026.rar" yEnc (1/50)' => 'rar:movie.2026',
            'Movie.2026.r00 yEnc (1/50)' => 'rar:movie.2026',
            '"Movie.2026.zip" yEnc (1/50)' => 'zip:movie.2026',
            'Movie.2026.z01 yEnc (1/50)' => 'zip:movie.2026',
            '"Movie.2026.7z.001" yEnc (1/50)' => '7z:movie.2026',
            'Movie.2026.mkv.001 yEnc (1/80)' => 'split:movie.2026.mkv',
        ], $keys);
        $this->assertSame(4, $plan->archiveSetCount());
    }

    #[Test]
    public function it_plans_an_unquoted_split_video_like_a_quoted_one(): void
    {
        $planner = new AdditionalWorkPlanner($this->makeConfig(['processPasswords' => true]));

        foreach (['Movie.2026.mkv.%03d yEnc (1/80)', '[%1$02d/80] - Movie.2026.mkv.%1$03d yEnc', '"Movie.2026.mkv.%03d" yEnc (1/80)'] as $format) {
            $plan = $planner->plan([
                ['title' => sprintf($format, 2), 'segments' => ['<m2-1>']],
                ['title' => sprintf($format, 1), 'segments' => ['<m1-1>', '<m1-2>']],
            ], 'alt.binaries.test');

            $this->assertCount(1, $plan->archiveCandidates, $format);
            $this->assertSame(sprintf($format, 1), $plan->archiveCandidates[0]->title);
            $this->assertSame(['<m1-1>'], $plan->archiveCandidates[0]->messageIds);
            $this->assertSame('split:movie.2026.mkv', $plan->archiveCandidates[0]->setKey);
        }
    }
}
