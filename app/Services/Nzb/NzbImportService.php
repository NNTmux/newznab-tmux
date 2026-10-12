<?php

declare(strict_types=1);

namespace App\Services\Nzb;

use App\Enums\NzbImportStatus;
use App\Jobs\CollectReleaseFingerprintMetadata;
use App\Models\Category;
use App\Models\Predb;
use App\Models\Release;
use App\Models\UsenetGroup;
use App\Services\BlacklistService;
use App\Services\Categorization\CategorizationService;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\ReleaseCleaningService;
use App\Services\Releases\ReleaseDuplicateFinder;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\NzbManifestReader;
use App\Support\Utf8;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for importing NZB files into the database.
 */
class NzbImportService
{
    protected BlacklistService $blacklistService;

    protected ReleaseCleaningService $releaseCleaner;

    protected \stdClass|bool $site;

    protected mixed $crossPostt;

    protected CategorizationService $category;

    protected ReleaseDuplicateFinder $releaseDuplicateFinder;

    /**
     * List of all the group names/ids in the DB.
     *
     * @var array<string, mixed>
     */
    protected array $allGroups;

    /**
     * Was this run from the browser?
     */
    protected bool $browser;

    /**
     * Return value for browser.
     */
    protected string $retVal;

    /**
     * Guid of the current releases.
     */
    protected string $relGuid;

    protected ?int $relId = null;

    protected ?string $verificationCandidateIdentity = null;

    public mixed $echoCLI;

    public NzbService $nzb;

    /**
     * Seconds to wait for another import of the same articles to finish (dedupe disabled).
     */
    protected int $identityLockWaitSeconds = 30;

    /**
     * Lease for the article lock; it must outlive the whole scan, insert and NZB write
     * ({@see Lock::block()} releases it as soon as the import finishes).
     */
    protected int $identityLockSeconds = 3600;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(array $options = [])
    {
        $this->echoCLI = config('nntmux.echocli');
        $this->blacklistService = new BlacklistService;
        $this->category = new CategorizationService;
        $this->nzb = app(NzbService::class);
        $this->releaseCleaner = new ReleaseCleaningService;
        $this->releaseDuplicateFinder = app(ReleaseDuplicateFinder::class);
        $this->crossPostt = app(ConfigurationProvider::class)->ingestion()->crossPostHours !== '' ? app(ConfigurationProvider::class)->ingestion()->crossPostHours : 2;

        // Set properties from options
        $this->browser = isset($options['Browser']) ? (bool) $options['Browser'] : false;
        $this->retVal = '';
    }

