<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ConsoleService;
use App\Services\IGDBService;
use App\Services\ReleaseImageService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class ConsoleServiceDlcParsingTest extends TestCase
{
    private string $databasePath;

    private array $originalEnvironment = [];

    private DlcTestDouble $service;

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-console-dlc-test.sqlite';

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
        $pdo->exec("INSERT INTO settings (name, value) VALUES
            ('categorizeforeign', '0'),
            ('catwebdl', '0'),
            ('title', 'NNTmux Test'),
            ('home_link', '/')");

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
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        DB::purge();
        DB::reconnect();

        $this->service = new DlcTestDouble;
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

    public function test_title_with_hyphen_is_parsed_correctly(): void
    {
        $result = $this->service->parseTitle('Halo.3.X360-PROPER');

        $this->assertIsArray($result);
        $this->assertSame('Halo 3', $result['title']);
    }

    public function test_title_without_dlc_flag_is_not_split(): void
    {
        $result = $this->service->parseTitle('Call.of.Duty.Advanced.Warfare.X360');

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('dlc', $result);
    }

    public function test_rock_band_network_title_not_shortened(): void
    {
        $result = $this->service->parseTitle('Rock.Band.Network.DLC.Song.X360');

        $this->assertIsArray($result);
        $this->assertSame('Rock Band Network DLC Song', $result['title']);
        $this->assertArrayNotHasKey('dlc', $result);
    }

    public function test_simple_game_title_extracts_title_and_platform(): void
    {
        $result = $this->service->parseTitle('Gears.of.War.4.X360-REPACK');

        $this->assertIsArray($result);
        $this->assertSame('Gears of War 4', $result['title']);
        $this->assertSame('X360', $result['platform']);
    }

    public function test_title_with_spaces_and_platform(): void
    {
        $result = $this->service->parseTitle('The.Witcher.3.Wild.Hunt.PS4');

        $this->assertIsArray($result);
        $this->assertSame('The Witcher 3 Wild Hunt', $result['title']);
        $this->assertSame('PS4', $result['platform']);
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

class DlcTestDouble extends ConsoleService
{
    public function __construct()
    {
        $this->echoOutput = false;
        $this->gameQty = 0;
        $this->lookupThrottleMs = 0;
        $this->imgSavePath = '';
        $this->renamed = false;
        $this->failCache = [];
        $this->igdbService = new class extends IGDBService {};
        $this->imageService = new ReleaseImageService;
    }
}
