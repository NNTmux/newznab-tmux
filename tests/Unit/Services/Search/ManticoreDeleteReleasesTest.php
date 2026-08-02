<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Services\Search\Drivers\ManticoreSearchDriver;
use Manticoresearch\Client;
use Manticoresearch\Table;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ManticoreDeleteReleasesTest extends TestCase
{
    #[Test]
    public function delete_releases_uses_document_api_not_sql(): void
    {
        $config = [
            'host' => '127.0.0.1',
            'port' => 9308,
            'indexes' => [
                'releases' => 'releases_rt',
                'predb' => 'predb_rt',
            ],
        ];

        $table = $this->createMock(Table::class);
        $table->expects($this->once())
            ->method('deleteDocumentsByIds')
            ->with([149331415, 42]);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('table')
            ->with('releases_rt')
            ->willReturn($table);
        $client->expects($this->never())->method('sql');

        $driver = new ManticoreSearchDriver($config);
        $prop = new \ReflectionProperty(ManticoreSearchDriver::class, 'manticoreSearch');
        $prop->setAccessible(true);
        $prop->setValue($driver, $client);

        $driver->deleteReleases([149331415, 42, 0, 149331415]);
    }

    #[Test]
    public function delete_releases_skips_empty_id_list(): void
    {
        $config = [
            'host' => '127.0.0.1',
            'port' => 9308,
            'indexes' => [
                'releases' => 'releases_rt',
                'predb' => 'predb_rt',
            ],
        ];

        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('table');
        $client->expects($this->never())->method('sql');

        $driver = new ManticoreSearchDriver($config);
        $prop = new \ReflectionProperty(ManticoreSearchDriver::class, 'manticoreSearch');
        $prop->setAccessible(true);
        $prop->setValue($driver, $client);

        $driver->deleteReleases([0, -1]);
    }
}