    /**
     * Begin importing NZB files.
     *
     * @param  null|callable(array{path:string,status:NzbImportStatus,release_id:int|null,release_guid:string|null,candidate_identity:string|null}):void  $resultCallback
     *
     * @throws FileNotFoundException
     */
    public function beginImport(
        mixed $filesToProcess,
        bool $useNzbName = false,
        bool $delete = false,
        bool $deleteFailed = false,
        int $source = 1,
        ?callable $resultCallback = null,
    ): bool|string {
        // Get all the groups in the DB.
        if (! $this->getAllGroups()) {
            if ($this->browser) {
                return $this->retVal;
            }

            return false;
        }

        $start = now()->toImmutable()->format('Y-m-d H:i:s');
        $nzbsImported = $nzbsSkipped = $nzbsDuplicate = 0;

        // Convert all files to string paths and filter to only process NZB files
        $nzbFiles = [];
        foreach ($filesToProcess as $file) {
            $filePath = $file instanceof \SplFileInfo ? $file->getPathname() : (string) $file;
            $lowerFilePath = strtolower($filePath);
            if (Str::endsWith($lowerFilePath, '.nzb') || Str::endsWith($lowerFilePath, '.nzb.gz')) {
                $nzbFiles[] = $filePath;
            }
        }

        if (empty($nzbFiles)) {
            $this->echoOut('No NZB files found to process.');
            if ($this->browser) {
                return $this->retVal;
            }

            return false;
        }

        $totalFilesFiltered = count($filesToProcess) - count($nzbFiles);
        if ($totalFilesFiltered > 0) {
            $this->echoOut("Filtered out {$totalFilesFiltered} non-NZB files. Processing ".count($nzbFiles).' NZB files.');
        }

        $reportResult = static function (
            string $path,
            NzbImportStatus $status,
            ?int $releaseId = null,
            ?string $releaseGuid = null,
            ?string $candidateIdentity = null,
        ) use ($resultCallback): void {
            if ($resultCallback === null) {
                return;
            }

            $resultCallback([
                'path' => $path,
                'status' => $status,
                'release_id' => $status === NzbImportStatus::Inserted ? $releaseId : null,
                'release_guid' => $status === NzbImportStatus::Inserted ? $releaseGuid : null,
                'candidate_identity' => $status === NzbImportStatus::Queued ? $candidateIdentity : null,
            ]);
        };

        // Loop over the NZB file names only.
        foreach ($nzbFiles as $nzbFilePath) {
            // Check if the file is really there.
            if (File::isFile($nzbFilePath)) {
                $this->relGuid = '';
                $this->relId = null;
                $this->verificationCandidateIdentity = null;

                // Get the contents of the NZB file as a string.
                if (Str::endsWith(strtolower($nzbFilePath), '.nzb.gz')) {
                    $nzbString = unzipGzipFile($nzbFilePath);
                } else {
                    $nzbString = File::get($nzbFilePath);
                }

                if ($nzbString === false) {
                    $this->echoOut('ERROR: Unable to read: '.$nzbFilePath);
                    $reportResult($nzbFilePath, NzbImportStatus::Failed);

                    if ($deleteFailed) {
                        File::delete($nzbFilePath);
                    }
                    $nzbsSkipped++;

                    continue;
                }

                // Load it as an XML object.
                $nzbXML = @simplexml_load_string($nzbString);
                if ($nzbXML === false || strtolower($nzbXML->getName()) !== 'nzb') {
                    $this->echoOut('ERROR: Unable to load NZB XML data: '.$nzbFilePath);
                    $reportResult($nzbFilePath, NzbImportStatus::Failed);

                    if ($deleteFailed) {
                        File::delete($nzbFilePath);
                    }
                    $nzbsSkipped++;

                    continue;
                }

                // Try to insert the NZB details into the DB and store the compressed NZB. With release
                // dedupe disabled both run under a per-article lock (see withArticleIdentityLock()).
                $nzbFileName = $useNzbName === true ? $this->deriveReleaseNameFromNzbPath($nzbFilePath) : '';
                $lockFingerprint = ! (bool) config('nntmux.release_dedupe_enabled', true) ? NzbArticleFingerprint::fromXml($nzbXML) : null;
                $stored = false;
                $path = null;
                try {
                    [$importStatus, $stored, $path] = $this->withArticleIdentityLock(
                        $lockFingerprint,
                        function () use ($nzbXML, $nzbFileName, $source, $nzbString): array {
                            $status = $this->scanNZBFile($nzbXML, $nzbFileName, $source);

                            return $status === NzbImportStatus::Inserted
                                ? [$status, ...$this->storeImportedNzb($nzbString)]
                                : [$status, false, null];
                        }
                    );
                } catch (LockTimeoutException) {
                    // Another worker is importing the same articles: not a failure, so never delete it.
                    $this->echoOut('Another import of the same articles is in progress; leaving it for a retry: '.$nzbFilePath);
                    $nzbsSkipped++;

                    continue;
                } catch (\Throwable $exception) {
                    Log::error('NZB import failed while scanning or inserting a release.', [
                        'path' => $nzbFilePath,
                        'exception' => $exception,
                    ]);

                    $message = 'ERROR: Problem inserting: '.$nzbFilePath;
                    if (! $this->browser) {
                        $message .= ': '.$exception->getMessage();
                    }

                    $this->echoOut($message);
                    $importStatus = NzbImportStatus::Failed;
                }

                if ($importStatus === NzbImportStatus::Queued) {
                    $reportResult($nzbFilePath, $importStatus, candidateIdentity: $this->verificationCandidateIdentity);
                    $nzbsImported++;
                    if ($delete) {
                        File::delete($nzbFilePath);
                    }
                } elseif ($importStatus === NzbImportStatus::Inserted) {
                    if (! $stored) {
                        $destination = $path ?? $this->relGuid;
                        $this->echoOut('ERROR: Problem compressing NZB file to: '.$destination);
                        $reportResult($nzbFilePath, NzbImportStatus::Failed);

                        if ($deleteFailed) {
                            File::delete($nzbFilePath);
                        }
                        $nzbsSkipped++;
                    } else {
                        $reportResult($nzbFilePath, NzbImportStatus::Inserted, $this->relId, $this->relGuid);
                        if ($this->relId !== null) {
                            try {
                                CollectReleaseFingerprintMetadata::dispatch($this->relId);
                            } catch (\Throwable $dispatchException) {
                                Log::warning('Could not queue imported release fingerprint metadata collection.', [
                                    'release_id' => $this->relId,
                                    'exception' => $dispatchException::class,
                                ]);
                            }
                        }

                        if ($delete) {
                            // Remove the nzb file.
                            File::delete($nzbFilePath);
                        }

                        $nzbsImported++;
                    }
                } else {
                    $reportResult($nzbFilePath, $importStatus);

                    if ($importStatus === NzbImportStatus::Duplicate) {
                        $nzbsDuplicate++;

                        if ($delete || $deleteFailed) {
                            File::delete($nzbFilePath);
                        }
                    } else {
                        if (in_array($importStatus, [NzbImportStatus::Blacklisted, NzbImportStatus::NoGroup], true)) {
                            if ($delete || $deleteFailed) {
                                File::delete($nzbFilePath);
                            }
                        } else {
                            $this->echoOut('ERROR: Failed to insert NZB!');
                            if ($deleteFailed) {
                                File::delete($nzbFilePath);
                            }
                        }

                        $nzbsSkipped++;
                    }
                }
            } else {
                $this->echoOut('ERROR: Unable to fetch: '.$nzbFilePath);
                $reportResult($nzbFilePath, NzbImportStatus::Failed);
                $nzbsSkipped++;
            }
        }
        $this->echoOut(
            'Processed '.
            $nzbsImported.
            ' NZBs in '.
            now()->diffInSeconds($start, true).' seconds, '.
            $nzbsSkipped.
            ' NZBs were skipped, '.
            $nzbsDuplicate.
            ' were duplicates.'
        );

        if ($this->browser) {
            return $this->retVal;
        }

        return true;
    }

