<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use App\Enums\BackfillDaysMode;
use App\Enums\GroupScanMode;
use App\Enums\RegistrationStatus;
use App\Enums\TmuxSequentialMode;
use App\Models\IngestionConfiguration;
use App\Models\RegistrationConfiguration;
use App\Models\TmuxConfiguration;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\MusicService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class SettingsMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
        DB::purge();
        DB::reconnect();
        Cache::flush();
        $this->dropConfigurationTables();
    }

    public function test_valid_values_are_preserved_normalized_and_reconstructable(): void
    {
        $this->createLegacySettings([
            'title' => 'Migrated Indexer',
            'imdblanguage' => 'de',
            'lookuplanguage' => 'fr',
            'minsizetoformrelease' => '1048576',
            'maxsizetoformrelease' => '2147483648',
            'amazonpubkey' => 'obsolete-public-key',
            'amazonprivkey' => 'obsolete-private-key',
            'amazonassociatetag' => 'obsolete-tag',
            'vnstat_args' => 'NULL',
            'fix_crap' => 'blacklist,codec',
            'colors_start' => '1',
            'colors_end' => '250',
            'colors_exc' => '4,8',
            'running' => '1',
            'unknown_plugin_key' => 'discard-me',
        ]);

        $migration = $this->migration();
        $migration->up();

        $this->assertFalse(Schema::hasTable('settings'));
        $this->assertSame('Migrated Indexer', DB::table('site_configurations')->value('title'));
        $this->assertSame('de', DB::table('metadata_configurations')->value('movie_language'));
        $this->assertSame(1048576, (int) DB::table('ingestion_configurations')->value('min_size_to_form_release'));
        $this->assertSame(2147483648, (int) DB::table('ingestion_configurations')->value('max_size_to_form_release'));
        $this->assertNull(DB::table('tmux_configurations')->value('vnstat_args'));
        $this->assertSame(['blacklist', 'codec'], DB::table('tmux_cleanup_rules')->orderBy('rule')->pluck('rule')->all());
        $this->assertSame([4, 8], DB::table('tmux_color_exclusions')->orderBy('color')->pluck('color')->map(static fn ($value): int => (int) $value)->all());
        $this->assertSame(1, (int) DB::table('processing_runtime_states')->value('tmux_running'));

        foreach (['amazon_public_key', 'amazon_private_key', 'amazon_associate_tag'] as $column) {
            $this->assertFalse(Schema::hasColumn('metadata_configurations', $column));
        }
        $this->removeAmazonCredentialsMigration()->up();

        $migration->down();
        $this->assertTrue(Schema::hasTable('settings'));
        $this->assertSame('Migrated Indexer', DB::table('settings')->where('name', 'title')->value('value'));
        foreach (['amazonpubkey', 'amazonprivkey', 'amazonassociatetag'] as $key) {
            $this->assertFalse(DB::table('settings')->where('name', $key)->exists());
        }
        $this->assertFalse(DB::table('settings')->where('name', 'unknown_plugin_key')->exists());
    }

    public function test_missing_values_use_definition_defaults_and_language_alias_falls_back(): void
    {
        $this->createLegacySettings(['lookuplanguage' => 'es']);

        $this->migration()->up();

        $this->assertSame('NNTmux', DB::table('site_configurations')->value('title'));
        $this->assertSame('es', DB::table('metadata_configurations')->value('movie_language'));
        $this->assertSame(20000, (int) DB::table('ingestion_configurations')->value('max_messages'));
    }

    public function test_invalid_recognized_values_are_reported_together_before_any_ddl(): void
    {
        $this->createLegacySettings([
            'completionpercent' => '101',
            'registerstatus' => '9',
            'safebackfilldate' => 'not-a-date',
            'innerfileblacklist' => 'invalid[',
        ]);

        try {
            $this->migration()->up();
            $this->fail('Expected migration preflight to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('completionpercent', $exception->getMessage());
            $this->assertStringContainsString('registerstatus', $exception->getMessage());
            $this->assertStringContainsString('safebackfilldate', $exception->getMessage());
            $this->assertStringContainsString('innerfileblacklist', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('settings'));
        $this->assertSame('101', DB::table('settings')->where('name', 'completionpercent')->value('value'));
        $this->assertFalse(Schema::hasTable('site_configurations'));
    }

    public function test_sqlite_schema_uses_typed_columns_enum_casts_singletons_and_normalized_constraints(): void
    {
        $this->migration()->up();

        $this->assertSame('tinyint', Schema::getColumnType('ingestion_configurations', 'grab_status'));
        $this->assertSame('integer', Schema::getColumnType('ingestion_configurations', 'max_messages'));
        $this->assertSame('integer', Schema::getColumnType('ingestion_configurations', 'min_size_to_form_release'));
        $this->assertSame('date', Schema::getColumnType('ingestion_configurations', 'safe_backfill_date'));
        $this->assertSame('text', Schema::getColumnType('site_configurations', 'terms'));

        foreach (['site_configurations', 'registration_configurations', 'ingestion_configurations', 'post_processing_configurations', 'metadata_configurations', 'tmux_configurations', 'processing_runtime_states'] as $table) {
            $this->assertSame(1, DB::table($table)->count(), "{$table} must contain one singleton row.");
            $this->assertSame(1, (int) DB::table($table)->value('id'));
        }

        $this->assertInstanceOf(RegistrationStatus::class, RegistrationConfiguration::singleton()->status);
        $this->assertInstanceOf(GroupScanMode::class, IngestionConfiguration::singleton()->new_group_scan_method);
        $this->assertInstanceOf(BackfillDaysMode::class, IngestionConfiguration::singleton()->backfill_days_mode);
        $this->assertInstanceOf(TmuxSequentialMode::class, TmuxConfiguration::singleton()->sequential_mode);

        $this->assertNotSame([], Schema::getForeignKeys('tmux_cleanup_rules'));
        DB::table('tmux_cleanup_rules')->insert(['tmux_configuration_id' => 1, 'rule' => 'codec']);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('tmux_cleanup_rules')->insert(['tmux_configuration_id' => 1, 'rule' => 'codec']);
    }

    public function test_existing_amazon_credentials_are_dropped_without_changing_active_metadata_settings(): void
    {
        $this->migration()->up();
        Schema::table('metadata_configurations', function (Blueprint $table): void {
            $table->text('amazon_public_key')->nullable();
            $table->text('amazon_private_key')->nullable();
            $table->text('amazon_associate_tag')->nullable();
        });
        DB::table('metadata_configurations')->where('id', 1)->update([
            'amazon_public_key' => 'obsolete-public-key',
            'amazon_private_key' => 'obsolete-private-key',
            'amazon_associate_tag' => 'obsolete-tag',
            'movie_language' => 'fr',
            'max_music_processed' => 42,
            'amazon_sleep_milliseconds' => 1500,
        ]);
        $before = (array) DB::table('metadata_configurations')->where('id', 1)->first();
        $columns = ['amazon_public_key', 'amazon_private_key', 'amazon_associate_tag'];
        $expected = array_diff_key($before, array_flip($columns));
        $migration = $this->removeAmazonCredentialsMigration();

        $migration->up();

        foreach ($columns as $column) {
            $this->assertFalse(Schema::hasColumn('metadata_configurations', $column));
        }
        $this->assertSame($expected, (array) DB::table('metadata_configurations')->where('id', 1)->first());
        $metadata = app(ConfigurationProvider::class)->metadata(fresh: true);
        $this->assertSame(42, $metadata->maxMusicProcessed);
        $this->assertSame(1500, $metadata->amazonSleepMilliseconds);
        $this->assertSame(42, app(MusicService::class)->musicqty);

        $migration->down();

        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('metadata_configurations', $column));
            $this->assertNull(DB::table('metadata_configurations')->value($column));
        }
        $migration->up();
        $this->assertSame($expected, (array) DB::table('metadata_configurations')->where('id', 1)->first());
    }

    private function removeAmazonCredentialsMigration(): Migration
    {
        return require database_path('migrations/2026_10_05_211849_remove_amazon_credentials_from_metadata_configurations.php');
    }

    /** @param  array<string, string|null>  $values */
    private function createLegacySettings(array $values): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name', 64)->primary();
            $table->text('value')->nullable();
        });

        DB::table('settings')->insert(array_map(
            static fn (string $name, ?string $value): array => ['name' => $name, 'value' => $value],
            array_keys($values),
            array_values($values),
        ));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_09_30_000000_replace_settings_with_typed_domain_configuration.php');
    }

    private function dropConfigurationTables(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['tmux_color_exclusions', 'tmux_cleanup_rules', 'processing_runtime_states', 'tmux_configurations', 'metadata_configurations', 'post_processing_configurations', 'ingestion_configurations', 'registration_configurations', 'site_configurations', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }
}
