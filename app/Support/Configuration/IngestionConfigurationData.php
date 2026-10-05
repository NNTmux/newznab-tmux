<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use App\Models\IngestionConfiguration;

final readonly class IngestionConfigurationData
{
    public function __construct(
        public int $binaryThreads,
        public int $backfillThreads,
        public int $releaseThreads,
        public int $collectionDelayHours,
        public int $collectionTimeoutHours,
        public int $crossPostHours,
        public int $completionPercent,
        public bool $grabStatus,
        public int $maxHeadersPerIteration,
        public int $maxMessages,
        public int $maxReleasesCreated,
        public int $nntpRetries,
        public int $nzbSplitLevel,
        public int $partRetentionHours,
        public int $releaseRetentionDays,
        public int $miscOtherRetentionHours,
        public int $miscHashedRetentionHours,
        public int $minFilesToFormRelease,
        public int $minSizeToFormRelease,
        public int $maxSizeToFormRelease,
        public int $newGroupScanMethod,
        public int $newGroupDaysToScan,
        public int $newGroupMessagesToScan,
        public string $safeBackfillDate,
        public bool $disableBackfillGroup,
        public bool $partRepair,
        public bool $safePartRepair,
        public int $maxPartRepair,
        public int $partRepairMaxTries,
        public bool $categorizeForeign,
        public bool $categorizeWebDl,
        public bool $showPasswordedReleases,
        public bool $deletePasswordedReleases,
        public int $backfillDaysMode,
        public int $backfillOrder,
        public int $backfillQuantity,
    ) {}

    public static function defaults(): self
    {
        return new self(1, 1, 1, 2, 48, 2, 95, true, 1000000, 20000, 1000, 10, 4, 72, 0, 0, 0, 1, 0, 0, 0, 1, 100000, '2012-06-24', false, true, false, 15000, 3, true, false, false, false, 1, 2, 100000);
    }

    public static function fromModel(IngestionConfiguration $model): self
    {
        return new self(
            (int) $model->binary_threads,
            (int) $model->backfill_threads,
            (int) $model->release_threads,
            (int) $model->collection_delay_hours,
            (int) $model->collection_timeout_hours,
            (int) $model->cross_post_hours,
            (int) $model->completion_percent,
            (bool) $model->grab_status,
            (int) $model->max_headers_per_iteration,
            (int) $model->max_messages,
            (int) $model->max_releases_created,
            (int) $model->nntp_retries,
            (int) $model->nzb_split_level,
            (int) $model->part_retention_hours,
            (int) $model->release_retention_days,
            (int) $model->misc_other_retention_hours,
            (int) $model->misc_hashed_retention_hours,
            (int) $model->min_files_to_form_release,
            (int) $model->min_size_to_form_release,
            (int) $model->max_size_to_form_release,
            $model->new_group_scan_method->value,
            (int) $model->new_group_days_to_scan,
            (int) $model->new_group_messages_to_scan,
            $model->safe_backfill_date->format('Y-m-d'),
            (bool) $model->disable_backfill_group,
            (bool) $model->part_repair,
            (bool) $model->safe_part_repair,
            (int) $model->max_part_repair,
            (int) $model->part_repair_max_tries,
            (bool) $model->categorize_foreign,
            (bool) $model->categorize_web_dl,
            (bool) $model->show_passworded_releases,
            (bool) $model->delete_passworded_releases,
            $model->backfill_days_mode->value,
            (int) $model->backfill_order,
            (int) $model->backfill_quantity,
        );
    }

    /** @return array<string, int|bool|string> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