    /**
     * Derive a clean release name from an NZB file path by stripping the
     * trailing ".nzb"/".nzb.gz" wrapper and a known trailing media/container
     * extension (e.g. ".mkv", ".mp4"). Example:
     *   "Show - S01E07 [1080p].mkv.nzb.gz" => "Show - S01E07 [1080p]".
     */
    protected function deriveReleaseNameFromNzbPath(string $path): string
    {
        $name = basename($path);

        // Strip trailing .nzb or .nzb.gz (case-insensitive).
        $name = preg_replace('/\.nzb(\.gz)?$/i', '', $name) ?? $name;

        // Strip one trailing known media/container extension from the allow-list.
        $name = preg_replace(
            '/\.(mkv|mp4|avi|mov|wmv|flv|m4v|ts|webm|mpg|mpeg|iso|m2ts|ogm|divx|vob)$/i',
            '',
            $name
        ) ?? $name;

        // Tidy up any trailing dots/whitespace left behind.
        return rtrim($name, ". \t\n\r\0\x0B");
    }

    protected function writeCompressedNzb(string $path, string $contents): bool
    {
        // Write next to the destination and rename into place, so a crash mid-write never leaves a
        // truncated gzip at the final path (which would look like a stored NZB).
        $temporaryPath = NzbService::temporaryNzbPathFor($path);
        $handle = @gzopen($temporaryPath, 'w5');
        if ($handle === false) {
            Log::error('Unable to open imported NZB destination for writing.', ['path' => $path]);

            return false;
        }

        $stored = false;

        try {
            $written = gzwrite($handle, $contents);
            if ($written !== \strlen($contents) || ! gzclose($handle)) {
                Log::error('Unable to write the complete imported NZB file.', ['path' => $path]);

                return false;
            }

            $handle = null;
            if (! $this->moveCompressedNzbIntoPlace($temporaryPath, $path)) {
                Log::error('Unable to move the imported NZB file into place.', ['path' => $path]);

                return false;
            }

            $stored = File::isFile($path);

            return $stored;
        } catch (\Throwable $exception) {
            Log::error('Imported NZB compression failed.', [
                'path' => $path,
                'exception' => $exception,
            ]);

            return false;
        } finally {
            if (\is_resource($handle)) {
                @gzclose($handle);
            }
            if (File::isFile($temporaryPath)) {
                File::delete($temporaryPath);
            }
            if (! $stored) {
                File::delete($path);
            }
        }
    }

