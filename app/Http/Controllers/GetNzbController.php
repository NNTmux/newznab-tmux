<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\User;
use App\Models\UserDownload;
use App\Models\UsersRelease;
use Blacklight\NZB;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

class GetNzbController extends BasePageController
{
    private const int BUFFER_SIZE = 1000000;

    private const string NZB_SUFFIX = '.nzb';

    /**
     * Download NZB file(s) for authenticated users
     *
     * @return Application|ResponseFactory|\Illuminate\Foundation\Application|JsonResponse|Response|ZipStream|StreamedResponse
     *
     * @throws Exception
     */
    public function getNzb(Request $request, ?string $guid = null)
    {
        // Normalize guid parameter
        $this->normalizeGuidParameter($request, $guid);

        // Authenticate and authorize user
        $userData = $this->authenticateUser($request);
        if (! \is_array($userData)) {
            return $userData; // Return error response
        }

        ['uid' => $uid, 'userName' => $userName, 'maxDownloads' => $maxDownloads, 'rssToken' => $rssToken] = $userData;

        // Check download limits
        $downloadLimitError = $this->checkDownloadLimit($uid, $maxDownloads);
        if ($downloadLimitError !== null) {
            return $downloadLimitError;
        }

        // Validate and sanitize ID parameter
        $releaseId = $this->validateAndSanitizeId($request);
        if (! \is_string($releaseId)) {
            return $releaseId; // Return error response
        }

        // Handle zip download request
        if ($this->isZipRequest($request)) {
            return $this->handleZipDownload($request, $uid, $userName, $maxDownloads, $releaseId);
        }

        // Handle single NZB download
        return $this->handleSingleNzbDownload($request, $uid, $rssToken, $releaseId);
    }

    /**
     * Normalize the guid parameter into the request
     */
    private function normalizeGuidParameter(Request $request, ?string $guid): void
    {
        if ($guid !== null && ! $request->has('id')) {
            $request->merge(['id' => $guid]);
        }
    }

    /**
     * Authenticate user via session or RSS token
     *
     * @return array<string, mixed>|Response
     */
    private function authenticateUser(Request $request)
    {
        // Try session authentication first
        if ($request->user()) {
            return $this->getUserDataFromSession();
        }

        // Try RSS token authentication
        return $this->getUserDataFromRssToken($request);
    }

    /**
     * Get user data from authenticated session
     *
     * @return array<string, mixed>|Response
     */
    private function getUserDataFromSession()
    {
        if ($this->userdata->hasRole('Disabled')) {
            return showApiError(101);
        }

        return [
            'uid' => $this->userdata->id,
            'userName' => $this->userdata->username,
            'maxDownloads' => $this->userdata->role->downloadrequests,
            'rssToken' => $this->userdata->api_token,
        ];
    }

    /**
     * Get user data from RSS token
     *
     * @return array<string, mixed>|Response
     */
    private function getUserDataFromRssToken(Request $request)
    {
        if ($request->missing('r')) {
            return showApiError(200);
        }

        $user = User::getByRssToken($request->input('r'));
        if (! $user) {
            return showApiError(100);
        }

        if ($user->hasRole('Disabled')) {
            return showApiError(101);
        }

        return [
            'uid' => $user->id,
            'userName' => $user->username,
            'maxDownloads' => $user->role->downloadrequests,
            'rssToken' => $user->api_token,
        ];
    }

    /**
     * Check if user has exceeded download limits
     *
     * @return Response|null
     *
     * @throws Exception
     */
    private function checkDownloadLimit(int $uid, int $maxDownloads): mixed
    {
        $requests = UserDownload::getDownloadRequests($uid);
        if ($requests > $maxDownloads) {
            return showApiError(501);
        }

        return null;
    }

    /**
     * Validate and sanitize the release ID parameter
     *
     * @return string|Response
     */
    private function validateAndSanitizeId(Request $request)
    {
        $id = $request->input('id');

        if (empty($id)) {
            return showApiError(200, 'Parameter id is required');
        }

        // Remove .nzb suffix if present
        $sanitizedId = str_ireplace(self::NZB_SUFFIX, '', $id);
        $request->merge(['id' => $sanitizedId]);

        return $sanitizedId;
    }

    /**
     * Check if this is a zip download request
     */
    private function isZipRequest(Request $request): bool
    {
        return $request->has('zip') && $request->input('zip') === '1';
    }

    /**
     * Handle zip download of multiple releases
     *
     * @return JsonResponse|ZipStream|StreamedResponse
     *
     * @throws Exception
     */
    private function handleZipDownload(
        Request $request,
        int $uid,
        string $userName,
        int $maxDownloads,
        string $releaseId
    ) {
        $guids = explode(',', $releaseId);
        $guidCount = \count($guids);

        // Check if zip download would exceed limits
        $requests = UserDownload::getDownloadRequests($uid);
        if ($requests + $guidCount > $maxDownloads) {
            return showApiError(501);
        }

        $zip = getStreamingZip($guids);
        if ($zip === '') {
            return response()->json(['message' => 'Unable to create .zip file'], 404);
        }

        // Update statistics
        $this->updateZipDownloadStatistics($request, $uid, $guids);

        Log::channel('zipped')->info("User {$userName} downloaded zipped files from site with IP: {$request->ip()}");

        return $zip;
    }

