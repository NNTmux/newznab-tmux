<?php

declare(strict_types=1);

namespace App\Services\Crc;

class CrcCandidateSelector
{
    /** @param array<array-key, object|array<string, mixed>> $rows */
    public function select(array $rows, CrcEvidence $evidence, int|float $releaseSize = 0): ?object
    {
        $candidates = $this->compatible($rows, $evidence, $releaseSize);

        return count($candidates) === 1 ? reset($candidates) : null;
    }

    /**
     * Keep complete rows until metadata validation; never combine fields from separate observations.
     * Release totals include packing/recovery overhead and cannot exclude member evidence.
     *
     * @param  array<array-key, object|array<string, mixed>>  $rows
     * @return array<string, object>
     */
    public function compatible(array $rows, CrcEvidence $evidence, int|float $releaseSize = 0): array
    {
        $candidates = [];
        foreach ($rows as $row) {
            $row = (object) $row;
            $title = $row->title ?? $row->searchname ?? '';
            $id = (int) ($row->predb_id ?? 0);
            if (! is_string($title) || trim($title) === '') {
                continue;
            }
            $crc = CrcEvidence::normalizeCrc($row->crchash ?? null);
            $oso = CrcEvidence::normalizeOso($row->osohash ?? null);
            if (($evidence->crcHash !== '' && $crc !== '' && $crc !== $evidence->crcHash)
                || ($evidence->osoHash !== '' && $oso !== '' && $oso !== $evidence->osoHash)) {
                continue;
            }
            $size = CrcEvidence::normalizeSize($row->filesize ?? null);
            if ($size === null || ($size > 0 && $evidence->fileSize > 0 && $size !== $evidence->fileSize)) {
                continue;
            }
            $date = CrcEvidence::normalizeDate($row->filedate ?? null);
            if ($date !== null && $evidence->fileDate !== null
                && abs((int) strtotime($date) - (int) strtotime($evidence->fileDate)) >= 2 * 86400) {
                continue;
            }
            $key = $id > 0 ? 'id:'.$id : 'title:'.$title;
            if (isset($candidates[$key]) && ($candidates[$key]->title ?? $candidates[$key]->searchname) !== $title) {
                // Inconsistent names for the same identity are not interchangeable.
                $key .= ':'.$title;
            }
            $candidates[$key] ??= $row;
        }

        return $candidates;
    }
}
