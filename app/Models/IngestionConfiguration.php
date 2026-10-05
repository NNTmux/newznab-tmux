<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BackfillDaysMode;
use App\Enums\GroupScanMode;
use App\Models\Concerns\IsSingletonConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $binary_threads
 * @property int $backfill_threads
 * @property int $release_threads
 * @property int $collection_delay_hours
 * @property int $collection_timeout_hours
 * @property int $cross_post_hours
 * @property int $completion_percent
 * @property bool $grab_status
 * @property int $max_headers_per_iteration
 * @property int $max_messages
 * @property int $max_releases_created
 * @property int $nntp_retries
 * @property int $nzb_split_level
 * @property int $part_retention_hours
 * @property int $release_retention_days
 * @property int $misc_other_retention_hours
 * @property int $misc_hashed_retention_hours
 * @property int $min_files_to_form_release
 * @property int $min_size_to_form_release
 * @property int $max_size_to_form_release
 * @property GroupScanMode $new_group_scan_method
 * @property int $new_group_days_to_scan
 * @property int $new_group_messages_to_scan
 * @property Carbon $safe_backfill_date
 * @property bool $disable_backfill_group
 * @property bool $part_repair
 * @property bool $safe_part_repair
 * @property int $max_part_repair
 * @property int $part_repair_max_tries
 * @property bool $categorize_foreign
 * @property bool $categorize_web_dl
 * @property bool $show_passworded_releases
 * @property bool $delete_passworded_releases
 * @property BackfillDaysMode $backfill_days_mode
 * @property int $backfill_order
 * @property int $backfill_quantity
 */
final class IngestionConfiguration extends Model
{
    use IsSingletonConfiguration;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'categorize_foreign' => 'boolean',
            'categorize_web_dl' => 'boolean',
            'delete_passworded_releases' => 'boolean',
            'disable_backfill_group' => 'boolean',
            'grab_status' => 'boolean',
            'new_group_scan_method' => GroupScanMode::class,
            'backfill_days_mode' => BackfillDaysMode::class,
            'part_repair' => 'boolean',
            'safe_part_repair' => 'boolean',
            'show_passworded_releases' => 'boolean',
            'safe_backfill_date' => 'date:Y-m-d',
        ];
    }
}
