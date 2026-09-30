<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\IsSingletonConfiguration;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $post_threads
 * @property int $post_threads_amazon
 * @property int $post_threads_non
 * @property int $nfo_threads
 * @property int $fix_name_threads
 * @property int $timeout_seconds
 * @property int $release_timeout_seconds
 * @property int $max_timeout_count
 * @property int $max_additional_processed
 * @property int $max_parts_processed
 * @property int $password_check_attempts
 * @property int $fix_names_per_run
 * @property int $max_nested_levels
 * @property bool $extract_using_rar_info
 * @property int $segments_to_download
 * @property int $ffmpeg_duration
 * @property string $inner_file_blacklist
 * @property bool $process_jpg
 * @property bool $process_thumbnails
 * @property bool $process_videos
 * @property bool $save_audio_preview
 * @property int $min_size_to_post_process
 * @property int $max_size_to_post_process
 * @property int $min_size_to_process_nfo
 * @property int $max_size_to_process_nfo
 * @property int $max_nfo_processed
 * @property int $max_nfo_retries
 * @property bool $lookup_nfo
 * @property bool $lookup_par2
 */
final class PostProcessingConfiguration extends Model
{
    use IsSingletonConfiguration;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'extract_using_rar_info' => 'boolean',
            'lookup_nfo' => 'boolean',
            'lookup_par2' => 'boolean',
            'process_jpg' => 'boolean',
            'process_thumbnails' => 'boolean',
            'process_videos' => 'boolean',
            'save_audio_preview' => 'boolean',
        ];
    }
}
