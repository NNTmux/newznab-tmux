<?php

declare(strict_types=1);

namespace Tests\Unit\Services\NameFixing;

use App\Services\NameFixing\Sdx264XxxNameResolver;
use App\Services\Search\Contracts\SearchServiceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class Sdx264XxxNameResolverTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sourceCodeProvider(): array
    {
        return [
            'Watch4Beauty' => ['W4B', 'Watch4Beauty'],
            'WhippedAss' => ['WA', 'WhippedAss'],
            'WetAndPuffy' => ['WAP', 'WetAndPuffy'],
            'WoodmanCastingX' => ['WCX', 'WoodmanCastingX'],
            'WeFuckBlackGirls' => ['WFBG', 'WeFuckBlackGirls'],
            'WowGirls' => ['WG', 'WowGirls'],
            'WhenGirlsPlay' => ['WGP', 'WhenGirlsPlay'],
            'unknown source' => ['ABC', 'ABC'],
        ];
    }

    #[DataProvider('sourceCodeProvider')]
    public function test_it_decodes_known_sources_and_preserves_unknown_sources(string $sourceCode, string $studio): void
    {
        $search = $this->createStub(SearchServiceInterface::class);
        $search->method('searchPredb')->willReturn([]);

        $result = (new Sdx264XxxNameResolver($search))->resolve(
            "SDX264XXX-{$sourceCode}210316SAPPHIRAINTERVIEW"
        );

        $this->assertNotNull($result);
        $this->assertSame(
            "{$studio}.2016.03.21.SAPPHIRAINTERVIEW.XXX.SD.x264-SDX264XXX",
            $result->newName
        );
        $this->assertSame(0, $result->preDbId);
    }

    public function test_it_uses_an_exact_predb_payload_match_to_restore_word_boundaries(): void
    {
        $search = $this->createMock(SearchServiceInterface::class);
        $search->expects($this->once())
            ->method('searchPredb')
            ->with('Watch4Beauty 2016 03 21')
            ->willReturn([
                [
                    'id' => 77,
                    'title' => 'Watch4Beauty.16.03.21.Sapphira.Interview.XXX.480p.MP4-SDX264XXX',
                    'filename' => '',
                    'source' => 'predb',
                ],
            ]);

        $result = (new Sdx264XxxNameResolver($search))->resolve(
            'SDX264XXX-W4B210316SAPPHIRAINTERVIEW'
        );

        $this->assertNotNull($result);
        $this->assertSame(
            'Watch4Beauty.2016.03.21.Sapphira.Interview.XXX.SD.x264-SDX264XXX',
            $result->newName
        );
        $this->assertSame(77, $result->preDbId);
    }

    public function test_it_rejects_near_predb_matches_and_uses_the_compact_fallback(): void
    {
        $search = $this->createStub(SearchServiceInterface::class);
        $search->method('searchPredb')->willReturn([
            [
                'id' => 88,
                'title' => 'Watch4Beauty.16.03.21.Sapphira.Interview.Extra.XXX.480p-SDX264XXX',
                'filename' => '',
                'source' => 'predb',
            ],
        ]);

        $result = (new Sdx264XxxNameResolver($search))->resolve(
            'SDX264XXX-W4B210316SAPPHIRAINTERVIEW'
        );

        $this->assertNotNull($result);
        $this->assertSame(
            'Watch4Beauty.2016.03.21.SAPPHIRAINTERVIEW.XXX.SD.x264-SDX264XXX',
            $result->newName
        );
        $this->assertSame(0, $result->preDbId);
    }

    public function test_it_falls_back_when_the_search_backend_is_unavailable(): void
    {
        $search = $this->createStub(SearchServiceInterface::class);
        $search->method('searchPredb')->willThrowException(new RuntimeException('Search unavailable'));

        $result = (new Sdx264XxxNameResolver($search))->resolve(
            'SDX264XXX-WG310316TALIAMINTRAWFOOD'
        );

        $this->assertNotNull($result);
        $this->assertSame(
            'WowGirls.2016.03.31.TALIAMINTRAWFOOD.XXX.SD.x264-SDX264XXX',
            $result->newName
        );
    }

    public function test_it_strips_paths_extensions_and_a_duplicated_release_trailer(): void
    {
        $search = $this->createStub(SearchServiceInterface::class);
        $search->method('searchPredb')->willReturn([]);

        $result = (new Sdx264XxxNameResolver($search))->resolve(
            'folder\\SDX264XXX-WG200115CANDYSWEETTOYINGWITHMYSELFXXXMP4-SDX264XXX.mp4'
        );

        $this->assertNotNull($result);
        $this->assertSame(
            'WowGirls.2015.01.20.CANDYSWEETTOYINGWITHMYSELF.XXX.SD.x264-SDX264XXX',
            $result->newName
        );
    }

    public function test_it_rejects_invalid_dates_and_empty_payloads(): void
    {
        $search = $this->createStub(SearchServiceInterface::class);
        $resolver = new Sdx264XxxNameResolver($search);

        $this->assertNull($resolver->resolve('SDX264XXX-W4B321316SAPPHIRAINTERVIEW'));
        $this->assertNull($resolver->resolve('SDX264XXX-W4B210316'));
        $this->assertNull($resolver->resolve('Some.Other.Release'));
    }

    public function test_it_caches_predb_candidates_for_the_same_source_and_date(): void
    {
        $search = $this->createMock(SearchServiceInterface::class);
        $search->expects($this->once())
            ->method('searchPredb')
            ->with('Watch4Beauty 2016 03 21')
            ->willReturn([]);

        $resolver = new Sdx264XxxNameResolver($search);

        $this->assertNotNull($resolver->resolve('SDX264XXX-W4B210316SAPPHIRAINTERVIEW'));
        $this->assertNotNull($resolver->resolve('SDX264XXX-W4B210316ANOTHERTITLE'));
    }
}
