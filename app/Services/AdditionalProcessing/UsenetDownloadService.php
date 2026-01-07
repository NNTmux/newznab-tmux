<?php

namespace App\Services\AdditionalProcessing;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\NNTP\NNTPService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Service for downloading content from Usenet via NNTP.
 * Handles message ID downloading with error handling and group availability detection.
 */
class UsenetDownloadService
{
    private NNTPService $nntp;

    public function __construct(
        private readonly ProcessingConfiguration $config
    ) {
        $this->nntp = new NNTPService;
    }

    /**
     * Download binary content from usenet using message IDs.
     *
     * @param  array|string  $messageIDs  Single or array of message IDs
     * @param  string  $groupName  Group name for logging
     * @param  int|null  $releaseId  Release ID for logging
     * @return array{success: bool, data: string|null, groupUnavailable: bool, error: string|null}
     *
     * @throws Exception
     */
    public function downloadByMessageIDs(
        array|string $messageIDs,
        string $groupName = '',
        ?int $releaseId = null
    ): array {
        $result = [
            'success' => false,
            'data' => null,
            'groupUnavailable' => false,
            'error' => null,
        ];

        if (empty($messageIDs)) {
            $result['error'] = 'No message IDs provided';

            return $result;
        }

        // Ensure array format
        if (is_string($messageIDs)) {
            $messageIDs = [$messageIDs];
        }

        if ($this->config->debugMode) {
            Log::debug('Attempting NNTP fetch', [
                'release_id' => $releaseId,
                'message_ids' => $messageIDs,
                'group' => $groupName,
            ]);
        }

        $binary = $this->nntp->getMessagesByMessageID($messageIDs, $this->config->alternateNNTP);

        // Handle non-string or empty response as failure
        if (! is_string($binary) || $binary === '') {
            $errorMessage = null;

            if (is_object($binary) && method_exists($binary, 'getMessage')) {
                $errorMessage = $binary->getMessage();

                // Check for group unavailability
                if (stripos($errorMessage, 'No such news group') !== false
                    || stripos($errorMessage, 'Group not found') !== false
                ) {
                    $result['groupUnavailable'] = true;
                    $result['error'] = 'Group unavailable: '.$errorMessage;

                    return $result;
                }
            }

            if ($this->config->debugMode) {
                Log::debug('NNTP fetch failed', [
                    'release_id' => $releaseId,
                    'message_ids' => $messageIDs,
                    'group' => $groupName,
                    'error_object' => is_object($binary) ? get_class($binary) : null,
                    'error_message' => $errorMessage,
                    'raw_type' => gettype($binary),
                    'length' => is_string($binary) ? strlen($binary) : 0,
                ]);
            }

            $result['error'] = $errorMessage ?? 'Download failed';

            return $result;
        }

        $result['success'] = true;
        $result['data'] = $binary;

        return $result;
    }

    /**
     * Download sample video content.
     */
    public function downloadSample(
        array $messageIDs,
        string $groupName = '',
        ?int $releaseId = null
    ): array {
        return $this->downloadByMessageIDs($messageIDs, $groupName, $releaseId);
    }

    /**
     * Download media info video content.
     */
    public function downloadMediaInfo(
        string|array $messageID,
        string $groupName = '',
        ?int $releaseId = null
    ): array {
        return $this->downloadByMessageIDs($messageID, $groupName, $releaseId);
    }

    /**
     * Download audio content.
     */
    public function downloadAudio(
        string|array $messageID,
        string $groupName = '',
        ?int $releaseId = null
    ): array {
        return $this->downloadByMessageIDs($messageID, $groupName, $releaseId);
    }

    /**
     * Download JPG content.
     */
    public function downloadJPG(
        array $messageIDs,
        string $groupName = '',
        ?int $releaseId = null
    ): array {
        return $this->downloadByMessageIDs($messageIDs, $groupName, $releaseId);
    }

    /**
     * Download compressed file content (RAR, ZIP, etc.).
     *
     * @param  array  $messageIDs  Message IDs to download
     * @param  string  $groupName  Group name for logging
     * @param  int|null  $releaseId  Release ID for logging
     * @param  string|null  $fileTitle  File title for logging
     * @return array{success: bool, data: string|null, groupUnavailable: bool, error: string|null}
     *
     * @throws Exception
     */
    public function downloadCompressedFile(
        array $messageIDs,
        string $groupName = '',
        ?int $releaseId = null,
        ?string $fileTitle = null
    ): array {
        if ($this->config->debugMode) {
            Log::debug('Attempting compressed fetch', [
                'release_id' => $releaseId,
                'file_title' => $fileTitle,
                'message_ids' => $messageIDs,
                'group' => $groupName,
            ]);
        }

        $result = $this->downloadByMessageIDs($messageIDs, $groupName, $releaseId);

        if (! $result['success'] && $this->config->debugMode) {
            Log::debug('Compressed fetch failed', [
                'release_id' => $releaseId,
                'file_title' => $fileTitle,
                'message_ids' => $messageIDs,
                'group' => $groupName,
                'error' => $result['error'],
            ]);
        }

        return $result;
    }

    /**
     * Get the NNTP client instance.
     */
    public function getNNTP(): NNTPService
    {
        return $this->nntp;
    }

    /**
     * Check if the minimum content size requirement is met.
     */
    public function meetsMinimumSize(string $data, int $minimumBytes = 40): bool
    {
        return strlen($data) > $minimumBytes;
    }
}
