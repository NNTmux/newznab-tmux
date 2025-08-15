<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\UsenetGroup;
use Blacklight\NameFixer;
use Blacklight\NNTP;
use dariusiii\rarinfo\Par2Info;
use Illuminate\Support\Carbon;

/**
 * Service responsible for parsing PAR2 data and applying results to releases.
 */
class Par2Processor
{
    private NameFixer $nameFixer;
    private Par2Info $par2Info;
    private bool $addPar2;
    private bool $alternateNNTP;

    public function __construct(NameFixer $nameFixer, Par2Info $par2Info, bool $addPar2, bool $alternateNNTP)
    {
        $this->nameFixer = $nameFixer;
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
     * @param  NNTP  $nntp  Class NNTP
     * @param  int  $show  Only show result or apply it.
     */
    public function parseFromMessage(string $messageID, int $relID, int $groupID, NNTP $nntp, int $show): bool
    {
        if ($messageID === '') {
            return false;
        }

        $query = Release::query()
            ->where(['isrenamed' => 0, 'id' => $relID])
            ->select(['id', 'groups_id', 'categories_id', 'name', 'searchname', 'postdate', 'id as releases_id'])
            ->first();

        if ($query === null) {
            return false;
        }

        // Only get a new name if the category is OTHER.
        $foundName = true;
        if (\in_array((int) $query['categories_id'], Category::OTHERS_GROUP, false)) {
            $foundName = false;
        }

        // Get the PAR2 file.
        $par2 = $nntp->getMessages(UsenetGroup::getNameByID($groupID), $messageID, $this->alternateNNTP);
        if ($nntp->isError($par2)) {
            return false;
        }

        // Put the PAR2 into Par2Info, check if there's an error.
        $this->par2Info->setData($par2);
        if ($this->par2Info->error) {
            return false;
        }

        // Get the file list from Par2Info.
        $files = $this->par2Info->getFileList();
        if ($files !== false && \count($files) > 0) {
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
                            $file['size'],
                            $query['postdate'] !== null ? Carbon::createFromFormat('Y-m-d H:i:s', $query['postdate']) : now(),
                            0,
                            $file['hash_16K']
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
                    if ($this->nameFixer->checkName($query, 1, 'PAR2, ', 1, $show)) {
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
                return true;
            }
        }

        return false;
    }
}