    /**
     * Update statistics for zip downloads
     */
    private function updateZipDownloadStatistics(Request $request, int $uid, array $guids): void
    {
        $guidCount = \count($guids);
        User::incrementGrabs($uid, $guidCount);

        $shouldDeleteFromCart = $request->has('del') && (int) $request->input('del') === 1;

        foreach ($guids as $guid) {
            Release::updateGrab($guid);
            UserDownload::addDownloadRequest($uid, $guid);

            if ($shouldDeleteFromCart) {
                UsersRelease::delCartByUserAndRelease($guid, $uid);
            }
        }
    }

    /**
     * Handle single NZB file download
     *
     * @return Response|StreamedResponse
     */
    private function handleSingleNzbDownload(
        Request $request,
        int $uid,
        string $rssToken,
        string $releaseId
    ) {
        // Get NZB file path and validate
        $nzbPath = (new NZB)->getNZBPath($releaseId);
        if (! File::exists($nzbPath)) {
            return showApiError(300, 'NZB file not found!');
        }

        // Get release data
        $releaseData = Release::getByGuid($releaseId);
        if ($releaseData === null) {
            return showApiError(300, 'Release not found!');
        }

        // Update statistics
        $this->updateDownloadStatistics($request, $uid, $releaseId, $releaseData->id);

        // Build response headers
        $headers = $this->buildNzbHeaders($releaseId, $uid, $rssToken, $releaseData);

        // Stream modified NZB content
        $cleanName = $this->sanitizeFilename($releaseData->searchname);

        return response()->streamDownload(
            fn () => $this->streamModifiedNzbContent($nzbPath, $uid),
            $cleanName.self::NZB_SUFFIX,
            $headers
        );
    }

    /**
     * Update download statistics for single NZB
     */
    private function updateDownloadStatistics(Request $request, int $uid, string $releaseId, int $releaseDbId): void
    {
        Release::updateGrab($releaseId);
        UserDownload::addDownloadRequest($uid, $releaseDbId);
        User::incrementGrabs($uid);

        if ($request->has('del') && (int) $request->input('del') === 1) {
            UsersRelease::delCartByUserAndRelease($releaseId, $uid);
        }
    }

    /**
     * Build headers for NZB download response
     *
     * @return array<string, string>
     */
    private function buildNzbHeaders(string $releaseId, int $uid, string $rssToken, Release $releaseData): array
    {
        $headers = [
            'Content-Type' => 'application/x-nzb',
            'Expires' => now()->addYear()->toRfc7231String(),
            'X-DNZB-Failure' => url('/failed')."?guid={$releaseId}&userid={$uid}&api_token={$rssToken}",
            'X-DNZB-Category' => e($releaseData->category_name),
            'X-DNZB-Details' => url("/details/{$releaseId}"),
        ];

        // Add optional metadata headers
        if (! empty($releaseData->imdbid) && $releaseData->imdbid > 0) {
            $headers['X-DNZB-MoreInfo'] = "http://www.imdb.com/title/tt{$releaseData->imdbid}";
        } elseif (! empty($releaseData->tvdb) && $releaseData->tvdb > 0) {
            $headers['X-DNZB-MoreInfo'] = "http://www.thetvdb.com/?tab=series&id={$releaseData->tvdb}";
        }

        if ((int) $releaseData->nfostatus === 1) {
            $headers['X-DNZB-NFO'] = url("/nfo/{$releaseId}");
        }

        $headers['X-DNZB-RCode'] = '200';
        $headers['X-DNZB-RText'] = 'OK, NZB content follows.';

        return $headers;
    }

    /**
     * Stream modified NZB content with user-specific modifications
     */
    private function streamModifiedNzbContent(string $nzbPath, int $uid): void
    {
        $fileHandle = gzopen($nzbPath, 'rb');
        if ($fileHandle === false) {
            return;
        }

        $buffer = '';
        $lastChunk = false;

        // Stream and modify content in chunks
        while (! gzeof($fileHandle)) {
            $chunk = gzread($fileHandle, self::BUFFER_SIZE);
            if ($chunk === false) {
                break;
            }

            // Combine with previous buffer to handle boundaries
            $buffer .= $chunk;

            // Check if this is the last chunk
            if (gzeof($fileHandle)) {
                $lastChunk = true;
            }

            // Process buffer
            if ($lastChunk) {
                // On last chunk, modify poster attributes
                $buffer = preg_replace('/file poster="/', 'file poster="'.$uid.'-', $buffer);
                echo $buffer;
            } else {
                // For intermediate chunks, keep some data in buffer to handle boundaries
                $safeLength = mb_strlen($buffer) - 1000; // Keep last 1KB in buffer
                if ($safeLength > 0) {
                    $output = mb_substr($buffer, 0, $safeLength);
                    $output = preg_replace('/file poster="/', 'file poster="'.$uid.'-', $output);
                    echo $output;
                    $buffer = mb_substr($buffer, $safeLength);
                }
            }
        }

        gzclose($fileHandle);
    }

    /**
     * Sanitize filename for download
     */
    private function sanitizeFilename(string $filename): string
    {
        return str_replace([',', ' ', '/', '\\'], '_', $filename);
    }
}
