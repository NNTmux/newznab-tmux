<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\LogIndexFile;
use App\Models\User;
use App\Services\LogViewer\Index\LogIndex;
use App\Services\LogViewer\Index\LogIndexer;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeLogIndex;
use Tests\TestCase;

class AdminLogViewerControllerTest extends TestCase
{
    private const array APPLICATION_LOG = [
        '[2026-03-10 09:00:00] local.INFO: first entry',
        '[2026-03-10 09:01:00] local.ERROR: payment failed',
        '#0 /app/Payments.php(12): charge()',
        '#1 {main}',
        '[2026-03-10 09:02:00] local.WARNING: third entry',
    ];

    private string $logDirectory = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logDirectory = storage_path('framework/testing/log-viewer/'.Str::uuid()->toString());
        File::ensureDirectoryExists($this->logDirectory);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mail.from.address' => '',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'nntmux.log_viewer.path' => $this->logDirectory,
            'logging.channels.admin' => ['driver' => 'monolog', 'handler' => TestHandler::class],
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();
        Queue::fake();

        $this->createSchema();
        $this->resetGlobalComposerState();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware(Google2FAMiddleware::class);
    }

    protected function tearDown(): void
    {
        if ($this->logDirectory !== '') {
            File::deleteDirectory($this->logDirectory);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_admin_sees_the_log_viewer_shell_with_available_logs(): void
    {
        $this->createLogFile('application.log', self::APPLICATION_LOG);
        $this->createLogFile('nested/secondary.log', ['[2026-03-10 10:00:00] local.INFO: secondary']);

        $response = $this->actingAs($this->admin())->get(route('admin.logs.index'));

        $response->assertOk();
        $response->assertSee('Log Viewer');
        $response->assertSee('x-data="adminLogViewer"', false);
        $response->assertSee('application.log');
        $response->assertSee('nested/secondary.log');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedEndpointProvider(): array
    {
        return [
            'index' => ['GET', 'admin.logs.index'],
            'files' => ['GET', 'admin.logs.files'],
            'entries' => ['GET', 'admin.logs.entries'],
            'entry' => ['GET', 'admin.logs.entry'],
            'search' => ['GET', 'admin.logs.search'],
            'facets' => ['GET', 'admin.logs.facets'],
            'download' => ['GET', 'admin.logs.download'],
            'truncate' => ['POST', 'admin.logs.truncate'],
            'destroy' => ['DELETE', 'admin.logs.destroy'],
        ];
    }

    #[DataProvider('protectedEndpointProvider')]
    public function test_non_admin_users_are_forbidden_from_every_endpoint(string $method, string $routeName): void
    {
        $this->createLogFile('application.log', self::APPLICATION_LOG);
        $user = $this->createUserWithRole('User');
        /** @var Authenticatable $authenticatedUser */
        $authenticatedUser = $user;

        $this->actingAs($authenticatedUser)
            ->json($method, route($routeName), ['file' => 'application.log', 'files' => ['application.log'], 'q' => 'entry', 'offset' => 0])
            ->assertForbidden();

        $this->assertSame(implode(PHP_EOL, self::APPLICATION_LOG).PHP_EOL, File::get($this->logDirectory.'/application.log'));
    }

    public function test_unknown_log_file_redirects_with_error(): void
    {
        $this->createLogFile('known.log', ['known entry']);

        $this->actingAs($this->admin())
            ->get(route('admin.logs.index', ['file' => 'missing.log']))
            ->assertRedirect(route('admin.logs.index'))
            ->assertSessionHas('error', 'Selected log file is not available.');
    }

    public function test_path_traversal_is_rejected_on_the_page(): void
    {
        $this->createLogFile('known.log', ['known entry']);

        $this->actingAs($this->admin())
            ->get(route('admin.logs.index', ['file' => '../../../bootstrap/app.php']))
            ->assertRedirect(route('admin.logs.index'))
            ->assertSessionHas('error', 'Selected log file is not available.');
    }

    public function test_files_endpoint_lists_newest_first_with_format_detection(): void
    {
        $this->createLogFile('application.log', self::APPLICATION_LOG, time() - 3600);
        $this->createLogFile('horizon.log', ['  2026-10-05 21:53:20 App\Jobs\Reindex ... DONE']);

        $response = $this->actingAs($this->admin())->getJson(route('admin.logs.files'));

        $response->assertOk();
        $this->assertSame(['horizon.log', 'application.log'], array_column($response->json('files'), 'path'));
        $this->assertSame([false, true], array_column($response->json('files'), 'structured'));
    }

    public function test_entries_groups_stack_traces_and_pages_with_the_before_cursor(): void
    {
        $this->createLogFile('application.log', self::APPLICATION_LOG);
        $admin = $this->admin();

        $firstPage = $this->actingAs($admin)->getJson(route('admin.logs.entries', ['file' => 'application.log', 'limit' => 50]));

        $firstPage->assertOk();
        $this->assertSame(['third entry', 'payment failed', 'first entry'], array_column($firstPage->json('entries'), 'message'));
        $this->assertSame("#0 /app/Payments.php(12): charge()\n#1 {main}", $firstPage->json('entries.1.body'));
        $this->assertSame('error', $firstPage->json('entries.1.level'));
        $this->assertNull($firstPage->json('before'));

        $errorsOnly = $this->actingAs($admin)->getJson(route('admin.logs.entries', ['file' => 'application.log', 'levels' => ['error']]));
        $this->assertSame(['payment failed'], array_column($errorsOnly->json('entries'), 'message'));

        $paged = $this->actingAs($admin)->getJson(route('admin.logs.entries', ['file' => 'application.log', 'limit' => 50, 'before' => $firstPage->json('entries.1.offset')]));
        $this->assertSame(['first entry'], array_column($paged->json('entries'), 'message'));
    }

    public function test_entries_rejects_unknown_traversal_and_symlinked_files(): void
    {
        $this->createLogFile('application.log', self::APPLICATION_LOG);
        $outside = dirname($this->logDirectory).'/outside-'.Str::random(8).'.txt';
        File::put($outside, 'secret');
        symlink($outside, $this->logDirectory.'/linked.log');
        $admin = $this->admin();

        try {
            foreach (['missing.log', '../../../../.env', 'linked.log'] as $file) {
                $this->actingAs($admin)
                    ->getJson(route('admin.logs.entries', ['file' => $file]))
                    ->assertNotFound();
            }
        } finally {
            File::delete($outside);
        }
    }

    public function test_entry_endpoint_resolves_the_full_entry_from_an_offset_inside_it(): void
    {
        $this->createLogFile('application.log', self::APPLICATION_LOG);
        $contents = File::get($this->logDirectory.'/application.log');

        $response = $this->actingAs($this->admin())->getJson(route('admin.logs.entry', [
            'file' => 'application.log',
            'offset' => strpos($contents, '#1 {main}'),
        ]));

        $response->assertOk();
        $response->assertJsonPath('entry.message', 'payment failed');
        $response->assertJsonPath('entry.offset', strpos($contents, '[2026-03-10 09:01:00]'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function grepBinaryProvider(): array
    {
        return [
            'grep engine' => ['grep'],
            'php engine' => [''],
        ];
    }

    #[DataProvider('grepBinaryProvider')]
    public function test_search_covers_requested_files_and_reports_matches_per_file(string $grepBinary): void
    {
        config(['nntmux.log_viewer.grep_binary' => $grepBinary]);
        $this->createLogFile('application.log', self::APPLICATION_LOG);
        $this->createLogFile('payments.log', ['[2026-03-10 11:00:00] local.ERROR: payment declined']);
        $this->createLogFile('ignored.log', ['[2026-03-10 12:00:00] local.ERROR: payment not requested']);

        $response = $this->actingAs($this->admin())->getJson(route('admin.logs.search', [
            'q' => 'PAYMENT',
            'files' => ['application.log', 'payments.log'],
        ]));

        $response->assertOk();
        $this->assertSame(['application.log', 'payments.log'], array_column($response->json('results'), 'file'));
        $this->assertSame(['payment failed'], array_column($response->json('results.0.entries'), 'message'));
        $this->assertSame(2, $response->json('results.0.total_matches'));
        $this->assertSame(['payment declined'], array_column($response->json('results.1.entries'), 'message'));
        $this->assertSame(['text' => 'payment', 'hit' => true], $response->json('results.1.entries.0.message_segments.0'));
        $response->assertDontSee('payment not requested');
    }

    public function test_search_rejects_invalid_regex_and_unknown_files(): void
    {
        $this->createLogFile('application.log', self::APPLICATION_LOG);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson(route('admin.logs.search', ['q' => 'pay(ment', 'regex' => 1, 'files' => ['application.log']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q' => 'Invalid regular expression.']);

        $this->actingAs($admin)
            ->getJson(route('admin.logs.search', ['q' => 'payment', 'files' => ['application.log', '../secrets.log']]))
            ->assertNotFound();
    }

    public function test_level_only_search_needs_no_term(): void
    {
        $this->createLogFile('application.log', self::APPLICATION_LOG);

        $response = $this->actingAs($this->admin())->getJson(route('admin.logs.search', [
            'levels' => ['warning'],
            'files' => ['application.log'],
        ]));

        $response->assertOk();
        $this->assertSame(['third entry'], array_column($response->json('results.0.entries'), 'message'));
    }

    public function test_download_streams_the_raw_file(): void
    {
        $this->createLogFile('application.log', self::APPLICATION_LOG);

        $response = $this->actingAs($this->admin())->get(route('admin.logs.download', ['file' => 'application.log']));

        $response->assertOk();
        $response->assertDownload('application.log');
        $this->assertSame(implode(PHP_EOL, self::APPLICATION_LOG).PHP_EOL, $response->streamedContent());
    }

    public function test_truncate_empties_the_file_and_is_audited(): void
    {
        $this->createLogFile('application.log', self::APPLICATION_LOG);

        $this->actingAs($this->admin())
            ->postJson(route('admin.logs.truncate'), ['file' => 'application.log'])
            ->assertOk()
            ->assertJsonPath('file.size', 0);

        $this->assertSame('', File::get($this->logDirectory.'/application.log'));
        $this->assertTrue($this->auditHandler()->hasWarningThatContains('Admin truncated log file'));
    }

    public function test_delete_removes_an_idle_file_but_refuses_an_active_one(): void
    {
        $this->createLogFile('old.log', ['old'], time() - 86400);
        $this->createLogFile('active.log', ['active']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->deleteJson(route('admin.logs.destroy'), ['file' => 'old.log'])
            ->assertOk();
        $this->actingAs($admin)
            ->deleteJson(route('admin.logs.destroy'), ['file' => 'active.log'])
            ->assertConflict();

        $this->assertFileDoesNotExist($this->logDirectory.'/old.log');
        $this->assertFileExists($this->logDirectory.'/active.log');
        $this->assertTrue($this->auditHandler()->hasWarningThatContains('Admin deleted log file'));
    }

    public function test_invalid_utf8_in_logs_still_produces_json(): void
    {
        $this->createLogFile('binary.log', ["[2026-03-10 09:00:00] local.INFO: bad \xC3\x28 bytes"]);

        $response = $this->actingAs($this->admin())->getJson(route('admin.logs.entries', ['file' => 'binary.log']));

        $response->assertOk();
        $this->assertStringStartsWith('bad ', (string) $response->json('entries.0.message'));
    }

    public function test_search_uses_the_log_index_for_caught_up_files_and_grep_for_the_rest(): void
    {
        $index = $this->enableIndex();
        $this->createLogFile('application.log', self::APPLICATION_LOG, time() - 600);
        $this->createLogFile('payments.log', ['[2026-03-10 11:00:00] local.ERROR: payment declined']);
        app(LogIndexer::class)->run(['application.log']);

        $response = $this->actingAs($this->admin())->getJson(route('admin.logs.search', [
            'q' => 'PAYMENT',
            'files' => ['application.log', 'payments.log'],
        ]));

        $response->assertOk();
        $response->assertJsonPath('engine', 'mixed');
        $this->assertSame(['manticore', 'grep'], array_column($response->json('results'), 'engine'));
        $this->assertSame(['payment failed'], array_column($response->json('results.0.entries'), 'message'));
        $this->assertSame("#0 /app/Payments.php(12): charge()\n#1 {main}", $response->json('results.0.entries.0.body'));
        $this->assertSame(['payment declined'], array_column($response->json('results.1.entries'), 'message'));
        $this->assertNotSame([], $index->queries);
    }

    public function test_regex_searches_and_index_failures_fall_back_to_grep(): void
    {
        $index = $this->enableIndex();
        $this->createLogFile('application.log', self::APPLICATION_LOG, time() - 600);
        app(LogIndexer::class)->run();
        $admin = $this->admin();

        $regex = $this->actingAs($admin)->getJson(route('admin.logs.search', ['q' => 'pay\w+ failed', 'regex' => 1, 'files' => ['application.log']]));
        $regex->assertOk()->assertJsonPath('results.0.engine', 'grep');
        $this->assertSame(['payment failed'], array_column($regex->json('results.0.entries'), 'message'));

        $index->failing = true;
        $failing = $this->actingAs($admin)->getJson(route('admin.logs.search', ['q' => 'payment', 'files' => ['application.log']]));
        $failing->assertOk()->assertJsonPath('results.0.engine', 'grep');
        $this->assertSame(['payment failed'], array_column($failing->json('results.0.entries'), 'message'));
    }

    public function test_grep_searches_filter_by_channel_and_time_range(): void
    {
        $this->createLogFile('application.log', [
            '[2026-03-10 09:00:00] security.WARNING: login throttled',
            '[2026-03-10 09:30:00] local.WARNING: disk almost full',
            '[2026-03-10 10:00:00] security.WARNING: login throttled again',
        ]);
        $admin = $this->admin();

        $byChannel = $this->actingAs($admin)->getJson(route('admin.logs.search', ['channels' => ['security'], 'files' => ['application.log']]));
        $this->assertSame(['login throttled again', 'login throttled'], array_column($byChannel->json('results.0.entries'), 'message'));

        $byTime = $this->actingAs($admin)->getJson(route('admin.logs.search', ['from' => '2026-03-10T09:15', 'to' => '2026-03-10T09:30', 'files' => ['application.log']]));
        $this->assertSame(['disk almost full'], array_column($byTime->json('results.0.entries'), 'message'));

        $this->actingAs($admin)
            ->getJson(route('admin.logs.search', ['from' => '2026-03-10T10:00', 'to' => '2026-03-10T09:00', 'channels' => ['bad channel'], 'files' => ['application.log']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to', 'channels.0']);
    }

    public function test_facets_come_from_the_index_and_are_unavailable_without_it(): void
    {
        $this->createLogFile('application.log', self::APPLICATION_LOG, time() - 600);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson(route('admin.logs.facets', ['files' => ['application.log']]))
            ->assertOk()
            ->assertExactJson(['available' => false]);

        $this->enableIndex();
        app(LogIndexer::class)->run();

        $this->actingAs($admin)
            ->getJson(route('admin.logs.facets', ['files' => ['application.log'], 'levels' => ['error']]))
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('partial', false)
            ->assertJsonPath('levels', ['info' => 1, 'error' => 1, 'warning' => 1])
            ->assertJsonPath('channels', ['local' => 3]);

        $this->actingAs($admin)
            ->getJson(route('admin.logs.facets', ['files' => ['application.log'], 'q' => 'pay.*', 'regex' => 1]))
            ->assertExactJson(['available' => false]);
    }

    public function test_files_endpoint_reports_index_progress_when_the_index_is_available(): void
    {
        $this->createLogFile('application.log', self::APPLICATION_LOG, time() - 600);
        $admin = $this->admin();

        $this->actingAs($admin)->getJson(route('admin.logs.files'))->assertJsonPath('files.0.index', null);

        $this->enableIndex();
        app(LogIndexer::class)->run();

        $this->actingAs($admin)
            ->getJson(route('admin.logs.files'))
            ->assertJsonPath('files.0.index.state', 'indexed')
            ->assertJsonPath('engine', 'manticore');
    }

    public function test_truncating_a_file_removes_it_from_the_index(): void
    {
        $index = $this->enableIndex();
        $this->createLogFile('application.log', self::APPLICATION_LOG, time() - 600);
        app(LogIndexer::class)->run();
        $fileId = LogIndexFile::query()->sole()->id;

        $this->actingAs($this->admin())
            ->postJson(route('admin.logs.truncate'), ['file' => 'application.log'])
            ->assertOk();

        $this->assertSame([$fileId], $index->purged);
        $this->assertSame(0, LogIndexFile::query()->count());
    }

    private function enableIndex(): FakeLogIndex
    {
        config(['nntmux.log_viewer.index.enabled' => true]);
        $index = new FakeLogIndex;
        $this->app->instance(LogIndex::class, $index);

        return $index;
    }

    private function admin(): Authenticatable
    {
        /** @var Authenticatable $admin */
        $admin = $this->createUserWithRole('Admin');

        return $admin;
    }

    private function auditHandler(): TestHandler
    {
        $handler = Log::channel('admin')->getLogger()->getHandlers()[0];
        $this->assertInstanceOf(TestHandler::class, $handler);

        return $handler;
    }

    private function createSchema(): void
    {

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->integer('rate_limit')->default(60);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('roles_id')->default(1);
            $table->string('api_token')->nullable();
            $table->boolean('verified')->default(true);
            $table->boolean('can_post')->default(true);
            $table->integer('rate_limit')->default(60);
            $table->string('theme_preference', 10)->default('light');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('lastlogin')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedInteger('role_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedInteger('permission_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->unsignedInteger('root_categories_id')->nullable();
            $table->integer('status')->default(1);
        });

        Schema::create('root_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->integer('status')->default(1);
        });

        Schema::create('user_excluded_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('categories_id');
        });

        Schema::create('content', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->string('url')->nullable();
            $table->text('body')->nullable();
            $table->text('metadescription')->nullable();
            $table->text('metakeywords')->nullable();
            $table->integer('contenttype')->default(1);
            $table->integer('status')->default(1);
            $table->integer('ordinal')->nullable();
            $table->integer('role')->default(0);
        });

        Schema::create('user_activities', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('username');
            $table->string('activity_type', 50);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        (require database_path('migrations/2026_10_08_224008_create_log_index_files_table.php'))->up();
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            [
                'name' => $roleName,
                'guard_name' => 'web',
            ],
            [
                'rate_limit' => 60,
            ]
        );

        $user = User::query()->create([
            'username' => strtolower($roleName).'_'.Str::random(8),
            'email' => Str::random(12).'@example.test',
            'password' => bcrypt('password'),
            'roles_id' => $role->id,
            'api_token' => Str::random(32),
            'verified' => true,
            'email_verified_at' => now(),
            'lastlogin' => now(),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function resetGlobalComposerState(): void
    {
        $reflection = new \ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * @param  list<string>  $lines
     */
    private function createLogFile(string $relativePath, array $lines, ?int $modifiedAt = null): string
    {
        $absolutePath = $this->logDirectory.'/'.$relativePath;

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, implode(PHP_EOL, $lines).PHP_EOL);
        touch($absolutePath, $modifiedAt ?? time());
        clearstatcache(true, $absolutePath);

        return $relativePath;
    }
}
