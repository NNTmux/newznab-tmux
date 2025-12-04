<?php

namespace App\Services\Tmux;

use App\Models\Settings;

/**
 * Service for building tmux window layouts based on sequential mode
 */
class TmuxLayoutBuilder
{
    protected TmuxSessionManager $sessionManager;

    protected TmuxPaneManager $paneManager;

    protected string $sessionName;

    public function __construct(TmuxSessionManager $sessionManager)
    {
        $this->sessionManager = $sessionManager;
        $this->sessionName = $sessionManager->getSessionName();
        $this->paneManager = new TmuxPaneManager($this->sessionName);
    }

    /**
     * Build the appropriate layout based on sequential mode
     */
    public function buildLayout(int $sequentialMode): bool
    {
        return match ($sequentialMode) {
            1 => $this->buildBasicLayout(),
            2 => $this->buildStrippedLayout(),
            default => $this->buildFullLayout(),
        };
    }

    /**
     * Build full non-sequential layout (mode 0)
     */
    protected function buildFullLayout(): bool
    {
        // Window 0: Monitor + Binaries + Backfill + Releases
        if (! $this->sessionManager->createSession('Monitor')) {
            return false;
        }

        // Select pane 0.0, then split for binaries (right side, 67%)
        $this->paneManager->selectPane('0.0');
        $this->paneManager->splitHorizontal('0', '67%', 'update_binaries');

        // Select pane 0.1 (binaries), then split for backfill (bottom, 67%)
        $this->paneManager->selectPane('0.1');
        $this->paneManager->splitVertical('0', '67%', 'backfill');

        // Split again for releases (bottom, 50%)
        $this->paneManager->splitVertical('0', '50%', 'update_releases');

        // Window 1: Fix names + Remove crap
        $this->paneManager->createWindow(1, 'utils');
        $this->paneManager->setPaneTitle('1.0', 'fixReleaseNames');
        $this->paneManager->selectPane('1.0');
        $this->paneManager->splitHorizontal('1', '50%', 'removeCrapReleases');

        // Window 2: Postprocessing (Left: Additional + TV + Amazon, Right: Movies + XXX)
        $this->paneManager->createWindow(2, 'post');
        $this->paneManager->setPaneTitle('2.0', 'postprocessing_additional');

        // Split horizontally to create left and right halves (don't set title yet)
        $this->paneManager->splitHorizontal('2', '50%', '');

        // Left side (2.0): split vertically for TV and Amazon
        $this->paneManager->selectPane('2.0');
        $this->paneManager->splitVertical('2', '67%', 'postprocessing_tv');
        $this->paneManager->splitVertical('2', '50%', 'postprocessing_amazon');

        // Right side: After left splits, right side is now 2.3
        $this->paneManager->selectPane('2.3');
        $this->paneManager->setPaneTitle('2.3', 'postprocessing_movies');
        $this->paneManager->splitVertical('2', '50%', 'postprocessing_xxx');

        // Window 3: IRC Scraper
        $this->createIRCScraperWindow();

        // Additional windows (optional monitoring tools)
        $this->createOptionalWindows();

        return true;
    }

    /**
     * Build basic sequential layout (mode 1)
     */
    protected function buildBasicLayout(): bool
    {
        // Window 0: Monitor + Releases
        if (! $this->sessionManager->createSession('Monitor')) {
            return false;
        }

        // Select pane 0.0, then split for releases
        $this->paneManager->selectPane('0.0');
        $this->paneManager->splitHorizontal('0', '67%', 'update_releases');

        // Window 1: Utils (Fix names + Remove crap)
        $this->paneManager->createWindow(1, 'utils');
        $this->paneManager->setPaneTitle('1.0', 'fixReleaseNames');
        $this->paneManager->selectPane('1.0');
        $this->paneManager->splitHorizontal('1', '50%', 'removeCrapReleases');

        // Window 2: Postprocessing (Left: Additional + TV + Amazon, Right: Movies + XXX)
        $this->paneManager->createWindow(2, 'post');
        $this->paneManager->setPaneTitle('2.0', 'postprocessing_additional');

        // Split horizontally to create left and right halves (don't set title yet)
        $this->paneManager->splitHorizontal('2', '50%', '');

        // Left side (2.0): split vertically for TV and Amazon
        $this->paneManager->selectPane('2.0');
        $this->paneManager->splitVertical('2', '67%', 'postprocessing_tv');
        $this->paneManager->splitVertical('2', '50%', 'postprocessing_amazon');

        // Right side: After left splits, right side is now 2.3
        $this->paneManager->selectPane('2.3');
        $this->paneManager->setPaneTitle('2.3', 'postprocessing_movies');
        $this->paneManager->splitVertical('2', '50%', 'postprocessing_xxx');

        // Window 3: IRC Scraper
        $this->createIRCScraperWindow();

        $this->createOptionalWindows();

        return true;
    }

    /**
     * Build stripped sequential layout (mode 2)
     */
    protected function buildStrippedLayout(): bool
    {
        // Window 0: Monitor + Sequential
        if (! $this->sessionManager->createSession('Monitor')) {
            return false;
        }

        // Select pane 0.0, then split for sequential
        $this->paneManager->selectPane('0.0');
        $this->paneManager->splitHorizontal('0', '67%', 'sequential');

        // Window 1: Amazon postprocessing
        $this->paneManager->createWindow(1, 'utils');
        $this->paneManager->setPaneTitle('1.0', 'fixReleaseNames');
        $this->paneManager->selectPane('1.0');
        $this->paneManager->splitHorizontal('1', '50%', 'postprocessing_amazon');

        // Window 2: IRC Scraper
        $this->createIRCScraperWindow();

        $this->createOptionalWindows();

        return true;
    }

