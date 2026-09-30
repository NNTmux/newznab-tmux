<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use App\Models\TmuxConfiguration;

final readonly class TmuxConfigurationData
{
    /**
     * @param  list<string>  $cleanupRules
     * @param  list<int>  $colorExclusions
     */
    public function __construct(
        public string $sessionName,
        public int $monitorDelay,
        public int $niceness,
        public int $sequentialMode,
        public int $sequentialTimer,
        public bool $binariesEnabled,
        public int $binariesTimer,
        public int $binariesKillTimer,
        public int $backfillMode,
        public int $backfillGroups,
        public int $backfillTimer,
        public bool $progressiveBackfill,
        public bool $releasesEnabled,
        public int $releaseTimer,
        public int $postMode,
        public int $postTimer,
        public int $postKillTimer,
        public int $postAmazonMode,
        public int $postAmazonTimer,
        public int $postNonMode,
        public int $postNonTimer,
        public bool $fixNamesEnabled,
        public int $fixTimer,
        public string $cleanupMode,
        public int $cleanupTimer,
        public bool $runIrcScraper,
        public bool $consoleEnabled,
        public bool $htopEnabled,
        public bool $mytopEnabled,
        public bool $nmonEnabled,
        public bool $vnstatEnabled,
        public ?string $vnstatArgs,
        public bool $tcpTrackEnabled,
        public ?string $tcpTrackArgs,
        public bool $bwmngEnabled,
        public bool $redisEnabled,
        public ?string $redisArgs,
        public bool $writeLogs,
        public int $collectionsKillThreshold,
        public int $postProcessKillThreshold,
        public int $colorsStart,
        public int $colorsEnd,
        public array $cleanupRules,
        public array $colorExclusions,
    ) {}

    public static function defaults(): self
    {
        return new self('nntmux', 30, 19, 0, 30, false, 30, 1, 0, 4, 30, false, false, 30, 0, 30, 300, 0, 30, 0, 30, false, 30, 'Disabled', 30, false, false, false, false, false, false, null, false, '-i eth0 port 443', false, false, null, false, 0, 0, 1, 250, [], [4, 8, 9, 11, 15, 16, 17, 18, 19, 46, 47, 48, 49, 50, 51, 52, 53, 59, 60]);
    }

    public static function fromModel(TmuxConfiguration $model): self
    {
        return new self(
            (string) $model->session_name,
            (int) $model->monitor_delay,
            (int) $model->niceness,
            $model->sequential_mode->value,
            (int) $model->sequential_timer,
            (bool) $model->binaries_enabled,
            (int) $model->binaries_timer,
            (int) $model->binaries_kill_timer,
            $model->backfill_mode->value,
            (int) $model->backfill_groups,
            (int) $model->backfill_timer,
            (bool) $model->progressive_backfill,
            (bool) $model->releases_enabled,
            (int) $model->release_timer,
            $model->post_mode->value,
            (int) $model->post_timer,
            (int) $model->post_kill_timer,
            $model->post_amazon_mode->value,
            (int) $model->post_amazon_timer,
            $model->post_non_mode->value,
            (int) $model->post_non_timer,
            (bool) $model->fix_names_enabled,
            (int) $model->fix_timer,
            (string) $model->cleanup_mode,
            (int) $model->cleanup_timer,
            (bool) $model->run_irc_scraper,
            (bool) $model->console_enabled,
            (bool) $model->htop_enabled,
            (bool) $model->mytop_enabled,
            (bool) $model->nmon_enabled,
            (bool) $model->vnstat_enabled,
            $model->vnstat_args === null ? null : (string) $model->vnstat_args,
            (bool) $model->tcp_track_enabled,
            $model->tcp_track_args === null ? null : (string) $model->tcp_track_args,
            (bool) $model->bwmng_enabled,
            (bool) $model->redis_enabled,
            $model->redis_args === null ? null : (string) $model->redis_args,
            (bool) $model->write_logs,
            (int) $model->collections_kill_threshold,
            (int) $model->post_process_kill_threshold,
            (int) $model->colors_start,
            (int) $model->colors_end,
            $model->cleanupRules->pluck('rule')->map(static fn (mixed $rule): string => (string) $rule)->values()->all(),
            $model->colorExclusions->pluck('color')->map(static fn (mixed $color): int => (int) $color)->values()->all(),
        );
    }

    /** @return array<string, int|bool|string|array<int, int|string>|null> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
