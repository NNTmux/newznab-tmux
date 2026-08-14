<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ReleaseComment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

final class ReleaseCommentLifecycleTest extends TestCase
{
    private string $databasePath = '';

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-release-comments.sqlite';
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings VALUES ('categorizeforeign', '0'), ('catwebdl', '0')");
        putenv('APP_ENV=testing');
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE='.$this->databasePath);
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
        $_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $this->databasePath;
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

    public function test_details_controller_submission_no_longer_reads_or_passes_gid(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/DetailsController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString('ReleaseComment::addComment((int) $data[\'id\']', $controller);
        $this->assertStringNotContainsString("\$data['gid']", $controller);
    }
}
