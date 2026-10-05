<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConfigurationDomain;
use App\Enums\LookupMode;
use App\Services\Configuration\ConfigurationProvider;
use App\Support\Configuration\ConfigurationField;
use App\Support\Configuration\SettingsPageCatalog;
use App\Support\SizeUnit;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AdminSettingsControllerTest extends TestCase
{
    private SettingsPageCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
        DB::purge();
        DB::reconnect();
        Cache::flush();
        $this->dropConfigurationTables();
        $this->migration()->up();
        $this->catalog = app(SettingsPageCatalog::class);
        $this->withoutMiddleware();
    }

    public function test_domain_routes_require_admin_and_two_factor_middleware(): void
    {
        $middleware = Route::getRoutes()->getByName('admin.settings.show')?->gatherMiddleware() ?? [];

        $this->assertContains('role:Admin', $middleware);
        $this->assertContains('2fa', $middleware);
    }

    public function test_every_domain_page_renders_from_the_catalog(): void
    {
        foreach (ConfigurationDomain::cases() as $domain) {
            $this->get(route('admin.settings.show', ['domain' => $domain->value]))
                ->assertOk()
                ->assertSee($domain->label().' settings')
                ->assertSee('Save '.$domain->label().' settings');
        }
    }

    public function test_rich_text_fields_use_the_shared_tinymce_component(): void
    {
        $this->get(route('admin.settings.show', ['domain' => 'site']))
            ->assertOk()
            ->assertSee('x-data="tinyMceEditor"', false)
            ->assertSee('id="terms" name="terms"', false)
            ->assertSee('tinymce-editor', false);

        $this->get(route('admin.settings.show', ['domain' => 'ingestion']))
            ->assertOk()
            ->assertDontSee('x-data="tinyMceEditor"', false)
            ->assertDontSee('tinymce-editor', false);
    }

    public function test_each_domain_accepts_a_complete_valid_atomic_update(): void
    {
        foreach (ConfigurationDomain::cases() as $domain) {
            $payload = $this->payload($domain);
            if ($domain === ConfigurationDomain::Site) {
                $payload['title'] = 'Typed Indexer';
            }

            $this->put(route('admin.settings.update', ['domain' => $domain->value]), $payload)
                ->assertRedirect(route('admin.settings.show', ['domain' => $domain->value]));
        }

        $this->assertSame('Typed Indexer', DB::table('site_configurations')->value('title'));
    }

    public function test_every_boolean_setting_can_be_disabled_and_remains_selected_after_reload(): void
    {
        foreach (ConfigurationDomain::cases() as $domain) {
            $booleanFields = array_values(array_filter(
                $this->catalog->fields($domain),
                static fn (ConfigurationField $field): bool => $field->control === 'boolean',
            ));

            if ($booleanFields === []) {
                continue;
            }

            $modelClass = $this->catalog->modelClass($domain);
            /** @var Model $model */
            $model = new $modelClass;
            $enabledValues = array_fill_keys(
                array_map(static fn (ConfigurationField $field): string => $field->column, $booleanFields),
                1,
            );
            DB::table($model->getTable())->where('id', 1)->update($enabledValues);

            $payload = $this->payload($domain);
            foreach ($booleanFields as $field) {
                $payload[$field->column] = '0';
            }

            $this->put(route('admin.settings.update', ['domain' => $domain->value]), $payload)
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('admin.settings.show', ['domain' => $domain->value]));

            foreach ($booleanFields as $field) {
                $this->assertSame(
                    0,
                    (int) DB::table($model->getTable())->where('id', 1)->value($field->column),
                    "{$domain->value}.{$field->column} was not persisted as disabled.",
                );
            }

            $markup = $this->get(route('admin.settings.show', ['domain' => $domain->value]))
                ->assertOk()
                ->getContent();

            foreach ($booleanFields as $field) {
                $this->assertMatchesRegularExpression(
                    '/<select[^>]*id="'.preg_quote($field->column, '/').'"[^>]*>.*?<option value="0" selected>Disabled<\/option>.*?<\/select>/s',
                    $markup,
                    "{$domain->value}.{$field->column} did not render Disabled as selected.",
                );
            }
        }
    }

    public function test_every_enum_setting_persists_an_alternative_and_remains_selected_after_reload(): void
    {
        foreach (ConfigurationDomain::cases() as $domain) {
            $enumFields = array_values(array_filter(
                $this->catalog->fields($domain),
                static fn (ConfigurationField $field): bool => $field->control === 'enum',
            ));

            if ($enumFields === []) {
                continue;
            }

            $payload = $this->payload($domain);
            $expectedValues = [];
            foreach ($enumFields as $field) {
                $currentValue = (string) $payload[$field->column];
                $alternative = array_find(
                    array_keys($field->options),
                    static fn (int|string $value): bool => (string) $value !== $currentValue,
                );
                $expectedValues[$field->column] = (string) $alternative;
                $payload[$field->column] = (string) $alternative;
            }

            $this->put(route('admin.settings.update', ['domain' => $domain->value]), $payload)
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('admin.settings.show', ['domain' => $domain->value]));

            $modelClass = $this->catalog->modelClass($domain);
            /** @var Model $model */
            $model = new $modelClass;
            $markup = $this->get(route('admin.settings.show', ['domain' => $domain->value]))
                ->assertOk()
                ->getContent();

            foreach ($enumFields as $field) {
                $expectedValue = $expectedValues[$field->column];
                $this->assertSame(
                    $expectedValue,
                    (string) DB::table($model->getTable())->where('id', 1)->value($field->column),
                    "{$domain->value}.{$field->column} did not persist its alternative option.",
                );
                $this->assertMatchesRegularExpression(
                    '/<select[^>]*id="'.preg_quote($field->column, '/').'"[^>]*>.*?<option value="'.preg_quote($expectedValue, '/').'" selected>.*?<\/option>.*?<\/select>/s',
                    $markup,
                    "{$domain->value}.{$field->column} did not render its alternative option as selected.",
                );
            }
        }
    }

    public function test_date_setting_persists_and_renders_in_the_native_input_format(): void
    {
        $payload = $this->payload(ConfigurationDomain::Ingestion);
        $payload['safe_backfill_date'] = '2013-04-05';

        $this->put(route('admin.settings.update', ['domain' => 'ingestion']), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.settings.show', ['domain' => 'ingestion']));

        $this->assertSame('2013-04-05', DB::table('ingestion_configurations')->value('safe_backfill_date'));

        $this->get(route('admin.settings.show', ['domain' => 'ingestion']))
            ->assertOk()
            ->assertSee('id="safe_backfill_date" name="safe_backfill_date" type="date" value="2013-04-05"', false);
    }

    public function test_validation_rejects_a_domain_without_partial_writes(): void
    {
        $payload = $this->payload(ConfigurationDomain::Ingestion);
        $payload['max_messages'] = 34567;
        $payload['completion_percent'] = 101;

        $this->from(route('admin.settings.show', ['domain' => 'ingestion']))
            ->put(route('admin.settings.update', ['domain' => 'ingestion']), $payload)
            ->assertSessionHasErrors('completion_percent');

        $this->assertSame(20000, (int) DB::table('ingestion_configurations')->value('max_messages'));
    }

    public function test_post_processing_movie_controls_save_and_stay_in_sync_with_metadata(): void
    {
        $provider = app(ConfigurationProvider::class);
        $provider->metadata();
        $originalLanguage = $provider->metadata()->movieLanguage;

        foreach (LookupMode::cases() as $mode) {
            $payload = $this->payload(ConfigurationDomain::PostProcessing);
            $payload['movie_lookup'] = $mode->value;
            $payload['max_movies_processed'] = 45;
            $payload['post_threads'] = 3;
            $payload['post_non_mode'] = 1;

            $this->put(route('admin.settings.update', ['domain' => 'post-processing']), $payload)
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('admin.settings.show', ['domain' => 'post-processing']));

            $this->assertSame($mode, $provider->metadata()->movieLookup);
            $this->assertSame(45, $provider->metadata()->maxMoviesProcessed);
            $this->assertSame($originalLanguage, $provider->metadata()->movieLanguage);
            $this->assertSame(3, $provider->postProcessing()->postThreads);
            $this->assertSame(1, $provider->tmux()->postNonMode);

            foreach (['post-processing', 'metadata'] as $domain) {
                $markup = $this->get(route('admin.settings.show', ['domain' => $domain]))
                    ->assertOk()
                    ->assertSee('Process Movies')
                    ->assertSee('id="max_movies_processed" name="max_movies_processed" type="number" value="45"', false)
                    ->getContent();
                $this->assertMatchesRegularExpression(
                    '/<select[^>]*id="movie_lookup"[^>]*>.*?<option value="'.$mode->value.'" selected>/s',
                    $markup,
                );
            }
        }

        $payload = $this->payload(ConfigurationDomain::Metadata);
        $payload['movie_lookup'] = LookupMode::All->value;
        $payload['max_movies_processed'] = 60;
        $this->put(route('admin.settings.update', ['domain' => 'metadata']), $payload)->assertSessionHasNoErrors();
        $this->get(route('admin.settings.show', ['domain' => 'post-processing']))
            ->assertOk()
            ->assertSee('id="max_movies_processed" name="max_movies_processed" type="number" value="60"', false);
    }

    public function test_invalid_movie_controls_do_not_save_either_configuration_domain(): void
    {
        $payload = $this->payload(ConfigurationDomain::PostProcessing);
        $payload['movie_lookup'] = 3;
        $payload['max_movies_processed'] = 0;
        $payload['post_threads'] = 4;
        $payload['post_non_mode'] = 1;

        $this->from(route('admin.settings.show', ['domain' => 'post-processing']))
            ->put(route('admin.settings.update', ['domain' => 'post-processing']), $payload)
            ->assertSessionHasErrors(['movie_lookup', 'max_movies_processed']);

        $this->assertSame(1, (int) DB::table('post_processing_configurations')->value('post_threads'));
        $this->assertSame(1, (int) DB::table('metadata_configurations')->value('movie_lookup'));
        $this->assertSame(100, (int) DB::table('metadata_configurations')->value('max_movies_processed'));
        $this->assertSame(0, (int) DB::table('tmux_configurations')->value('post_non_mode'));
    }

    public function test_post_processing_updates_without_movie_controls_preserve_their_values(): void
    {
        DB::table('metadata_configurations')->where('id', 1)->update([
            'movie_lookup' => LookupMode::Renamed->value,
            'max_movies_processed' => 65,
        ]);
        $payload = $this->payload(ConfigurationDomain::PostProcessing);
        $payload['post_threads'] = 2;

        $this->put(route('admin.settings.update', ['domain' => 'post-processing']), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(2, (int) DB::table('post_processing_configurations')->value('post_threads'));
        $this->assertSame(LookupMode::Renamed, app(ConfigurationProvider::class)->metadata(fresh: true)->movieLookup);
        $this->assertSame(65, app(ConfigurationProvider::class)->metadata(fresh: true)->maxMoviesProcessed);
    }

    public function test_legacy_nonzero_pane_modes_render_enabled_and_save_as_switches(): void
    {
        DB::table('tmux_configurations')->where('id', 1)->update([
            'post_non_mode' => 3,
            'post_amazon_mode' => 2,
        ]);

        $markup = $this->get(route('admin.settings.show', ['domain' => 'tmux']))->assertOk()->getContent();
        foreach (['post_non_mode', 'post_amazon_mode'] as $column) {
            $this->assertMatchesRegularExpression('/<select[^>]*id="'.$column.'"[^>]*>.*?<option value="1" selected>Enabled<\/option>/s', $markup);
        }

        $payload = $this->payload(ConfigurationDomain::Tmux);
        $payload['post_non_mode'] = 1;
        $payload['post_amazon_mode'] = 1;
        $this->put(route('admin.settings.update', ['domain' => 'tmux']), $payload)->assertSessionHasNoErrors();

        $this->assertSame(1, app(ConfigurationProvider::class)->tmux(fresh: true)->postNonMode);
        $this->assertSame(1, app(ConfigurationProvider::class)->tmux(fresh: true)->postAmazonMode);
    }

    public function test_metadata_settings_omit_and_ignore_retired_amazon_credentials(): void
    {
        $columns = ['amazon_public_key', 'amazon_private_key', 'amazon_associate_tag'];
        $response = $this->get(route('admin.settings.show', ['domain' => 'metadata']))->assertOk();
        $payload = $this->payload(ConfigurationDomain::Metadata);
        foreach ($columns as $column) {
            $response->assertDontSee($column);
            $this->assertNotContains($column, $this->catalog->writableColumns(ConfigurationDomain::Metadata));
            $payload[$column] = 'obsolete-value';
            $payload['clear_'.$column] = '1';
        }
        $payload['movie_language'] = 'de';

        $this->put(route('admin.settings.update', ['domain' => 'metadata']), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.settings.show', ['domain' => 'metadata']));

        $this->assertSame('de', app(ConfigurationProvider::class)->metadata(fresh: true)->movieLanguage);
        foreach ($columns as $column) {
            $this->assertFalse(Schema::hasColumn('metadata_configurations', $column));
        }
    }

    public function test_logo_size_conversion_normalized_sets_and_cache_invalidation(): void
    {
        Storage::fake('public');
        $sitePayload = $this->payload(ConfigurationDomain::Site);
        $sitePayload['title'] = 'Cached Before Update';
        $sitePayload['site_logo'] = UploadedFile::fake()->image('logo.png', 128, 128);
        $this->put(route('admin.settings.update', ['domain' => 'site']), $sitePayload)->assertSessionHasNoErrors();
        $firstLogo = (string) DB::table('site_configurations')->value('site_logo');
        Storage::disk('public')->assertExists($firstLogo);

        $cached = app(ConfigurationProvider::class)->site();
        $this->assertSame('Cached Before Update', $cached->title);

        $sitePayload = $this->payload(ConfigurationDomain::Site);
        $sitePayload['title'] = 'Cache Invalidated';
        $this->put(route('admin.settings.update', ['domain' => 'site']), $sitePayload)->assertSessionHasNoErrors();
        $this->assertSame('Cache Invalidated', app(ConfigurationProvider::class)->site()->title);
        $this->assertSame($firstLogo, DB::table('site_configurations')->value('site_logo'));

        $sitePayload['site_logo'] = UploadedFile::fake()->image('replacement.png', 256, 256);
        $this->put(route('admin.settings.update', ['domain' => 'site']), $sitePayload)->assertSessionHasNoErrors();
        $replacementLogo = (string) DB::table('site_configurations')->value('site_logo');
        $this->assertNotSame($firstLogo, $replacementLogo);
        Storage::disk('public')->assertMissing($firstLogo);
        Storage::disk('public')->assertExists($replacementLogo);

        $sitePayload = $this->payload(ConfigurationDomain::Site);
        $sitePayload['remove_site_logo'] = '1';
        $this->put(route('admin.settings.update', ['domain' => 'site']), $sitePayload)->assertSessionHasNoErrors();
        $this->assertNull(DB::table('site_configurations')->value('site_logo'));
        Storage::disk('public')->assertMissing($replacementLogo);

        $ingestionPayload = $this->payload(ConfigurationDomain::Ingestion);
        $ingestionPayload['min_size_to_form_release'] = '1.5';
        $ingestionPayload['min_size_to_form_release_unit'] = 'GB';
        $this->put(route('admin.settings.update', ['domain' => 'ingestion']), $ingestionPayload)->assertSessionHasNoErrors();
        $this->assertSame(1610612736, (int) DB::table('ingestion_configurations')->value('min_size_to_form_release'));

        $tmuxPayload = $this->payload(ConfigurationDomain::Tmux);
        $tmuxPayload['cleanup_rules'] = ['codec', 'blacklist', 'codec'];
        $tmuxPayload['color_exclusions'] = '8, 4, 8';
        $this->put(route('admin.settings.update', ['domain' => 'tmux']), $tmuxPayload)->assertSessionHasNoErrors();
        $this->assertSame(['blacklist', 'codec'], DB::table('tmux_cleanup_rules')->orderBy('rule')->pluck('rule')->all());
        $this->assertSame([4, 8], DB::table('tmux_color_exclusions')->orderBy('color')->pluck('color')->map(static fn ($value): int => (int) $value)->all());
    }

    public function test_legacy_get_routes_redirect_and_legacy_posts_are_not_accepted(): void
    {
        $this->get('/admin/site-edit')->assertRedirect(route('admin.settings.show', ['domain' => 'site']));
        $this->get('/admin/tmux-edit')->assertRedirect(route('admin.settings.show', ['domain' => 'tmux']));
        $this->post('/admin/site-edit')->assertMethodNotAllowed();
        $this->post('/admin/tmux-edit')->assertMethodNotAllowed();
    }

    /** @return array<string, mixed> */
    private function payload(ConfigurationDomain $domain): array
    {
        $modelClass = $this->catalog->modelClass($domain);
        /** @var Model $model */
        $model = $modelClass::query()->findOrFail(1);
        $payload = [];

        foreach ($this->catalog->fields($domain) as $field) {
            $value = $model->getAttribute($field->column);
            if ($value instanceof BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }

            if ($field->control === 'upload') {
                continue;
            }
            if ($field->control === 'bytes') {
                $size = SizeUnit::fromBytes($value);
                $payload[$field->column] = $size['value'];
                $payload[$field->column.'_unit'] = $size['unit'];
            } elseif ($field->control === 'secret') {
                $payload[$field->column] = '';
            } elseif ($field->column === 'cleanup_rules') {
                $payload[$field->column] = DB::table('tmux_cleanup_rules')->pluck('rule')->all();
            } elseif ($field->column === 'color_exclusions') {
                $payload[$field->column] = DB::table('tmux_color_exclusions')->pluck('color')->implode(',');
            } else {
                $payload[$field->column] = $value;
            }
        }

        return $payload;
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_09_30_000000_replace_settings_with_typed_domain_configuration.php');
    }

    private function dropConfigurationTables(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['tmux_color_exclusions', 'tmux_cleanup_rules', 'processing_runtime_states', 'tmux_configurations', 'metadata_configurations', 'post_processing_configurations', 'ingestion_configurations', 'registration_configurations', 'site_configurations'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }
}
