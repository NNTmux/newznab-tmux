<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Services\Runners\PostProcessRunner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class PostProcessRunnerBooksGateTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-postprocess-books-gate.sqlite';

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
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('lookupbooks', '1'), ('postthreadsnon', '1')");

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

        DB::table('settings')->upsert([
            ['name' => 'lookupbooks', 'value' => '1'],
            ['name' => 'postthreadsnon', 'value' => '1'],
        ], ['name'], ['value']);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        DB::purge();
        DB::reconnect();

        $this->createSchema();
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

    public function test_process_books_treats_obfuscated_book_rows_as_pending_work(): void
    {
        DB::table('releases')->insert([
            'id' => 1,
            'name' => 'N_NZB_[2_5]_-_History_of_War_-_Issue_158_2026.rar',
            'searchname' => 'N_NZB_[2_5]_-_History_of_War_-_Issue_158_2026.rar',
            'groups_id' => 1,
            'size' => 1,
            'postdate' => now(),
            'adddate' => now(),
            'guid' => str_repeat('a', 40),
            'leftguid' => 'a',
            'fromname' => 'poster@example.com',
            'categories_id' => Category::BOOKS_MAGAZINES,
            'bookinfo_id' => -2,
        ]);

        $runner = new class extends PostProcessRunner
        {
            public array $captured = [];

            public function headerNone(): void {}

            protected function headerStart(string $workType, int $count, int $maxProcesses): void {}

            protected function executeCommand(string $command): string
            {
                $this->captured[] = $command;

                return '';
            }
        };

        $runner->processBooks();

        $this->assertCount(1, $runner->captured);
        $this->assertStringContainsString('artisan postprocess:guid books a', $runner->captured[0]);
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

        if (! Schema::hasTable('releases')) {
            Schema::create('releases', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name')->default('');
                $table->string('searchname')->default('');
                $table->unsignedInteger('groups_id')->default(0);
                $table->unsignedBigInteger('size')->default(0);
                $table->dateTime('postdate')->nullable();
                $table->dateTime('adddate')->nullable();
                $table->string('guid', 40);
                $table->char('leftguid', 1);
                $table->string('fromname')->nullable();
                $table->integer('categories_id')->default(Category::OTHER_MISC);
                $table->integer('bookinfo_id')->nullable();
            });
        }
    }
}
