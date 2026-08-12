<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\NzbImportStatus;
use App\Models\Category;
use App\Services\Nzb\NzbImportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NzbImportServiceTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-nzb-import-test', '.sqlite');

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
        Cache::flush();

        Schema::create('categories', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('title');
            $table->integer('status');
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

    public function test_begin_import_uses_specific_messages_and_counts_duplicates_separately(): void
    {
        $duplicateFile = $this->makeNzbFile('duplicate');
        $blacklistedFile = $this->makeNzbFile('blacklisted');
        $noGroupFile = $this->makeNzbFile('nogroup');
        $failedFile = $this->makeNzbFile('failed');

        $service = new class(['Browser' => true], [NzbImportStatus::Duplicate, NzbImportStatus::Blacklisted, NzbImportStatus::NoGroup, NzbImportStatus::Failed]) extends NzbImportService
        {
            /**
             * @param  array<NzbImportStatus>  $statuses
             */
            public function __construct(array $options, private array $statuses)
            {
                parent::__construct($options);
            }

            protected function getAllGroups(): bool
            {
                return true;
            }

            protected function scanNZBFile(mixed &$nzbXML, mixed $nzbFileName = '', mixed $source = ''): NzbImportStatus
            {
                $status = array_shift($this->statuses) ?? NzbImportStatus::Failed;

                match ($status) {
                    NzbImportStatus::Duplicate => $this->echoOut('This release is already in our DB so skipping: duplicate subject'),
                    NzbImportStatus::Blacklisted => $this->echoOut('Subject is blacklisted: blacklisted subject'),
                    NzbImportStatus::NoGroup => $this->echoOut('No group found for missing-group subject (one of alt.test are missing'),
                    default => null,
                };

                return $status;
            }
        };

        $result = $service->beginImport(
            [$duplicateFile, $blacklistedFile, $noGroupFile, $failedFile],
            delete: false,
            deleteFailed: true,
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('This release is already in our DB so skipping: duplicate subject', $result);
        $this->assertStringContainsString('Subject is blacklisted: blacklisted subject', $result);
        $this->assertStringContainsString('No group found for missing-group subject (one of alt.test are missing', $result);
        $this->assertSame(1, substr_count($result, 'ERROR: Failed to insert NZB!'));
        $this->assertStringContainsString('Processed 0 NZBs in ', $result);
        $this->assertStringContainsString('3 NZBs were skipped, 1 were duplicates.', $result);

        $this->assertFileDoesNotExist($duplicateFile);
        $this->assertFileDoesNotExist($blacklistedFile);
        $this->assertFileDoesNotExist($noGroupFile);
        $this->assertFileDoesNotExist($failedFile);
    }

    public function test_begin_import_deletes_duplicate_blacklisted_and_no_group_files_when_delete_is_enabled(): void
    {
        $duplicateFile = $this->makeNzbFile('duplicate-delete');
        $blacklistedFile = $this->makeNzbFile('blacklisted-delete');
        $noGroupFile = $this->makeNzbFile('nogroup-delete');

        $service = new class(['Browser' => true], [NzbImportStatus::Duplicate, NzbImportStatus::Blacklisted, NzbImportStatus::NoGroup]) extends NzbImportService
        {
            /**
             * @param  array<NzbImportStatus>  $statuses
             */
            public function __construct(array $options, private array $statuses)
            {
                parent::__construct($options);
            }

            protected function getAllGroups(): bool
            {
                return true;
            }

            protected function scanNZBFile(mixed &$nzbXML, mixed $nzbFileName = '', mixed $source = ''): NzbImportStatus
            {
                $status = array_shift($this->statuses) ?? NzbImportStatus::Failed;

                match ($status) {
                    NzbImportStatus::Duplicate => $this->echoOut('This release is already in our DB so skipping: duplicate subject'),
                    NzbImportStatus::Blacklisted => $this->echoOut('Subject is blacklisted: blacklisted subject'),
                    NzbImportStatus::NoGroup => $this->echoOut('No group found for missing-group subject (one of alt.test are missing'),
                    default => null,
                };

                return $status;
            }
        };

        $result = $service->beginImport(
            [$duplicateFile, $blacklistedFile, $noGroupFile],
            delete: true,
            deleteFailed: false,
        );

        $this->assertIsString($result);
        $this->assertStringNotContainsString('ERROR: Failed to insert NZB!', $result);
        $this->assertStringContainsString('2 NZBs were skipped, 1 were duplicates.', $result);

        $this->assertFileDoesNotExist($duplicateFile);
        $this->assertFileDoesNotExist($blacklistedFile);
        $this->assertFileDoesNotExist($noGroupFile);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nzbFilenameProvider(): array
    {
        return [
            'plain .nzb' => ['foo.nzb', 'foo'],
            'plain .nzb.gz' => ['foo.nzb.gz', 'foo'],
            'mkv wrapper' => ['foo.mkv.nzb.gz', 'foo'],
            'uppercase wrapper' => ['bar.MP4.NZB.GZ', 'bar'],
            'release with brackets' => [
                '[DKB] Kami-tachi ni Hirowareta Otoko - S01E07 [1080p][H.265 10bit].mkv.nzb.gz',
                '[DKB] Kami-tachi ni Hirowareta Otoko - S01E07 [1080p][H.265 10bit]',
            ],
            'non-media inner ext stays' => ['release.name.nzb.gz', 'release.name'],
            'no trailing media ext' => ['something.nzb', 'something'],
            'full path input' => [sys_get_temp_dir().'/nested/path/Show - 01.mp4.nzb.gz', 'Show - 01'],
        ];
    }

    /**
     * @dataProvider nzbFilenameProvider
     */
    #[DataProvider('nzbFilenameProvider')]
    public function test_derive_release_name_strips_wrapper_and_media_extension(string $input, string $expected): void
    {
        $service = new class(['Browser' => true]) extends NzbImportService
        {
            public function deriveForTest(string $path): string
            {
                return $this->deriveReleaseNameFromNzbPath($path);
            }
        };

        $this->assertSame($expected, $service->deriveForTest($input));
    }

    public function test_group_names_are_trimmed_before_import(): void
    {
        $service = new class(['Browser' => true]) extends NzbImportService
        {
            public function groupNameForTest(\SimpleXMLElement $group): string
            {
                return $this->normalizeGroupName($group);
            }
        };

        $group = simplexml_load_string('<group> alt.binaries.xylo </group>');
        $this->assertInstanceOf(\SimpleXMLElement::class, $group);

        $this->assertSame('alt.binaries.xylo', $service->groupNameForTest($group));
    }

    public function test_nzb_category_metadata_resolves_active_id_and_unique_case_insensitive_title(): void
    {
        $this->insertCategory(2040, 'HD', Category::STATUS_ACTIVE);
        $this->insertCategory(3040, 'Lossless', Category::STATUS_ACTIVE);

        $this->assertSame(2040, $this->resolveNzbCategory('<meta type="category"> 2040 </meta>'));
        $this->assertSame(3040, $this->resolveNzbCategory('<meta type="CATEGORY"> lossLESS </meta>'));
        $this->assertSame(2040, $this->resolveNzbCategory(
            '<meta type="category">2040</meta>',
            ' xmlns="http://www.newzbin.com/DTD/2003/nzb"'
        ));
    }

    public function test_nzb_category_metadata_rejects_unknown_inactive_disabled_and_ambiguous_values(): void
    {
        $this->insertCategory(2040, 'HD', Category::STATUS_ACTIVE);
        $this->insertCategory(3040, 'Lossless', Category::STATUS_INACTIVE);
        $this->insertCategory(5040, 'HD', Category::STATUS_ACTIVE);
        $this->insertCategory(6040, 'X264', Category::STATUS_DISABLED);

        $this->assertNull($this->resolveNzbCategory('<meta type="category">9999</meta>'));
        $this->assertNull($this->resolveNzbCategory('<meta type="category">3040</meta>'));
        $this->assertNull($this->resolveNzbCategory('<meta type="category">X264</meta>'));
        $this->assertNull($this->resolveNzbCategory('<meta type="category">HD</meta>'));
    }

    public function test_nzb_category_metadata_rejects_conflicting_matches(): void
    {
        $this->insertCategory(2040, 'Movie HD', Category::STATUS_ACTIVE);
        $this->insertCategory(5040, 'TV HD', Category::STATUS_ACTIVE);

        $this->assertNull($this->resolveNzbCategory(
            '<meta type="category">2040</meta><meta type="category">TV HD</meta>'
        ));
    }

    public function test_nzb_category_metadata_falls_back_when_category_is_missing_or_blank(): void
    {
        $this->assertNull($this->resolveNzbXml('<nzb><file subject="example" /></nzb>'));
        $this->assertNull($this->resolveNzbCategory(''));
        $this->assertNull($this->resolveNzbCategory('<meta type="category"> </meta>'));
        $this->assertNull($this->resolveNzbCategory('<meta type="password">secret</meta>'));
    }

    public function test_compressed_nzb_write_returns_false_for_an_unwritable_destination(): void
    {
        $service = new class(['Browser' => true]) extends NzbImportService
        {
            public function writeForTest(string $path, string $contents): bool
            {
                return $this->writeCompressedNzb($path, $contents);
            }
        };

        $path = sys_get_temp_dir().'/missing-'.bin2hex(random_bytes(5)).'/release.nzb.gz';

        $this->assertFalse($service->writeForTest($path, '<nzb />'));
        $this->assertFileDoesNotExist($path);
    }

    private function makeNzbFile(string $suffix): string
    {
        $path = sys_get_temp_dir().'/'.$suffix.'-'.bin2hex(random_bytes(5)).'.nzb';
        file_put_contents($path, '<nzb></nzb>');

        return $path;
    }

    private function insertCategory(int $id, string $title, int $status): void
    {
        DB::table('categories')->insert([
            'id' => $id,
            'title' => $title,
            'status' => $status,
        ]);
    }

    private function resolveNzbCategory(string $headMetadata, string $nzbAttributes = ''): ?int
    {
        return $this->resolveNzbXml("<nzb{$nzbAttributes}><head>{$headMetadata}</head></nzb>");
    }

    private function resolveNzbXml(string $xml): ?int
    {
        $service = new class(['Browser' => true]) extends NzbImportService
        {
            public function resolveForTest(\SimpleXMLElement $nzb): ?int
            {
                return $this->resolveNzbCategoryId($nzb);
            }
        };

        $nzb = simplexml_load_string($xml);
        $this->assertInstanceOf(\SimpleXMLElement::class, $nzb);

        return $service->resolveForTest($nzb);
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
