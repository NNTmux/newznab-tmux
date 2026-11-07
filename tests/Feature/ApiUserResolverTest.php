<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Api\ApiUserResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ApiUserResolverTest extends TestCase
{
    private const string TOKEN = 'unverified-user-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->string('email');
            $table->string('api_token');
            $table->unsignedInteger('roles_id')->default(1);
            $table->boolean('verified')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->softDeletes();
        });
        DB::table('roles')->insert(['id' => 1, 'name' => 'User']);
        DB::table('users')->insert(['username' => 'pending', 'email' => 'pending@example.test', 'api_token' => self::TOKEN]);
    }

    #[Test]
    public function a_v2_lookup_does_not_let_an_unverified_user_through_v1(): void
    {
        $resolver = new ApiUserResolver;

        $v2User = $resolver->v2(self::TOKEN);
        $v1User = $resolver->v1(self::TOKEN);

        $this->assertNotNull($v2User);
        $this->assertNull($v1User);
    }
}
