<?php

declare(strict_types=1);

namespace Tests\Unit\Services\NameFixing;

use App\Services\NameFixing\FilePrioritizer;
use PHPUnit\Framework\TestCase;

class FilePrioritizerTest extends TestCase
{
    public function test_prioritize_for_matching_skips_url_and_dvd_structure_noise(): void
    {
        $prioritizer = new FilePrioritizer;

        $prioritized = $prioritizer->prioritizeForMatching([
            'Film ;-)/VIDEO_TS/VIDEO_TS.VOB',
            'Film ;-)/VIDEO_TS/VTS_01_1.VOB',
            'Film ;-)/1.jpg',
            'Film ;-)/cover the fisher king.jpg',
            'Film ;-)/Extreem Online Contact.url',
            'Film ;-)/release.nfo',
        ]);

        $this->assertSame([
            'Film ;-)/release.nfo',
            'Film ;-)/cover the fisher king.jpg',
        ], array_values($prioritized));
    }
}
