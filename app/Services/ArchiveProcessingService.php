<?php

declare(strict_types=1);

namespace App\Services;

use dariusiii\rarinfo\ArchiveInfo;

class ArchiveProcessingService
{
    public function __construct(private readonly ArchiveInfo $archiveInfo) {}

    /**
     * Analyze compressed data (RAR/ZIP/etc.).
     * Returns an array with: ok(bool), error(?string), summary(?array), is_encrypted(bool)
     *
     * @return array<string, mixed>
     */
    public function analyze(string $compressedData): array
    {
        $ok = $this->archiveInfo->setData($compressedData, true);
        if (! $ok) {
            return ['ok' => false, 'error' => $this->archiveInfo->error ?: 'Unknown error', 'summary' => null, 'is_encrypted' => false];
        }
        if ($this->archiveInfo->error !== '') {
            return ['ok' => false, 'error' => $this->archiveInfo->error, 'summary' => null, 'is_encrypted' => false];
        }
        $summary = $this->archiveInfo->getSummary(true);
        $isEncrypted = ! empty($this->archiveInfo->isEncrypted) || (isset($summary['is_encrypted']) && (int) $summary['is_encrypted'] !== 0);

        return ['ok' => true, 'error' => null, 'summary' => $summary, 'is_encrypted' => $isEncrypted];
    }

    /**
     * Get file list from currently analyzed archive.
     *
     * @return array<string, mixed>
     */
    public function getFileList(): array
    {
        return $this->archiveInfo->getArchiveFileList() ?: [];
    }

    /**
     * Get file contents by name and source from the current archive.
     */
    public function getFileData(string $name, string $source): string|false
    {
        return $this->archiveInfo->getFileData($name, $source);
    }

    /**
     * Extract file by name from the current archive to destination path.
     */
    public function extractFile(string $name, string $destPath): bool
    {
        return $this->archiveInfo->extractFile($name, $destPath);
    }
}
