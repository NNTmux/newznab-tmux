<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\CbpReleaseEligibilityTest;

final class CbpReleaseEligibilityMariaDbTest extends CbpReleaseEligibilityTest
{
    protected function configureDatabase(): void
    {
        $database = getenv('CBP_INTEGRATION_DB_DATABASE');
        if ($database === false || $database === '') {
            $this->markTestSkipped('Set CBP_INTEGRATION_DB_DATABASE to an empty, isolated MariaDB test database.');
        }

        config([
            'database.default' => 'mariadb',
            'database.connections.mariadb.url' => null,
            'database.connections.mariadb.database' => $database,
            'database.connections.mariadb.username' => (string) getenv('CBP_INTEGRATION_DB_USERNAME'),
            'database.connections.mariadb.password' => (string) getenv('CBP_INTEGRATION_DB_PASSWORD'),
        ]);
        DB::purge('mariadb');
        DB::reconnect('mariadb');

        foreach (['collections', 'collection_groups', 'binaries', 'parts'] as $table) {
            if (Schema::hasTable($table)) {
                $this->fail('Refusing to use an occupied CBP integration database.');
            }
        }
    }
}
