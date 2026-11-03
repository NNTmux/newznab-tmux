<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tmux\TmuxLayoutBuilder;
use App\Services\Tmux\TmuxSessionManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class TmuxLayoutBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_it_uses_nerd_font_icons_when_enabled(): void
    {
        $builder = $this->makeBuilder(true);

        $this->assertSame('󰍹 Monitor', $builder->paneDisplayName('Monitor'));
        $this->assertSame('󰻞 IRC', $builder->windowDisplayName('IRC'));
        $this->assertSame('custom-pane', $builder->paneDisplayName('custom-pane'));
    }

    public function test_it_falls_back_to_plain_labels_when_nerd_fonts_are_disabled(): void
    {
        $builder = $this->makeBuilder(false);

        $this->assertSame('Monitor', $builder->paneDisplayName('Monitor'));
        $this->assertSame('IRC', $builder->windowDisplayName('IRC'));
        $this->assertSame('custom-pane', $builder->paneDisplayName('custom-pane'));
    }

    private function makeBuilder(bool $useNerdFonts): object
    {
        $container = new Container;
        $container->instance('config', new Repository([
            'tmux.config_file' => '/tmp/tmux.conf',
            'tmux.fonts.use_nerd_fonts' => $useNerdFonts,
        ]));

        Container::setInstance($container);

        $sessionManager = new class extends TmuxSessionManager
        {
            public function getSessionName(): string
            {
                return 'test-session';
            }
        };

        return new class($sessionManager) extends TmuxLayoutBuilder
        {
            public function paneDisplayName(string $name): string
            {
                return $this->getPaneDisplayName($name);
            }

            public function windowDisplayName(string $name): string
            {
                return $this->getWindowDisplayName($name);
            }
        };
    }
}
