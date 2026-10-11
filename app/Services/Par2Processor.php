<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Par2RenameOutcome;
use App\Models\Category;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\UsenetGroup;
use App\Services\NameFixing\NameFixingService;
use App\Services\NNTP\NNTPService;
use dariusiii\rarinfo\Par2Info;
use Illuminate\Support\Carbon;

/**
 * Service responsible for parsing PAR2 data and applying results to releases.
 */
class Par2Processor
{
    private NameFixingService $nameFixingService;

    private Par2Info $par2Info;

    private bool $addPar2;

    private bool $alternateNNTP;

    private bool $existingNameConfirmed = false;

    public function __construct(NameFixingService $nameFixingService, Par2Info $par2Info, bool $addPar2, bool $alternateNNTP)
    {
        $this->nameFixingService = $nameFixingService;
        $this->par2Info = $par2Info;
        $this->addPar2 = $addPar2;
        $this->alternateNNTP = $alternateNNTP;
    }

    /**
     * Attempt to get a better name from a PAR2 file and categorize the release.
     *
     * @param  string  $messageID  MessageID from NZB file.
     * @param  int  $relID  ID of the release.
     * @param  int  $groupID  Group ID of the release.
     * @param  NNTPService  $nntp  Class NNTPService
     * @param  int  $show  Only show result or apply it.
     */
    public function parseFromMessage(string $messageID, int $relID, int $groupID, NNTPService $nntp, int $show): bool
    {
        $outcome = $this->parseForRename($messageID, $relID, $groupID, $nntp, $show);

        return $outcome === Par2RenameOutcome::Renamed || $this->existingNameConfirmed;
    }

    /** @param null|callable(): bool $canPersist */
    public function parseForRename(string $messageID, int $relID, int $groupID, NNTPService $nntp, int $show, ?callable $canPersist = null, bool $expectedPar2 = false): Par2RenameOutcome
    {
        $this->existingNameConfirmed = false;
        $this->nameFixingService->reset();
        if ($messageID === '') {
            return Par2RenameOutcome::Retryable;
        }

        $query = Release::query()
            ->where(['isrenamed' => 0, 'id' => $relID])
            ->select(['id', 'groups_id', 'categories_id', 'name', 'searchname', 'postdate', 'id as releases_id'])
            ->first();

        if ($query === null) {
            return Par2RenameOutcome::NoMatch;
        }

        // Only get a new name if the category is OTHER.
        $attemptRename = \in_array((int) $query['categories_id'], Category::OTHERS_GROUP, true);
        $foundName = ! $attemptRename;

        // Get the PAR2 file.
        $par2 = $nntp->getMessages(UsenetGroup::getNameByID($groupID), $messageID, $this->alternateNNTP);
        if (NNTPService::isError($par2) || ! is_string($par2) || $par2 === '') {
            return Par2RenameOutcome::Retryable;
        }

        if ($canPersist !== null && ! $canPersist()) {
            return Par2RenameOutcome::Deferred;
        }

        // Put the PAR2 into Par2Info, check if there's an error.
        $this->par2Info->setData($par2);
        if ($this->par2Info->error) {
            return $expectedPar2 || str_contains($par2, Par2Info::PACKET_MARKER)
                ? Par2RenameOutcome::Retryable : Par2RenameOutcome::NoMatch;
        }

        // Get the file list from Par2Info.
        $files = $this->par2Info->getFileList();
        if (! array_filter($files, static fn (array $file): bool => isset($file['name']))) {
            return Par2RenameOutcome::Retryable;
        }
        if (\count($files) > 0) {
            $filesAdded = 0;

            // Loop through the files.
            foreach ($files as $file) {
                if (! isset($file['name'])) {
                    continue;
                }

                // If we found a name and added 20 files, stop.
                if ($foundName === true && $filesAdded > 20) {
                    break;
                }

                if ($this->addPar2) {
                    // Add to release files.
                    if ($filesAdded < 21 && ReleaseFile::query()->where(['releases_id' => $relID, 'name' => $file['name']])->first() === null) {
                        // Try to add the files to the DB.
                        if (ReleaseFile::addReleaseFiles(
                            $relID,
                            $file['name'],
                            $file['size'] ?? 0,
                            $query['postdate'] !== null ? Carbon::createFromFormat('Y-m-d H:i:s', $query['postdate']) : now(),
                            0,
                            $file['hash_16K'] ?? ''
                        )) {
                            $filesAdded++;
                        }
                    }
                } else {
                    $filesAdded++;
                }

                // Try to get a new name.
                if ($foundName === false) {
                    $query['textstring'] = $file['name'];
                    if ($this->nameFixingService->checkName($query, true, 'PAR2, ', true, (bool) $show, markProcessed: false)) {
                        $foundName = true;
                    }
                }
            }

            // If we found some files.
            if ($filesAdded > 0) {
                // Update the file count with the new file count + old file count.
                Release::whereId($relID)->increment('rarinnerfilecount', $filesAdded);
            }
            if ($foundName === true) {
                if (! $attemptRename) {
                    $this->existingNameConfirmed = true;

                    return Par2RenameOutcome::NoMatch;
                }

                return Par2RenameOutcome::Renamed;
            }
        }

        return Par2RenameOutcome::NoMatch;
    }
}
