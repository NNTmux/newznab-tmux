<?php

namespace Tests\Support;

use App\Services\Binaries\BinariesConfig;
use App\Services\Binaries\BinariesService;
use App\Services\Binaries\HeaderStorageService;
use App\Services\Binaries\MissedPartHandler;
use Illuminate\Support\Facades\DB;

class TestBinariesHarness extends BinariesService
{
    public bool $failPartsInsert = false;

    public ?int $failAfterFlushCount = null; // fail after N successful flushes

    private int $flushCount = 0;

    private array $testGroupMySQL = [];

    private HeaderStorageService $testHeaderStorage;

    private MissedPartHandler $testMissedPartHandler;


    public function __construct()
    {
        // Create a minimal config that doesn't require database access
        $config = new BinariesConfig(
            messageBuffer: 50000,
            compressedHeaders: false,
            partRepair: true,
            newGroupScanByDays: false,
            newGroupMessagesToScan: 50000,
            newGroupDaysToScan: 3,
            partRepairLimit: 15000,
            partRepairMaxTries: 3,
            partsChunkSize: 5000,
            binariesUpdateChunkSize: 1000,
            echoCli: false
        );

        // Call parent with config (will still try to create NNTP, but we won't use it)
        parent::__construct($config);

        // Override with test-specific services
        $this->testHeaderStorage = new HeaderStorageService(config: $config);
        $this->testMissedPartHandler = new MissedPartHandler(
            $config->partRepairLimit,
            $config->partRepairMaxTries
        );
    }

    // Expose protected method for direct testing via new service.
    public function publicStoreHeaders(array $headers): void
    {
        if (empty($this->testGroupMySQL)) {
            $this->testGroupMySQL = ['id' => 1, 'name' => 'alt.test'];
        }
        config(['tests.force_simulated_rollback' => false]);

        // Parse headers first to add 'matches'
        $parsedHeaders = [];
        foreach ($headers as $header) {
            if (preg_match('/^\s*(?!"Usenet Index Post)(.+)\s+\((\d+)\/(\d+)\)/', $header['Subject'], $matches)) {
                if (stripos($header['Subject'], 'yEnc') === false) {
                    $matches[1] .= ' yEnc';
                }
                $header['matches'] = $matches;
                $parsedHeaders[] = $header;
            }
        }

        $this->testHeaderStorage->store($parsedHeaders, $this->testGroupMySQL, true);
    }

    public function setAddToPartRepair(bool $val): void
    {
        // This is now handled by passing parameter to store()
    }

    // Simulate scan path minimally to test rollback + part repair queue logic without NNTP.
    public function simulateScan(array $headers, array $group, bool $enablePartRepair = true): void
    {
        $this->testGroupMySQL = $group;

        // Parse headers first to add 'matches'
        $parsedHeaders = [];
        $headersReceived = [];
        foreach ($headers as $header) {
            if (isset($header['Number'])) {
                $headersReceived[] = $header['Number'];
            }
            if (preg_match('/^\s*(?!"Usenet Index Post)(.+)\s+\((\d+)\/(\d+)\)/', $header['Subject'], $matches)) {
                if (stripos($header['Subject'], 'yEnc') === false) {
                    $matches[1] .= ' yEnc';
                }
                $header['matches'] = $matches;
                $parsedHeaders[] = $header;
            }
        }

        // If we are simulating a failure, do not perform any inserts; just mark all as missed.
        if ($this->failPartsInsert) {
            if ($enablePartRepair) {
                foreach (array_unique($headersReceived) as $num) {
                    $driver = DB::getDriverName();
                    if ($driver === 'sqlite') {
                        DB::statement('INSERT INTO missed_parts (numberid, groups_id, attempts) VALUES (?, ?, 1) ON CONFLICT(numberid, groups_id) DO UPDATE SET attempts = attempts + 1', [$num, $group['id']]);
                    } else {
                        DB::insert('INSERT INTO missed_parts (numberid, groups_id, attempts) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1', [$num, $group['id']]);
                    }
                }
            }

            return;
        }

        // Normal path: process and insert.
        $failedInserts = $this->testHeaderStorage->store($parsedHeaders, $group, $enablePartRepair);

        if ($enablePartRepair && ! empty($failedInserts)) {
            $this->testMissedPartHandler->addMissingParts($failedInserts, $group['id']);
        }
    }
}
