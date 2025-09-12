<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CategorizePcGameTest extends TestCase
{
    private function makeCategorizeNoCtor(): object
    {
        $rc = new ReflectionClass(\Blacklight\Categorize::class);

        return $rc->newInstanceWithoutConstructor();
    }

    public function test_pc_game_detects_common_scene_groups(): void
    {
        $samples = [
            'Starfield-RUNE',
            'Baldurs.Gate.3.TENOKE',
            'ELDEN.RING-EMPRESS',
            'Horizon.Zero.Dawn-CODEX',
            'Cyberpunk.2077.GOG',
            'Forza.Horizon.5.ElAmigos',
            'The.Witcher.3.Wild.Hunt.PLaza',
            'Resident.Evil.4.Remake-FITGIRL',
            'Red.Dead.Redemption.2.DODI-Repack',
            'Some.Game.SKiDROW',
        ];

        foreach ($samples as $name) {
            $c = $this->makeCategorizeNoCtor();
            $c->releaseName = $name;
            $c->poster = '';
            $this->assertTrue($c->isPCGame(), "Expected PC game match for: $name");
            $this->assertSame(Category::PC_GAMES, $this->readTmpCat($c), "Expected PC_GAMES category for: $name");
        }
    }

    public function test_pc_game_detects_keywords_and_platform_cues(): void
    {
        $samples = [
            'Awesome.Game.SteamRip',
            'Great.Game.Repack-FitGirl',
            'Indie.Title.DRM-Free.GOG',
            'Cool.Game.PC.Game.2024',
            'Windows.10.Title.Repack',
            'Title-[PC]-DRMFree',
        ];

        foreach ($samples as $name) {
            $c = $this->makeCategorizeNoCtor();
            $c->releaseName = $name;
            $c->poster = '';
            $this->assertTrue($c->isPCGame(), "Expected PC game keyword match for: $name");
            $this->assertSame(Category::PC_GAMES, $this->readTmpCat($c), "Expected PC_GAMES category for: $name");
        }
    }

    public function test_console_and_mac_not_misclassified_as_pc_game(): void
    {
        $negatives = [
            'The.Last.of.Us.Part.I.PS5',
            'Gran.Turismo.7.PS4.CUSA12345',
            'Mario.Kart.8.Deluxe.NSW.NSP',
            'Zelda.Breath.of.the.Wild.Switch.XCI',
            'Halo.Infinite.Xbox.Series.X',
            'Gears.5.XBOXONE',
            'Some.Game.macOS.13',
        ];

        foreach ($negatives as $name) {
            $c = $this->makeCategorizeNoCtor();
            $c->releaseName = $name;
            $c->poster = '';
            $this->assertFalse($c->isPCGame(), "Did not expect PC game match for console/Mac: $name");
        }
    }

    private function readTmpCat(object $instance): int
    {
        $rp = (new ReflectionClass($instance))->getProperty('tmpCat');
        $rp->setAccessible(true);

        return (int) $rp->getValue($instance);
    }
}

