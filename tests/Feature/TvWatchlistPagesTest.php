<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ClearanceMiddleware;
use App\Http\Middleware\Google2FAMiddleware;
use App\Models\Category;
use App\Models\User;
use App\Services\Releases\ReleaseBrowseService;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TvWatchlistPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();
        Queue::fake();

        $this->createSchema();
        $property = (new \ReflectionClass(GlobalDataComposer::class))->getProperty('resolvedData');
        $property->setValue(null, null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware([Google2FAMiddleware::class, ClearanceMiddleware::class]);
    }

    #[Test]
    public function my_show_releases_render_inside_a_single_layout(): void
    {
        $this->mock(ReleaseBrowseService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getBrowseOrdering')->andReturn(['posted_desc']);
            $mock->shouldReceive('getShowsRange')->andReturn([]);
        });

        $html = $this->actingAs($this->member())->get(route('myshows.browse'))
            ->assertOk()
            ->assertSee('My show releases')
            ->assertSee('No releases yet')
            ->getContent();

        $this->assertSame(1, substr_count(strtolower((string) $html), '<!doctype html>'), 'The page must not nest a second layout.');
        $this->assertSame(1, substr_count((string) $html, '<header'.' class="app-page-header'), 'Only one page header is rendered.');
    }

    #[Test]
    public function my_shows_lists_followed_shows_without_repeating_the_header_actions(): void
    {
        $member = $this->member();
        DB::table('root_categories')->insert(['id' => Category::TV_ROOT, 'title' => 'TV']);
        DB::table('videos')->insert(['id' => 7, 'title' => 'Severance', 'started' => '2022-02-18']);
        DB::table('tv_info')->insert(['videos_id' => 7, 'summary' => 'Office workers have their memories surgically divided.', 'publisher' => 'Apple TV+', 'image' => 1]);
        DB::table('user_series')->insert(['users_id' => $member->getAuthIdentifier(), 'videos_id' => 7, 'categories' => '', 'created_at' => now()]);

        $html = $this->actingAs($member)->get(route('myshows'))
            ->assertOk()
            ->assertSee('Severance')
            ->assertSee('aria-label="Edit categories for Severance"', false)
            ->assertSee('Apple TV+ · Since 2022')
            ->assertSee('Office workers have their memories surgically divided.')
            ->assertSee('alt="Severance cover"', false)
            ->assertSee('href="'.route('series').'"', false)
            ->getContent();

        $this->assertSame(1, substr_count((string) $html, 'href="'.route('myshows.browse').'"'), 'The releases link appears once, in the header.');
        $this->assertStringNotContainsString('max-w-7xl mx-auto', (string) $html);
    }

    #[Test]
    public function adding_a_show_opens_my_shows(): void
    {
        $member = $this->member();
        DB::table('root_categories')->insert(['id' => Category::TV_ROOT, 'title' => 'TV']);
        DB::table('videos')->insert(['id' => 7, 'title' => 'Severance']);
        DB::table('tv_info')->insert(['videos_id' => 7]);

        $this->actingAs($member)
            ->post('/myshows?action=doadd', ['id' => 7, 'from' => '/series/S'])
            ->assertRedirect(route('myshows'));

        $this->assertSame(1, DB::table('user_series')->where('users_id', $member->getAuthIdentifier())->where('videos_id', 7)->count());
    }

    #[Test]
    public function the_series_list_marks_followed_shows_and_the_current_letter(): void
    {
        $this->actingAs($this->member());

        $html = view('series.viewserieslist', [
            'serieslist' => ['S' => [
                ['id' => 7, 'title' => 'Severance', 'userseriesid' => 3, 'publisher' => 'Apple TV+', 'tvmaze' => 44933],
                ['id' => 8, 'title' => 'Shogun', 'userseriesid' => null],
            ]],
            'seriesrange' => range('A', 'Z'),
            'seriesletter' => 'S',
            'showname' => '',
            'site' => (object) ['dereferrerLink' => ''],
        ])->render();

        $this->assertStringContainsString('2 series', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*/series/S"\s+aria-current="page"#', $html);
        $this->assertStringContainsString('Following', $html);
        $this->assertStringContainsString('aria-label="Remove Severance from My shows"', $html);
        $this->assertMatchesRegularExpression('#myshows\?action=add&amp;id=8[^"]*"[^>]*>.*?Follow#s', $html);
        $this->assertStringContainsString('tvmaze.com/shows/44933', $html);
        $this->assertSame(1, substr_count($html, 'href="'.route('myshows.browse').'"'), 'Actions are not repeated below the header.');
    }

    private function member(): Authenticatable
    {
        $role = Role::query()->firstOrCreate(['name' => 'User', 'guard_name' => 'web'], ['rate_limit' => 60]);

        /** @var User $user */
        $user = User::query()->create([
            'username' => 'member',
            'email' => 'member@example.test',
            'password' => bcrypt('password'),
            'roles_id' => $role->id,
            'api_token' => str_repeat('a', 32),
            'verified' => true,
            'email_verified_at' => now(),
            'lastlogin' => now(),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
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
        Schema::create('users_releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('releases_id');
            $table->timestamps();
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
        Schema::create('videos', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->date('started')->nullable();
        });
        Schema::create('tv_info', function (Blueprint $table): void {
            $table->unsignedInteger('videos_id')->primary();
            $table->text('summary')->nullable();
            $table->string('publisher')->nullable();
            $table->boolean('image')->default(false);
        });
        Schema::create('user_series', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('videos_id');
            $table->string('categories')->nullable();
            $table->timestamps();
        });
    }
}
