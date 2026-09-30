<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use App\Models\PostProcessingConfiguration;

final readonly class PostProcessingConfigurationData
{
    public function __construct(
        public int $postThreads,
        public int $postThreadsAmazon,
        public int $postThreadsNon,
        public int $nfoThreads,
        public int $fixNameThreads,
        public int $timeoutSeconds,
        public int $releaseTimeoutSeconds,
        public int $maxTimeoutCount,
        public int $maxAdditionalProcessed,
        public int $maxPartsProcessed,
        public int $passwordCheckAttempts,
        public int $fixNamesPerRun,
        public int $maxNestedLevels,
        public bool $extractUsingRarInfo,
        public int $segmentsToDownload,
        public int $ffmpegDuration,
        public string $innerFileBlacklist,
        public bool $processJpg,
        public bool $processThumbnails,
        public bool $processVideos,
        public bool $saveAudioPreview,
        public int $minSizeToPostProcess,
        public int $maxSizeToPostProcess,
        public int $minSizeToProcessNfo,
        public int $maxSizeToProcessNfo,
        public int $maxNfoProcessed,
        public int $maxNfoRetries,
        public bool $lookupNfo,
        public bool $lookupPar2,
    ) {}

    public static function defaults(): self
    {
        return new self(1, 1, 1, 1, 1, 60, 120, 3, 25, 3, 1, 10, 3, false, 2, 5, '/setup.exe|password.url/i', false, false, false, false, 1048576, 107374182400, 1048576, 107374182400, 100, 5, true, false);
    }

    public static function fromModel(PostProcessingConfiguration $model): self
    {
        return new self(
            (int) $model->post_threads,
            (int) $model->post_threads_amazon,
            (int) $model->post_threads_non,
            (int) $model->nfo_threads,
            (int) $model->fix_name_threads,
            (int) $model->timeout_seconds,
            (int) $model->release_timeout_seconds,
            (int) $model->max_timeout_count,
            (int) $model->max_additional_processed,
            (int) $model->max_parts_processed,
            (int) $model->password_check_attempts,
            (int) $model->fix_names_per_run,
            (int) $model->max_nested_levels,
            (bool) $model->extract_using_rar_info,
            (int) $model->segments_to_download,
            (int) $model->ffmpeg_duration,
            (string) $model->inner_file_blacklist,
            (bool) $model->process_jpg,
            (bool) $model->process_thumbnails,
            (bool) $model->process_videos,
            (bool) $model->save_audio_preview,
            (int) $model->min_size_to_post_process,
            (int) $model->max_size_to_post_process,
            (int) $model->min_size_to_process_nfo,
            (int) $model->max_size_to_process_nfo,
            (int) $model->max_nfo_processed,
            (int) $model->max_nfo_retries,
            (bool) $model->lookup_nfo,
            (bool) $model->lookup_par2,
        );
    }

    /** @return array<string, int|bool|string> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
