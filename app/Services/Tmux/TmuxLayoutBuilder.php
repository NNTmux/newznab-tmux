<?php

namespace App\Services\Tmux;

use App\Models\Settings;

/**
 * Service for building tmux window layouts based on sequential mode
 *
 * Uses Nerd Font icons for pane names to provide a modern visual experience.
 */
class TmuxLayoutBuilder
{
    protected TmuxSessionManager $sessionManager;

    protected TmuxPaneManager $paneManager;

    protected string $sessionName;

    /**
     * Pane name icons mapping - uses Nerd Font symbols
     * Requires a Nerd Font to display properly (FiraCode NF, JetBrains Mono NF, etc.)
     */
    protected array $paneIcons = [
        // Core processing
        'Monitor' => '󰍹 Monitor',
        'update_binaries' => ' Binaries',
        'backfill' => '󰑓 Backfill',
        'update_releases' => ' Releases',
        'sequential' => '󰒿 Sequential',

        // Utilities
        'fixReleaseNames' => '󰯃 Fix Names',
        'removeCrapReleases' => '󰆴 Remove Crap',

        // Postprocessing
        'postprocessing_additional' => ' Additional',
        'postprocessing_tv' => '󰟴 TV/Anime',
        'postprocessing_amazon' => ' Amazon',
        'postprocessing_movies' => ' Movies',
        'postprocessing_xxx' => '󰞋 XXX',

        // IRC
        'scrapeIRC' => '󰻞 IRC Scraper',

        // Monitoring tools
        'htop' => ' htop',
        'nmon' => '󰨇 nmon',
        'vnstat' => '󰛳 vnstat',
        'tcptrack' => '󱘖 tcptrack',
        'bwm-ng' => '󰾆 bwm-ng',
        'mytop' => ' mytop',
        'redis' => ' Redis',
        'bash' => ' Console',
    ];

    public function __construct(TmuxSessionManager $sessionManager)
    {
        $this->sessionManager = $sessionManager;
        $this->sessionName = $sessionManager->getSessionName();
        $this->paneManager = new TmuxPaneManager($this->sessionName);
    }