    protected function moveCompressedNzbIntoPlace(string $temporaryPath, string $finalPath): bool
    {
        return @rename($temporaryPath, $finalPath);
    }

    /**
     * Scan and process an NZB file.
     *
     * @throws \Exception
     */
    protected function scanNZBFile(mixed &$nzbXML, mixed $nzbFileName = '', mixed $source = ''): NzbImportStatus
    {
        $binary_names = [];
        $totalFiles = $totalSize = $groupID = 0;
        $isBlackListed = $groupName = $firstName = $posterName = $postDate = false;

        // Go through the NZB, get the details, look if it's blacklisted, look if we have the groups.
        foreach ($nzbXML->file as $file) {
            $binary_names[] = $file['subject'];
            $totalFiles++;
            $groupID = -1;

            // Get the nzb info.
            if ($firstName === false) {
                $firstName = (string) $file->attributes()->subject;
            }
            if ($posterName === false) {
                $posterName = (string) $file->attributes()->poster;
            }
            if ($postDate === false) {
                $postDate = Carbon::createFromTimestamp((string) $file->attributes()->date, date_default_timezone_get())->format('Y-m-d H:i:s');
            }

            // Make a fake message array to use to check the blacklist.
            $msg = ['Subject' => (string) $file->attributes()->subject, 'From' => (string) $file->attributes()->poster, 'Message-ID' => ''];

            // Get the group names, group_id, check if it's blacklisted.
            $groupArr = [];
            foreach ($file->groups->group as $group) {
                $group = $this->normalizeGroupName($group);

                // If group_id is -1 try to get a group_id.
                if ($groupID === -1) {
                    if (array_key_exists($group, $this->allGroups)) {
                        $groupID = $this->allGroups[$group];
                        if (! $groupName) {
                            $groupName = $group;
                        }
                    } else {
                        $group = UsenetGroup::isValidGroup($group);
                        if ($group !== false) {
                            $groupID = UsenetGroup::addGroup([
                                'name' => $group,
                                'description' => 'Added by NZBimport script.',
                                'backfill_target' => 1,
                                'minfilestoformrelease' => '',
                                'minsizetoformrelease' => '',
                                'first_record' => 0,
                                'last_record' => 0,
                                'active' => 0,
                                'backfill' => 0,
                            ]);
                            $this->allGroups[$group] = $groupID;

                            $this->echoOut("Adding missing group: ($group)");
                        }
                    }
                }
                // Add all the found groups to an array.
                $groupArr[] = $group;

                // Check if this NZB is blacklisted (only if group is valid).
                if ($group !== false && $this->blacklistService->isBlackListed($msg, $group)) {
                    $isBlackListed = true;
                    break;
                }
            }

            // If we found a group and it's not blacklisted.
            if ($groupID !== -1 && ! $isBlackListed) {
                // Get the size of the release.
                if (\count($file->segments->segment) > 0) {
                    foreach ($file->segments->segment as $segment) {
                        $totalSize += (int) $segment->attributes()->bytes;
                    }
                }
            } else {
                if ($isBlackListed) {
                    $errorMessage = 'Subject is blacklisted: '.mb_convert_encoding(trim($firstName), 'UTF-8', mb_list_encodings());
                } else {
                    $errorMessage = 'No group found for '.$firstName.' (one of '.implode(', ', $groupArr).' are missing';
                }
                $this->echoOut($errorMessage);

                // Persist blacklist usage stats if we matched any rule during this NZB processing
                $this->blacklistService->updateBlacklistUsage($this->blacklistService->getAndClearIdsToUpdate()); // @phpstan-ignore argument.type

                return $isBlackListed ? NzbImportStatus::Blacklisted : NzbImportStatus::NoGroup;
            }
        }

        // After scanning all files, persist any matched whitelist/blacklist usage
        $this->blacklistService->updateBlacklistUsage($this->blacklistService->getAndClearIdsToUpdate()); // @phpstan-ignore argument.type

        // Try to insert the NZB details into the DB.
        return $this->insertNZB(
            [
                'subject' => $firstName,
                'useFName' => $nzbFileName,
                'postDate' => empty($postDate) ? now()->format('Y-m-d H:i:s') : $postDate,
                'from' => empty($posterName) ? '' : $posterName,
                'groups_id' => $groupID,
                'groupName' => $groupName,
                'totalFiles' => $totalFiles,
                'totalSize' => $totalSize,
                'nzbCategoryId' => $this->resolveNzbCategoryId($nzbXML),
                'articleFingerprint' => NzbArticleFingerprint::fromXml($nzbXML),
                'verificationXml' => config('release_verification.required', true) ? $nzbXML->asXML() : null,
            ]
        );
    }

