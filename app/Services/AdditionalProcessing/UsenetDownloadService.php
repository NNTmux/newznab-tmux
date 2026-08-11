<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\DTO\DownloadMetrics;
use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\NNTP\NNTPService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Service for downloading content from Usenet via NNTP.
 * Handles message ID downloading with error handling and group availability detection.
 */
class UsenetDownloadService
{
    private const int MAX_CACHE_ENTRIES = 4;

    private const int MAX_CACHE_ENTRY_BYTES = 8_388_608;

    private const int MAX_CACHE_BYTES = 16_777_216;

    private NNTPService $nntp;

    private bool $releaseScopeActive = false;

    /**
     * @var array<string, array{success: bool, data: string|null, groupUnavailable: bool, error: string|null}>
     */
    private array $releaseCache = [];

    private int $cachedBytes = 0;

    private int $logicalRequests = 0;

    private int $networkRequests = 0;

    private int $cacheHits = 0;

    private int $bytesDownloaded = 0;

    private int $bytesReused = 0;

    public function __construct(
        private readonly ProcessingConfiguration $config,
        ?NNTPService $nntp = null
    ) {
        $this->nntp = $nntp ?? new NNTPService;
    }

    public function beginReleaseScope(): void
    {
        $this->clearReleaseScope();
        $this->releaseScopeActive = true;
    }

    public function finishReleaseScope(): DownloadMetrics
    {
        $metrics = new DownloadMetrics(
            logicalRequests: $this->logicalRequests,
            networkRequests: $this->networkRequests,
            cacheHits: $this->cacheHits,
            bytesDownloaded: $this->bytesDownloaded,
            bytesReused: $this->bytesReused,
        );

        $this->clearReleaseScope();

        return $metrics;
    }

    /**
     * Download binary content from usenet using message IDs.
     *
     * @param  array<int|string, mixed>|string  $messageIDs  Single or array of message IDs
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
                'message_id_count' => count($messageIDs),
                'request_fingerprint' => $this->downloadFingerprint($messageIDs, $groupName),
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
                    'message_id_count' => count($messageIDs),
                    'request_fingerprint' => $this->downloadFingerprint($messageIDs, $groupName),
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
     * Download content for a specific processing step.
     *
     * @param  array<int|string, mixed>|string  $messageIDs
     * @return array{success: bool, data: string|null, groupUnavailable: bool, error: string|null}
     */
    public function download(
        DownloadKind $kind,
        array|string $messageIDs,
        string $groupName = '',
        ?int $releaseId = null,
        ?string $fileTitle = null
    ): array {
        $messageIdList = is_array($messageIDs) ? array_values($messageIDs) : [$messageIDs];
        $cacheKey = $this->downloadFingerprint($messageIdList, $groupName);

        if ($this->releaseScopeActive) {
            $this->logicalRequests++;

            if (isset($this->releaseCache[$cacheKey])) {
                $cached = $this->releaseCache[$cacheKey];
                $this->cacheHits++;
                $this->bytesReused += is_string($cached['data']) ? strlen($cached['data']) : 0;

                return $cached;
            }
        }

        if ($this->config->debugMode) {
            Log::debug('Attempting download', [
                'kind' => $kind->value,
                'release_id' => $releaseId,
                'file_title' => $fileTitle,
                'message_id_count' => count($messageIdList),
                'request_fingerprint' => $cacheKey,
                'group' => $groupName,
            ]);
        }

        if ($this->releaseScopeActive && $messageIdList !== [] && $messageIdList !== ['']) {
            $this->networkRequests++;
        }

        $result = $this->downloadByMessageIDs($messageIDs, $groupName, $releaseId);

        if ($this->releaseScopeActive && $result['success'] && is_string($result['data'])) {
            $byteSize = strlen($result['data']);
            $this->bytesDownloaded += $byteSize;
            $this->rememberSuccessfulDownload($cacheKey, $result, $byteSize);
        }

        if (! $result['success'] && $this->config->debugMode) {
            Log::debug('Download failed', [
                'kind' => $kind->value,
                'release_id' => $releaseId,
                'file_title' => $fileTitle,
                'message_id_count' => count($messageIdList),
                'request_fingerprint' => $cacheKey,
                'group' => $groupName,
                'error' => $result['error'],
            ]);
        }

        return $result;
    }

    /**
     * @param  list<mixed>  $messageIds
     */
    private function downloadFingerprint(array $messageIds, string $groupName): string
    {
        return hash('sha256', serialize([
            'message_ids' => array_map(static fn (mixed $messageId): string => (string) $messageId, $messageIds),
            'group' => $groupName,
            'alternate' => $this->config->alternateNNTP,
        ]));
    }

    /**
     * @param  array{success: bool, data: string|null, groupUnavailable: bool, error: string|null}  $result
     */
    private function rememberSuccessfulDownload(string $cacheKey, array $result, int $byteSize): void
    {
        if ($byteSize > self::MAX_CACHE_ENTRY_BYTES
            || count($this->releaseCache) >= self::MAX_CACHE_ENTRIES
            || ($this->cachedBytes + $byteSize) > self::MAX_CACHE_BYTES
        ) {
            return;
        }

        $this->releaseCache[$cacheKey] = $result;
        $this->cachedBytes += $byteSize;
    }

    private function clearReleaseScope(): void
    {
        $this->releaseScopeActive = false;
        $this->releaseCache = [];
        $this->cachedBytes = 0;
        $this->logicalRequests = 0;
        $this->networkRequests = 0;
        $this->cacheHits = 0;
        $this->bytesDownloaded = 0;
        $this->bytesReused = 0;
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
