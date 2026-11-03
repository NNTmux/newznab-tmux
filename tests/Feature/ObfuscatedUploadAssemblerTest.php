<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CollectionFileCheckStatus;
use App\Services\Binaries\BinariesConfig;
use App\Services\Binaries\CollectionHandler;
use App\Services\Binaries\HeaderStorageService;
use App\Services\Binaries\Obfuscated\ObfuscatedUploadAssembler;
use App\Services\Binaries\Obfuscated\ObfuscatedUploadConfig;
use App\Services\CollectionsCleaningService;
use App\Services\ReleaseProcessingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ObfuscatedUploadAssemblerTest extends TestCase
{
    private const int GROUP = 1;

    private int $articleNumber = 1000;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'nntmux.echocli' => false]);
        DB::purge();
        DB::reconnect();
        $this->travelTo(now()->startOfSecond());
        $this->createTables();
    }

    protected function tearDown(): void
    {
        foreach (['parts', 'binaries', 'collection_groups', 'collections'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_randomized_articles_are_merged_into_one_sized_collection(): void
    {
        $this->ingestCountedUpload('bob@home.com', [3, 2]);
        $source = (int) DB::table('collections')->max('id');
        DB::table('collection_groups')->insert(['collections_id' => $source, 'group_name' => 'alt.binaries.crosspost']);
        $this->travel(30)->minutes();

        $result = $this->assembler()->assembleGroup(self::GROUP);

        $this->assertSame(1, $result->merged);
        $this->assertSame(5, $result->mergedArticles);
        $collection = DB::table('collections')->sole();
        $this->assertSame(CollectionFileCheckStatus::Sized->value, (int) $collection->filecheck);
        $this->assertSame(2, (int) $collection->totalfiles);
        $this->assertSame(500, (int) $collection->filesize);
        $this->assertSame(ObfuscatedUploadAssembler::ASSEMBLED_REGEX_ID, (int) $collection->collection_regexes_id);
        $this->assertMatchesRegularExpression('/^Rnd[A-Za-z0-9]+$/', $collection->subject, 'bare name, no counter or quotes');
        $this->assertSame([3, 2], DB::table('binaries')->orderBy('filenumber')->pluck('totalparts')->map('intval')->all());
        $this->assertSame([3, 2], DB::table('binaries')->orderBy('filenumber')->pluck('currentparts')->map('intval')->all());
        $this->assertSame(5, DB::table('parts')->whereIn('binaries_id', DB::table('binaries')->pluck('id'))->count());
        $this->assertSame(['alt.binaries.crosspost', 'alt.binaries.test'], DB::table('collection_groups')->where('collections_id', $collection->id)->orderBy('group_name')->pluck('group_name')->all());
        $this->assertSame(0, DB::table('collection_groups')->where('collections_id', '<>', $collection->id)->count());
    }

    public function test_dry_run_and_repeated_runs_leave_the_tables_alone(): void
    {
        $this->ingestCountedUpload('bob@home.com', [3, 2]);
        $this->travel(30)->minutes();

        $dryRun = $this->assembler()->assembleGroup(self::GROUP, dryRun: true);

        $this->assertCount(1, $dryRun->planned);
        $this->assertSame(0, $dryRun->merged);
        $this->assertSame(5, DB::table('collections')->count());

        $this->assembler()->assembleGroup(self::GROUP);
        $snapshot = DB::table('collections')->get()->toArray();
        $again = $this->assembler()->assembleGroup(self::GROUP);

        $this->assertSame(0, $again->merged);
        $this->assertEquals($snapshot, DB::table('collections')->get()->toArray());
    }

    public function test_same_size_volumes_without_a_file_counter_are_assigned_by_posting_order(): void
    {
        // a.b.misc style: `[k/N]` is read as file and part counter, two PAR2 volumes share a size.
        $poster = '3c1a7b61e895@4ccd9408f595.invalid';
        $this->ingestUncountedFile($poster, 4);
        $volumeA = $this->ingestUncountedFile($poster, 2);
        $volumeB = $this->ingestUncountedFile($poster, 2);
        $this->travel(30)->minutes();

        $result = $this->assembler()->assembleGroup(self::GROUP);

        $this->assertSame(1, $result->merged);
        $this->assertSame(2, $result->planned[0]->ambiguousFiles());
        $this->assertSame([4, 2, 2], DB::table('binaries')->orderBy('filenumber')->pluck('totalparts')->map('intval')->all());
        $volumes = DB::table('binaries')->where('totalparts', 2)->orderBy('filenumber')->pluck('id');
        $this->assertSame($volumeA, DB::table('parts')->where('binaries_id', $volumes[0])->orderBy('partnumber')->pluck('number')->map('intval')->all());
        $this->assertSame($volumeB, DB::table('parts')->where('binaries_id', $volumes[1])->orderBy('partnumber')->pluck('number')->map('intval')->all());
    }

    public function test_incomplete_upload_is_held_past_the_collection_delay_and_released_after_the_hold(): void
    {
        $this->ingestCountedUpload('bob@home.com', [3, 2], skipPart: [1, 2]);
        $this->travel(3)->hours();

        $this->processor()->processIncompleteCollections(self::GROUP);

        $this->assertSame([CollectionFileCheckStatus::ObfuscatedHold->value], $this->statuses());
        $this->assertSame(4, DB::table('collections')->count());

        $this->travel(7)->hours();
        $this->processor()->processIncompleteCollections(self::GROUP);

        $this->assertNotContains(CollectionFileCheckStatus::ObfuscatedHold->value, $this->statuses());
    }

    public function test_disabled_assembler_leaves_articles_to_the_stale_path(): void
    {
        $this->ingestCountedUpload('bob@home.com', [3, 2]);
        $this->travel(3)->hours();

        $this->processor(enabled: false)->processIncompleteCollections(self::GROUP);

        $this->assertSame(5, DB::table('collections')->count());
        $this->assertSame([CollectionFileCheckStatus::CompleteParts->value], $this->statuses());
    }

    public function test_command_dry_run_reports_without_writing(): void
    {
        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->boolean('active')->default(true);
        });
        DB::table('usenet_groups')->insert(['id' => self::GROUP, 'name' => 'alt.binaries.test', 'active' => 1]);
        config(['nntmux.cbp.obfuscated.min_articles' => 2]);
        $this->ingestCountedUpload('bob@home.com', [3, 2]);
        $this->travel(30)->minutes();

        $this->artisan('cbp:assemble-obfuscated', ['--group' => ['alt.binaries.test'], '--dry-run' => true])
            ->expectsOutputToContain('Dry run: would assemble 1 upload(s)')
            ->assertSuccessful();

        $this->assertSame(5, DB::table('collections')->count());
        Schema::drop('usenet_groups');
    }

    /**
     * @param  list<int>  $partsPerFile
     * @param  array{0: int, 1: int}|null  $skipPart  [file, part] left out
     */
    private function ingestCountedUpload(string $poster, array $partsPerFile, ?array $skipPart = null): void
    {
        $headers = [];
        $totalFiles = \count($partsPerFile);
        foreach ($partsPerFile as $index => $totalParts) {
            foreach (range(1, $totalParts) as $part) {
                if ($skipPart === [$index + 1, $part]) {
                    continue;
                }
                $subject = sprintf('[%d/%d] "Rnd%s" yEnc', $index + 1, $totalFiles, $this->randomToken());
                $headers[] = $this->header($poster, $subject, $part, $totalParts);
            }
        }
        $this->store($headers);
    }

    /**
     * @return list<int> article numbers in part order
     */
    private function ingestUncountedFile(string $poster, int $totalParts): array
    {
        $headers = [];
        foreach (range(1, $totalParts) as $part) {
            $subject = sprintf('[%d/%d] "%s.bin" yEnc', $part, $totalParts, bin2hex(random_bytes(8)));
            $headers[] = $this->header($poster, $subject, $part, $totalParts);
        }
        $this->store($headers);

        return array_map(static fn (array $header): int => $header['Number'], $headers);
    }

    /**
     * @return array<string, mixed>
     */
    private function header(string $poster, string $subject, int $part, int $totalParts): array
    {
        $number = $this->articleNumber++;

        return [
            'Number' => $number, 'From' => $poster, 'Date' => now()->timestamp,
            'Message-ID' => '<'.$number.'@nyuu>', 'Bytes' => 100, 'Xref' => '',
            'matches' => [$subject.' ('.$part.'/'.$totalParts.')', $subject, $part, $totalParts],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $headers
     */
    private function store(array $headers): void
    {
        $storage = new HeaderStorageService(new CollectionHandler(new class extends CollectionsCleaningService
        {
            public function collectionsCleaner(string $subject, string $groupName = ''): array
            {
                return ['id' => -10, 'name' => $subject];
            }
        }), config: new BinariesConfig(headerChunkSize: 50, sqlChunkSize: 50));

        $this->assertSame([], $storage->store($headers, ['id' => self::GROUP, 'name' => 'alt.binaries.test']));
    }

    private function randomToken(): string
    {
        $token = '';
        foreach (range(1, 24) as $index) {
            $token .= ['a', 'B', '7'][$index % 3].chr(random_int(0, 1) === 1 ? random_int(65, 90) : random_int(97, 122));
        }

        return $token;
    }

    private function config(bool $enabled = true): ObfuscatedUploadConfig
    {
        return new ObfuscatedUploadConfig(enabled: $enabled, minArticles: 2);
    }

    private function assembler(): ObfuscatedUploadAssembler
    {
        return new ObfuscatedUploadAssembler($this->config());
    }

    private function processor(bool $enabled = true): ReleaseProcessingService
    {
        return new ReleaseProcessingService(
            binariesConfig: new BinariesConfig(reconcileBatchSize: 50),
            obfuscatedUploadAssembler: new ObfuscatedUploadAssembler($this->config($enabled)),
        );
    }

    /**
     * @return list<int>
     */
    private function statuses(): array
    {
        return DB::table('collections')->distinct()->pluck('filecheck')->map('intval')->values()->all();
    }

    private function createTables(): void
    {
        Schema::create('collections', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('subject')->default('');
            $table->string('fromname')->default('');
            $table->dateTime('date')->nullable();
            $table->string('xref')->default('');
            $table->unsignedInteger('groups_id');
            $table->unsignedInteger('totalfiles')->default(0);
            $table->binary('collectionhash', 20, true)->nullable()->unique();
            $table->integer('collection_regexes_id')->default(0);
            $table->dateTime('dateadded')->nullable();
            $table->dateTime('added')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->integer('filecheck')->default(0);
            $table->unsignedBigInteger('filesize')->default(0);
            $table->integer('releases_id')->nullable();
            $table->string('noise')->default('');
        });
        Schema::create('collection_groups', function (Blueprint $table): void {
            $table->unsignedInteger('collections_id');
            $table->string('group_name');
            $table->unique(['collections_id', 'group_name']);
        });
        Schema::create('binaries', function (Blueprint $table): void {
            $table->increments('id');
            $table->binary('binaryhash', 16, true)->nullable();
            $table->string('name')->default('');
            $table->unsignedInteger('collections_id');
            $table->integer('totalparts')->default(0);
            $table->integer('currentparts')->default(0);
            $table->integer('filenumber')->default(0);
            $table->unsignedBigInteger('partsize')->default(0);
            $table->integer('partcheck')->default(0);
            $table->unique(['collections_id', 'binaryhash']);
        });
        Schema::create('parts', function (Blueprint $table): void {
            $table->unsignedInteger('binaries_id');
            $table->unsignedBigInteger('number');
            $table->string('messageid');
            $table->unsignedInteger('partnumber');
            $table->unsignedInteger('size');
            $table->primary(['binaries_id', 'partnumber']);
        });
    }
}
