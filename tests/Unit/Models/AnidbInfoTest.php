<?php

namespace Tests\Unit\Models;

use App\Models\AnidbInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnidbInfoTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_anidb_url_when_anidbid_exists(): void
    {
        $animeInfo = AnidbInfo::create([
            'anidbid' => 12345,
            'type' => 'TV Series',
        ]);

        $url = $animeInfo->getAnidbUrl();

        $this->assertEquals('https://anidb.net/anime/12345', $url);
    }

    /** @test */
    public function it_returns_anilist_url_when_anilist_id_exists(): void
    {
        $animeInfo = AnidbInfo::create([
            'anidbid' => 1,
            'anilist_id' => 9253,
            'type' => 'TV Series',
        ]);

        $url = $animeInfo->getAnilistUrl();

        $this->assertEquals('https://anilist.co/anime/9253', $url);
    }

    /** @test */
    public function it_returns_myanimelist_url_when_myanimelist_id_exists(): void
    {
        $animeInfo = AnidbInfo::create([
            'anidbid' => 1,
            'myanimelist_id' => 9253,
            'type' => 'TV Series',
        ]);

        $url = $animeInfo->getMyAnimeListUrl();

        $this->assertEquals('https://myanimelist.net/anime/9253', $url);
    }

    /** @test */
    public function it_returns_all_external_links(): void
    {
        $animeInfo = AnidbInfo::create([
            'anidbid' => 12345,
            'anilist_id' => 9253,
            'myanimelist_id' => 9253,
            'type' => 'TV Series',
        ]);

        $links = $animeInfo->getExternalLinks();

        $this->assertIsArray($links);
        $this->assertArrayHasKey('anidb', $links);
        $this->assertArrayHasKey('anilist', $links);
        $this->assertArrayHasKey('myanimelist', $links);
    }

    /** @test */
    public function it_checks_if_external_links_exist(): void
    {
        $animeInfo = AnidbInfo::create([
            'anidbid' => 1,
            'anilist_id' => 123,
            'type' => 'TV Series',
        ]);

        $this->assertTrue($animeInfo->hasExternalLinks());
    }
}
