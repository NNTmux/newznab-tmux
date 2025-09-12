<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class GamesParseTest extends TestCase
{
    private function makeGamesInstance(): object
    {
        $rc = new ReflectionClass(\Blacklight\Games::class);

        return $rc->newInstanceWithoutConstructor();
    }

    #[DataProvider('titlesProvider')]
    public function test_parse_title_variants(string $input, string $expected): void
    {
        $games = $this->makeGamesInstance();
        $res = $games->parseTitle($input);

        $this->assertIsArray($res, 'Expected parseTitle to return an array');
        $this->assertArrayHasKey('title', $res);
        $this->assertSame($expected, $res['title']);
    }

    public static function titlesProvider(): array
    {
        return [
            ['Cyberpunk.2077-GOG', 'Cyberpunk 2077'],
            ['ELDEN RING - Deluxe Edition [v1.08 + DLCs] - EMPRESS', 'ELDEN RING'],
            ['[FitGirl] Red.Dead.Redemption.2.v1.0.1436.28.Repack', 'Red Dead Redemption 2'],
            ['Baldurs_Gate_3_v1.0.2_MULTI12-EMPRESS', 'Baldurs Gate 3'],
            ['Horizon Zero Dawn Complete Edition (PC ISO) (Multi10) - CODEX', 'Horizon Zero Dawn'],
            ['Starfield.Update.1.7.29.Patch-FLT', 'Starfield'],
            ['The Witcher 3 Wild Hunt GOTY - GOG-GAMES', 'The Witcher 3 Wild Hunt'],
            ['Forza.Horizon.5.Incl.DLCs.MULTi10-ElAmigos', 'Forza Horizon 5'],
            ['Assassins.Creed.Mirage-Deluxe.Edition-EMPRESS', 'Assassins Creed Mirage'],
            ['Resident.Evil.4.Remake.REPACK-DODI', 'Resident Evil 4 Remake'],
        ];
    }
}
