<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminGroupController;
use App\Http\Middleware\Google2FAMiddleware;
use App\Http\Requests\Admin\AdminGroupListRequest;
use App\Models\Content;
use App\Models\UsenetGroup;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class AdminGroupControllerTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-admin-group-test.sqlite';

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);

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
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        $this->createSchema();
        $this->resetGlobalComposerState();
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

    public function test_create_bulk_casts_string_flags_before_calling_add_bulk(): void
    {
        $request = Request::create('/admin/group-bulk', 'POST', [
            'action' => 'submit',
            'groupfilter' => " \n",
            'active' => '1',
            'backfill' => '0',
        ]);

        $response = app(AdminGroupController::class)->createBulk($request);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('admin.groups.bulk', $response->name());
        $this->assertSame('Bulk Add Newsgroups', $response->getData()['title']);
        $this->assertSame('No group list provided.', $response->getData()['groupmsglist']);
    }

    public function test_edit_submit_converts_min_size_unit_to_bytes(): void
    {
        $this->createUsenetGroupsTable();
        $groupId = DB::table('usenet_groups')->insertGetId([
            'name' => 'alt.binaries.test',
            'description' => 'Test group',
            'active' => 1,
            'backfill' => 1,
            'minsizetoformrelease' => null,
            'minfilestoformrelease' => null,
        ]);

        $request = Request::create('/admin/group-edit', 'POST', [
            'action' => 'submit',
            'id' => (string) $groupId,
            'name' => 'alt.binaries.test',
            'description' => 'Test group',
            'backfill_target' => '1',
            'first_record' => '0',
            'last_record' => '0',
            'active' => '1',
            'backfill' => '1',
            'minsizetoformrelease' => '2',
            'minsizetoformrelease_unit' => 'GB',
            'minfilestoformrelease' => '1',
        ]);

        $response = app(AdminGroupController::class)->edit($request);

        $this->assertTrue($response->isRedirect());
        $this->assertSame(2147483648, (int) DB::table('usenet_groups')->where('id', $groupId)->value('minsizetoformrelease'));
    }

    public function test_edit_submit_keeps_blank_min_size_as_null(): void
    {
        $this->createUsenetGroupsTable();
        $groupId = DB::table('usenet_groups')->insertGetId([
            'name' => 'alt.binaries.test',
            'description' => 'Test group',
            'active' => 1,
            'backfill' => 1,
            'minsizetoformrelease' => 1048576,
            'minfilestoformrelease' => null,
        ]);

        $request = Request::create('/admin/group-edit', 'POST', [
            'action' => 'submit',
            'id' => (string) $groupId,
            'name' => 'alt.binaries.test',
            'description' => 'Test group',
            'backfill_target' => '1',
            'first_record' => '0',
            'last_record' => '0',
            'active' => '1',
            'backfill' => '1',
            'minsizetoformrelease' => '',
            'minsizetoformrelease_unit' => 'MB',
            'minfilestoformrelease' => '1',
        ]);

        app(AdminGroupController::class)->edit($request);

        $this->assertNull(DB::table('usenet_groups')->where('id', $groupId)->value('minsizetoformrelease'));
    }

    public function test_edit_view_exposes_group_min_size_split_into_value_and_unit(): void
    {
        $this->createUsenetGroupsTable();
        $groupId = DB::table('usenet_groups')->insertGetId([
            'name' => 'alt.binaries.test',
            'description' => 'Test group',
            'active' => 1,
            'backfill' => 1,
            'minsizetoformrelease' => 2147483648,
            'minfilestoformrelease' => null,
        ]);

        $request = Request::create('/admin/group-edit', 'GET', ['id' => (string) $groupId]);

        $response = app(AdminGroupController::class)->edit($request);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame(['value' => 2, 'unit' => 'GB'], $response->getData()['groupMinSize']);
        $this->assertSame(['MB', 'GB'], $response->getData()['sizeUnits']);
    }

    public function test_group_list_counts_each_groups_releases(): void
    {
        $this->createUsenetGroupsTable();
        $this->createReleasesTable();
        $busyId = DB::table('usenet_groups')->insertGetId(['name' => 'alt.binaries.boneless', 'active' => 1]);
        $quietId = DB::table('usenet_groups')->insertGetId(['name' => 'alt.binaries.boneless.nl', 'active' => 1]);
        $emptyId = DB::table('usenet_groups')->insertGetId(['name' => 'alt.binaries.empty', 'active' => 1]);
        DB::table('releases')->insert([
            ['groups_id' => $busyId], ['groups_id' => $busyId], ['groups_id' => $busyId], ['groups_id' => $quietId],
        ]);

        $groups = collect(UsenetGroup::getGroupsRange('', null, true)->items())->keyBy('id');

        $this->assertSame(3, $groups[$busyId]->num_releases);
        $this->assertSame(1, $groups[$quietId]->num_releases);
        $this->assertSame(0, $groups[$emptyId]->num_releases);
        $this->assertNull(UsenetGroup::getGroupsRange()->items()[0]->num_releases);
    }

    #[DataProvider('columnSorts')]
    public function test_group_list_sorts_all_data_columns_before_pagination(string $sort, array $ascending, array $descending): void
    {
        $this->seedListGroups();
        config(['nntmux.items_per_page' => 2]);

        foreach (['asc' => $ascending, 'desc' => $descending] as $direction => $expected) {
            $firstPage = $this->groupListView(['sort' => $sort, 'direction' => $direction]);
            $secondPage = $this->groupListView(['sort' => $sort, 'direction' => $direction, 'page' => 2]);
            $actual = collect($firstPage->getData()['grouplist']->items())
                ->merge($secondPage->getData()['grouplist']->items())->pluck('name')->all();

            $this->assertSame($expected, $actual, $sort.' '.$direction);
        }
    }

    public static function columnSorts(): array
    {
        $numericAscending = ['alt.binaries.c', 'alt.binaries.b', 'alt.binaries.a'];
        $numericDescending = ['alt.binaries.a', 'alt.binaries.b', 'alt.binaries.c'];

        return [
            'group' => ['name', $numericDescending, $numericAscending],
            'first post' => ['first_post', $numericAscending, $numericDescending],
            'last post' => ['last_post', $numericAscending, $numericDescending],
            'last updated' => ['last_updated', $numericAscending, $numericDescending],
            'status' => ['active', ['alt.binaries.c', 'alt.binaries.a', 'alt.binaries.b'], $numericDescending],
            'backfill' => ['backfill', ['alt.binaries.c', 'alt.binaries.a', 'alt.binaries.b'], $numericDescending],
            'releases' => ['releases', $numericAscending, $numericDescending],
            'min files' => ['min_files', $numericAscending, $numericDescending],
            'min size' => ['min_size', $numericAscending, $numericDescending],
            'backfill days' => ['backfill_days', $numericAscending, $numericDescending],
        ];
    }

    #[DataProvider('columnFilters')]
    public function test_group_list_filters_each_data_column(array $filters, array $expected): void
    {
        $this->seedListGroups();

        $page = $this->groupListView($filters)->getData()['grouplist'];

        $this->assertSame($expected, collect($page->items())->pluck('name')->all());
        $this->assertSame(count($expected), $page->total());
    }

    public static function columnFilters(): array
    {
        $middle = ['alt.binaries.b'];

        return [
            'group' => [['groupname' => '  binaries.b  '], $middle],
            'first post' => [['first_post_from' => '2026-10-12', 'first_post_to' => '2026-10-12'], $middle],
            'last post' => [['last_post_from' => '2026-10-12', 'last_post_to' => '2026-10-12'], $middle],
            'last updated' => [['last_updated_from' => '2026-10-12', 'last_updated_to' => '2026-10-12'], $middle],
            'status' => [['active' => '0'], ['alt.binaries.c']],
            'backfill' => [['backfill' => '0'], ['alt.binaries.c']],
            'releases' => [['releases_min' => '3', 'releases_max' => '8'], $middle],
            'min files' => [['min_files_min' => '3', 'min_files_max' => '8'], $middle],
            'min size in MB' => [['min_size_min' => '4.5', 'min_size_max' => '5.5'], $middle],
            'backfill days' => [['backfill_days_min' => '3', 'backfill_days_max' => '8'], $middle],
            'upper bound only' => [['releases_max' => '5'], ['alt.binaries.b', 'alt.binaries.c']],
            'combined filters' => [['active' => '1', 'backfill' => '1', 'releases_min' => '3', 'min_files_max' => '8'], $middle],
        ];
    }

    public function test_filters_and_sort_survive_pagination_and_header_links(): void
    {
        $this->seedListGroups();
        config(['nntmux.items_per_page' => 1]);
        $parameters = ['groupname' => 'binaries', 'active' => '1', 'backfill' => '1', 'releases_min' => '3', 'sort' => 'releases', 'direction' => 'desc'];
        $view = $this->groupListView($parameters + ['page' => 2]);
        $page = $view->getData()['grouplist'];
        parse_str(parse_url($page->url(1), PHP_URL_QUERY), $query);

        $this->assertEquals($parameters + ['page' => '1'], $query);
        $this->assertSame(['alt.binaries.b'], collect($page->items())->pluck('name')->all());

        $html = $view->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $headers = $xpath->query('//th/a');
        $this->assertCount(10, $headers);
        foreach ($headers as $header) {
            parse_str(parse_url($header->getAttribute('href'), PHP_URL_QUERY), $headerQuery);
            $this->assertArrayNotHasKey('page', $headerQuery);
            $this->assertSame('3', $headerQuery['releases_min']);
            $this->assertSame('1', $headerQuery['active']);
            if ($headerQuery['sort'] === 'releases') {
                $this->assertSame('asc', $headerQuery['direction']);
                $this->assertSame('descending', $header->parentNode->getAttribute('aria-sort'));
            }
        }
        $this->assertStringContainsString('name="sort" value="releases"', $html);
        $this->assertStringContainsString('name="direction" value="desc"', $html);
    }

    public function test_empty_filtered_list_still_renders_filter_controls(): void
    {
        $this->seedListGroups();

        $html = $this->groupListView(['releases_min' => '100'])->render();

        $this->assertStringContainsString('name="groupname"', $html);
        $this->assertStringContainsString('name="releases_min"', $html);
        $this->assertStringContainsString('Clear filters', $html);
        $this->assertStringContainsString('No groups match', $html);
    }

    public function test_invalid_range_redirect_preserves_the_submitted_filter_form(): void
    {
        $this->seedListGroups();
        config(['nntmux.items_per_page' => 1]);
        $this->withoutMiddleware([
            Authenticate::class,
            Google2FAMiddleware::class,
            RoleMiddleware::class,
        ]);
        $previousUrl = route('admin.group-list', ['sort' => 'releases', 'direction' => 'desc']);
        $parameters = [
            'groupname' => 'binaries.b', 'active' => '1', 'backfill' => '1',
            'first_post_from' => '2026-10-12', 'releases_min' => '10', 'releases_max' => '2',
            'min_size_min' => '4.5', 'sort' => 'releases', 'direction' => 'desc',
        ];

        $this->from($previousUrl)->get(route('admin.group-list', $parameters))
            ->assertRedirect($previousUrl)->assertSessionHasErrors('releases_max');
        $response = $this->get($previousUrl)->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);

        foreach (['groupname', 'first_post_from', 'releases_min', 'releases_max', 'min_size_min', 'sort', 'direction'] as $field) {
            $this->assertSame($parameters[$field], $xpath->query('//input[@name="'.$field.'"]')->item(0)->getAttribute('value'), $field);
        }
        foreach (['active', 'backfill'] as $field) {
            $this->assertSame('1', $xpath->query('//select[@name="'.$field.'"]/option[@selected]')->item(0)->getAttribute('value'));
        }
        $this->assertCount(1, $xpath->query('//details[@open]'));
        foreach ($xpath->query('//th/a') as $header) {
            parse_str(parse_url($header->getAttribute('href'), PHP_URL_QUERY), $headerQuery);
            $this->assertArrayNotHasKey('releases_min', $headerQuery);
        }
    }

    public function test_zero_release_and_unconfigured_minimum_filters_include_empty_groups(): void
    {
        $this->seedListGroups();
        DB::table('usenet_groups')->insert(['name' => 'alt.binaries.empty']);

        $page = $this->groupListView(['releases_max' => '0', 'min_files_max' => '0', 'min_size_max' => '0'])->getData()['grouplist'];

        $this->assertSame(['alt.binaries.empty'], collect($page->items())->pluck('name')->all());
        $this->assertSame(0, $page->items()[0]->num_releases);
    }

    public function test_active_and_inactive_routes_keep_their_status_scope(): void
    {
        $this->seedListGroups();

        $active = $this->groupListView(['active' => '0', 'sort' => 'releases', 'direction' => 'desc'], 'active')->getData()['grouplist'];
        $inactive = $this->groupListView(['active' => '1', 'backfill' => '0'], 'inactive')->getData()['grouplist'];

        $this->assertSame(['alt.binaries.a', 'alt.binaries.b'], collect($active->items())->pluck('name')->all());
        $this->assertSame(['alt.binaries.c'], collect($inactive->items())->pluck('name')->all());
    }

    #[DataProvider('invalidListInputs')]
    public function test_group_list_rejects_invalid_sort_and_filters(array $parameters, string $field): void
    {
        $this->seedListGroups();

        try {
            $this->groupListView($parameters);
            $this->fail('Expected invalid list parameters to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public static function invalidListInputs(): array
    {
        return [
            'sort injection' => [['sort' => 'name desc; DROP TABLE releases'], 'sort'],
            'direction injection' => [['direction' => 'desc; SELECT 1'], 'direction'],
            'array status' => [['active' => ['1']], 'active'],
            'invalid backfill' => [['backfill' => '2'], 'backfill'],
            'negative releases' => [['releases_min' => '-1'], 'releases_min'],
            'fractional files' => [['min_files_min' => '1.5'], 'min_files_min'],
            'inverted range' => [['releases_min' => '10', 'releases_max' => '2'], 'releases_max'],
            'invalid date' => [['first_post_from' => '2026-02-31'], 'first_post_from'],
            'inverted dates' => [['last_post_from' => '2026-10-15', 'last_post_to' => '2026-10-12'], 'last_post_to'],
        ];
    }

    private function seedListGroups(): void
    {
        $this->createUsenetGroupsTable();
        $this->createReleasesTable();
        foreach ([['c', 2, 0, '10'], ['a', 10, 1, '15'], ['b', 5, 1, '12']] as [$suffix, $number, $enabled, $day]) {
            $id = DB::table('usenet_groups')->insertGetId([
                'name' => 'alt.binaries.'.$suffix,
                'active' => $enabled,
                'backfill' => $enabled,
                'first_record_postdate' => '2026-10-'.$day.' 00:00:00',
                'last_record_postdate' => '2026-10-'.$day.' 23:59:59',
                'last_updated' => '2026-10-'.$day.' 12:00:00',
                'minfilestoformrelease' => $number,
                'minsizetoformrelease' => $number * 1048576,
                'backfill_target' => $number,
            ]);
            DB::table('releases')->insert(array_fill(0, $number, ['groups_id' => $id]));
        }
    }

    private function groupListView(array $parameters, string $method = 'index'): View
    {
        $request = AdminGroupListRequest::create('/admin/group-list', 'GET', $parameters);
        $request->setContainer($this->app)->setRedirector(app('redirect'));
        $this->app->instance('request', $request);
        app('url')->setRequest($request);
        $request->validateResolved();

        return app(AdminGroupController::class)->{$method}($request);
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

        if (! Schema::hasTable('content')) {
            Schema::create('content', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title')->default('');
                $table->string('url', 2000)->nullable();
                $table->text('body')->nullable();
                $table->string('metadescription', 1000)->default('');
                $table->string('metakeywords', 1000)->default('');
                $table->integer('contenttype')->default(Content::TYPE_USEFUL);
                $table->integer('status')->default(Content::STATUS_ENABLED);
                $table->integer('ordinal')->nullable();
                $table->integer('role')->default(Content::ROLE_EVERYONE);
            });
        }
    }

    private function createUsenetGroupsTable(): void
    {
        if (Schema::hasTable('usenet_groups')) {
            return;
        }

        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->default('');
            $table->string('description')->default('');
            $table->unsignedBigInteger('first_record')->default(0);
            $table->unsignedBigInteger('last_record')->default(0);
            $table->dateTime('first_record_postdate')->nullable();
            $table->dateTime('last_record_postdate')->nullable();
            $table->dateTime('last_updated')->nullable();
            $table->boolean('active')->default(false);
            $table->boolean('backfill')->default(false);
            $table->unsignedBigInteger('minsizetoformrelease')->nullable();
            $table->unsignedBigInteger('minfilestoformrelease')->nullable();
            $table->integer('backfill_target')->default(1);
        });
    }

    private function createReleasesTable(): void
    {
        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('groups_id')->index();
        });
    }

    private function resetGlobalComposerState(): void
    {
        $reflection = new ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');
        $property->setValue(null, null);
    }
}
