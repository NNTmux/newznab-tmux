<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\AdditionalProcessing\AdditionalCandidateQuery;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use ReflectionProperty;

/**
 * File-backed SQLite database with the release, predb and group columns the guarded rename paths read.
 */
trait ReleaseRenameDatabase
{
    private string $databasePath = '';

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-release-rename-'.getmypid().'-'.str_replace('\\', '-', static::class).'.sqlite';
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
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('categorizeforeign', '0'), ('catwebdl', '0')");
        $this->setReleaseRenameEnvironmentValue('APP_ENV', 'testing');
        $this->setReleaseRenameEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setReleaseRenameEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function createReleaseRenameSchema(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'nntmux.echocli' => false,
        ]);
        DB::purge();
        DB::reconnect();
        (new ReflectionProperty(AdditionalCandidateQuery::class, 'supportsClaims'))->setValue(null, null);

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('guid', 40);
            $table->char('leftguid', 1)->default('a');
            $table->string('name')->default('');
            $table->string('searchname')->default('');
            $table->string('fromname')->nullable();
            $table->unsignedInteger('groups_id')->default(1);
            $table->integer('categories_id')->default(10);
            $table->unsignedInteger('predb_id')->default(0);
            $table->unsignedBigInteger('size')->default(0);
            $table->dateTime('postdate')->nullable();
            $table->tinyInteger('isrenamed')->default(0);
            $table->tinyInteger('iscategorized')->default(0);
            $table->tinyInteger('proc_par2')->default(0);
            $table->smallInteger('haspreview')->default(0);
            $table->smallInteger('passwordstatus')->default(0);
            $table->tinyInteger('nzbstatus')->default(1);
            $table->unsignedInteger('rarinnerfilecount')->default(0);
            $table->unsignedInteger('videos_id')->default(0);
            $table->integer('tv_episodes_id')->default(0);
            $table->unsignedTinyInteger('tv_lookup_attempts')->default(0);
            $table->dateTime('tv_lookup_retry_at')->nullable();
            $table->string('imdbid')->nullable();
            $table->string('musicinfo_id')->nullable();
            $table->string('consoleinfo_id')->nullable();
            $table->string('bookinfo_id')->nullable();
            $table->unsignedTinyInteger('book_lookup_attempts')->default(0);
            $table->dateTime('book_lookup_retry_at')->nullable();
            $table->dateTime('book_name_normalized_at')->nullable();
            $table->string('anidbid')->nullable();
            $table->unsignedInteger('movieinfo_id')->nullable();
            $table->unsignedInteger('gamesinfo_id')->nullable();
            $table->tinyInteger('proc_pp')->default(0);
            $table->timestamp('additional_pp_claimed_at')->nullable();
            $table->string('additional_pp_claim_token', 64)->nullable();
        });
        Schema::create('predb', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->unique();
        });
        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->unique();
        });
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.binaries.test']);
    }

    protected function removeReleaseRenameDatabase(): void
    {
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    protected function restoreReleaseRenameEnvironment(): void
    {
        foreach ($this->originalEnvironment as $key => $value) {
            $this->setReleaseRenameEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    /**
     * A release this worker holds the additional-processing claim for.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function insertClaimedRelease(array $overrides = [], string $token = 'worker-token'): object
    {
        DB::table('releases')->insert([
            'id' => 1,
            'guid' => str_repeat('a', 40),
            'name' => 'd41d8cd98f00b204e9800998ecf8427e',
            'searchname' => 'd41d8cd98f00b204e9800998ecf8427e',
            'categories_id' => 10,
            'postdate' => now(),
            'additional_pp_claimed_at' => now(),
            'additional_pp_claim_token' => $token,
            ...$overrides,
        ]);

        return DB::table('releases')->where('id', $overrides['id'] ?? 1)->first();
    }

    private function setReleaseRenameEnvironmentValue(string $key, ?string $value): void
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
}
