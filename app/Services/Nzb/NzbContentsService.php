<?php

declare(strict_types=1);

namespace App\Services\Nzb;

use App\Enums\Par2RenameOutcome;
use App\Models\Release;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\NfoService;
use App\Services\NNTP\NNTPService;
use App\Services\PostProcessService;
use App\Support\Par2RenameProgress;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Service for processing NZB contents - extracting NFO files, PAR2 information,
 * and calculating release completion from NZB files.
 */
class NzbContentsService
{
    protected NzbService $nzbService;

    protected NzbParserService $parserService;

    protected NNTPService $nntp;

    protected NfoService $nfo;

    protected PostProcessService $postProcessService;

    protected bool $lookupPar2;

    protected bool $echoOutput;

    protected bool $alternateNntp;

    /** @var array{candidates_probed: int, articles_received: int} */
    private array $par2Stats = ['candidates_probed' => 0, 'articles_received' => 0];

    private bool $par2StructureValid = true;

    public function __construct(
        ?NzbService $nzbService = null,
        ?NzbParserService $parserService = null,
        ?NNTPService $nntp = null,
        ?NfoService $nfo = null,
        ?PostProcessService $postProcessService = null
    ) {
        $this->echoOutput = (bool) config('nntmux.echocli');
        $this->nzbService = $nzbService ?? app(NzbService::class);
        $this->parserService = $parserService ?? app(NzbParserService::class);
        $this->nntp = $nntp ?? new NNTPService;
        $this->nfo = $nfo ?? new NfoService;
        $this->postProcessService = $postProcessService ?? app(PostProcessService::class);
        $this->lookupPar2 = (int) app(ConfigurationProvider::class)->postProcessing()->lookupPar2 === 1;
        $this->alternateNntp = (bool) config('nntmux_nntp.alternate_article_fallback');
    }

    /**
     * Look for an .nfo file in the NZB, download it, verify it, and return the content.
     *
     * @param  string  $guid  The release GUID.
     * @param  int  $relID  The release ID.
     * @param  int  $groupID  The group ID.
     * @param  string  $groupName  The group name.
     * @return string|false The verified NFO content as a string, or false if not found, download failed, or verification failed.
     *
     * @throws \Exception If NNTP operations fail.
     */
    public function getNfoFromNzb(string $guid, int $relID, int $groupID, string $groupName): string|false
    {
        // Step 1: Attempt to find a potential NFO message ID
        $messageID = $this->parseNzb($guid, $relID, $groupID, true);

        // If no NFO message ID found
        if ($messageID === false || ! isset($messageID['id'])) {
            if ($this->echoOutput) {
                echo '-';
            }
            // Make sure we set status to NFO_NONFO
            Release::query()->where('id', $relID)->update(['nfostatus' => NfoService::NFO_NONFO]);

            return false;
        }

        // Step 2: Attempt to download the potential NFO content
        $fetchedBinary = $this->nntp->getMessages($groupName, $messageID['id'], $this->alternateNntp);

        // Check if download failed
        if ($this->nntp->isError($fetchedBinary)) {
            // NFO download failed, decrement attempts to allow retries
            Release::query()->where('id', $relID)->decrement('nfostatus');
            if ($this->echoOutput) {
                echo 'f';
            }

            return false;
        }

        // Step 3: Verify if the downloaded content is actually an NFO file
        if ($this->nfo->isNFO($fetchedBinary, $guid)) {
            // NFO verification successful
            if ($this->echoOutput) {
                // Show if it was found via explicit name (+) or potentially hidden (*)
                echo $messageID['hidden'] === false ? '+' : '*';
            }

            return $fetchedBinary;
        }

        // Step 4: Handle verification failure - not a valid NFO
        if ($this->echoOutput) {
            echo '-';
        }
        Release::query()->where('id', $relID)->update(['nfostatus' => NfoService::NFO_NONFO]);

        return false;
    }

