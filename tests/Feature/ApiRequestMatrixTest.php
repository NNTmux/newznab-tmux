<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class ApiRequestMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mail.from.address' => 'api-matrix@example.test',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();

        $this->createSchema();
        $this->seedData();
    }

    public function test_v1_invalid_sort_returns_xml_201_error(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $response = $this->get('/api/v1/api?t=search&apikey='.$token.'&q=test&sort=bad_value');

        $response->assertOk();
        $response->assertSee('<error code="201"', false);
        $response->assertSee('Incorrect parameter (sort', false);
    }

    public function test_v1_invalid_maxage_returns_xml_201_error(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $response = $this->get('/api/v1/api?t=search&apikey='.$token.'&q=test&maxage=abc');

        $response->assertOk();
        $response->assertSee('<error code="201"', false);
        $response->assertSee('maxage must be numeric', false);
    }

    public function test_v2_invalid_sort_returns_json_400_error(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $this->getJson('/api/v2/search?api_token='.$token.'&id=test&sort=bad_value')
            ->assertStatus(400)
            ->assertJsonPath('error', 'Incorrect parameter (sort must be one of: cat_asc/desc, name_asc/desc, size_asc/desc, files_asc/desc, stats_asc/desc, posted_asc/desc)');
    }

    public function test_v2_invalid_maxage_returns_json_400_error(): void
    {
        $token = (string) DB::table('users')->value('api_token');

        $this->getJson('/api/v2/search?api_token='.$token.'&id=test&maxage=abc')
            ->assertStatus(400)
            ->assertJsonPath('error', 'Incorrect parameter (maxage must be numeric)');
    }

    public function test_v1_caps_menu_data_includes_groups_and_genres(): void
    {
        $apiController = app(ApiController::class);
        $reflection = new ReflectionClass($apiController);
        $typeProperty = $reflection->getProperty('type');
        $typeProperty->setAccessible(true);
        $typeProperty->setValue($apiController, 'caps');

        $menu = $apiController->getForMenu();

        $this->assertSame('alt.binaries.test', $menu['groups'][0]['name']);
        $this->assertSame('Test Genre', $menu['genres'][0]['name']);
    }

    public function test_v2_capabilities_includes_groups_and_genres(): void
    {
        $this->getJson('/api/v2/capabilities')
            ->assertOk()
            ->assertJsonPath('groups.0.name', 'alt.binaries.test')
            ->assertJsonPath('genres.0.name', 'Test Genre');
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->integer('rate_limit')->default(60);
            $table->integer('apirequests')->default(1000);
            $table->integer('downloadrequests')->default(100);
            $table->integer('addyears')->default(0);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('roles_id')->default(1);
            $table->string('api_token')->nullable()->index();
            $table->string('host')->nullable();
            $table->timestamp('apiaccess')->nullable();
            $table->boolean('verified')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->integer('rate_limit')->default(60);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
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

        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('root_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->integer('status')->default(1);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->unsignedInteger('root_categories_id')->nullable();
            $table->integer('status')->default(1);
            $table->text('description')->nullable();
        });

        Schema::create('user_excluded_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('categories_id');
        });

        Schema::create('user_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->text('request')->nullable();
            $table->timestamp('timestamp')->nullable();
        });

        Schema::create('user_downloads', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->timestamp('timestamp')->nullable();
        });

        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->string('description')->nullable();
            $table->timestamp('last_updated')->nullable();
        });

        Schema::create('genres', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->integer('type')->default(3000);
            $table->boolean('disabled')->default(false);
        });

        Schema::create('registration_periods', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    private function seedData(): void
    {
        DB::table('settings')->insert([
            ['name' => 'strapline', 'value' => 'Test strapline'],
            ['name' => 'metakeywords', 'value' => 'test,api'],
            ['name' => 'registerstatus', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
            ['name' => 'title', 'value' => 'NNTmux Test'],
            ['name' => 'home_link', 'value' => '/'],
        ]);

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'User',
            'guard_name' => 'web',
            'rate_limit' => 60,
            'apirequests' => 1000,
            'downloadrequests' => 100,
            'addyears' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'username' => 'matrix_user',
            'email' => 'matrix@example.test',
            'password' => bcrypt('secret'),
            'roles_id' => 1,
            'api_token' => Str::random(32),
            'verified' => 1,
            'email_verified_at' => now(),
            'rate_limit' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('root_categories')->insert([
            'id' => 5000,
            'title' => 'TV',
            'status' => 1,
        ]);

        DB::table('categories')->insert([
            'id' => 5030,
            'title' => 'SD',
            'root_categories_id' => 5000,
            'status' => 1,
            'description' => 'TV SD',
        ]);

        DB::table('usenet_groups')->insert([
            'name' => 'alt.binaries.test',
            'active' => 1,
            'description' => 'Test usenet group',
            'last_updated' => now(),
        ]);

        DB::table('genres')->insert([
            'id' => 1,
            'title' => 'Test Genre',
            'type' => 3000,
            'disabled' => 0,
        ]);
    }
}
