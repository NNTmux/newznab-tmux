<?php

namespace Tests\Feature;

use App\Services\Binaries\BinariesConfig;
use App\Services\Binaries\HeaderStorageService;
use App\Services\CollectionsCleaningService;
use App\Services\RegexService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CollectionNamingTest extends TestCase
{
    /**
     * Files of one real production post that used to land in one collection each.
     */
    private const array DOA_POST = [
        'DOA-OEEANBRN[-001/102] - "DOA-OEEANBRN.par2" yEnc',
        'DOA-OEEANBRN[-039/102] - "DOA-OEEANBRN.part038.rar" yEnc',
        'DOA-OEEANBRN[-040/102] - "DOA-OEEANBRN.part039.rar" yEnc',
        'DOA-OEEANBRN[-099/102] - "DOA-OEEANBRN.vol291+34.PAR2" yEnc',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();
        // SQLite evaluates `X REGEXP Y` as regexp(Y, X).
        DB::connection()->getPdo()->sqliteCreateFunction(
            'REGEXP',
            static fn (?string $pattern, ?string $subject): int => $subject !== null && $pattern !== null
                && @preg_match('/'.str_replace('/', '\/', $pattern).'/i', $subject) === 1 ? 1 : 0,
            2
        );
        Cache::flush();

        DB::statement('CREATE TABLE collection_regexes (
            id INTEGER PRIMARY KEY, group_regex VARCHAR(255), regex VARCHAR(255),
            status INT DEFAULT 1, ordinal INT DEFAULT 0
        )');
    }

    public function test_generic_cleaner_gives_every_file_of_a_dash_counter_post_the_same_name(): void
    {
        $cleaner = new CollectionsCleaningService;
        $names = array_unique(array_map(
            static fn (string $subject): string => $cleaner->collectionsCleaner($subject, 'alt.binaries.dvd')['name'],
            self::DOA_POST
        ));

        $this->assertCount(1, $names);
    }

    public function test_music_cleaner_gives_every_file_of_a_spaced_counter_post_the_same_name(): void
    {
        $cleaner = new CollectionsCleaningService;
        $names = array_unique(array_map(
            static fn (string $subject): string => $cleaner->collectionsCleaner($subject, 'alt.binaries.sounds.lossless')['name'],
            [
                "Manfred Mann's Earth Band 1 - nightsteff [062/ 115] \"1973 - Messin'.part04.rar\" yEnc",
                "Manfred Mann's Earth Band 1 - nightsteff [064/ 115] \"1973 - Messin'.part06.rar\" yEnc",
            ]
        ));

        $this->assertCount(1, $names);
    }

    public function test_dash_counter_post_is_stored_as_one_collection_with_its_file_total(): void
    {
        $this->createStorageTables();
        $headers = [];
        foreach (self::DOA_POST as $index => $subject) {
            $headers[] = [
                'Number' => 7000 + $index,
                'Subject' => $subject.' (1/1)',
                'From' => 'insane@yourbrain.ciom (ghostbrain)',
                'Date' => time(),
                'Bytes' => 100,
                'Message-ID' => '<doa'.$index.'@example.com>',
                'Xref' => 'news.example alt.binaries.dvd:'.(7000 + $index),
                'matches' => [0 => $subject.' (1/1)', 1 => $subject, 2 => 1, 3 => 1],
            ];
        }

        $failed = (new HeaderStorageService(config: new BinariesConfig(sqlChunkSize: 50)))
            ->store($headers, ['id' => 1, 'name' => 'alt.binaries.dvd'], true);

        $this->assertSame([], $failed);
        $this->assertSame(1, DB::table('collections')->count());
        $this->assertSame(102, (int) DB::table('collections')->value('totalfiles'));
        $this->assertSame(4, DB::table('binaries')->count());
    }

    public function test_regex_list_is_fetched_once_per_group_and_group_name_is_bound(): void
    {
        DB::table('collection_regexes')->insert([
            'id' => 1, 'group_regex' => "^alt\\.binaries\\.o'test$", 'regex' => '/^(?P<name>Match\.Me)/i', 'status' => 1, 'ordinal' => 0,
        ]);
        $selects = 0;
        DB::listen(static function (QueryExecuted $query) use (&$selects): void {
            if (str_contains($query->sql, 'FROM collection_regexes')) {
                $selects++;
            }
        });

        $regexes = new RegexService('collection_regexes');
        $this->assertSame('Match.Me', $regexes->tryRegex('Match.Me.Please yEnc', "alt.binaries.o'test"));
        $this->assertSame(1, $regexes->matchedRegex);
        $this->assertSame('', $regexes->tryRegex('Nothing here yEnc', "alt.binaries.o'test"));

        $this->assertSame(1, $selects);
    }

    private function createStorageTables(): void
    {
        DB::statement('CREATE TABLE collections (
            id INTEGER PRIMARY KEY, subject VARCHAR(255), fromname VARCHAR(255), date DATETIME NULL,
            xref TEXT DEFAULT \'\', groups_id INT, totalfiles INT, collectionhash VARCHAR(40) UNIQUE,
            collection_regexes_id INT, dateadded DATETIME NULL, last_seen_at DATETIME NULL,
            filecheck INT DEFAULT 0, filesize INT DEFAULT 0, noise VARCHAR(64) DEFAULT \'\'
        )');
        DB::statement('CREATE TABLE collection_groups (collections_id INT, group_name VARCHAR(255), UNIQUE(collections_id, group_name))');
        DB::statement('CREATE TABLE binaries (
            id INTEGER PRIMARY KEY, binaryhash BLOB, name VARCHAR(255), collections_id INT, totalparts INT,
            currentparts INT, filenumber INT, partsize INT, partcheck INT DEFAULT 0, UNIQUE(binaryhash, collections_id)
        )');
        DB::statement('CREATE TABLE parts (
            binaries_id INT, number INT, messageid VARCHAR(255), partnumber INT, size INT, UNIQUE(binaries_id, partnumber)
        )');
    }
}
