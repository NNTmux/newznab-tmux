<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BookService;
use App\Services\NameFixing\Extractors\ObfuscatedSubjectExtractor;
use Tests\TestCase;

class BookServiceTest extends TestCase
{
    private function makeService(): BookService
    {
        /** @var BookService $service */
        $service = (new \ReflectionClass(BookService::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(BookService::class, 'obfuscatedSubjectExtractor');
        $property->setAccessible(true);
        $property->setValue($service, new ObfuscatedSubjectExtractor);

        return $service;
    }

    public function test_parse_release_name_extracts_author_title_and_isbn(): void
    {
        $service = $this->makeService();

        $parsed = $service->parseReleaseName('Eric.Evans - Domain.Driven.Design 978-0321125217 RETAIL EPUB');

        $this->assertSame('Domain Driven Design', $parsed->title);
        $this->assertSame('Eric Evans', $parsed->author);
        $this->assertSame('9780321125217', $parsed->isbn);
    }

    public function test_parse_release_name_flags_software_as_junk(): void
    {
        $service = $this->makeService();

        $parsed = $service->parseReleaseName('Foxit PDF Editor Pro 14.0.4.33508 Multilingual x64', 'ebook');

        $this->assertTrue($parsed->isJunk);
    }

    public function test_parse_release_name_flags_tv_season_as_junk(): void
    {
        $service = $this->makeService();

        $tvReleases = [
            'El verano en que me enamore Temporada 01 PAR2',
            'Breaking.Bad.S05E16.720p.BluRay.x264',
            'The.Last.of.Us.Season.1.WEBRip.x265',
            'La.Casa.de.Papel.Saison.03.HDTV',
            'Dark.Staffel.02.German.WEB-DL',
            'Game.of.Thrones.S08E06.1080p.AMZN.WEB-DL',
        ];

        foreach ($tvReleases as $release) {
            $parsed = $service->parseReleaseName($release, 'ebook');
            $this->assertTrue($parsed->isJunk, "Expected '{$release}' to be flagged as junk");
        }
    }

    public function test_parse_release_name_flags_par2_repair_files_as_junk(): void
    {
        $service = $this->makeService();

        $parsed = $service->parseReleaseName('Some.Release.Name.PAR2', 'ebook');
        $this->assertTrue($parsed->isJunk);
    }

    public function test_parse_release_name_strips_video_terms_from_title(): void
    {
        $service = $this->makeService();

        $parsed = $service->parseReleaseName('El verano en que me enamore Temporada 01 PAR2', 'ebook');

        $this->assertStringNotContainsString('Temporada', $parsed->title);
        $this->assertStringNotContainsString('PAR2', $parsed->title);
    }

    public function test_tv_and_video_releases_flagged_as_junk_for_both_types(): void
    {
        $service = $this->makeService();

        $releases = [
            'El verano en que me enamore Temporada 01 PAR2',
            'Breaking.Bad.S05E16.720p.BluRay.x264',
            'The.Last.of.Us.Season.1.WEBRip.x265',
            'Game.of.Thrones.S08E06.1080p.AMZN.WEB-DL',
        ];

        foreach ($releases as $release) {
            $ebookParsed = $service->parseReleaseName($release, 'ebook');
            $this->assertTrue($ebookParsed->isJunk, "Expected ebook '{$release}' to be junk");

            $audiobookParsed = $service->parseReleaseName($release, 'audiobook');
            $this->assertTrue($audiobookParsed->isJunk, "Expected audiobook '{$release}' to be junk");
        }
    }

    public function test_legitimate_book_not_flagged_as_junk(): void
    {
        $service = $this->makeService();

        $parsed = $service->parseReleaseName('Eric.Evans - Domain.Driven.Design 978-0321125217 RETAIL EPUB', 'ebook');
        $this->assertFalse($parsed->isJunk);
        $this->assertFalse($parsed->isMagazine);
    }

    public function test_parse_release_name_extracts_obfuscated_quoted_book_title(): void
    {
        $service = $this->makeService();

        $parsed = $service->parseReleaseName('N:/NZB [2/8] - "Harry Styles Songbook - 1st Edition 2026.part1.rar"', 'ebook');

        $this->assertSame('Harry Styles Songbook - 1st Edition', $parsed->title);
        $this->assertFalse($parsed->isJunk);
    }

    public function test_parse_release_name_marks_issue_with_year_as_magazine(): void
    {
        $service = $this->makeService();

        $parsed = $service->parseReleaseName('N:/NZB [2/5] - "History of War - Issue 158, 2026.rar"', 'ebook');

        $this->assertSame('History of War - Issue 158,', $parsed->title);
        $this->assertTrue($parsed->isMagazine);
    }

    public function test_parse_release_name_marks_mcn_hybrid_magazine_as_magazine(): void
    {
        $service = $this->makeService();

        $parsed = $service->parseReleaseName('MCN.April.22.2026.HYBRID.MAGAZINE.eBook-21A1', 'ebook');

        $this->assertTrue($parsed->isMagazine);
        $this->assertSame('MCN - April 22, 2026', $parsed->title);
    }

    public function test_parse_release_name_marks_normalized_mcn_date_as_magazine(): void
    {
        $service = $this->makeService();

        $parsed = $service->parseReleaseName('MCN April 22, 2026', 'ebook');

        $this->assertTrue($parsed->isMagazine);
    }

    public function test_parse_release_name_keeps_mcn_prefix_in_title_for_magazine(): void
    {
        $service = $this->makeService();

        $parsed = $service->parseReleaseName('MCN - April 22, 2026', 'ebook');

        $this->assertTrue($parsed->isMagazine);
        $this->assertStringStartsWith('MCN', $parsed->title);
    }
}