    /**
     * Gets the completion from the NZB, optionally looks if there is an NFO/PAR2 file.
     *
     * @param  string  $guid  The release GUID.
     * @param  int  $relID  The release ID.
     * @param  int  $groupID  The group ID.
     * @param  bool  $nfoCheck  Whether to specifically look for an NFO file.
     * @return array<string, mixed>|false An array containing NFO message ID and hidden status, or false if not found/error.
     *
     * @throws \Exception If NNTP operations fail.
     */
    public function parseNzb(string $guid, int $relID, int $groupID, bool $nfoCheck = false): bool|array
    {
        $nzbFile = $this->loadNzb($guid);
        if ($nzbFile === false) {
            return false;
        }

        $messageID = $hiddenID = '';
        $actualParts = $artificialParts = 0;
        // Initialize foundPAR2 based on settings; if lookupPar2 is false, we don't need to find one.
        $foundPAR2 = $this->lookupPar2 === false;
        // Initialize NFO flags based on whether we are checking for NFOs.
        $foundNFO = $hiddenNFO = $nfoCheck === false;
        $nfoMessageId = null; // Store potential NFO message ID here

        foreach ($nzbFile->file as $nzbContents) {
            $segmentCountInFile = 0;
            $firstSegmentId = null; // Initialize here for each file
            foreach ($nzbContents->segments->segment as $segment) {
                $actualParts++;
                $segmentCountInFile++;
                // Store the first segment ID of the current file, potentially useful for NFO/PAR2
                if ($segmentCountInFile === 1) {
                    $firstSegmentId = (string) $segment;
                }
            }

            $subject = (string) $nzbContents->attributes()->subject;

            // Calculate artificial parts from subject
            $artificialParts += $this->parserService->extractPartsTotal($subject);

            // --- NFO Detection ---
            // Check for explicit NFO files first (with enhanced patterns)
            if ($nfoCheck && ! $foundNFO && isset($firstSegmentId)) {
                $nfoDetection = $this->parserService->detectNfoFile($subject);
                if ($nfoDetection !== false) {
                    $nfoMessageId = ['hidden' => $nfoDetection['hidden'], 'id' => $firstSegmentId, 'priority' => $nfoDetection['priority']];
                    $foundNFO = true;
                }
            }

            // Check for potential "hidden" NFOs with improved detection
            // Only consider this if an explicit NFO wasn't found yet
            if ($nfoCheck && ! $foundNFO && ! $hiddenNFO && isset($firstSegmentId)) {
                $hiddenNfoDetection = $this->parserService->detectHiddenNfoFile($subject, $segmentCountInFile);
                if ($hiddenNfoDetection !== false) {
                    $nfoMessageId = ['hidden' => $hiddenNfoDetection['hidden'], 'id' => $firstSegmentId, 'priority' => $hiddenNfoDetection['priority']];
                    $hiddenNFO = true;
                }
            }

            // --- PAR2 Detection ---
            // Look specifically for the .par2 index file (often small, but not always 1/1)
            if ($this->lookupPar2 && ! $foundPAR2 && isset($firstSegmentId) && $this->parserService->detectPar2IndexFile($subject)) {
                // Attempt to parse the PAR2 file using its first segment ID
                if ($this->postProcessService->parsePAR2($firstSegmentId, $relID, $groupID, $this->nntp, 1) === true) {
                    Release::query()->where('id', $relID)->update(['proc_par2' => 1]);
                    $foundPAR2 = true;
                }
            }
        } // End foreach $nzbFile->file

        // Calculate completion
        $completion = $this->calculateCompletion($actualParts, $artificialParts);

        Release::query()->where('id', $relID)->update(['completion' => $completion]);

        // If NFO check was requested, return the found message ID (prioritizing explicit)
        if ($nfoCheck && $nfoMessageId !== null && \strlen($nfoMessageId['id']) > 1) {
            return $nfoMessageId;
        }

        // If NFO check was requested but nothing suitable was found
        if ($nfoCheck && $nfoMessageId === null) {
            // Update status to indicate no NFO was found in the NZB structure
            Release::query()->where('id', $relID)->update(['nfostatus' => NfoService::NFO_NONFO]);

            return false;
        }

        // If NFO check was not requested, the function's primary goal might be just completion/PAR2 update.
        return false;
    }

    /**
     * Loads and parses an NZB file based on a GUID.
     *
     * @param  string  $guid  The release GUID to locate the NZB file
     * @return SimpleXMLElement|false The parsed NZB file as SimpleXMLElement or false on failure
     */
    public function loadNzb(string $guid): SimpleXMLElement|false
    {
        // Fetch the NZB file path using the GUID
        $nzbPath = $this->nzbService->nzbPath($guid);
        if ($nzbPath === false) {
            return false;
        }

        // Attempt to decompress the NZB file
        $nzbContents = unzipGzipFile($nzbPath);
        if (empty($nzbContents)) {
            if ($this->echoOutput) {
                $perms = fileperms($nzbPath);
                $formattedPerms = $perms !== false ? decoct($perms & 0777) : 'unknown';
                echo PHP_EOL."Unable to decompress: {$nzbPath} - {$formattedPerms} - may have bad file permissions, skipping.".PHP_EOL;
            }

            return false;
        }

        return $this->parserService->parseNzbXml($nzbContents, $this->echoOutput, $guid);
    }

    /**
     * Attempts to get the release name from a par2 file.
     *
     * @throws \Exception
     */
    public function checkPar2(string $guid, int $relID, int $groupID, int $nameStatus, int $show): bool
    {
        return $this->checkPar2Result($guid, $relID, $groupID, $nameStatus, $show) === Par2RenameOutcome::Renamed;
    }

