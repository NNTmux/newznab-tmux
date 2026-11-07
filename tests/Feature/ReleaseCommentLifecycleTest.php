<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\DetailsController;
use App\Models\ReleaseComment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PDO;
use Tests\TestCase;

final class ReleaseCommentLifecycleTest extends TestCase
{
    private string $databasePath = '';

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-release-comments.sqlite';

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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->databasePath]);
        DB::purge();
        DB::reconnect();
        DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, username VARCHAR(255), deleted_at DATETIME NULL)');
        DB::statement('CREATE TABLE releases (id INTEGER PRIMARY KEY, comments INTEGER DEFAULT 0)');
        DB::statement('CREATE TABLE release_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT, releases_id INTEGER NOT NULL, text VARCHAR(2000),
            isvisible INTEGER DEFAULT 1, username VARCHAR(255), users_id INTEGER,
            created_at DATETIME, updated_at DATETIME, host VARCHAR(45)
        )');
        DB::table('users')->insert(['id' => 7, 'username' => 'commenter']);
        DB::table('releases')->insert(['id' => 10, 'comments' => 99]);
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
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

    public function test_add_delete_and_visible_only_recount_use_release_id(): void
    {
        $commentId = ReleaseComment::addComment(10, 'Visible comment', 7, '127.0.0.1');
        DB::table('release_comments')->insert([
            'releases_id' => 10,
            'text' => 'Hidden comment',
            'isvisible' => 0,
            'username' => 'commenter',
            'users_id' => 7,
        ]);
        ReleaseComment::updateReleaseCommentCount(10);

        $this->assertSame(1, (int) DB::table('releases')->where('id', 10)->value('comments'));
        $this->assertDatabaseHas('release_comments', [
            'id' => $commentId,
            'releases_id' => 10,
            'text' => 'Visible comment',
        ]);

        ReleaseComment::deleteComment($commentId);

        $this->assertSame(0, (int) DB::table('releases')->where('id', 10)->value('comments'));
        $this->assertDatabaseMissing('release_comments', ['id' => $commentId]);
    }

    public function test_comment_ip_is_not_stored_when_ip_storage_is_off(): void
    {
        config(['nntmux_settings.store_user_ip' => false]);

        $commentId = ReleaseComment::addComment(10, 'Private comment', 7, '203.0.113.9');

        $this->assertSame('', DB::table('release_comments')->where('id', $commentId)->value('host'));
    }

    public function test_users_without_posting_rights_cannot_comment(): void
    {
        $response = $this->postComment($this->commenter(canPost: false), 'Blocked comment');

        $this->assertSame('You are not allowed to post comments.', $response->getSession()?->get('error'));
        $this->assertSame(0, DB::table('release_comments')->count());
    }

    public function test_overlong_comments_are_rejected(): void
    {
        $response = $this->postComment($this->commenter(), str_repeat('a', 2001));

        $this->assertStringStartsWith('Comments must be between', (string) $response->getSession()?->get('error'));
        $this->assertSame(0, DB::table('release_comments')->count());
    }

    public function test_comment_posting_is_rate_limited_per_user(): void
    {
        $user = $this->commenter();
        RateLimiter::clear('release-comment:'.$user->id);

        for ($comment = 1; $comment <= 6; $comment++) {
            $response = $this->postComment($user, "Comment {$comment}");
        }

        $this->assertSame(5, DB::table('release_comments')->count());
        $this->assertStringContainsString('too quickly', (string) $response->getSession()?->get('error'));
    }

    private function commenter(bool $canPost = true): User
    {
        $user = new User;
        $user->id = 7;
        $user->can_post = $canPost;

        return $user;
    }

    private function postComment(User $user, string $text): RedirectResponse
    {
        $controller = (new \ReflectionClass(DetailsController::class))->newInstanceWithoutConstructor();
        $controller->userdata = $user;
        $request = Request::create('/details/guid-1', 'POST', ['txtAddComment' => $text]);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        return (new \ReflectionMethod($controller, 'postComment'))->invoke($controller, $request, 10, 'guid-1');
    }

    public function test_details_controller_submission_no_longer_reads_or_passes_gid(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/DetailsController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString('$this->postComment($request, (int) $data[\'id\'], $guid)', $controller);
        $this->assertStringNotContainsString("\$data['gid']", $controller);
    }
}
