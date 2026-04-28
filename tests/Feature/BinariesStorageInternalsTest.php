<?php

namespace Tests\Feature;

use App\Services\Binaries\BinaryHandler;
use App\Services\Binaries\CollectionHandler;
use App\Services\Binaries\HeaderParser;
use App\Services\Binaries\HeaderStorageTransaction;
use App\Services\Binaries\PartHandler;
use App\Services\BlacklistService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BinariesStorageInternalsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();
    }

    public function test_header_parser_excludes_usenet_index_posts_and_returns_received_numbers(): void
    {
        $parser = new HeaderParser(new class extends BlacklistService
        {
            public function isBlackListed(array $msg, string $groupName): bool
            {
                return false;
            }
        });

        $result = $parser->parse([
            $this->rawHeader(101, 'Example.Release (1/2)'),
            $this->rawHeader(102, 'Usenet Index Post Example.Release (1/1)'),
            ['Subject' => 'Missing number (1/1)'],
        ], 'alt.test');

        $this->assertSame([101, 102], $result['received']);
        $this->assertCount(1, $result['headers']);
        $this->assertSame(1, $result['notYEnc']);
    }

    public function test_part_handler_ignored_duplicate_is_not_reported_failed(): void
    {
        DB::statement('CREATE TABLE parts (
            binaries_id INT,
            number INT,
            messageid VARCHAR(255),
            partnumber INT,
            size INT,
            UNIQUE(binaries_id, number)
        )');

        $handler = new PartHandler(100);
        $this->assertTrue($handler->addPart(1, $this->parsedHeader(201, 1)));
        $this->assertTrue($handler->flush());
        $this->assertSame([201], $handler->getInsertedNumbers());
        $this->assertSame([], $handler->getFailedNumbers());

        $this->assertTrue($handler->addPart(1, $this->parsedHeader(201, 1)));
        $this->assertTrue($handler->flush());
        $this->assertSame([], $handler->getFailedNumbers());
        $this->assertSame(1, DB::table('parts')->count());
    }

    public function test_sqlite_rollback_cleanup_keeps_unrelated_parts_with_same_article_number(): void
    {
        DB::statement('CREATE TABLE collections (id INTEGER PRIMARY KEY, collectionhash VARCHAR(40), noise VARCHAR(64))');
        DB::statement('CREATE TABLE binaries (id INTEGER PRIMARY KEY, collections_id INT)');
        DB::statement('CREATE TABLE parts (binaries_id INT, number INT, messageid VARCHAR(255), UNIQUE(binaries_id, number))');

        DB::table('collections')->insert(['id' => 1, 'collectionhash' => 'keep', 'noise' => '']);
        DB::table('binaries')->insert(['id' => 1, 'collections_id' => 1]);
        DB::table('parts')->insert(['binaries_id' => 1, 'number' => 777, 'messageid' => '<keep@example>']);

        $collectionHandler = new CollectionHandler;
        $binaryHandler = new BinaryHandler;
        $partHandler = new PartHandler;
        $transaction = new HeaderStorageTransaction($collectionHandler, $binaryHandler, $partHandler);

        $transaction->begin();
        DB::table('collections')->insert(['id' => 2, 'collectionhash' => 'rollback', 'noise' => $transaction->getBatchNoise()]);
        DB::table('binaries')->insert(['id' => 2, 'collections_id' => 2]);
        DB::table('parts')->insert(['binaries_id' => 2, 'number' => 777, 'messageid' => '<rollback@example>']);
        $this->setPrivateProperty($collectionHandler, 'insertedCollectionIds', [2 => true]);
        $this->setPrivateProperty($binaryHandler, 'insertedBinaryIds', [2 => true]);
        $transaction->markError();
        $this->assertFalse($transaction->finish());

        $this->assertSame(1, DB::table('parts')->where('binaries_id', 1)->where('number', 777)->count());
        $this->assertSame(0, DB::table('parts')->where('binaries_id', 2)->count());
    }

    private function rawHeader(int $number, string $subject): array
    {
        return [
            'Number' => $number,
            'Subject' => $subject,
            'From' => 'poster@example.com',
            'Date' => time(),
            'Bytes' => 100,
            'Message-ID' => '<msg'.$number.'@example.com>',
            'Xref' => 'news.example.com group:'.$number,
        ];
    }

    private function parsedHeader(int $number, int $partNumber): array
    {
        $header = $this->rawHeader($number, 'Example.Release ('.$partNumber.'/2)');
        $header['matches'] = [
            0 => $header['Subject'],
            1 => 'Example.Release',
            2 => $partNumber,
            3 => 2,
        ];

        return $header;
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }
}
