<?php

namespace Blacklight;

use App\Models\Release;
use App\Models\Settings;
use Blacklight\processing\PostProcess;
use Blacklight\utility\Utility;

/**
 * Gets information contained within the NZB.
 *
 *
 * Class NZBContents
 */
class NZBContents
{
    protected NNTP $nntp;

    protected Nfo $nfo;

    protected PostProcess $pp;

    protected NZB $nzb;

    protected bool $lookuppar2;

    /**
     * @var bool
     */
    protected mixed $echooutput;

    protected bool $alternateNNTP;

    public function __construct()
    {

        $this->echooutput = config('nntmux.echocli');
        $this->nntp = new NNTP;
        $this->nfo = new Nfo;
        $this->pp = new PostProcess;
        $this->nzb = new NZB;
        $this->lookuppar2 = (int) Settings::settingValue('lookuppar2') === 1;
        $this->alternateNNTP = config('nntmux_nntp.use_alternate_nntp_server');
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
    public function getNfoFromNZB(string $guid, int $relID, int $groupID, string $groupName): string|false
    {
        // Step 1: Attempt to find a potential NFO message ID
        $messageID = $this->parseNZB($guid, $relID, $groupID, true);

        // If no NFO message ID found
        if ($messageID === false || ! isset($messageID['id'])) {
            if ($this->echooutput) {
                echo '-';
            }
            // Make sure we set status to NFO_NONFO
            Release::query()->where('id', $relID)->update(['nfostatus' => Nfo::NFO_NONFO]);

            return false;
        }

        // Step 2: Attempt to download the potential NFO content
        $fetchedBinary = $this->nntp->getMessages($groupName, $messageID['id'], $this->alternateNNTP);

        // Check if download failed
        if ($this->nntp->isError($fetchedBinary)) {
            // NFO download failed, decrement attempts to allow retries
            Release::query()->where('id', $relID)->decrement('nfostatus');
            if ($this->echooutput) {
                echo 'f';
            }

            return false;
        }

        // Step 3: Verify if the downloaded content is actually an NFO file
        if ($this->nfo->isNFO($fetchedBinary, $guid)) {
            // NFO verification successful
            if ($this->echooutput) {
                // Show if it was found via explicit name (+) or potentially hidden (*)
                echo $messageID['hidden'] === false ? '+' : '*';
            }

            return $fetchedBinary;
        }

        // Step 4: Handle verification failure - not a valid NFO
        if ($this->echooutput) {
            echo '-';
        }
        Release::query()->where('id', $relID)->update(['nfostatus' => Nfo::NFO_NONFO]);

        return false;
    }

    /**
     * Gets the completion from the NZB, optionally looks if there is an NFO/PAR2 file.
     *
     * This version includes improved regex for PAR2 file detection.
     *
     * @param  string  $guid  The release GUID.
     * @param  int  $relID  The release ID.
     * @param  int  $groupID  The group ID.
     * @param  bool  $nfoCheck  Whether to specifically look for an NFO file.
     * @return array|false An array containing NFO message ID and hidden status, or false if not found/error.
     *
     * @throws \Exception If NNTP operations fail.
     */
    public function parseNZB($guid, $relID, $groupID, bool $nfoCheck = false): bool|array
    {
        $nzbFile = $this->loadNzb($guid);
        if ($nzbFile === false) {
            return false;
        }

        $messageID = $hiddenID = '';
        $actualParts = $artificialParts = 0;
        // Initialize foundPAR2 based on settings; if lookuppar2 is false, we don't need to find one.
        $foundPAR2 = $this->lookuppar2 === false;
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
            if (preg_match('/(?:[(\[])?(\d+)[\/)\\]](\d+)[)\]]?$/', $subject, $parts)) {
                // Improve artificial parts calculation robustness (e.g., "[15/20]", "(15/20)")
                if (isset($parts[2]) && (int) $parts[2] > 0) {
                    // Use the total count from the subject if available and seems valid
                    $artificialParts += (int) $parts[2];
                }
            } elseif (preg_match('/(\d+)\)$/', $subject, $parts)) {
                // Fallback to original simple check if the more robust one fails
                $artificialParts += (int) $parts[1];
            }

            // --- NFO Detection ---
            // Check for explicit NFO files first
            if ($nfoCheck && ! $foundNFO && isset($firstSegmentId) && preg_match('/\.\b(nfo|diz|info?)\b(?![.-])/i', $subject)) {
                $nfoMessageId = ['hidden' => false, 'id' => $firstSegmentId];
                $foundNFO = true; // Found an explicit NFO, prioritize this
            }
            // Check for potential "hidden" NFOs (single segment, common name, not other known types)
            // Only consider this if an explicit NFO wasn't found yet
            elseif ($nfoCheck && ! $foundNFO && ! $hiddenNFO && isset($firstSegmentId) && $segmentCountInFile === 1 && preg_match('/\(1\/1\)$/i', $subject)) {
                // Simplified exclusion: check if it's NOT likely another common file type based on extension pattern
                if (! preg_match('/\.(?:exe|com|bat|cmd|scr|dll|zip|rar|[rst]\d{2}|[a-z0-9]{3}|7z|ace|tar|gz|bz2|iso|bin|cue|img|mdf|nrg|dmg|vhd|mp3|flac|ogg|aac|wav|wma|avi|mkv|mp4|mov|wmv|mpg|mpeg|ts|vob|jpg|jpeg|png|gif|bmp|tif|tiff|psd|pdf|doc|docx|xls|xlsx|ppt|pptx|txt|log|xml|html|css|js|php|py|java|c|cpp|h|cs|sql|db|dbf|mdb|accdb|par2?|sfv|md5|sha1|sha256|url|lnk|cfg|ini|inf|sys|tmp|bak|msi|pkg|deb|rpm|apk|ipa)\b/i', $subject)) {
                    $nfoMessageId = ['hidden' => true, 'id' => $firstSegmentId];
                    $hiddenNFO = true; // Found a potential hidden NFO
                }
            }

            // --- PAR2 Detection ---
            // Look specifically for the .par2 index file (often small, but not always 1/1)
            if ($this->lookuppar2 && ! $foundPAR2 && isset($firstSegmentId) && preg_match('/\.par2$/i', $subject)) {
                // Attempt to parse the PAR2 file using its first segment ID
                // Ensure $this->pp is initialized and parsePAR2 exists and accepts these parameters
                if (method_exists($this->pp, 'parsePAR2') && $this->pp->parsePAR2($firstSegmentId, $relID, $groupID, $this->nntp, 1) === true) {
                    Release::query()->where('id', $relID)->update(['proc_par2' => 1]); // Use constant if available
                    $foundPAR2 = true;
                }
            }
        } // End foreach $nzbFile->file

        // Calculate completion
        // Avoid division by zero and handle cases where parts info might be missing/incorrect
        if ($artificialParts > 0) {
            $completion = min(100, ($actualParts / $artificialParts) * 100);
        } elseif ($actualParts > 0) {
            // If artificial parts couldn't be determined, but we have actual parts,
            // we can't calculate completion accurately based on subject.
            // Consider if $actualParts alone means 100% or if it's unknown.
            // Setting to 100 if actual parts > 0 and artificial is 0 might be misleading.
            // Let's default to 0 or another state indicating unknown completion from subject.
            $completion = 0; // Or potentially set a specific status?
        } else {
            // If both are zero (e.g., empty NZB or parsing issue), completion is 0.
            $completion = 0;
        }

        Release::query()->where('id', $relID)->update(['completion' => $completion]);

        // If NFO check was requested, return the found message ID (prioritizing explicit)
        if ($nfoCheck && $nfoMessageId !== null && isset($nfoMessageId['id']) && \strlen($nfoMessageId['id']) > 1) {
            return $nfoMessageId;
        }

        // If NFO check was requested but nothing suitable was found
        if ($nfoCheck && $nfoMessageId === null) {
            // Update status to indicate no NFO was found in the NZB structure
            Release::query()->where('id', $relID)->update(['nfostatus' => Nfo::NFO_NONFO]);

            return false;
        }

        // If NFO check was not requested, the function's primary goal might be just completion/PAR2 update.
        // The original function returned false here. Decide if true (parsed successfully) or false is more appropriate.
        // Returning false maintains original behavior when nfoCheck is false and no NFO ID is returned.
        return false;
    }

