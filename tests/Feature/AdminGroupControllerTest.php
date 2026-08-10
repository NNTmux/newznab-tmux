<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminGroupController;
use App\Models\Content;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PDO;
use ReflectionClass;
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
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        $this->createSchema();
        $this->seedSettings();
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
            $table->dateTime('last_updated')->nullable();
            $table->boolean('active')->default(false);
            $table->boolean('backfill')->default(false);
            $table->unsignedBigInteger('minsizetoformrelease')->nullable();
            $table->unsignedBigInteger('minfilestoformrelease')->nullable();
            $table->integer('backfill_target')->default(1);
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

    private function resetGlobalComposerState(): void
    {
        $reflection = new ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');
        $property->setValue(null, null);
    }
}
