<?php

namespace Tests\Feature;

use App\Models\Country;
use Database\Seeders\CountriesTableSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

final class CountriesTableSeederTest extends TestCase
{
    private string $databasePath = '';

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-countries-test.sqlite';

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

        $settings = [
            'categorizeforeign' => '0',
            'catwebdl' => '0',
            'delaytime' => '2',
            'crossposttime' => '2',
            'maxnzbsprocessed' => '1000',
            'completionpercent' => '100',
            'collection_timeout' => '30',
            'maxsizetoformrelease' => '10737418240',
            'minsizetoformrelease' => '0',
            'minfilestoformrelease' => '1',
            'releaseretentiondays' => '0',
            'deletepasswordedrelease' => '0',
            'miscotherretentionhours' => '0',
            'mischashedretentionhours' => '0',
            'partretentionhours' => '0',
            'last_run_time' => '',
        ];

        $statement = $pdo->prepare('INSERT INTO settings (name, value) VALUES (:name, :value)');
        foreach ($settings as $name => $value) {
            $statement->execute(['name' => $name, 'value' => $value]);
        }

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
        ]);

        Schema::dropIfExists('countries');

        Schema::create('countries', function (Blueprint $table): void {
            $table->char('iso_3166_2', 2)->primary();
            $table->string('name');
            $table->string('full_name')->nullable();
            $table->index('name');
            $table->index('full_name');
        });
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

    public function test_it_seeds_countries_using_iso_codes_as_the_primary_key(): void
    {
        $this->seed(CountriesTableSeeder::class);

        $country = Country::query()->find('US');

        $this->assertNotNull($country);
        $this->assertSame('United States', $country->name);
    }

    public function test_it_resolves_country_codes_from_country_names_and_full_names(): void
    {
        $this->seed(CountriesTableSeeder::class);

        $this->assertSame('DE', countryCode('Germany'));
        $this->assertSame('US', countryCode('United States of America'));
        $this->assertSame('', countryCode('Atlantis'));
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
