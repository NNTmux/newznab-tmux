<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\Google2FAMiddleware;
use App\Models\User;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use ReflectionClass;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Server-rendered coverage for the Group List control redesign (#18) and the
 * select-all markup contract the Alpine component depends on (#17).
 *
 * The client-side halves are covered where they can be: the pure selection
 * summary in `tests/js/group-selection.test.js` (Node's built-in runner), and
 * the markup contract here. There is deliberately no browser coverage of the
 * live viewport widths, selection transitions, or the color schemes — the
 * repository has no browser driver, and #17 rules out adding one for this fix.
 * Those checks stay manual.
 */
class AdminGroupListPageTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-admin-group-list-test', '.sqlite');

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES
            ('categorizeforeign', '0'),
            ('catwebdl', '0'),
            ('title', 'NNTmux Test'),
            ('home_link', '/')");

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'mail.from.address' => 'noreply@example.test',
            'mail.from.name' => 'NNTmux Tests',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'nntmux.items_per_page' => 2,
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        $this->createSchema();
        $this->seedSettings();
        $this->seedCategories();
        $this->resetGlobalComposerState();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware(Google2FAMiddleware::class);
    }

    protected function tearDown(): void
    {
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_group_list_renders_the_compact_action_bar(): void
    {
        $this->createGroups(3);

        $response = $this->actingAs($this->admin())->get(route('admin.group-list'));

        $response->assertOk();
        $response->assertSee('Search groups');
        $response->assertSee('aria-label="Search groups"', false);
        $response->assertSee('3</span> groups', false);
        $response->assertSee('Page 1/2');
        $response->assertSee('Maintenance');
        $response->assertSee('Reset All');
        $response->assertSee('Purge All');
    }

    public function test_search_submit_control_is_icon_only(): void
    {
        $this->createGroups(1);

        $response = $this->actingAs($this->admin())->get(route('admin.group-list'));

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '#<button type="submit"[^>]*aria-label="Search groups"[^>]*>\s*<i class="fas fa-search"[^>]*></i>\s*</button>#',
            (string) $response->getContent(),
            'The search submit button must render the magnifying-glass icon and no label text.'
        );
    }

    public function test_reset_selected_action_is_bound_to_the_live_selection_count(): void
    {
        $this->createGroups(1);

        $response = $this->actingAs($this->admin())->get(route('admin.group-list'));

        $response->assertOk();
        $response->assertSee('x-show="hasSelection"', false);
        $response->assertSee('x-text="selectedCount"', false);
        $response->assertSee(' selected', false);
    }

    public function test_select_all_checkbox_has_an_accessible_name_and_no_competing_model(): void
    {
        $this->createGroups(1);

        $response = $this->actingAs($this->admin())->get(route('admin.group-list'));

        $response->assertOk();
        $response->assertSee('aria-label="Select all groups on this page"', false);
        $response->assertSee('@change="toggleAllCheckboxes()"', false);
        $response->assertDontSee('x-model="allChecked"', false);
    }

    public function test_numbered_pagination_appears_only_below_the_table(): void
    {
        $this->createGroups(5);

        $response = $this->actingAs($this->admin())->get(route('admin.group-list'));

        $response->assertOk();
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'aria-label="Pagination Navigation"'),
            'The page must render exactly one numbered paginator.'
        );
    }

    public function test_pagination_links_preserve_the_group_name_search(): void
    {
        $this->createGroups(3, 'alt.binaries.match');
        $this->createGroups(1, 'alt.binaries.other');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.group-list', ['groupname' => 'match']));

        $response->assertOk();
        $response->assertSee('groupname=match', false);
        $response->assertSee('value="match"', false);
        $response->assertSee('3</span> groups', false);
    }

    public function test_zero_result_search_keeps_the_search_controls_editable(): void
    {
        $this->createGroups(2);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.group-list', ['groupname' => 'no-such-group']));

        $response->assertOk();
        $response->assertSee('No matching groups');
        $response->assertSee('Search groups');
        $response->assertSee('value="no-such-group"', false);
        $response->assertSee('Clear search');
        $response->assertSee('0</span> groups', false);
        $response->assertDontSee('Page 1/');
    }

    public function test_empty_group_table_without_a_search_shows_the_add_groups_state(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.group-list'));

        $response->assertOk();
        $response->assertSee('No groups available');
        $response->assertSee('Add Groups');
        $response->assertDontSee('Search groups');
    }

    public function test_reset_selected_groups_rejects_an_empty_payload(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.ajax'), [
            'action' => 'reset_selected_groups',
            'group_ids' => '[]',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['success' => false, 'message' => 'No groups specified']);
    }

    public function test_reset_selected_groups_is_closed_to_non_admins(): void
    {
        $user = $this->createUserWithRole('User');
        /** @var Authenticatable $authenticatedUser */
        $authenticatedUser = $user;

        $response = $this->actingAs($authenticatedUser)->post(route('admin.ajax'), [
            'action' => 'reset_selected_groups',
            'group_ids' => '[1]',
        ]);

        $response->assertForbidden();
    }

    public function test_group_list_exposes_edit_selected_values_and_modal(): void
    {
        $this->createGroups(1);

        $response = $this->actingAs($this->admin())->get(route('admin.group-list'));

        $response->assertOk();
        $response->assertSee('Edit <span x-text="selectedCount">0</span> selected', false);
        $response->assertSee('data-backfill-target="1"', false);
        $response->assertSee('data-min-files=""', false);
        $response->assertSee('data-min-size=""', false);
        $response->assertSee('data-active="1"', false);
        $response->assertSee('data-backfill="0"', false);
        $response->assertSee('Edit Selected Groups');
        $response->assertSee('Minimum File Size');
    }

    public function test_edit_selected_updates_only_submitted_settings_and_returns_rows(): void
    {
        $this->createGroups(2);
        $ids = DB::table('usenet_groups')->orderBy('id')->pluck('id')->all();
        $lastUpdated = DB::table('usenet_groups')->where('id', $ids[0])->value('last_updated');

        $response = $this->actingAs($this->admin())->post(route('admin.ajax'), [
            'action' => 'edit_selected_groups',
            'group_ids' => json_encode($ids),
            'changes' => json_encode([
                'backfill_target' => 30,
                'minsizetoformrelease' => '100M',
                'active' => 0,
            ]),
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'updated' => 2,
        ]);
        $response->assertJsonPath('rows.'.$ids[0], fn (string $row): bool => str_contains($row, 'id="grouprow-'.$ids[0].'"'));
        $response->assertJsonPath('rows.'.$ids[1], fn (string $row): bool => str_contains($row, 'id="grouprow-'.$ids[1].'"'));

        foreach ($ids as $id) {
            $group = DB::table('usenet_groups')->where('id', $id)->first();
            $this->assertSame(30, $group->backfill_target);
            $this->assertSame(104857600, $group->minsizetoformrelease);
            $this->assertSame(0, $group->active);
            $this->assertSame(0, $group->backfill, 'An omitted setting must remain untouched.');
            $this->assertNull($group->minfilestoformrelease, 'An omitted release floor must remain untouched.');
        }

        $this->assertSame($lastUpdated, DB::table('usenet_groups')->where('id', $ids[0])->value('last_updated'));
    }

    public function test_edit_selected_normalizes_zero_release_floors_to_null(): void
    {
        $this->createGroups(1);
        $id = DB::table('usenet_groups')->value('id');
        DB::table('usenet_groups')->where('id', $id)->update([
            'minfilestoformrelease' => 5,
            'minsizetoformrelease' => 1024,
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.ajax'), [
            'action' => 'edit_selected_groups',
            'group_ids' => json_encode([$id]),
            'changes' => json_encode([
                'minfilestoformrelease' => 0,
                'minsizetoformrelease' => '0',
            ]),
        ]);

        $response->assertOk();
        $this->assertNull(DB::table('usenet_groups')->where('id', $id)->value('minfilestoformrelease'));
        $this->assertNull(DB::table('usenet_groups')->where('id', $id)->value('minsizetoformrelease'));
    }

    public function test_edit_selected_rejects_invalid_or_nonsensical_requests(): void
    {
        $this->createGroups(1);
        $id = DB::table('usenet_groups')->value('id');
        $admin = $this->admin();

        foreach ([
            ['group_ids' => [$id], 'changes' => []],
            ['group_ids' => [$id], 'changes' => ['backfill_target' => 0]],
            ['group_ids' => [$id], 'changes' => ['backfill_target' => 7301]],
            ['group_ids' => [$id], 'changes' => ['minfilestoformrelease' => 3000000000]],
            ['group_ids' => [$id], 'changes' => ['minsizetoformrelease' => '10K']],
            ['group_ids' => [999999], 'changes' => ['active' => 1]],
            ['group_ids' => [$id], 'changes' => ['description' => 'tampered']],
        ] as $payload) {
            $response = $this->actingAs($admin)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])->post(route('admin.ajax'), [
                    'action' => 'edit_selected_groups',
                    'group_ids' => json_encode($payload['group_ids']),
                    'changes' => json_encode($payload['changes']),
                ]);

            $response->assertUnprocessable();
        }
    }

    public function test_edit_selected_is_closed_to_non_admins(): void
    {
        $user = $this->createUserWithRole('User');
        /** @var Authenticatable $authenticatedUser */
        $authenticatedUser = $user;

        $response = $this->actingAs($authenticatedUser)->post(route('admin.ajax'), [
            'action' => 'edit_selected_groups',
            'group_ids' => '[1]',
            'changes' => '{"active":1}',
        ]);

        $response->assertForbidden();
    }

    public function test_single_group_edit_accepts_the_same_file_size_grammar(): void
    {
        $this->createGroups(1);
        $group = DB::table('usenet_groups')->first();

        $response = $this->actingAs($this->admin())->post('/admin/group-edit?action=submit', [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'backfill_target' => 1,
            'first_record' => 0,
            'last_record' => 0,
            'active' => 1,
            'backfill' => 0,
            'minfilestoformrelease' => 0,
            'minsizetoformrelease' => '2.5G',
        ]);

        $response->assertRedirect('admin/group-list');
        $this->assertSame(2684354560, DB::table('usenet_groups')->where('id', $group->id)->value('minsizetoformrelease'));
        $this->assertNull(DB::table('usenet_groups')->where('id', $group->id)->value('minfilestoformrelease'));
    }

    private function admin(): Authenticatable
    {
        /** @var Authenticatable $admin */
        $admin = $this->createUserWithRole('Admin');

        return $admin;
    }

    private function createGroups(int $count, string $prefix = 'alt.binaries.group'): void
    {
        $rows = [];

        for ($index = 1; $index <= $count; $index++) {
            $rows[] = [
                'name' => $prefix.'.'.$index,
                'description' => 'Test group '.$index,
                'first_record_postdate' => '2024-01-01 00:00:00',
                'last_record_postdate' => '2024-06-01 00:00:00',
                'last_updated' => '2024-06-02 00:00:00',
                'active' => $index % 2,
                'backfill' => 0,
                'minfilestoformrelease' => null,
                'minsizetoformrelease' => null,
                'backfill_target' => 1,
            ];
        }

        DB::table('usenet_groups')->insert($rows);
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function createSchema(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->text('value')->nullable();
            });
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->integer('rate_limit')->default(60);
            $table->boolean('isdefault')->default(false);
            $table->unsignedInteger('defaultinvites')->default(0);
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
            $table->integer('rate_limit')->default(60);
            $table->string('api_token')->nullable();
            $table->boolean('verified')->default(true);
            $table->boolean('can_post')->default(true);
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

        Schema::create('root_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->unsignedInteger('root_categories_id')->nullable();
            $table->text('description')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('user_excluded_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('categories_id');
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

        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('first_record_postdate')->nullable();
            $table->string('last_record_postdate')->nullable();
            $table->string('last_updated')->nullable();
            $table->unsignedBigInteger('first_record')->default(0);
            $table->unsignedBigInteger('last_record')->default(0);
            $table->boolean('active')->default(false);
            $table->boolean('backfill')->default(false);
            $table->unsignedInteger('minfilestoformrelease')->nullable();
            $table->unsignedBigInteger('minsizetoformrelease')->nullable();
            $table->unsignedInteger('backfill_target')->default(1);
        });
    }

    private function seedSettings(): void
    {
        DB::table('settings')->upsert([
            ['name' => 'title', 'value' => 'NNTmux Test'],
            ['name' => 'home_link', 'value' => '/'],
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
        ], ['name'], ['value']);
    }

    private function seedCategories(): void
    {
        DB::table('root_categories')->insert([
            'id' => 1,
            'title' => 'General',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('categories')->insert([
            'id' => 1,
            'title' => 'General',
            'root_categories_id' => 1,
            'description' => 'General category',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
                'isdefault' => $roleName === 'User',
                'defaultinvites' => 1,
            ]
        );

        /** @var User $user */
        $user = User::withoutEvents(fn () => User::query()->create([
            'username' => strtolower($roleName).'_'.Str::random(8),
            'email' => Str::random(12).'@example.test',
            'password' => bcrypt('password'),
            'roles_id' => $role->id,
            'rate_limit' => 60,
            'api_token' => Str::random(32),
            'verified' => true,
            'email_verified_at' => now(),
            'lastlogin' => now(),
        ]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole($role);

        return $user->fresh();
    }

    private function resetGlobalComposerState(): void
    {
        $reflection = new ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');
        $property->setValue(null, null);
    }
}