    /**
     * Loads and parses an NZB file based on a GUID.
     *
     * @param  string  $guid  The release GUID to locate the NZB file
     * @return \SimpleXMLElement|bool The parsed NZB file as SimpleXMLElement or false on failure
     */
    public function loadNzb(string $guid): \SimpleXMLElement|bool
    {
        // Fetch the NZB file path using the GUID
        $nzbPath = $this->nzb->NZBPath($guid);
        if ($nzbPath === false) {
            return false;
        }

        // Attempt to decompress the NZB file
        $nzbContents = Utility::unzipGzipFile($nzbPath);
        if (empty($nzbContents)) {
            if ($this->echooutput) {
                $perms = fileperms($nzbPath);
                $formattedPerms = $perms !== false ? decoct($perms & 0777) : 'unknown';
                echo PHP_EOL."Unable to decompress: {$nzbPath} - {$formattedPerms} - may have bad file permissions, skipping.".PHP_EOL;
            }

            return false;
        }

        // Safely parse the XML content
        libxml_use_internal_errors(true);
        $nzbFile = simplexml_load_string($nzbContents);

        if ($nzbFile === false) {
            if ($this->echooutput) {
                $errors = libxml_get_errors();
                $errorMsg = ! empty($errors) ? ' - XML error: '.$errors[0]->message : '';
                echo PHP_EOL."Unable to load NZB: {$guid} appears to be an invalid NZB{$errorMsg}, skipping.".PHP_EOL;
                libxml_clear_errors();
            }

            return false;
        }

        return $nzbFile;
    }

    /**
     * Attempts to get the release name from a par2 file.
     *
     *
     * @throws \Exception
     */
    public function checkPAR2(string $guid, int $relID, int $groupID, int $nameStatus, int $show): bool
    {
        $nzbFile = $this->loadNzb($guid);
        if ($nzbFile !== false) {
            foreach ($nzbFile->file as $nzbContents) {
                if ($nameStatus === 1 && $this->pp->parsePAR2((string) $nzbContents->segments->segment, $relID, $groupID, $this->nntp, $show) && preg_match('/\.(par[2" ]|\d{2,3}").+\(1\/1\)/i', (string) $nzbContents->attributes()->subject)) {
                    Release::query()->where('id', $relID)->update(['proc_par2' => 1]);

                    return true;
                }
            }
        }
        if ($nameStatus === 1) {
            Release::query()->where('id', $relID)->update(['proc_par2' => 1]);
        }

        return false;
    }
}