    public function checkPar2Result(string $guid, int $relID, int $groupID, int $nameStatus, int $show, ?float $deadline = null): Par2RenameOutcome
    {
        $this->par2Stats = ['candidates_probed' => 0, 'articles_received' => 0];
        if ($nameStatus !== 1) {
            return Par2RenameOutcome::Deferred;
        }

        $lock = null;
        $claimed = false;
        try {
            $cache = Cache::store((string) config('tmux.fix_names.cache_store', 'redis'));
            $store = $cache->getStore();
            if (! $store instanceof LockProvider) {
                throw new RuntimeException('The PAR2 rename cache store must support atomic locks.');
            }
            $lease = max(600, 10 * max(
                (int) config('nntmux_nntp.socket_timeout', 120),
                (int) config('nntmux_nntp.alternate_server_socket_timeout', 120)
            ));
            $lock = $store->lock('rename:par2:'.$relID, $lease);
            if (! $lock instanceof Lock) {
                throw new RuntimeException('The PAR2 rename cache lock must support renewal.');
            }
            $claimed = $lock->get();
            if (! $claimed) {
                return Par2RenameOutcome::Deferred;
            }

            $release = Release::query()->where('id', $relID)->first(['proc_par2', 'isrenamed']);
            if ($release === null || (int) $release->proc_par2 === 1) {
                Par2RenameProgress::forget($relID);

                return Par2RenameOutcome::NoMatch;
            }
            if ((int) $release->isrenamed === 1) {
                if (! $this->refreshPar2Claim($lock)) {
                    return Par2RenameOutcome::Deferred;
                }
                Release::query()->where('id', $relID)->update(['proc_par2' => 1]);
                Par2RenameProgress::forget($relID);

                return Par2RenameOutcome::NoMatch;
            }

            $nzbFile = $this->loadNzb($guid);
            if ($nzbFile === false) {
                return Par2RenameOutcome::Retryable;
            }

            $candidates = $this->par2Candidates($nzbFile);
            $signature = hash('sha256', serialize($candidates));
            $progress = Par2RenameProgress::get($relID);
            $offset = is_array($progress) && ($progress['signature'] ?? '') === $signature
                ? max(0, (int) ($progress['offset'] ?? 0)) : 0;
            $retry = [];
            $savedRetries = is_array($progress) && ($progress['signature'] ?? '') === $signature
                ? ($progress['retry_indexes'] ?? []) : [];
            if (is_array($savedRetries)) {
                foreach ($savedRetries as $index) {
                    if (is_int($index) && $index >= 0 && $index < count($candidates)) {
                        $retry[$index] = true;
                    }
                }
            }
            $pending = $offset < count($candidates) ? range($offset, count($candidates) - 1) : [];
            $pending = array_values(array_unique(array_merge($pending, array_keys($retry))));
            $budget = max(1, (int) config('tmux.fix_names.par2_max_probes', 4));
            $outcome = Par2RenameOutcome::NoMatch;

            foreach ($pending as $index) {
                if ($this->par2Stats['candidates_probed'] >= $budget || ($deadline !== null && now()->getTimestampMs() / 1000 >= $deadline) || ! $this->refreshPar2Claim($lock)) {
                    return Par2RenameOutcome::Deferred;
                }

                $this->par2Stats['candidates_probed']++;
                try {
                    $outcome = $this->postProcessService->parsePAR2ForRename(
                        $candidates[$index]['id'], $relID, $groupID, $this->nntp, $show,
                        function () use ($lock): bool {
                            $this->par2Stats['articles_received']++;

                            return $this->refreshPar2Claim($lock);
                        }, $candidates[$index]['explicit']
                    );
                } catch (Throwable $e) {
                    Log::warning('PAR2 probe remains pending', ['release_id' => $relID, 'candidate_index' => $index, 'error' => $e->getMessage()]);
                    $outcome = Par2RenameOutcome::Retryable;
                }
                if ($outcome === Par2RenameOutcome::Deferred || ! $this->refreshPar2Claim($lock)) {
                    return Par2RenameOutcome::Deferred;
                }
                if ($outcome === Par2RenameOutcome::Renamed) {
                    break;
                }
                if ($outcome === Par2RenameOutcome::Retryable) {
                    unset($retry[$index]);
                    $retry[$index] = true;
                } else {
                    unset($retry[$index]);
                }
                $offset = max($offset, $index + 1);
                if (! Par2RenameProgress::save($relID, $signature, $offset, array_keys($retry))) {
                    return Par2RenameOutcome::NoMatch;
                }
            }

            if ($outcome !== Par2RenameOutcome::Renamed && ($retry !== [] || ! $this->par2StructureValid)) {
                return Par2RenameOutcome::Retryable;
            }

            if (! $this->refreshPar2Claim($lock)) {
                return Par2RenameOutcome::Deferred;
            }
            Release::query()->where('id', $relID)->update(['proc_par2' => 1]);
            Par2RenameProgress::forget($relID);

            return $outcome;
        } catch (Throwable $e) {
            Log::warning('PAR2 rename attempt remains pending', ['release_id' => $relID, 'error' => $e->getMessage()]);

            return Par2RenameOutcome::Retryable;
        } finally {
            if ($claimed && $lock !== null) {
                try {
                    $lock->release();
                } catch (Throwable $e) {
                    Log::warning('Could not release PAR2 rename claim; it will expire', ['release_id' => $relID, 'error' => $e->getMessage()]);
                }
            }
        }
    }