    /**
     * Get the display name with icon for a pane
     */
    protected function getPaneDisplayName(string $name): string
    {
        return $this->paneIcons[$name] ?? $name;
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
        if (! $this->sessionManager->createSession($this->getPaneDisplayName('Monitor'))) {
            return false;
        }

        // Select pane 0.0, then split for binaries (right side, 67%)
        $this->paneManager->selectPane('0.0');
        $this->paneManager->splitHorizontal('0', '67%', $this->getPaneDisplayName('update_binaries'));

        // Select pane 0.1 (binaries), then split for backfill (bottom, 67%)
        $this->paneManager->selectPane('0.1');
        $this->paneManager->splitVertical('0', '67%', $this->getPaneDisplayName('backfill'));

        // Split again for releases (bottom, 50%)
        $this->paneManager->splitVertical('0', '50%', $this->getPaneDisplayName('update_releases'));

        // Window 1: Fix names + Remove crap
        $this->paneManager->createWindow(1, ' Utils');
        $this->paneManager->setPaneTitle('1.0', $this->getPaneDisplayName('fixReleaseNames'));
        $this->paneManager->selectPane('1.0');
        $this->paneManager->splitHorizontal('1', '50%', $this->getPaneDisplayName('removeCrapReleases'));

        // Window 2: Postprocessing (Left: Additional + TV + Amazon, Right: Movies + XXX)
        $this->paneManager->createWindow(2, ' Post');
        $this->paneManager->setPaneTitle('2.0', $this->getPaneDisplayName('postprocessing_additional'));

        // Split horizontally to create left and right halves (don't set title yet)
        $this->paneManager->splitHorizontal('2', '50%', '');

        // Left side (2.0): split vertically for TV and Amazon
        $this->paneManager->selectPane('2.0');
        $this->paneManager->splitVertical('2', '67%', $this->getPaneDisplayName('postprocessing_tv'));
        $this->paneManager->splitVertical('2', '50%', $this->getPaneDisplayName('postprocessing_amazon'));

        // Right side: After left splits, right side is now 2.3
        $this->paneManager->selectPane('2.3');
        $this->paneManager->setPaneTitle('2.3', $this->getPaneDisplayName('postprocessing_movies'));
        $this->paneManager->splitVertical('2', '50%', $this->getPaneDisplayName('postprocessing_xxx'));

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
        if (! $this->sessionManager->createSession($this->getPaneDisplayName('Monitor'))) {
            return false;
        }

        // Select pane 0.0, then split for releases
        $this->paneManager->selectPane('0.0');
        $this->paneManager->splitHorizontal('0', '67%', $this->getPaneDisplayName('update_releases'));

        // Window 1: Utils (Fix names + Remove crap)
        $this->paneManager->createWindow(1, ' Utils');
        $this->paneManager->setPaneTitle('1.0', $this->getPaneDisplayName('fixReleaseNames'));
        $this->paneManager->selectPane('1.0');
        $this->paneManager->splitHorizontal('1', '50%', $this->getPaneDisplayName('removeCrapReleases'));

        // Window 2: Postprocessing (Left: Additional + TV + Amazon, Right: Movies + XXX)
        $this->paneManager->createWindow(2, ' Post');
        $this->paneManager->setPaneTitle('2.0', $this->getPaneDisplayName('postprocessing_additional'));

        // Split horizontally to create left and right halves (don't set title yet)
        $this->paneManager->splitHorizontal('2', '50%', '');

        // Left side (2.0): split vertically for TV and Amazon
        $this->paneManager->selectPane('2.0');
        $this->paneManager->splitVertical('2', '67%', $this->getPaneDisplayName('postprocessing_tv'));
        $this->paneManager->splitVertical('2', '50%', $this->getPaneDisplayName('postprocessing_amazon'));

        // Right side: After left splits, right side is now 2.3
        $this->paneManager->selectPane('2.3');
        $this->paneManager->setPaneTitle('2.3', $this->getPaneDisplayName('postprocessing_movies'));
        $this->paneManager->splitVertical('2', '50%', $this->getPaneDisplayName('postprocessing_xxx'));

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
        if (! $this->sessionManager->createSession($this->getPaneDisplayName('Monitor'))) {
            return false;
        }

        // Select pane 0.0, then split for sequential
        $this->paneManager->selectPane('0.0');
        $this->paneManager->splitHorizontal('0', '67%', $this->getPaneDisplayName('sequential'));

        // Window 1: Amazon postprocessing
        $this->paneManager->createWindow(1, ' Utils');
        $this->paneManager->setPaneTitle('1.0', $this->getPaneDisplayName('fixReleaseNames'));
        $this->paneManager->selectPane('1.0');
        $this->paneManager->splitHorizontal('1', '50%', $this->getPaneDisplayName('postprocessing_amazon'));

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
        $this->paneManager->createWindow(3, '󰻞 IRC');
        $this->paneManager->setPaneTitle('3.0', $this->getPaneDisplayName('scrapeIRC'));

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
            $this->paneManager->createWindow($windowIndex, $this->getPaneDisplayName('htop'));
            $this->paneManager->respawnPane("{$windowIndex}.0", 'htop');
            $windowIndex++;
        }

        // nmon
        if ((int) Settings::settingValue('nmon') === 1 && $this->commandExists('nmon')) {
            $this->paneManager->createWindow($windowIndex, $this->getPaneDisplayName('nmon'));
            $this->paneManager->respawnPane("{$windowIndex}.0", 'nmon -t');
            $windowIndex++;
        }

        // vnstat
        if ((int) Settings::settingValue('vnstat') === 1 && $this->commandExists('vnstat')) {
            $vnstatArgs = Settings::settingValue('vnstat_args') ?? '';
            $this->paneManager->createWindow($windowIndex, $this->getPaneDisplayName('vnstat'));
            $this->paneManager->respawnPane("{$windowIndex}.0", "watch -n10 'vnstat {$vnstatArgs}'");
            $windowIndex++;
        }

