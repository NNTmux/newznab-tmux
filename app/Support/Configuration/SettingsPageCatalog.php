<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use App\Enums\ConfigurationDomain;
use App\Enums\LookupMode;
use App\Enums\RegistrationStatus;
use App\Models\IngestionConfiguration;
use App\Models\MetadataConfiguration;
use App\Models\PostProcessingConfiguration;
use App\Models\RegistrationConfiguration;
use App\Models\SiteConfiguration;
use App\Models\TmuxConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SettingsPageCatalog
{
    /** @var array<string, list<string>> */
    private const array COLUMNS = [
        'site' => ['title', 'home_link', 'site_logo', 'strapline', 'meta_title', 'meta_description', 'meta_keywords', 'footer', 'dereferrer_link', 'terms', 'trailers_display', 'trailers_size_x', 'trailers_size_y'],
        'registration' => ['status'],
        'ingestion' => ['binary_threads', 'backfill_threads', 'release_threads', 'collection_delay_hours', 'collection_timeout_hours', 'cross_post_hours', 'completion_percent', 'grab_status', 'max_headers_per_iteration', 'max_messages', 'max_releases_created', 'nntp_retries', 'nzb_split_level', 'part_retention_hours', 'release_retention_days', 'misc_other_retention_hours', 'misc_hashed_retention_hours', 'min_files_to_form_release', 'min_size_to_form_release', 'max_size_to_form_release', 'new_group_scan_method', 'new_group_days_to_scan', 'new_group_messages_to_scan', 'safe_backfill_date', 'disable_backfill_group', 'part_repair', 'safe_part_repair', 'max_part_repair', 'part_repair_max_tries', 'categorize_foreign', 'categorize_web_dl', 'show_passworded_releases', 'delete_passworded_releases', 'backfill_days_mode', 'backfill_order', 'backfill_quantity'],
        'post-processing' => ['post_threads', 'post_threads_amazon', 'post_threads_non', 'nfo_threads', 'fix_name_threads', 'timeout_seconds', 'release_timeout_seconds', 'max_timeout_count', 'max_additional_processed', 'max_parts_processed', 'password_check_attempts', 'fix_names_per_run', 'max_nested_levels', 'extract_using_rar_info', 'segments_to_download', 'ffmpeg_duration', 'inner_file_blacklist', 'process_jpg', 'process_thumbnails', 'process_videos', 'save_audio_preview', 'min_size_to_post_process', 'max_size_to_post_process', 'min_size_to_process_nfo', 'max_size_to_process_nfo', 'max_nfo_processed', 'max_nfo_retries', 'lookup_nfo', 'lookup_par2'],
        'metadata' => ['anime_lookup', 'book_lookup', 'game_lookup', 'movie_lookup', 'music_lookup', 'tv_lookup', 'movie_language', 'imdb_alternate_url', 'max_anime_processed', 'max_books_processed', 'max_games_processed', 'max_movies_processed', 'max_music_processed', 'max_tv_processed', 'amazon_public_key', 'amazon_private_key', 'amazon_associate_tag', 'amazon_sleep_milliseconds'],
        'tmux' => ['session_name', 'monitor_delay', 'niceness', 'sequential_mode', 'sequential_timer', 'binaries_enabled', 'binaries_timer', 'binaries_kill_timer', 'backfill_mode', 'backfill_groups', 'backfill_timer', 'progressive_backfill', 'releases_enabled', 'release_timer', 'post_mode', 'post_timer', 'post_kill_timer', 'post_amazon_mode', 'post_amazon_timer', 'post_non_mode', 'post_non_timer', 'fix_names_enabled', 'fix_timer', 'cleanup_mode', 'cleanup_timer', 'run_irc_scraper', 'console_enabled', 'htop_enabled', 'mytop_enabled', 'nmon_enabled', 'vnstat_enabled', 'vnstat_args', 'tcp_track_enabled', 'tcp_track_args', 'bwmng_enabled', 'redis_enabled', 'redis_args', 'write_logs', 'collections_kill_threshold', 'post_process_kill_threshold', 'colors_start', 'colors_end', 'cleanup_rules', 'color_exclusions'],
    ];

    /** @var list<string> */
    private const array BOOLEANS = ['trailers_display', 'grab_status', 'disable_backfill_group', 'part_repair', 'safe_part_repair', 'categorize_foreign', 'categorize_web_dl', 'show_passworded_releases', 'delete_passworded_releases', 'extract_using_rar_info', 'process_jpg', 'process_thumbnails', 'process_videos', 'save_audio_preview', 'lookup_nfo', 'lookup_par2', 'imdb_alternate_url', 'binaries_enabled', 'progressive_backfill', 'releases_enabled', 'fix_names_enabled', 'run_irc_scraper', 'console_enabled', 'htop_enabled', 'mytop_enabled', 'nmon_enabled', 'vnstat_enabled', 'tcp_track_enabled', 'bwmng_enabled', 'redis_enabled', 'write_logs'];

    /** @var list<string> */
    private const array BYTES = ['min_size_to_form_release', 'max_size_to_form_release', 'min_size_to_post_process', 'max_size_to_post_process', 'min_size_to_process_nfo', 'max_size_to_process_nfo'];

    /** @var list<string> */
    private const array TEXTAREAS = ['meta_description', 'meta_keywords', 'footer', 'terms'];

    /** @var list<string> */
    private const array NULLABLE = ['site_logo', 'amazon_public_key', 'amazon_private_key', 'amazon_associate_tag', 'vnstat_args', 'tcp_track_args', 'redis_args'];

    /** @var list<string> */
    private const array SECRETS = ['amazon_public_key', 'amazon_private_key', 'amazon_associate_tag'];

    /** @var list<string> */
    private const array POSITIVE_INTEGERS = [
        'trailers_size_x', 'trailers_size_y', 'binary_threads', 'backfill_threads', 'release_threads',
        'collection_timeout_hours', 'max_headers_per_iteration', 'max_messages', 'max_releases_created',
        'backfill_quantity', 'post_threads', 'post_threads_amazon', 'post_threads_non', 'nfo_threads',
        'fix_name_threads', 'release_timeout_seconds', 'max_timeout_count', 'max_additional_processed',
        'max_parts_processed', 'password_check_attempts', 'fix_names_per_run', 'max_nested_levels',
        'segments_to_download', 'ffmpeg_duration', 'max_nfo_processed', 'max_anime_processed',
        'max_books_processed', 'max_games_processed', 'max_movies_processed', 'max_music_processed',
        'max_tv_processed', 'monitor_delay', 'sequential_timer', 'binaries_timer', 'backfill_groups',
        'backfill_timer', 'release_timer', 'post_timer', 'post_amazon_timer', 'post_non_timer',
        'fix_timer', 'cleanup_timer',
    ];

    /** @return list<ConfigurationField> */
    public function fields(ConfigurationDomain $domain): array
    {
        return array_map(fn (string $column): ConfigurationField => $this->field($domain, $column), self::COLUMNS[$domain->value]);
    }

    /** @return list<string> */
    public function writableColumns(ConfigurationDomain $domain): array
    {
        return array_values(array_filter(
            self::COLUMNS[$domain->value],
            static fn (string $column): bool => ! in_array($column, ['cleanup_rules', 'color_exclusions'], true),
        ));
    }

    /** @return class-string<Model> */
    public function modelClass(ConfigurationDomain $domain): string
    {
        return match ($domain) {
            ConfigurationDomain::Site => SiteConfiguration::class,
            ConfigurationDomain::Registration => RegistrationConfiguration::class,
            ConfigurationDomain::Ingestion => IngestionConfiguration::class,
            ConfigurationDomain::PostProcessing => PostProcessingConfiguration::class,
            ConfigurationDomain::Metadata => MetadataConfiguration::class,
            ConfigurationDomain::Tmux => TmuxConfiguration::class,
        };
    }

    private function field(ConfigurationDomain $domain, string $column): ConfigurationField
    {
        $label = Str::headline($column);
        $guidance = match ($domain) {
            ConfigurationDomain::Site, ConfigurationDomain::Registration, ConfigurationDomain::Metadata => 'Takes effect immediately.',
            ConfigurationDomain::Tmux => 'The monitor reloads this value on its next cycle; layout changes require a tmux restart.',
            default => 'Restart active workers after changing this value.',
        };

        if ($column === 'site_logo') {
            return new ConfigurationField($column, 'Site Logo', 'PNG, JPEG, or WebP image, up to 2 MB.', 'upload', ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'], guidance: $guidance);
        }

        if ($column === 'cleanup_rules') {
            $options = array_combine(self::cleanupRules(), array_map(Str::headline(...), self::cleanupRules()));

            return new ConfigurationField($column, 'Cleanup Rules', 'Select release cleanup checks to run.', 'multiselect', ['nullable', 'array'], $options, guidance: $guidance);
        }

        if ($column === 'color_exclusions') {
            return new ConfigurationField($column, 'Color Exclusions', 'Comma-separated tmux color indexes from 0 through 255.', 'number-list', ['nullable', 'string'], guidance: $guidance);
        }

        $options = $this->options($column);
        if ($options !== []) {
            return new ConfigurationField($column, $label, $this->help($column), 'enum', ['required', 'integer', 'in:'.implode(',', array_keys($options))], $options, guidance: $guidance);
        }

        if (in_array($column, self::BOOLEANS, true)) {
            return new ConfigurationField($column, $label, $this->help($column), 'boolean', ['required', 'boolean'], [1 => 'Enabled', 0 => 'Disabled'], guidance: $guidance);
        }

        if (in_array($column, self::BYTES, true)) {
            return new ConfigurationField($column, $label, $this->help($column), 'bytes', ['required', 'numeric', 'min:0'], unit: 'MB/GB', guidance: $guidance);
        }

        if ($column === 'safe_backfill_date') {
            return new ConfigurationField($column, $label, 'Oldest date allowed for safe backfill.', 'date', ['required', 'date_format:Y-m-d'], guidance: $guidance);
        }

        if ($column === 'inner_file_blacklist') {
            return new ConfigurationField($column, $label, 'Regular expression used to reject unsafe inner filenames.', 'regex', ['required', 'string', 'max:2000'], guidance: $guidance);
        }

        if (in_array($column, self::SECRETS, true)) {
            return new ConfigurationField($column, $label, 'Stored encrypted. Leave blank to preserve the current value.', 'secret', ['nullable', 'string', 'max:2000'], sensitive: true, guidance: $guidance);
        }

        if (in_array($column, self::TEXTAREAS, true)) {
            $rules = $column === 'terms' ? ['nullable', 'string', 'max:65000'] : ['required', 'string', 'max:65000'];

            return new ConfigurationField($column, $label, $this->help($column), $column === 'terms' ? 'rich-text' : 'textarea', $rules, guidance: $guidance);
        }

        if ($this->isInteger($domain, $column)) {
            $minimum = $column === 'niceness' ? -20 : (in_array($column, self::POSITIVE_INTEGERS, true) ? 1 : 0);
            $maximum = match ($column) {
                'completion_percent' => 100,
                'niceness' => 19,
                'colors_start', 'colors_end' => 255,
                default => null,
            };
            $rules = ['required', 'integer', 'min:'.$minimum];
            if ($maximum !== null) {
                $rules[] = 'max:'.$maximum;
            }

            return new ConfigurationField($column, $label, $this->help($column), 'integer', $rules, unit: $this->unit($column), guidance: $guidance);
        }

        $rules = in_array($column, [...self::NULLABLE, 'dereferrer_link'], true) ? ['nullable', 'string', 'max:1000'] : ['required', 'string', 'max:1000'];
        $control = $column === 'dereferrer_link' ? 'url' : 'text';

        return new ConfigurationField($column, $label, $this->help($column), $control, $rules, guidance: $guidance);
    }

    /** @return array<int, string> */
    private function options(string $column): array
    {
        if ($column === 'status') {
            return array_column(array_map(static fn (RegistrationStatus $status): array => ['value' => $status->value, 'label' => $status->label()], RegistrationStatus::cases()), 'label', 'value');
        }

        if (str_ends_with($column, '_lookup')) {
            return array_column(array_map(static fn (LookupMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()], LookupMode::cases()), 'label', 'value');
        }

        return match ($column) {
            'new_group_scan_method' => [0 => 'Posts', 1 => 'Days'],
            'backfill_days_mode' => [1 => 'Days per group', 2 => 'Safe backfill date'],
            'backfill_order' => [1 => 'Newest', 2 => 'Oldest', 3 => 'Alphabetical', 4 => 'Alphabetical reverse', 5 => 'Most posts', 6 => 'Fewest posts'],
            'sequential_mode' => [0 => 'Full', 1 => 'Basic', 2 => 'Stripped'],
            'backfill_mode' => [0 => 'Disabled', 1 => 'All', 4 => 'Safe'],
            'post_mode', 'post_amazon_mode', 'post_non_mode' => [0 => 'Disabled', 1 => 'Additional', 2 => 'NFO', 3 => 'All'],
            default => [],
        };
    }

    private function isInteger(ConfigurationDomain $domain, string $column): bool
    {
        return ! in_array($column, [...self::BOOLEANS, ...self::BYTES, ...self::TEXTAREAS, ...self::NULLABLE, 'safe_backfill_date', 'inner_file_blacklist', 'title', 'home_link', 'strapline', 'meta_title', 'dereferrer_link', 'movie_language', 'session_name', 'cleanup_mode'], true)
            && $this->options($column) === [];
    }

    private function help(string $column): string
    {
        return match ($column) {
            'home_link' => 'Home path or absolute URL used by site navigation.',
            'dereferrer_link' => 'Optional URL prefix placed before outbound metadata links.',
            'completion_percent' => 'Minimum completion percentage retained during release processing.',
            'monitor_delay' => 'Seconds between tmux monitor refresh cycles.',
            'movie_language' => 'ISO language code used for movie metadata lookup.',
            default => 'Controls '.Str::lower(Str::headline($column)).'.',
        };
    }

    private function unit(string $column): ?string
    {
        return match (true) {
            str_ends_with($column, '_seconds') => 'seconds',
            str_ends_with($column, '_hours') => 'hours',
            str_ends_with($column, '_days') => 'days',
            str_ends_with($column, '_milliseconds') => 'milliseconds',
            str_ends_with($column, '_timer'), $column === 'monitor_delay' => 'seconds',
            default => null,
        };
    }

    /** @return list<string> */
    public static function cleanupRules(): array
    {
        return ['blacklist', 'blfiles', 'executable', 'gibberish', 'hashed', 'installbin', 'passworded', 'passwordurl', 'sample', 'scr', 'short', 'size', 'nzb', 'codec'];
    }
}