    /**
     * Create IRC scraper window
     */
    protected function createIRCScraperWindow(): bool
    {
        $this->paneManager->createWindow(3, 'IRCScraper');
        $this->paneManager->setPaneTitle('3.0', 'scrapeIRC');

        return true;
    }

    /**
     * Create optional monitoring windows based on settings
     *
     * Creates separate tmux windows for enabled monitoring tools.
     * Each tool gets its own dedicated window starting from index 4.
     *
     * Window layout:
     * - Window 0: Monitor + Processing panes (binaries/backfill/releases)
     * - Window 1: Utilities (fix names, remove crap)
     * - Window 2: Postprocessing (additional, tv/anime, movies, amazon)
     * - Window 3: IRC Scraper
     * - Window 4+: Monitoring tools (htop, nmon, vnstat, tcptrack, bwm-ng, mytop, redis, console)
     */
    protected function createOptionalWindows(): void
    {
        $windowIndex = 4;

        // htop
        if ((int) Settings::settingValue('htop') === 1 && $this->commandExists('htop')) {
            $this->paneManager->createWindow($windowIndex, 'htop');
            $this->paneManager->respawnPane("{$windowIndex}.0", 'htop');
            $windowIndex++;
        }

        // nmon
        if ((int) Settings::settingValue('nmon') === 1 && $this->commandExists('nmon')) {
            $this->paneManager->createWindow($windowIndex, 'nmon');
            $this->paneManager->respawnPane("{$windowIndex}.0", 'nmon -t');
            $windowIndex++;
        }

        // vnstat
        if ((int) Settings::settingValue('vnstat') === 1 && $this->commandExists('vnstat')) {
            $vnstatArgs = Settings::settingValue('vnstat_args') ?? '';
            $this->paneManager->createWindow($windowIndex, 'vnstat');
            $this->paneManager->respawnPane("{$windowIndex}.0", "watch -n10 'vnstat {$vnstatArgs}'");
            $windowIndex++;
        }

        // tcptrack
        if ((int) Settings::settingValue('tcptrack') === 1 && $this->commandExists('tcptrack')) {
            $tcptrackArgs = Settings::settingValue('tcptrack_args') ?? '';
            $this->paneManager->createWindow($windowIndex, 'tcptrack');
            $this->paneManager->respawnPane("{$windowIndex}.0", "tcptrack {$tcptrackArgs}");
            $windowIndex++;
        }

        // bwm-ng
        if ((int) Settings::settingValue('bwmng') === 1 && $this->commandExists('bwm-ng')) {
            $this->paneManager->createWindow($windowIndex, 'bwm-ng');
            $this->paneManager->respawnPane("{$windowIndex}.0", 'bwm-ng');
            $windowIndex++;
        }

        // mytop
        if ((int) Settings::settingValue('mytop') === 1 && $this->commandExists('mytop')) {
            $this->paneManager->createWindow($windowIndex, 'mytop');
            $this->paneManager->respawnPane("{$windowIndex}.0", 'mytop -u');
            $windowIndex++;
        }

        // redis monitoring
        if ((int) Settings::settingValue('redis') === 1 && $this->commandExists('redis-cli')) {
            $redisHost = config('database.redis.default.host', '127.0.0.1');
            $redisPort = config('database.redis.default.port', 6379);
            $redisArgs = Settings::settingValue('redis_args') ?? '';
            $refreshInterval = 2;

            $this->paneManager->createWindow($windowIndex, 'redis');

            // Check if custom args provided for simple redis-cli output
            if (! empty($redisArgs) && $redisArgs !== 'NULL') {
                $this->paneManager->respawnPane("{$windowIndex}.0", "watch -n{$refreshInterval} -c 'redis-cli -h {$redisHost} -p {$redisPort} {$redisArgs}'");
            } else {
                // Use modern visual monitoring script
                $monitorScript = base_path('misc/redis-monitor.sh');
                if (file_exists($monitorScript)) {
                    $this->paneManager->respawnPane("{$windowIndex}.0", "bash {$monitorScript} {$redisHost} {$redisPort} {$refreshInterval}");
                } else {
                    // Fallback to basic redis-cli info with color
                    $this->paneManager->respawnPane("{$windowIndex}.0", "watch -n{$refreshInterval} -c 'redis-cli -h {$redisHost} -p {$redisPort} info'");
                }
            }
            $windowIndex++;
        }

        // bash console
        if ((int) Settings::settingValue('console') === 1) {
            $this->paneManager->createWindow($windowIndex, 'bash');
            $this->paneManager->respawnPane("{$windowIndex}.0", 'bash -i');
        }
    }

    /**
     * Check if a command exists
     */
    protected function commandExists(string $command): bool
    {
        $result = \Illuminate\Support\Facades\Process::timeout(5)
            ->run("which {$command} 2>/dev/null");

        return $result->successful() && str_contains($result->output(), $command);
    }
}
