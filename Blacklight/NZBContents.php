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
        $this->lookuppar2 = (int) Settings::settingValue('..lookuppar2') === 1;
        $this->alternateNNTP = config('nntmux_nntp.use_alternate_nntp_server');
    }

    /**
     * Look for an .nfo file in the NZB, return the NFO message id.
     *
     *
     * @return bool|mixed
     *
     * @throws \Exception
     */
    public function getNfoFromNZB($guid, $relID, $groupID, $groupName): mixed
    {
        $fetchedBinary = false;

        $messageID = $this->parseNZB($guid, $relID, $groupID, true);
        if ($messageID !== false) {
            $fetchedBinary = $this->nntp->getMessages($groupName, $messageID['id'], $this->alternateNNTP);
            if ($this->nntp->isError($fetchedBinary)) {
                // NFO download failed, increment attempts.
                Release::query()->where('id', $relID)->decrement('nfostatus');
                if ($this->echooutput) {
                    echo 'f';
                }

                return false;
            }
            if ($this->nfo->isNFO($fetchedBinary, $guid) === true) {
                if ($this->echooutput) {
                    echo $messageID['hidden'] === false ? '+' : '*';
                }
            } else {
                if ($this->echooutput) {
                    echo '-';
                }
                Release::query()->where('id', $relID)->update(['nfostatus' => Nfo::NFO_NONFO]);
                $fetchedBinary = false;
            }
        } else {
            if ($this->echooutput) {
                echo '-';
            }
            Release::query()->where('id', $relID)->update(['nfostatus' => Nfo::NFO_NONFO]);
        }

        return $fetchedBinary;
    }

    /**
     * Gets the completion from the NZB, optionally looks if there is an NFO/PAR2 file.
     *
     *
     * @return array|false
     *
     * @throws \Exception
     */
    public function parseNZB($guid, $relID, $groupID, bool $nfoCheck = false): bool|array
    {
        $nzbFile = $this->LoadNZB($guid);
        if ($nzbFile !== false) {
            $messageID = $hiddenID = '';
            $actualParts = $artificialParts = 0;
            $foundPAR2 = $this->lookuppar2 === false;
            $foundNFO = $hiddenNFO = $nfoCheck === false;

            foreach ($nzbFile->file as $nzbcontents) {
                foreach ($nzbcontents->segments->segment as $segment) {
                    $actualParts++;
                }

                $subject = (string) $nzbcontents->attributes()->subject;
                if (preg_match('/(\d+)\)$/', $subject, $parts)) {
                    $artificialParts += $parts[1];
                }

                if (($foundNFO === false) && preg_match('/\.\b(nfo|inf|ofn)\b(?![ .-])/i', $subject)) {
                    $messageID = (string) $nzbcontents->segments->segment;
                    $foundNFO = true;
                }

                if ($foundNFO === false && $hiddenNFO === false && preg_match('/\(1\/1\)$/i', $subject) && ! preg_match('/\.(apk|bat|bmp|cbr|cbz|cfg|css|csv|cue|db|dll|doc|epub|exe|gif|htm|ico|idx|ini'.'|jpg|lit|log|m3u|mid|mobi|mp3|nib|nzb|odt|opf|otf|par|par2|pdf|psd|pps|png|ppt|r\d{2,4}'.'|rar|sfv|srr|sub|srt|sql|rom|rtf|tif|torrent|ttf|txt|vb|vol\d+\+\d+|wps|xml|zip)/i', $subject)) {
                    $hiddenID = (string) $nzbcontents->segments->segment;
                    $hiddenNFO = true;
                }

                if ($foundPAR2 === false && preg_match('/\.(par[&2" ]|\d{2,3}").+\(1\/1\)$/i', $subject) && $this->pp->parsePAR2((string) $nzbcontents->segments->segment, $relID, $groupID, $this->nntp, 1) === true) {
                    Release::query()->where('id', $relID)->update(['proc_par2' => 1]);
                    $foundPAR2 = true;
                }
            }

            if ($artificialParts === 0 || $actualParts === 0) {
                $completion = 0;
            } else {
                $completion = ($actualParts / $artificialParts) * 100;
            }
            if ($completion > 100) {
                $completion = 100;
            }

            Release::query()->where('id', $relID)->update(['completion' => $completion]);

            if ($foundNFO === true && \strlen($messageID) > 1) {
                return ['hidden' => false, 'id' => $messageID];
            }

            if ($hiddenNFO === true && \strlen($hiddenID) > 1) {
                return ['hidden' => true, 'id' => $hiddenID];
            }
        }

        return false;
    }

    public function LoadNZB($guid): \SimpleXMLElement|bool
    {
        // Fetch the NZB location using the GUID.
        $nzbPath = $this->nzb->NZBPath($guid);
        if ($nzbPath === false) {
            return false;
        }
        $nzbContents = Utility::unzipGzipFile($nzbPath);
        if (! $nzbContents) {
            if ($this->echooutput) {
                echo PHP_EOL.
                    'Unable to decompress: '.
                    $nzbPath.
                    ' - '.
                    fileperms($nzbPath).
                    ' - may have bad file permissions, skipping.'.
                    PHP_EOL;
            }

            return false;
        }

        $nzbFile = @simplexml_load_string($nzbContents);
        if (! $nzbFile) {
            if ($this->echooutput) {
                echo PHP_EOL."Unable to load NZB: $guid appears to be an invalid NZB, skipping.".PHP_EOL;
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
        $nzbFile = $this->LoadNZB($guid);
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
