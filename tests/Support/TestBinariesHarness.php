<?php

namespace Tests\Support;

use App\Services\BlacklistService;
use App\Services\XrefService;
use Blacklight\Binaries;
use Blacklight\ColorCLI;
use Illuminate\Support\Facades\DB;

class TestBinariesHarness extends Binaries
{
    public bool $failPartsInsert = false;

    public ?int $failAfterFlushCount = null; // fail after N successful flushes

    private int $flushCount = 0;

    protected mixed $_collectionsCleaning; // override parent type

    public function __construct()
    {
        // Manually initialize only what storeHeaders/flushPartsChunk need; skip NNTP + Settings lookups.
        $this->startUpdate = now();
        $this->timeCleaning = 0;
        $this->_echoCLI = false;
        $this->_pdo = DB::connection()->getPdo();
        $this->colorCli = new ColorCLI;
        $this->_collectionsCleaning = new class
        {
            public function collectionsCleaner($subject, $groupName): array
            {
                return ['id' => 1, 'name' => 'COLL'];
            }
        };
        $this->xrefService = new XrefService;
        $this->blacklistService = new BlacklistService;
        $this->messageBuffer = 50000;
        $this->_compressedHeaders = false;
        $this->_partRepair = true;
        $this->_newGroupScanByDays = false;
        $this->_newGroupMessagesToScan = 50000;
        $this->_newGroupDaysToScan = 3;
        $this->_partRepairLimit = 15000;
        $this->_partRepairMaxTries = 3;
        $this->blackList = $this->whiteList = [];
    }

    // Expose protected storeHeaders for direct testing.
    public function publicStoreHeaders(array $headers): void
    {
        if (empty($this->groupMySQL)) {
            $this->groupMySQL = ['id' => 1, 'name' => 'alt.test'];
        }
        $this->startCleaning = now();
        config(['tests.force_simulated_rollback' => false]);
        $this->storeHeaders($headers);
    }

    public function setAddToPartRepair(bool $val): void
    {
        $this->addToPartRepair = $val;
    }

    // Simulate scan path minimally to test rollback + part repair queue logic without NNTP.
    public function simulateScan(array $headers, array $group, bool $enablePartRepair = true): void
    {
        $this->groupMySQL = $group;
        $this->first = $headers[0]['Number'];
        $this->last = end($headers)['Number'];
        $this->headersReceived = array_column($headers, 'Number');
        $this->addToPartRepair = $enablePartRepair;
        $this->startCleaning = now();

        // If we are simulating a failure, do not perform any inserts; just mark all as missed.
        if ($this->failPartsInsert) {
            if ($enablePartRepair) {
                foreach (array_unique($this->headersReceived) as $num) {
                    DB::insert('INSERT INTO missed_parts (numberid, groups_id, attempts) VALUES (?, ?, 1)', [$num, $group['id']]);
                }
            }

            return;
        }

        // Normal path: process and insert.
        $this->storeHeaders($headers);

        if ($enablePartRepair && ! empty($this->headersNotInserted)) {
            foreach (array_unique($this->headersNotInserted) as $num) {
                DB::insert('INSERT INTO missed_parts (numberid, groups_id, attempts) VALUES (?, ?, 1)', [$num, $group['id']]);
            }
        }
    }

    // Force chunk failure to trigger rollback when flag set.
    protected function flushPartsChunk(array $parts): bool
    {
        $this->flushCount++;
        if ($this->failPartsInsert) {
            if ($this->failAfterFlushCount === null) {
                return false; // Always fail when flag set and no threshold provided.
            }
            if ($this->flushCount > $this->failAfterFlushCount) {
                return false; // Fail after N successful flushes
            }
        }

        return parent::flushPartsChunk($parts);
    }
}
