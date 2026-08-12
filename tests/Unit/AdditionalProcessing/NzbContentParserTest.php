<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\NzbContentParser;
use App\Services\Nzb\NzbParserService;
use App\Services\Nzb\NzbService;
use PHPUnit\Framework\TestCase;

class NzbContentParserTest extends TestCase
{
    use CreatesProcessingConfiguration;

    public function test_it_selects_configured_segments_from_a_bare_main_video_for_media_info(): void
    {
        $parser = $this->makeParser();
        $config = $this->makeConfig([
            'processMediaInfo' => true,
            'processThumbnails' => true,
            'segmentsToDownload' => 3,
        ]);

        $result = $parser->extractMessageIDs([
            [
                'title' => 'Example.Show.S01E01.1080p.WEB-DL.mkv" yEnc (1/4)',
                'segments' => ['main-1', 'main-2', 'main-3', 'main-4'],
            ],
        ], 'alt.binaries.tv', $config);

        $this->assertSame(['main-1', 'main-2', 'main-3'], $result['mediaInfoMessageIDs']);
        $this->assertSame([], $result['sampleMessageIDs']);
    }

    public function test_it_keeps_the_explicit_sample_for_the_sample_branch_and_main_video_for_media_info(): void
    {
        $parser = $this->makeParser();
        $config = $this->makeConfig([
            'processMediaInfo' => true,
            'processThumbnails' => true,
            'segmentsToDownload' => 2,
        ]);

        $result = $parser->extractMessageIDs([
            [
                'title' => 'Example.Show.S01E01.sample.mkv" yEnc (1/3)',
                'segments' => ['sample-1', 'sample-2', 'sample-3'],
            ],
            [
                'title' => 'Example.Show.S01E01.1080p.WEB-DL.mkv" yEnc (1/3)',
                'segments' => ['main-1', 'main-2', 'main-3'],
            ],
        ], 'alt.binaries.tv', $config);

        $this->assertSame(['sample-1', 'sample-2'], $result['sampleMessageIDs']);
        $this->assertSame(['main-1', 'main-2'], $result['mediaInfoMessageIDs']);
    }

    public function test_it_does_not_treat_sample_in_the_release_title_as_a_sample_file(): void
    {
        $parser = $this->makeParser();
        $config = $this->makeConfig([
            'processMediaInfo' => true,
            'processThumbnails' => true,
            'segmentsToDownload' => 2,
        ]);

        $result = $parser->extractMessageIDs([
            [
                'title' => 'The.Sample.2026.1080p.WEB-DL.mkv" yEnc (1/3)',
                'segments' => ['main-1', 'main-2', 'main-3'],
            ],
        ], 'alt.binaries.movies', $config);

        $this->assertSame([], $result['sampleMessageIDs']);
        $this->assertSame(['main-1', 'main-2'], $result['mediaInfoMessageIDs']);
    }

    private function makeParser(): NzbContentParser
    {
        return new NzbContentParser(
            $this->createStub(NzbService::class),
            $this->createStub(NzbParserService::class),
        );
    }
}
