<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConfigurationDomain;
use App\Services\Configuration\ConfigurationProvider;
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

    public function test_secret_blank_preserves_and_explicit_clear_removes_encrypted_value(): void
    {
        $payload = $this->payload(ConfigurationDomain::Metadata);
        $payload['amazon_private_key'] = 'secret-value';
        $this->put(route('admin.settings.update', ['domain' => 'metadata']), $payload)->assertSessionHasNoErrors();

        $encrypted = (string) DB::table('metadata_configurations')->value('amazon_private_key');
        $this->assertNotSame('secret-value', $encrypted);

        $payload = $this->payload(ConfigurationDomain::Metadata);
        $payload['amazon_private_key'] = '';
        $this->put(route('admin.settings.update', ['domain' => 'metadata']), $payload)->assertSessionHasNoErrors();
        $this->assertSame($encrypted, DB::table('metadata_configurations')->value('amazon_private_key'));

        $payload['clear_amazon_private_key'] = '1';
        $this->put(route('admin.settings.update', ['domain' => 'metadata']), $payload)->assertSessionHasNoErrors();
        $this->assertNull(DB::table('metadata_configurations')->value('amazon_private_key'));
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