    /**
     * Record the article fingerprint of a just-inserted release; if that fails, remove the release
     * so it is not left marked as having an NZB that was never stored.
     *
     * @throws \Throwable
     */
    protected function persistArticleFingerprint(int $releaseId, ?string $fingerprint): void
    {
        try {
            ReleaseArticleFingerprintStore::store($releaseId, $fingerprint);
        } catch (\Throwable $exception) {
            // Delete through the model so ReleaseObserver also removes the search document.
            Release::query()->find($releaseId)?->delete();

            throw $exception;
        }
    }

    /**
     * Store the compressed NZB for the release just inserted; on failure remove the release so its
     * fingerprint is never left without an NZB.
     *
     * @return array{0: bool, 1: ?string} Whether the NZB was stored, and its path when known
     */
    protected function storeImportedNzb(string $nzbString): array
    {
        $path = null;
        try {
            $path = $this->nzb->getNzbPath($this->relGuid, 0, true);
            $stored = $this->writeCompressedNzb($path, $nzbString);
        } catch (\Throwable $exception) {
            Log::error('NZB import failed while storing the compressed file.', [
                'guid' => $this->relGuid,
                'path' => $path,
                'exception' => $exception,
            ]);
            $stored = false;
        }

        if (! $stored) {
            // Delete through the model so ReleaseObserver also removes the search document.
            Release::query()->where('guid', $this->relGuid)->first()?->delete();
        }

        return [$stored, $path];
    }

    /**
     * With release dedupe disabled, an NZB is still a duplicate when an existing release references
     * exactly the same articles (e.g. the same file imported twice), matched by article_fingerprint.
     */
    protected function findIdenticalArticleUpload(int $totalSize, ?string $fingerprint): ?Release
    {
        if ($fingerprint === null) {
            return null;
        }

        foreach (ReleaseArticleFingerprintStore::candidates($fingerprint) as $persisted) {
            // A fingerprint without its stored NZB (e.g. an import that crashed mid-write) is ignored.
            if ($this->nzb->nzbPath((string) $persisted->guid) !== false) {
                return $persisted;
            }
        }

        if ($totalSize <= 0) {
            return null;
        }

        // Releases written before article_fingerprint existed: compare stored NZBs of the same size.
        $candidates = Release::query()
            ->where('size', $totalSize)
            ->when(ReleaseArticleFingerprintStore::columnExists(), static fn ($query) => $query->whereNull(ReleaseArticleFingerprintStore::COLUMN))
            ->select(['id', 'guid', 'name', 'searchname', 'fromname', 'size'])
            ->lazyById(200);

        foreach ($candidates as $candidate) {
            $contents = $this->nzb->readNzbContents((string) $candidate->guid);
            if ($contents !== false && NzbArticleFingerprint::fromContents($contents) === $fingerprint) {
                return $candidate;
            }
        }

        return null;
    }

    protected function resolveNzbCategoryId(mixed $nzbXML): ?int
    {
        if (! $nzbXML instanceof \SimpleXMLElement) {
            return null;
        }

        $categoryMetadata = [];
        foreach ($nzbXML->head->meta ?? [] as $meta) {
            if (mb_strtolower(trim((string) $meta['type'])) !== 'category') {
                continue;
            }

            $value = trim((string) $meta);
            if ($value !== '') {
                $categoryMetadata[] = $value;
            }
        }

        if ($categoryMetadata === []) {
            return null;
        }

        $activeCategories = Category::query()
            ->where('status', Category::STATUS_ACTIVE)
            ->get(['id', 'title']);
        $resolvedCategoryIds = [];

        foreach ($categoryMetadata as $value) {
            if (ctype_digit($value)) {
                $matchingCategories = $activeCategories->filter(
                    static fn (Category $category): bool => $category->id === (int) $value
                );
            } else {
                $normalizedValue = mb_strtolower($value);
                $matchingCategories = $activeCategories->filter(
                    static fn (Category $category): bool => mb_strtolower($category->title) === $normalizedValue
                );
            }

            if ($matchingCategories->count() !== 1) {
                return null;
            }

            $resolvedCategoryIds[] = (int) $matchingCategories->first()->id;
        }

        $resolvedCategoryIds = array_values(array_unique($resolvedCategoryIds));

        return count($resolvedCategoryIds) === 1 ? $resolvedCategoryIds[0] : null;
    }

