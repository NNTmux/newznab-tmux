<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Categorization\Categorizers\PcCategorizer;
use App\Services\Categorization\ReleaseContext;
use PHPUnit\Framework\TestCase;

class CategorizePcGameTest extends TestCase
{
    private PcCategorizer $categorizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->categorizer = new PcCategorizer;
    }

    public function test_pc_game_detects_common_scene_groups(): void
    {
        $samples = [
            'Starfield-RUNE',
            'Baldurs.Gate.3-TENOKE',
            'ELDEN.RING-EMPRESS',
            'Horizon.Zero.Dawn-CODEX',
            'Cyberpunk.2077-GOG',
            'Forza.Horizon.5-ElAmigos',
            'The.Witcher.3.Wild.Hunt-PLaza',
            'Resident.Evil.4.Remake-FITGIRL',
            'Red.Dead.Redemption.2-DODI',
            'Some.Game-SKiDROW',
        ];

        foreach ($samples as $name) {
            $context = new ReleaseContext(
                releaseName: $name,
                groupId: 0,
                groupName: '',
                poster: ''
            );
            $result = $this->categorizer->categorize($context);
            $this->assertTrue($result->isSuccessful(), "Expected PC game match for: $name");
            $this->assertSame(Category::PC_GAMES, $result->categoryId, "Expected PC_GAMES category for: $name");
        }
    }

    public function test_pc_game_detects_keywords_and_platform_cues(): void
    {
        $samples = [
            'Awesome.Game.SteamRip',
            'Great.Game.Repack-FitGirl',
            'Indie.Title.DRM-Free-GOG',
            'Cool.Game.PC.Game.2024',
            'Title-[PC]-Game',
        ];

        foreach ($samples as $name) {
            $context = new ReleaseContext(
                releaseName: $name,
                groupId: 0,
                groupName: '',
                poster: ''
            );
            $result = $this->categorizer->categorize($context);
            $this->assertTrue($result->isSuccessful(), "Expected PC game keyword match for: $name");
            $this->assertSame(Category::PC_GAMES, $result->categoryId, "Expected PC_GAMES category for: $name");
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
            $context = new ReleaseContext(
                releaseName: $name,
                groupId: 0,
                groupName: '',
                poster: ''
            );
            $result = $this->categorizer->categorize($context);
            // Either not matched, or matched to a non-PC_GAMES category (like PC_MAC)
            if ($result->isSuccessful()) {
                $this->assertNotSame(Category::PC_GAMES, $result->categoryId, "Did not expect PC_GAMES for console/Mac: $name");
            }
        }
    }
}
