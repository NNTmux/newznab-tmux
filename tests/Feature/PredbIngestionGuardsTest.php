<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Services\IRCScraper;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Offline checks for the approved PreDB sources (IRC announce channel and the predb.net API).
 */
class PredbIngestionGuardsTest extends TestCase
{
    private const string TITLE = 'Some.Show.S01E01.1080p.WEB.h264-GROUP';

    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-predb-ingestion-guards-test.sqlite';
        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        new PDO('sqlite:'.$this->databasePath);
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

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'app.timezone' => 'UTC',
        ]);
        DB::purge();
        DB::reconnect();

        // NOCASE mirrors the case-insensitive utf8mb4_unicode_ci title key on MySQL/MariaDB.
        Schema::create('predb', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title')->collation('NOCASE')->unique();
            $table->string('nfo')->nullable();
            $table->string('size', 50)->nullable();
            $table->string('category')->nullable();
            $table->dateTime('predate')->nullable();
            $table->string('source', 50)->default('');
            $table->unsignedInteger('requestid')->default(0);
            $table->unsignedInteger('groups_id')->default(0);
            $table->tinyInteger('nuked')->default(0);
            $table->string('nukereason')->nullable();
            $table->string('files', 50)->nullable();
            $table->string('filename')->default('');
            $table->tinyInteger('searched')->default(0);
        });

        Search::shouldReceive('insertPredb')->zeroOrMoreTimes();
        Search::shouldReceive('updatePreDb')->zeroOrMoreTimes();
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

    #[Test]
    public function an_irc_announcement_is_stored_with_its_pre_time_and_source(): void
    {
        $this->irc()->receive($this->announce('NEW', self::TITLE));

        $row = DB::table('predb')->first();
        $this->assertSame(self::TITLE, $row->title);
        $this->assertSame('2026-10-01 12:00:00', $row->predate);
        $this->assertSame('#a.b.teevee', $row->source);
        $this->assertSame('100MB', $row->size);
    }

    #[Test]
    public function an_overlength_irc_title_is_rejected_instead_of_truncated(): void
    {
        $this->irc()->receive($this->announce('NEW', str_repeat('Long.Title.', 25).'x264-GROUP'));

        $this->assertSame(0, DB::table('predb')->count());
    }

    #[Test]
    public function an_irc_title_that_only_matches_by_collation_never_touches_the_other_literal(): void
    {
        DB::table('predb')->insert(['title' => strtolower(self::TITLE), 'source' => 'original', 'size' => '1MB']);

        $this->irc()->receive($this->announce('NEW', self::TITLE));
        $this->irc()->receive($this->announce('UPD', self::TITLE, size: '999MB'));

        $this->assertSame(1, DB::table('predb')->count());
        $this->assertSame('1MB', DB::table('predb')->value('size'));
        $this->assertSame(strtolower(self::TITLE), DB::table('predb')->value('title'));
    }

    #[Test]
    public function an_irc_update_keeps_the_known_pre_time_and_source_but_fills_unknown_ones(): void
    {
        DB::table('predb')->insert(['title' => self::TITLE, 'source' => '#original', 'predate' => '2020-01-01 00:00:00']);
        $this->irc()->receive($this->announce('UPD', self::TITLE, size: '200MB'));

        $row = DB::table('predb')->first();
        $this->assertSame('2020-01-01 00:00:00', $row->predate);
        $this->assertSame('#original', $row->source);
        $this->assertSame('200MB', $row->size);

        DB::table('predb')->update(['predate' => null, 'source' => '']);
        $this->irc()->receive($this->announce('UPD', self::TITLE));

        $row = DB::table('predb')->first();
        $this->assertSame('2026-10-01 12:00:00', $row->predate);
        $this->assertSame('#a.b.teevee', $row->source);
    }

    #[Test]
    public function an_irc_nuke_only_changes_the_nuke_state(): void
    {
        DB::table('predb')->insert(['title' => self::TITLE, 'source' => '#original', 'size' => '1MB', 'predate' => '2020-01-01 00:00:00']);

        $this->irc()->receive($this->announce('NUK', self::TITLE, size: '999MB', nuke: '[NUKED: bad.ivtc]'));

        $row = DB::table('predb')->first();
        $this->assertSame(2, (int) $row->nuked);
        $this->assertSame('bad.ivtc', $row->nukereason);
        $this->assertSame('1MB', $row->size);
        $this->assertSame('2020-01-01 00:00:00', $row->predate);
    }

    #[Test]
    public function an_unparseable_or_nuke_time_is_stored_as_unknown(): void
    {
        $this->irc()->receive($this->announce('NEW', self::TITLE, time: 'not a time'));
        $this->irc()->receive($this->announce('NUK', 'Other.Release.2026.1080p-GROUP', nuke: '[NUKED: dupe]'));

        $this->assertSame([null, null], DB::table('predb')->orderBy('id')->pluck('predate')->all());
    }

    private function announce(string $type, string $title, string $time = '2026-10-01 12:00:00', string $size = '100MB', string $nuke = ''): string
    {
        return "{$type}: [DT: {$time}] [TT: {$title}] [SC: #a.b.teevee] [CT: TV] [RQ: N/A] [SZ: {$size}] [FL: 10] [FN: {$title}.mkv]".($nuke !== '' ? ' '.$nuke : '');
    }

    private function irc(): object
    {
        return new class extends IRCScraper
        {
            public function __construct()
            {
                $this->_ignoredChannels = [];
                $this->_groupList = [];
                $this->_debug = false;
                $this->_silent = true;
                $this->_resetPreVariables();
            }

            public function receive(string $message): void
            {
                $this->_channelData = ['message' => $message];
                $this->processChannelMessages();
            }
        };
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
}
