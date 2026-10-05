<?php

declare(strict_types=1);

use Database\Support\LegacySettingsManifest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/../support/LegacySettingsManifest.php';

return new class extends Migration
{
    public function up(): void
    {
        $legacy = Schema::hasTable('settings')
            ? DB::table('settings')->pluck('value', 'name')->all()
            : [];

        $domains = $this->materializeDomains($legacy);
        $runtime = $this->materializeRuntime($legacy);
        $this->assertValid($legacy, $domains, $runtime);

        $this->createDomainTables();

        $now = now();
        foreach ($domains as $table => $attributes) {
            DB::table($table)->insert(['id' => 1, ...$attributes, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('processing_runtime_states')->insert(['id' => 1, ...$runtime, 'created_at' => $now, 'updated_at' => $now]);
        $this->insertSetValues($legacy);

        foreach (array_keys(LegacySettingsManifest::DOMAINS) as $table) {
            if (DB::table($table)->where('id', 1)->count() !== 1) {
                throw new RuntimeException("Configuration cutover verification failed for [{$table}].");
            }
        }

        if (DB::table('processing_runtime_states')->where('id', 1)->count() !== 1) {
            throw new RuntimeException('Configuration cutover verification failed for [processing_runtime_states].');
        }

        Schema::dropIfExists('settings');
    }

    public function down(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name', 64)->primary();
            $table->text('value')->nullable();
        });

        $rows = [];
        foreach (LegacySettingsManifest::DOMAINS as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $configuration = (array) DB::table($table)->where('id', 1)->first();
            foreach ($definitions as $column => $definition) {
                $value = $configuration[$column] ?? $definition['default'];
                $rows[$definition['keys'][0]] = $this->legacyValue($value, $definition['type']);
            }
        }

        if (Schema::hasTable('processing_runtime_states')) {
            $state = (array) DB::table('processing_runtime_states')->where('id', 1)->first();
            foreach (LegacySettingsManifest::RUNTIME as $legacyKey => $definition) {
                $rows[$legacyKey] = $this->legacyValue($state[$definition['column']] ?? $definition['default'], $definition['type']);
            }
        }

        if (Schema::hasTable('tmux_cleanup_rules')) {
            $rows['fix_crap'] = DB::table('tmux_cleanup_rules')->orderBy('rule')->pluck('rule')->implode(',');
        }
        if (Schema::hasTable('tmux_color_exclusions')) {
            $rows['colors_exc'] = DB::table('tmux_color_exclusions')->orderBy('color')->pluck('color')->implode(', ');
        }

        DB::table('settings')->insert(array_map(
            static fn (string $name, mixed $value): array => ['name' => $name, 'value' => $value],
            array_keys($rows),
            array_values($rows),
        ));

        Schema::dropIfExists('tmux_color_exclusions');
        Schema::dropIfExists('tmux_cleanup_rules');
        Schema::dropIfExists('processing_runtime_states');
        foreach (array_reverse(array_keys(LegacySettingsManifest::DOMAINS)) as $table) {
            Schema::dropIfExists($table);
        }
    }

    /** @param  array<string, mixed>  $legacy */
    /** @return array<string, array<string, mixed>> */
    private function materializeDomains(array $legacy): array
    {
        $domains = [];
        foreach (LegacySettingsManifest::DOMAINS as $table => $definitions) {
            foreach ($definitions as $column => $definition) {
                $domains[$table][$column] = $this->materializeValue($legacy, $definition);
            }
        }

        return $domains;
    }

    /** @param  array<string, mixed>  $legacy */
    /** @return array<string, mixed> */
    private function materializeRuntime(array $legacy): array
    {
        $runtime = [];
        foreach (LegacySettingsManifest::RUNTIME as $legacyKey => $definition) {
            $runtime[$definition['column']] = $this->convertValue($legacy[$legacyKey] ?? $definition['default'], $definition['type']);
        }

        return $runtime;
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array{keys: list<string>, type: string, default: mixed, min?: int, max?: int}  $definition
     */
    private function materializeValue(array $legacy, array $definition): mixed
    {
        $value = $definition['default'];
        foreach ($definition['keys'] as $key) {
            if (array_key_exists($key, $legacy)) {
                $value = $legacy[$key];
                break;
            }
        }

        return $this->convertValue($value, $definition['type']);
    }

    private function convertValue(mixed $value, string $type): mixed
    {
        if ($type === 'nullable_string') {
            if ($value === null || $value === '' || strtoupper((string) $value) === 'NULL') {
                return null;
            }

            return (string) $value;
        }

        if ($type === 'nullable_datetime') {
            if ($value === null || $value === '' || strtoupper((string) $value) === 'NULL') {
                return null;
            }

            return (string) $value;
        }

        return match ($type) {
            'bool' => (bool) (int) $value,
            'int' => (int) $value,
            default => (string) $value,
        };
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<string, array<string, mixed>>  $domains
     * @param  array<string, mixed>  $runtime
     */
    private function assertValid(array $legacy, array $domains, array $runtime): void
    {
        $errors = [];
        foreach (LegacySettingsManifest::DOMAINS as $table => $definitions) {
            foreach ($definitions as $column => $definition) {
                $raw = null;
                $source = null;
                foreach ($definition['keys'] as $key) {
                    if (array_key_exists($key, $legacy)) {
                        $raw = $legacy[$key];
                        $source = $key;
                        break;
                    }
                }
                if ($source === null) {
                    continue;
                }

                $this->validateValue($errors, $source, $raw, $definition);
            }
        }

        foreach (LegacySettingsManifest::RUNTIME as $key => $definition) {
            if (array_key_exists($key, $legacy)) {
                $this->validateValue($errors, $key, $legacy[$key], $definition);
            }
        }

        $this->validateRangePair($errors, 'release formation size', $domains['ingestion_configurations']['min_size_to_form_release'], $domains['ingestion_configurations']['max_size_to_form_release']);
        $this->validateRangePair($errors, 'post-processing size', $domains['post_processing_configurations']['min_size_to_post_process'], $domains['post_processing_configurations']['max_size_to_post_process']);
        $this->validateRangePair($errors, 'NFO-processing size', $domains['post_processing_configurations']['min_size_to_process_nfo'], $domains['post_processing_configurations']['max_size_to_process_nfo']);

        if ($domains['tmux_configurations']['colors_start'] > $domains['tmux_configurations']['colors_end']) {
            $errors[] = 'colors_start must be less than or equal to colors_end';
        }

        $allowedCleanupRules = ['blacklist', 'blfiles', 'executable', 'gibberish', 'hashed', 'installbin', 'passworded', 'passwordurl', 'sample', 'scr', 'short', 'size', 'nzb', 'codec'];
        foreach ($this->stringList($legacy['fix_crap'] ?? '') as $rule) {
            if (! in_array($rule, $allowedCleanupRules, true)) {
                $errors[] = "fix_crap contains an unknown cleanup rule [{$rule}]";
            }
        }

        foreach ($this->stringList($legacy['colors_exc'] ?? '') as $color) {
            if (preg_match('/^\d+$/', $color) !== 1 || (int) $color > 255) {
                $errors[] = "colors_exc contains an invalid color [{$color}]";
            } elseif ((int) $color < $domains['tmux_configurations']['colors_start'] || (int) $color > $domains['tmux_configurations']['colors_end']) {
                $errors[] = "colors_exc color [{$color}] must be inside colors_start and colors_end";
            }
        }

        unset($runtime);

        if ($errors !== []) {
            throw new RuntimeException("Invalid legacy settings; no schema changes were made:\n - ".implode("\n - ", $errors));
        }
    }

    /**
     * @param  list<string>  $errors
     * @param  array{type: string, default: mixed, min?: int, max?: int}  $definition
     */
    private function validateValue(array &$errors, string $key, mixed $value, array $definition): void
    {
        $type = $definition['type'];
        if (str_starts_with($type, 'nullable_') && ($value === null || $value === '' || strtoupper((string) $value) === 'NULL')) {
            return;
        }

        if ($type === 'bool' && ! in_array((string) $value, ['0', '1'], true)) {
            $errors[] = "{$key} must be 0 or 1; got [{$value}]";

            return;
        }

        if ($type === 'int') {
            if (preg_match('/^-?\d+$/', (string) $value) !== 1) {
                $errors[] = "{$key} must be an integer; got [{$value}]";

                return;
            }

            $integer = (int) $value;
            if (isset($definition['min']) && $integer < $definition['min']) {
                $errors[] = "{$key} must be at least {$definition['min']}; got [{$value}]";
            }
            if (isset($definition['max']) && $integer > $definition['max']) {
                $errors[] = "{$key} must be at most {$definition['max']}; got [{$value}]";
            }
        }

        if ($type === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
            if ($date === false || $date->format('Y-m-d') !== (string) $value) {
                $errors[] = "{$key} must be a valid YYYY-MM-DD date; got [{$value}]";
            }
        }

        if ($type === 'regex' && @preg_match((string) $value, '') === false) {
            $errors[] = "{$key} must be a valid regular expression; got [{$value}]";
        }

        if ($type === 'nullable_datetime' && strtotime((string) $value) === false) {
            $errors[] = "{$key} must be a valid date-time; got [{$value}]";
        }
    }

    /** @param  list<string>  $errors */
    private function validateRangePair(array &$errors, string $label, int $minimum, int $maximum): void
    {
        if ($maximum > 0 && $minimum > $maximum) {
            $errors[] = "{$label} minimum must not exceed its maximum";
        }
    }

    /** @param  array<string, mixed>  $legacy */
    private function insertSetValues(array $legacy): void
    {
        $cleanupRules = $this->stringList($legacy['fix_crap'] ?? '');
        if ($cleanupRules !== []) {
            DB::table('tmux_cleanup_rules')->insert(array_map(
                static fn (string $rule): array => ['tmux_configuration_id' => 1, 'rule' => $rule],
                $cleanupRules,
            ));
        }

        $colors = array_values(array_filter(array_map(
            static fn (string $color): ?int => preg_match('/^\d+$/', $color) === 1 ? (int) $color : null,
            $this->stringList($legacy['colors_exc'] ?? '4,8,9,11,15,16,17,18,19,46,47,48,49,50,51,52,53,59,60'),
        ), static fn (?int $color): bool => $color !== null));
        if ($colors !== []) {
            DB::table('tmux_color_exclusions')->insert(array_map(
                static fn (int $color): array => ['tmux_configuration_id' => 1, 'color' => $color],
                array_values(array_unique($colors)),
            ));
        }
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if ($value === null || $value === '' || $value === '0') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn (string $item): bool => $item !== ''));
    }

    private function legacyValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($type === 'bool') {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function createDomainTables(): void
    {
        Schema::create('site_configurations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('title')->default('NNTmux');
            $table->string('home_link')->default('/');
            $table->string('site_logo')->nullable();
            $table->string('strapline')->default('');
            $table->string('meta_title')->default('');
            $table->text('meta_description');
            $table->text('meta_keywords');
            $table->text('footer');
            $table->string('dereferrer_link', 1000)->default('');
            $table->longText('terms');
            $table->boolean('trailers_display')->default(true);
            $table->unsignedSmallInteger('trailers_size_x')->default(480);
            $table->unsignedSmallInteger('trailers_size_y')->default(345);
            $table->timestamps();
        });

        Schema::create('registration_configurations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamps();
        });

        Schema::create('ingestion_configurations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            foreach (['binary_threads', 'backfill_threads', 'release_threads'] as $column) {
                $table->unsignedSmallInteger($column)->default(1);
            }
            foreach (['collection_delay_hours', 'collection_timeout_hours', 'cross_post_hours', 'completion_percent', 'nntp_retries', 'nzb_split_level', 'part_retention_hours', 'release_retention_days', 'misc_other_retention_hours', 'misc_hashed_retention_hours', 'min_files_to_form_release', 'new_group_scan_method', 'new_group_days_to_scan', 'part_repair_max_tries', 'backfill_days_mode', 'backfill_order'] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            foreach (['max_headers_per_iteration', 'max_messages', 'max_releases_created', 'new_group_messages_to_scan', 'max_part_repair', 'backfill_quantity'] as $column) {
                $table->unsignedBigInteger($column)->default(0);
            }
            foreach (['min_size_to_form_release', 'max_size_to_form_release'] as $column) {
                $table->unsignedBigInteger($column)->default(0);
            }
            foreach (['grab_status', 'disable_backfill_group', 'part_repair', 'safe_part_repair', 'categorize_foreign', 'categorize_web_dl', 'show_passworded_releases', 'delete_passworded_releases'] as $column) {
                $table->boolean($column)->default(false);
            }
            $table->date('safe_backfill_date');
            $table->timestamps();
        });

        Schema::create('post_processing_configurations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            foreach (['post_threads', 'post_threads_amazon', 'post_threads_non', 'nfo_threads', 'fix_name_threads', 'max_timeout_count', 'max_parts_processed', 'password_check_attempts', 'max_nested_levels', 'segments_to_download'] as $column) {
                $table->unsignedSmallInteger($column)->default(1);
            }
            foreach (['timeout_seconds', 'release_timeout_seconds', 'max_additional_processed', 'fix_names_per_run', 'ffmpeg_duration', 'max_nfo_processed', 'max_nfo_retries'] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            foreach (['min_size_to_post_process', 'max_size_to_post_process', 'min_size_to_process_nfo', 'max_size_to_process_nfo'] as $column) {
                $table->unsignedBigInteger($column)->default(0);
            }
            foreach (['extract_using_rar_info', 'process_jpg', 'process_thumbnails', 'process_videos', 'save_audio_preview', 'lookup_nfo', 'lookup_par2'] as $column) {
                $table->boolean($column)->default(false);
            }
            $table->text('inner_file_blacklist');
            $table->timestamps();
        });

        Schema::create('metadata_configurations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            foreach (['anime_lookup', 'book_lookup', 'game_lookup', 'movie_lookup', 'music_lookup', 'tv_lookup'] as $column) {
                $table->unsignedTinyInteger($column)->default(0);
            }
            $table->string('movie_language', 8)->default('en');
            $table->boolean('imdb_alternate_url')->default(false);
            foreach (['max_anime_processed', 'max_books_processed', 'max_games_processed', 'max_movies_processed', 'max_music_processed', 'max_tv_processed', 'amazon_sleep_milliseconds'] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            $table->timestamps();
        });

        Schema::create('tmux_configurations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('session_name')->default('nntmux');
            $table->unsignedInteger('monitor_delay')->default(30);
            $table->smallInteger('niceness')->default(19);
            foreach (['sequential_mode', 'backfill_mode', 'post_mode', 'post_amazon_mode', 'post_non_mode'] as $column) {
                $table->unsignedTinyInteger($column)->default(0);
            }
            foreach (['sequential_timer', 'binaries_timer', 'binaries_kill_timer', 'backfill_groups', 'backfill_timer', 'release_timer', 'post_timer', 'post_kill_timer', 'post_amazon_timer', 'post_non_timer', 'fix_timer', 'cleanup_timer', 'collections_kill_threshold', 'post_process_kill_threshold'] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            foreach (['binaries_enabled', 'progressive_backfill', 'releases_enabled', 'fix_names_enabled', 'run_irc_scraper', 'console_enabled', 'htop_enabled', 'mytop_enabled', 'nmon_enabled', 'vnstat_enabled', 'tcp_track_enabled', 'bwmng_enabled', 'redis_enabled', 'write_logs'] as $column) {
                $table->boolean($column)->default(false);
            }
            $table->string('cleanup_mode')->default('Disabled');
            $table->string('vnstat_args', 1000)->nullable();
            $table->string('tcp_track_args', 1000)->nullable();
            $table->string('redis_args', 1000)->nullable();
            $table->unsignedTinyInteger('colors_start')->default(1);
            $table->unsignedTinyInteger('colors_end')->default(250);
            $table->timestamps();
        });

        Schema::create('tmux_cleanup_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('tmux_configuration_id')->default(1);
            $table->string('rule', 32);
            $table->unique(['tmux_configuration_id', 'rule']);
            $table->foreign('tmux_configuration_id')->references('id')->on('tmux_configurations')->cascadeOnDelete();
        });

        Schema::create('tmux_color_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('tmux_configuration_id')->default(1);
            $table->unsignedTinyInteger('color');
            $table->unique(['tmux_configuration_id', 'color']);
            $table->foreign('tmux_configuration_id')->references('id')->on('tmux_configurations')->cascadeOnDelete();
        });

        Schema::create('processing_runtime_states', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('tmux_running')->default(false);
            $table->boolean('stop_requested')->default(false);
            $table->dateTime('last_binary_run_at')->nullable();
            $table->string('monitor_path', 1000)->nullable();
            $table->string('monitor_path_a', 1000)->nullable();
            $table->string('monitor_path_b', 1000)->nullable();
            $table->timestamps();
        });
    }
};