    /**
     * Serialise the identity check, insert and NZB file write for one set of articles, so two
     * concurrent imports of the same NZB cannot both pass {@see findIdenticalArticleUpload()}
     * and a fingerprint is never visible without its stored NZB (dedupe disabled).
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     *
     * @throws LockTimeoutException When another import holds the lock for longer than the wait.
     */
    protected function withArticleIdentityLock(?string $fingerprint, callable $callback): mixed
    {
        if ($fingerprint === null) {
            return $callback();
        }

        $store = Cache::store((string) config('nntmux.release_dedupe_lock_store', 'database'))->getStore();
        if (! $store instanceof LockProvider) {
            throw new \RuntimeException('The release_dedupe_lock_store cache store does not support locks.');
        }

        return $store->lock('nzb-import-article:'.$fingerprint, $this->identityLockSeconds)
            ->block($this->identityLockWaitSeconds, $callback);
    }

    /**
     * Insert the NZB details into the database.
     *
     * @throws \Exception
     */
    protected function insertNZB(mixed $nzbDetails): NzbImportStatus
    {
        if (config('release_verification.required', true)) {
            if (! is_string($nzbDetails['verificationXml'] ?? null)) {
                throw new \RuntimeException('NZB must be queued for download verification before publication.');
            }
            $subject = $this->normalizeImportedSubject((string) $nzbDetails['subject']);
            $info = (object) ['groups_id' => $nzbDetails['groups_id'],
                'searchname' => $nzbDetails['useFName'] !== '' ? $nzbDetails['useFName'] : $subject,
                'fromname' => $nzbDetails['from'], 'postdate' => $nzbDetails['postDate'], 'metadata_source' => 'import',
                'import_name_override' => $nzbDetails['useFName'] !== '', 'original_subject' => $subject];
            $categoryId = $nzbDetails['nzbCategoryId'] ?? null;
            if (is_int($categoryId) && $categoryId > 0
                && Category::query()->where('id', $categoryId)->where('status', Category::STATUS_ACTIVE)->exists()) {
                $info->categories_id = $categoryId;
            }
            $manifest = app(NzbManifestReader::class)->read($nzbDetails['verificationXml'], $info);
            $manifest['identity'] = sha1('import:'.($nzbDetails['articleFingerprint'] ?? $nzbDetails['verificationXml']));
            app(CandidateRepository::class)->enqueue($manifest);
            $this->verificationCandidateIdentity = $manifest['identity'];
            $this->echoOut('NZB queued for download verification.');

            return NzbImportStatus::Queued;
        }

        // Make up a GUID for the release.
        $this->relGuid = Str::uuid()->toString();

        $subject = $this->normalizeImportedSubject((string) $nzbDetails['subject']);

        $renamed = 0;
        $cleanedMeta = null;
        if ($nzbDetails['useFName'] !== '') {
            $cleanName = $nzbDetails['useFName'];
            $renamed = 1;
        } else {
            $cleanedMeta = $this->releaseCleaner->releaseCleaner($subject, $nzbDetails['from'], $nzbDetails['groupName']);
            if (\is_array($cleanedMeta)) {
                $cleanName = $cleanedMeta['cleansubject'] ?? $subject;
                $renamed = (isset($cleanedMeta['properlynamed']) && $cleanedMeta['properlynamed'] === true) ? 1 : 0;
            } else {
                $cleanName = \is_string($cleanedMeta) ? $cleanedMeta : $subject;
            }
        }

        if (! \is_string($cleanName)) {
            $cleanName = $subject;
        }

        $preIdFromCleaner = 0;
        if (\is_array($cleanedMeta) && isset($cleanedMeta['predb']) && (int) $cleanedMeta['predb'] > 0) {
            $preIdFromCleaner = (int) $cleanedMeta['predb'];
        }

        $predbIdInt = $preIdFromCleaner;
        if ($predbIdInt === 0 && $cleanName !== '') {
            $preMatch = Predb::matchPre($cleanName);
            if ($preMatch !== false) {
                $cleanName = $preMatch['title'];
                $predbIdInt = (int) $preMatch['predb_id'];
                $renamed = 1;
            }
        }

        $escapedSubject = $subject;
        $escapedFromName = $nzbDetails['from'];
        $escapedSearchName = Utf8::clean($cleanName);
        if ($escapedSearchName === '') {
            $escapedSearchName = $escapedSubject;
        }

        [$dupeCheck, $dupeReason] = $this->releaseDuplicateFinder->findDuplicate(
            $escapedSubject,
            $escapedSearchName,
            $predbIdInt,
            (int) $nzbDetails['totalSize']
        );

        if ($dupeCheck === null && ! (bool) config('nntmux.release_dedupe_enabled', true)) {
            $dupeCheck = $this->findIdenticalArticleUpload((int) $nzbDetails['totalSize'], $nzbDetails['articleFingerprint'] ?? null);
            $dupeReason = $dupeCheck !== null ? 'identical_articles' : null;
        }

        if ($dupeCheck !== null) {
            Log::info('NZB import skipped as duplicate', [
                'reason' => $dupeReason,
                'matched_release_id' => $dupeCheck->id,
                'new_searchname' => $escapedSearchName,
                'existing_searchname' => $dupeCheck->searchname,
                'new_size' => (int) $nzbDetails['totalSize'],
                'existing_size' => (int) $dupeCheck->size,
                'new_fromname' => $escapedFromName,
                'existing_fromname' => $dupeCheck->fromname,
                'new_name' => $escapedSubject,
                'existing_name' => $dupeCheck->name,
            ]);
            $this->echoOut('This release is already in our DB so skipping: '.$subject);

            return NzbImportStatus::Duplicate;
        }

        $categoryId = $nzbDetails['nzbCategoryId'];
        if (! \is_int($categoryId)) {
            $determinedCategory = $this->category->determineCategory($nzbDetails['groups_id'], $cleanName, $escapedFromName);
            $categoryId = (int) $determinedCategory['categories_id'];
        }

        $relID = Release::insertRelease(
            [
                'name' => $escapedSubject,
                'searchname' => $escapedSearchName,
                'totalpart' => $nzbDetails['totalFiles'],
                'groups_id' => $nzbDetails['groups_id'],
                'guid' => $this->relGuid,
                'postdate' => $nzbDetails['postDate'],
                'fromname' => $escapedFromName,
                'size' => $nzbDetails['totalSize'],
                'categories_id' => $categoryId,
                'isrenamed' => $renamed,
                'predb_id' => $predbIdInt,
                'nzbstatus' => NzbService::NZB_ADDED,
            ]
        );

        if ($relID === null) {
            $this->echoOut('ERROR: Problem inserting: '.$subject);

            return NzbImportStatus::Failed;
        }

        $this->relId = (int) $relID;
        $this->persistArticleFingerprint($this->relId, $nzbDetails['articleFingerprint'] ?? null);

        return NzbImportStatus::Inserted;
    }

    protected function normalizeImportedSubject(string $subject): string
    {
        $partLess = preg_replace('/(\(\d+\/\d+\))*$/', 'yEnc', $subject) ?? $subject;

        return mb_convert_encoding(trim(preg_replace('/yEnc.*$/i', 'yEnc', $partLess) ?? $partLess), 'UTF-8', mb_list_encodings());
    }

    protected function normalizeGroupName(\SimpleXMLElement|string $group): string
    {
        return trim((string) $group);
    }

    /**
     * Get all groups in the DB.
     */
    protected function getAllGroups(): bool
    {
        $this->allGroups = [];
        $groups = UsenetGroup::query()->get(['id', 'name']);

        if ($groups instanceof \Traversable) {
            foreach ($groups as $group) {
                $this->allGroups[$group['name']] = $group['id'];
            }
        }

        if (\count($this->allGroups) === 0) {
            $this->echoOut('You have no groups in your database!');

            return false;
        }

        return true;
    }

    /**
     * Echo message to browser or CLI.
     */
    protected function echoOut(string $message): void
    {
        if ($this->browser) {
            $this->retVal .= $message.'<br />';
        } elseif ($this->echoCLI) {
            cli()->notice($message);
        }
    }
}