        // tcptrack
        if ((int) Settings::settingValue('tcptrack') === 1 && $this->commandExists('tcptrack')) {
            $tcptrackArgs = Settings::settingValue('tcptrack_args') ?? '';
            $this->paneManager->createWindow($windowIndex, $this->getPaneDisplayName('tcptrack'));
            $this->paneManager->respawnPane("{$windowIndex}.0", "tcptrack {$tcptrackArgs}");
            $windowIndex++;
        }

        // bwm-ng
        if ((int) Settings::settingValue('bwmng') === 1 && $this->commandExists('bwm-ng')) {
            $this->paneManager->createWindow($windowIndex, $this->getPaneDisplayName('bwm-ng'));
            $this->paneManager->respawnPane("{$windowIndex}.0", 'bwm-ng');
            $windowIndex++;
        }

        // mytop
        if ((int) Settings::settingValue('mytop') === 1 && $this->commandExists('mytop')) {
            $this->paneManager->createWindow($windowIndex, $this->getPaneDisplayName('mytop'));
            $this->paneManager->respawnPane("{$windowIndex}.0", 'mytop -u');
            $windowIndex++;
        }

        // redis monitoring
        if ((int) Settings::settingValue('redis') === 1) {
            $redisArgs = Settings::settingValue('redis_args') ?? '';
            $refreshInterval = 30;

            $this->paneManager->createWindow($windowIndex, $this->getPaneDisplayName('redis'));

            // Check if custom args provided for simple redis-cli output
            if (! empty($redisArgs) && $redisArgs !== 'NULL' && $this->commandExists('redis-cli')) {
                $redisHost = config('database.redis.default.host', '127.0.0.1');
                $redisPort = config('database.redis.default.port', 6379);
                $this->paneManager->respawnPane("{$windowIndex}.0", "watch -n{$refreshInterval} -c 'redis-cli -h {$redisHost} -p {$redisPort} {$redisArgs}'");
            } else {
                // Use PHP-based Redis monitor service
                $redisHost = config('database.redis.default.host', '127.0.0.1');
                $redisPort = config('database.redis.default.port', 6379);
                $artisan = base_path('artisan');

                // Determine how to connect to Redis
                $connectionInfo = $this->resolveRedisConnection($redisHost, (int) $redisPort);

                if ($connectionInfo['use_sail']) {
                    // Use sail to run inside Docker container
                    $sail = base_path('sail');
                    $this->paneManager->respawnPane("{$windowIndex}.0", "{$sail} artisan redis:monitor --refresh={$refreshInterval}");
                } else {
                    // Run directly, potentially with host override
                    $envPrefix = $connectionInfo['override_host'] ? "REDIS_HOST={$connectionInfo['host']} " : '';
                    $this->paneManager->respawnPane("{$windowIndex}.0", "{$envPrefix}php {$artisan} redis:monitor --refresh={$refreshInterval}");
                }
            }
            $windowIndex++;
        }

        // bash console
        if ((int) Settings::settingValue('console') === 1) {
            $this->paneManager->createWindow($windowIndex, $this->getPaneDisplayName('bash'));
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

    /**
     * Resolve how to connect to Redis from the host
     *
     * Returns an array with:
     * - 'use_sail' => bool - whether to use sail to run inside Docker
     * - 'override_host' => bool - whether to override REDIS_HOST env var
     * - 'host' => string - the host to use (only relevant if override_host is true)
     */
    protected function resolveRedisConnection(string $host, int $port): array
    {
        // If host is already an IP or localhost, use it directly
        if (filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost') {
            return ['use_sail' => false, 'override_host' => false, 'host' => $host];
        }

        // Try to resolve the hostname
        $resolved = gethostbyname($host);
        if ($resolved !== $host) {
            // Hostname resolves - use it directly
            return ['use_sail' => false, 'override_host' => false, 'host' => $host];
        }

        // Hostname doesn't resolve (Docker internal hostname)
        // Check if Redis is accessible on 127.0.0.1 (Docker port forwarding)
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($socket !== false) {
            fclose($socket);

            // Redis accessible on localhost - override host to 127.0.0.1
            return ['use_sail' => false, 'override_host' => true, 'host' => '127.0.0.1'];
        }

        // Redis not accessible on localhost - need to use sail
        return ['use_sail' => true, 'override_host' => false, 'host' => $host];
    }
}
