<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ReleaseReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReleaseReportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        Cache::flush();

        Schema::dropIfExists('settings');
        Schema::dropIfExists('release_reports');
        Schema::dropIfExists('releases');

        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
        });

        Schema::create('release_reports', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('releases_id');
            $table->unsignedInteger('users_id');
            $table->string('reason', 255);
            $table->text('description')->nullable();
            $table->string('status', 255);
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_store_accepts_a_numeric_string_release_id(): void
    {
        $this->mockAuthenticatedUserId();
        $releaseId = $this->createRelease();

        $response = $this->postJson(route('release-report.store'), [
            'release_id' => (string) $releaseId,
            'reason' => 'spam',
            'description' => 'Runtime regression coverage for numeric string ids.',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('release_reports', [
            'releases_id' => $releaseId,
            'users_id' => 1,
            'reason' => 'spam',
            'status' => 'pending',
        ]);
    }

    public function test_check_reported_accepts_a_numeric_string_release_id(): void
    {
        $this->mockAuthenticatedUserId();
        $releaseId = $this->createRelease();

        ReleaseReport::query()->create([
            'releases_id' => $releaseId,
            'users_id' => 1,
            'reason' => 'duplicate',
            'description' => null,
            'status' => 'pending',
        ]);

        $response = $this->getJson(route('release-report.check', [
            'release_id' => (string) $releaseId,
        ]));

        $response
            ->assertOk()
            ->assertJson([
                'has_reported' => true,
            ]);
    }

    private function mockAuthenticatedUserId(int $userId = 1): void
    {
        Auth::shouldReceive('check')->andReturn(false);
        Auth::shouldReceive('id')->andReturn($userId);
    }

    private function createRelease(int $id = 1): int
    {
        DB::table('releases')->insert([
            'id' => $id,
        ]);

        return $id;
    }
}