    /** @return array{candidates_probed: int, articles_received: int} */
    public function par2Stats(): array
    {
        return $this->par2Stats;
    }

    /** @phpstan-impure */
    private function refreshPar2Claim(Lock $lock): bool
    {
        return $lock->refresh();
    }

    /** @return list<array{id: string, explicit: bool}> */
    private function par2Candidates(SimpleXMLElement $nzb): array
    {
        $explicit = $volumes = $hidden = [];
        $this->par2StructureValid = count($nzb->file) > 0;
        foreach ($nzb->file as $file) {
            if (count($file->segments->segment) === 0) {
                $this->par2StructureValid = false;

                continue;
            }
            foreach ($file->segments->segment as $entry) {
                if (trim((string) $entry) === '') {
                    $this->par2StructureValid = false;
                }
            }
            $subject = (string) $file->attributes()->subject;
            $segment = $file->segments->segment[0];
            $id = trim((string) $segment);
            if ($id === '') {
                $this->par2StructureValid = false;

                continue;
            }

            if (preg_match('/\.par2(?:["\s]|$)/i', $subject)) {
                if (preg_match('/\.vol\d+\+\d+\.par2(?:["\s]|$)/i', $subject)) {
                    $volumes[$id] = ['id' => $id, 'explicit' => true];
                } else {
                    $explicit[$id] = ['id' => $id, 'explicit' => true];
                }
            } else {
                $knownContent = preg_match('/\.(?:rar|r\d{2,3}|\d{3}|7z|zip|mkv|mp4|avi|m2ts|ts|iso|nfo|sfv|srr|srs|jpg|png|mp3|flac)(?:["\s]|$)/i', $subject);
                $singlePart = count($file->segments->segment) === 1 && $this->parserService->extractPartsTotal($subject) <= 1;
                if (! $knownContent || $singlePart) {
                    $bytes = filter_var((string) $segment->attributes()->bytes, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($bytes === false) {
                        $this->par2StructureValid = false;

                        continue;
                    }
                    if ($bytes <= max(1, (int) config('tmux.fix_names.par2_max_probe_bytes', 2097152))) {
                        $hidden[$id] = ['id' => $id, 'explicit' => false, 'bytes' => $bytes];
                    }
                }
            }
        }

        uasort($hidden, static fn (array $first, array $second): int => $first['bytes'] <=> $second['bytes']);

        return array_values($explicit + $volumes + $hidden);
    }

    /**
     * Calculate the completion percentage from actual and expected parts.
     *
     * @param  int  $actualParts  The actual number of parts found
     * @param  int  $artificialParts  The expected number of parts from subject
     * @return float The completion percentage (0-100)
     */
    protected function calculateCompletion(int $actualParts, int $artificialParts): float
    {
        // Avoid division by zero and handle cases where parts info might be missing/incorrect
        if ($artificialParts > 0) {
            return min(100, ($actualParts / $artificialParts) * 100);
        } elseif ($actualParts > 0) {
            // If artificial parts couldn't be determined, but we have actual parts,
            // we can't calculate completion accurately based on subject.
            return 0;
        }

        // If both are zero (e.g., empty NZB or parsing issue), completion is 0.
        return 0;
    }

    /**
     * Set NNTP service instance.
     */
    public function setNntp(NNTPService $nntp): void
    {
        $this->nntp = $nntp;
    }

    /**
     * Set NFO handler instance.
     */
    public function setNfo(NfoService $nfo): void
    {
        $this->nfo = $nfo;
    }

    /**
     * Set echo output setting.
     */
    public function setEchoOutput(bool $echo): void
    {
        $this->echoOutput = $echo;
    }

    /**
     * Get echo output setting.
     */
    public function getEchoOutput(): bool
    {
        return $this->echoOutput;
    }
}
