<?php

declare(strict_types=1);

namespace App\Services\Tmux;

use App\Enums\TmuxPaneRole;
use App\Models\Settings;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

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

    private ?string $lastError = null;

    private bool $sessionCreated = false;

    /**
     * Pane name icons mapping - uses Nerd Font symbols
     * Requires a Nerd Font to display properly (FiraCode NF, JetBrains Mono NF, etc.)
     *
     * @var array<string, mixed>
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
        'postprocessing_amazon' => ' Metadata',
        'postprocessing_movies' => ' Movies',

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
        $this->sessionName = $sessionManager->sessionName();
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
        try {
            match ($sequentialMode) {
                1 => $this->buildBasicLayout(),
                2 => $this->buildStrippedLayout(),
                default => $this->buildFullLayout(),
            };

            return true;
        } catch (Throwable $exception) {
            $this->lastError = $exception->getMessage();

            if ($this->sessionCreated) {
                $this->sessionManager->killSession();
            }

            return false;
        }
    }

    /**
     * Build full non-sequential layout (mode 0)
     */
    protected function buildFullLayout(): void
    {
        // Window 0: Monitor + Binaries + Backfill + Releases
        $monitor = $this->createSessionPane(TmuxPaneRole::Monitor, $this->getPaneDisplayName('Monitor'));
        $binaries = $this->splitHorizontal(
            $monitor,
            67,
            TmuxPaneRole::Binaries,
            $this->getPaneDisplayName('update_binaries'),
        );
        $backfill = $this->splitVertical(
            $binaries,
            67,
            TmuxPaneRole::Backfill,
            $this->getPaneDisplayName('backfill'),
        );
        $this->splitVertical(
            $backfill,
            50,
            TmuxPaneRole::Releases,
            $this->getPaneDisplayName('update_releases'),
        );

        // Window 1: Fix names + Remove crap
        $fixNames = $this->createWindowPane(
            1,
            ' Utils',
            TmuxPaneRole::FixNames,
            $this->getPaneDisplayName('fixReleaseNames'),
        );
        $this->splitHorizontal(
            $fixNames,
            50,
            TmuxPaneRole::RemoveCrap,
            $this->getPaneDisplayName('removeCrapReleases'),
        );

        // Window 2: Postprocessing (left stack and right-side movies)
        $additional = $this->createWindowPane(
            2,
            ' Post',
            TmuxPaneRole::PostAdditional,
            $this->getPaneDisplayName('postprocessing_additional'),
        );
        $this->splitHorizontal(
            $additional,
            50,
            TmuxPaneRole::PostMovies,
            $this->getPaneDisplayName('postprocessing_movies'),
        );
        $tv = $this->splitVertical(
            $additional,
            67,
            TmuxPaneRole::PostTv,
            $this->getPaneDisplayName('postprocessing_tv'),
        );
        $this->splitVertical(
            $tv,
            50,
            TmuxPaneRole::PostMetadata,
            $this->getPaneDisplayName('postprocessing_amazon'),
        );

        // Window 3: IRC Scraper
        $this->createIRCScraperWindow();

        // Additional windows (optional monitoring tools)
        $this->createOptionalWindows();

    }

    /**
     * Build basic sequential layout (mode 1)
     */
    protected function buildBasicLayout(): void
    {
        // Window 0: Monitor + Releases
        $monitor = $this->createSessionPane(TmuxPaneRole::Monitor, $this->getPaneDisplayName('Monitor'));
        $this->splitHorizontal(
            $monitor,
            67,
            TmuxPaneRole::Releases,
            $this->getPaneDisplayName('update_releases'),
        );

        // Window 1: Utils (Fix names + Remove crap)
        $fixNames = $this->createWindowPane(
            1,
            ' Utils',
            TmuxPaneRole::FixNames,
            $this->getPaneDisplayName('fixReleaseNames'),
        );
        $this->splitHorizontal(
            $fixNames,
            50,
            TmuxPaneRole::RemoveCrap,
            $this->getPaneDisplayName('removeCrapReleases'),
        );

        // Window 2: Postprocessing
        $additional = $this->createWindowPane(
            2,
            ' Post',
            TmuxPaneRole::PostAdditional,
            $this->getPaneDisplayName('postprocessing_additional'),
        );
        $this->splitHorizontal(
            $additional,
            50,
            TmuxPaneRole::PostMovies,
            $this->getPaneDisplayName('postprocessing_movies'),
        );
        $tv = $this->splitVertical(
            $additional,
            67,
            TmuxPaneRole::PostTv,
            $this->getPaneDisplayName('postprocessing_tv'),
        );
        $this->splitVertical(
            $tv,
            50,
            TmuxPaneRole::PostMetadata,
            $this->getPaneDisplayName('postprocessing_amazon'),
        );

        // Window 3: IRC Scraper
        $this->createIRCScraperWindow();

        $this->createOptionalWindows();

    }

    /**
     * Build stripped sequential layout (mode 2)
     */
    protected function buildStrippedLayout(): void
    {
        // Window 0: Monitor + Sequential
        $monitor = $this->createSessionPane(TmuxPaneRole::Monitor, $this->getPaneDisplayName('Monitor'));
        $this->splitHorizontal(
            $monitor,
            67,
            TmuxPaneRole::Sequential,
            $this->getPaneDisplayName('sequential'),
        );

        // Window 1: Metadata postprocessing
        $fixNames = $this->createWindowPane(
            1,
            ' Utils',
            TmuxPaneRole::FixNames,
            $this->getPaneDisplayName('fixReleaseNames'),
        );
        $this->splitHorizontal(
            $fixNames,
            50,
            TmuxPaneRole::PostMetadata,
            $this->getPaneDisplayName('postprocessing_amazon'),
        );

        // Window 2: IRC Scraper
        $this->createIRCScraperWindow();

        $this->createOptionalWindows();

    }

    /**
     * Create IRC scraper window
     */
    protected function createIRCScraperWindow(): void
    {
        $this->createWindowPane(
            3,
            '󰻞 IRC',
            TmuxPaneRole::IrcScraper,
            $this->getPaneDisplayName('scrapeIRC'),
        );
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
     * - Window 2: Postprocessing (additional, tv/anime, movies, metadata)
     * - Window 3: IRC Scraper
     * - Window 4+: Monitoring tools (htop, nmon, vnstat, tcptrack, bwm-ng, mytop, redis, console)
     */
    protected function createOptionalWindows(): void
    {
        $windowIndex = 4;

        // htop
        if ((int) Settings::settingValue('htop') === 1 && $this->commandExists('htop')) {
            $pane = $this->createWindowPane($windowIndex, $this->getPaneDisplayName('htop'), TmuxPaneRole::Htop, $this->getPaneDisplayName('htop'));
            $this->requireSuccess($this->paneManager->respawnPane($pane, 'htop'), 'respawn htop pane');
            $windowIndex++;
        }

        // nmon
        if ((int) Settings::settingValue('nmon') === 1 && $this->commandExists('nmon')) {
            $pane = $this->createWindowPane($windowIndex, $this->getPaneDisplayName('nmon'), TmuxPaneRole::Nmon, $this->getPaneDisplayName('nmon'));
            $this->requireSuccess($this->paneManager->respawnPane($pane, 'nmon -t'), 'respawn nmon pane');
            $windowIndex++;
        }

        // vnstat
        if ((int) Settings::settingValue('vnstat') === 1 && $this->commandExists('vnstat')) {
            $vnstatArgs = Settings::settingValue('vnstat_args') ?? '';
            $pane = $this->createWindowPane($windowIndex, $this->getPaneDisplayName('vnstat'), TmuxPaneRole::Vnstat, $this->getPaneDisplayName('vnstat'));
            $this->requireSuccess($this->paneManager->respawnPane($pane, "watch -n10 'vnstat {$vnstatArgs}'"), 'respawn vnstat pane');
            $windowIndex++;
        }

        // tcptrack
        if ((int) Settings::settingValue('tcptrack') === 1 && $this->commandExists('tcptrack')) {
            $tcptrackArgs = Settings::settingValue('tcptrack_args') ?? '';
            $pane = $this->createWindowPane($windowIndex, $this->getPaneDisplayName('tcptrack'), TmuxPaneRole::Tcptrack, $this->getPaneDisplayName('tcptrack'));
            $this->requireSuccess($this->paneManager->respawnPane($pane, "tcptrack {$tcptrackArgs}"), 'respawn tcptrack pane');
            $windowIndex++;
        }

        // bwm-ng
        if ((int) Settings::settingValue('bwmng') === 1 && $this->commandExists('bwm-ng')) {
            $pane = $this->createWindowPane($windowIndex, $this->getPaneDisplayName('bwm-ng'), TmuxPaneRole::BandwidthMonitor, $this->getPaneDisplayName('bwm-ng'));
            $this->requireSuccess($this->paneManager->respawnPane($pane, 'bwm-ng'), 'respawn bwm-ng pane');
            $windowIndex++;
        }

        // mytop
        if ((int) Settings::settingValue('mytop') === 1 && $this->commandExists('mytop')) {
            $pane = $this->createWindowPane($windowIndex, $this->getPaneDisplayName('mytop'), TmuxPaneRole::Mytop, $this->getPaneDisplayName('mytop'));
            $this->requireSuccess($this->paneManager->respawnPane($pane, 'mytop -u'), 'respawn mytop pane');
            $windowIndex++;
        }

        // redis monitoring
        if ((int) Settings::settingValue('redis') === 1) {
            $redisArgs = Settings::settingValue('redis_args') ?? '';
            $refreshInterval = 30;

            $pane = $this->createWindowPane($windowIndex, $this->getPaneDisplayName('redis'), TmuxPaneRole::Redis, $this->getPaneDisplayName('redis'));

            // Check if custom args provided for simple redis-cli output
            if (! empty($redisArgs) && $redisArgs !== 'NULL' && $this->commandExists('redis-cli')) {
                $redisHost = config('database.redis.default.host', '127.0.0.1');
                $redisPort = config('database.redis.default.port', 6379);
                $this->requireSuccess($this->paneManager->respawnPane($pane, "watch -n{$refreshInterval} -c 'redis-cli -h {$redisHost} -p {$redisPort} {$redisArgs}'"), 'respawn Redis pane');
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
                    $this->requireSuccess($this->paneManager->respawnPane($pane, "{$sail} artisan redis:monitor --refresh={$refreshInterval}"), 'respawn Redis pane');
                } else {
                    // Run directly, potentially with host override
                    $envPrefix = $connectionInfo['override_host'] ? "REDIS_HOST={$connectionInfo['host']} " : '';
                    $this->requireSuccess($this->paneManager->respawnPane($pane, "{$envPrefix}php {$artisan} redis:monitor --refresh={$refreshInterval}"), 'respawn Redis pane');
                }
            }
            $windowIndex++;
        }

        // bash console
        if ((int) Settings::settingValue('console') === 1) {
            $pane = $this->createWindowPane($windowIndex, $this->getPaneDisplayName('bash'), TmuxPaneRole::Console, $this->getPaneDisplayName('bash'));
            $this->requireSuccess($this->paneManager->respawnPane($pane, 'bash -i'), 'respawn console pane');
        }
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function createSessionPane(TmuxPaneRole $role, string $title): string
    {
        $paneId = $this->sessionManager->createSession($title);
        if ($paneId === null) {
            throw new RuntimeException($this->sessionManager->lastError() ?? 'Unable to create tmux session.');
        }

        $this->sessionCreated = true;
        $this->configurePane($paneId, $role, $title);

        return $paneId;
    }

    private function createWindowPane(int $index, string $windowName, TmuxPaneRole $role, string $title): string
    {
        $paneId = $this->paneManager->createWindow($index, $windowName);
        if ($paneId === null) {
            throw new RuntimeException($this->paneManager->lastError() ?? "Unable to create tmux window {$index}.");
        }

        $this->configurePane($paneId, $role, $title);

        return $paneId;
    }

    private function splitHorizontal(string $target, int $percentage, TmuxPaneRole $role, string $title): string
    {
        $paneId = $this->paneManager->splitHorizontal($target, $percentage);
        if ($paneId === null) {
            throw new RuntimeException($this->paneManager->lastError() ?? "Unable to split tmux pane {$target}.");
        }

        $this->configurePane($paneId, $role, $title);

        return $paneId;
    }

    private function splitVertical(string $target, int $percentage, TmuxPaneRole $role, string $title): string
    {
        $paneId = $this->paneManager->splitVertical($target, $percentage);
        if ($paneId === null) {
            throw new RuntimeException($this->paneManager->lastError() ?? "Unable to split tmux pane {$target}.");
        }

        $this->configurePane($paneId, $role, $title);

        return $paneId;
    }

    private function configurePane(string $paneId, TmuxPaneRole $role, string $title): void
    {
        $this->requireSuccess($this->paneManager->setPaneTitle($paneId, $title), "set title for {$role->value} pane");
        $this->requireSuccess($this->paneManager->setPaneRole($paneId, $role), "tag {$role->value} pane");
    }

    private function requireSuccess(bool $successful, string $operation): void
    {
        if (! $successful) {
            $error = $this->paneManager->lastError();
            $suffix = $error === null || $error === '' ? '' : ": {$error}";

            throw new RuntimeException("Unable to {$operation}{$suffix}.");
        }
    }

    /**
     * Check if a command exists
     */
    protected function commandExists(string $command): bool
    {
        $result = Process::timeout(5)
            ->run(['which', $command]);

        return $result->successful() && str_contains($result->output(), $command);
    }

    /**
     * Resolve how to connect to Redis from the host
     *
     * Returns an array with:
     * - 'use_sail' => bool - whether to use sail to run inside Docker
     * - 'override_host' => bool - whether to override REDIS_HOST env var
     * - 'host' => string - the host to use (only relevant if override_host is true)
     *
     * @return array<string, mixed>
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
