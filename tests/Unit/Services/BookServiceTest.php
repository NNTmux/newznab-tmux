<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BookService;
use Tests\TestCase;

class BookServiceTest extends TestCase
{
    public function test_parse_release_name_extracts_author_title_and_isbn(): void
    {
        /** @var BookService $service */
        $service = (new \ReflectionClass(BookService::class))->newInstanceWithoutConstructor();

        $parsed = $service->parseReleaseName('Eric.Evans - Domain.Driven.Design 978-0321125217 RETAIL EPUB');

        $this->assertSame('Domain Driven Design', $parsed->title);
        $this->assertSame('Eric Evans', $parsed->author);
        $this->assertSame('9780321125217', $parsed->isbn);
    }

    public function test_parse_release_name_flags_software_as_junk(): void
    {
        /** @var BookService $service */
        $service = (new \ReflectionClass(BookService::class))->newInstanceWithoutConstructor();

        $parsed = $service->parseReleaseName('Foxit PDF Editor Pro 14.0.4.33508 Multilingual x64', 'ebook');

        $this->assertTrue($parsed->isJunk);
    }

    public function test_parse_release_name_flags_tv_season_as_junk(): void
    {
        /** @var BookService $service */
        $service = (new \ReflectionClass(BookService::class))->newInstanceWithoutConstructor();

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
        /** @var BookService $service */
        $service = (new \ReflectionClass(BookService::class))->newInstanceWithoutConstructor();

        $parsed = $service->parseReleaseName('Some.Release.Name.PAR2', 'ebook');
        $this->assertTrue($parsed->isJunk);
    }

    public function test_parse_release_name_strips_video_terms_from_title(): void
    {
        /** @var BookService $service */
        $service = (new \ReflectionClass(BookService::class))->newInstanceWithoutConstructor();

        $parsed = $service->parseReleaseName('El verano en que me enamore Temporada 01 PAR2', 'ebook');

        $this->assertStringNotContainsString('Temporada', $parsed->title);
        $this->assertStringNotContainsString('PAR2', $parsed->title);
    }

    public function test_tv_and_video_releases_flagged_as_junk_for_both_types(): void
    {
        /** @var BookService $service */
        $service = (new \ReflectionClass(BookService::class))->newInstanceWithoutConstructor();

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
        /** @var BookService $service */
        $service = (new \ReflectionClass(BookService::class))->newInstanceWithoutConstructor();

        $parsed = $service->parseReleaseName('Eric.Evans - Domain.Driven.Design 978-0321125217 RETAIL EPUB', 'ebook');
        $this->assertFalse($parsed->isJunk);
        $this->assertFalse($parsed->isMagazine);
    }
}
