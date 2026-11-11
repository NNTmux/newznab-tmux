<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\Google2FAMiddleware;
use App\Jobs\SendContactUsEmail;
use App\Models\User;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContactUsFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'mail.from.address' => 'staff@example.test',
            'captcha.recaptcha.enabled' => false,
            'captcha.turnstile.enabled' => false,
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
        $this->withoutMiddleware(Google2FAMiddleware::class);
    }

    #[Test]
    public function signed_in_users_see_their_account_email_and_an_optional_other_address(): void
    {
        $this->actingAs($this->member())->get(route('contact-us'))
            ->assertOk()
            ->assertSee('We will reply to')
            ->assertSee('member@example.test')
            ->assertSee('value="member"', false)
            ->assertSee('name="use_different_email"', false)
            ->assertDontSee('name="useremail" placeholder="Your email address"', false);
    }

    #[Test]
    public function the_site_header_shows_how_many_releases_are_in_the_signed_in_users_basket(): void
    {
        $member = $this->member();
        DB::table('users_releases')->insert([
            ['users_id' => $member->getAuthIdentifier(), 'releases_id' => 11],
            ['users_id' => $member->getAuthIdentifier(), 'releases_id' => 12],
            ['users_id' => 999, 'releases_id' => 13],
        ]);

        $this->actingAs($member)->get(route('contact-us'))
            ->assertOk()
            ->assertSeeInOrder(['Download basket items:', 'cart-count', '>2</span>'], false);
    }

    #[Test]
    public function guests_must_type_an_email(): void
    {
        $this->get(route('contact-us'))
            ->assertOk()
            ->assertDontSee('name="use_different_email"', false);

        $this->post(route('contact-us'), ['username' => 'Guest', 'comment' => 'Hello there, a question.'])
            ->assertSessionHasErrors('useremail');

        $this->post(route('contact-us'), ['username' => 'Guest', 'useremail' => 'guest@example.test', 'comment' => 'Hello there, a question.'])
            ->assertRedirect(route('contact-us'))
            ->assertSessionHas('success');

        $this->assertReplyTo('guest@example.test');
    }

    #[Test]
    public function signed_in_users_reply_from_their_account_email_by_default(): void
    {
        $this->actingAs($this->member())
            ->post(route('contact-us'), ['username' => 'member', 'useremail' => 'ignored', 'comment' => 'Hello there, a question.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contact-us'));

        $this->assertReplyTo('member@example.test');
    }

    #[Test]
    public function signed_in_users_can_reply_from_a_different_email(): void
    {
        $member = $this->member();

        $this->actingAs($member)
            ->post(route('contact-us'), ['username' => 'member', 'use_different_email' => '1', 'comment' => 'Hello there, a question.'])
            ->assertSessionHasErrors('useremail');
        Queue::assertNotPushed(SendContactUsEmail::class);

        $this->actingAs($member)
            ->post(route('contact-us'), ['username' => 'member', 'use_different_email' => '1', 'useremail' => 'other@example.test', 'comment' => 'Hello there, a question.'])
            ->assertSessionHasNoErrors();

        $this->assertReplyTo('other@example.test', 'Account: member (member@example.test)');
    }

    private function assertReplyTo(string $email, string $bodyContains = 'Hello there, a question.'): void
    {
        Queue::assertPushedOn('contactemail', SendContactUsEmail::class, function (SendContactUsEmail $job) use ($email, $bodyContains): bool {
            $state = (fn (): array => [$this->email, $this->mailTo, $this->mailBody])->call($job);

            return $state[0] === $email && $state[1] === 'staff@example.test' && str_contains($state[2], $bodyContains);
        });
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
    }
}
