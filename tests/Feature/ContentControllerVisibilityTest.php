<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Controllers\ContentController;
use App\Models\Content;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ContentControllerVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('content', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->default('');
            $table->integer('contenttype')->default(Content::TYPE_USEFUL);
            $table->integer('status')->default(Content::STATUS_ENABLED);
            $table->integer('ordinal')->nullable();
            $table->integer('role')->default(Content::ROLE_EVERYONE);
            $table->timestamps();
        });
        DB::table('content')->insert([
            ['id' => 1, 'title' => 'Front', 'status' => Content::STATUS_ENABLED, 'role' => Content::ROLE_EVERYONE, 'ordinal' => 1],
            ['id' => 2, 'title' => 'Public', 'status' => Content::STATUS_ENABLED, 'role' => Content::ROLE_EVERYONE, 'ordinal' => 2],
            ['id' => 3, 'title' => 'Admin only', 'status' => Content::STATUS_ENABLED, 'role' => Content::ROLE_ADMIN, 'ordinal' => 3],
            ['id' => 4, 'title' => 'Disabled', 'status' => Content::STATUS_DISABLED, 'role' => Content::ROLE_EVERYONE, 'ordinal' => 4],
        ]);
    }

    #[Test]
    public function content_list_hides_admin_only_and_disabled_pages_from_users(): void
    {
        $titles = $this->callController('getAllButFront', UserRole::USER->value)->pluck('title')->all();

        $this->assertSame(['Public'], $titles);
    }

    #[Test]
    public function content_list_shows_admin_only_pages_to_admins(): void
    {
        $titles = $this->callController('getAllButFront', UserRole::ADMIN->value)->pluck('title')->all();

        $this->assertSame(['Public', 'Admin only'], $titles);
    }

    #[Test]
    public function a_disabled_page_cannot_be_opened_by_id(): void
    {
        $this->assertNull($this->callController('getContentById', 4, UserRole::USER->value));
        $this->assertSame('Public', $this->callController('getContentById', 2, UserRole::USER->value)?->title);
    }

    private function callController(string $method, int ...$arguments): mixed
    {
        $controller = (new \ReflectionClass(ContentController::class))->newInstanceWithoutConstructor();

        return (new \ReflectionMethod($controller, $method))->invoke($controller, ...$arguments);
    }
}
