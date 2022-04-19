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
    /**
     * @var \Blacklight\NNTP
     */
    protected $nntp;

    /**
     * @var \Blacklight\Nfo
     */
    protected $nfo;

    /**
     * @var \Blacklight\processing\PostProcess
     */
    protected $pp;

    /**
     * @var \Blacklight\NZB
     */
    protected $nzb;

    /**
     * @var bool
     */
    protected $lookuppar2;

    /**
     * @var bool
     */
    protected $echooutput;

    /**
     * @var bool
     */
    protected $alternateNNTP;

    /**
     * Construct.
     *
     * @param  array  $options
     *                          array(
     *                          'Echo'        => bool        ; To echo to CLI or not.
     *                          'NNTP'        => NNTP        ; Class NNTP.
     *                          'Nfo'         => Nfo         ; Class Nfo.
     *                          'NZB'         => NZB         ; Class NZB.
     *                          'Settings'    => DB          ; Class Blacklight\db\DB.
     *                          'PostProcess' => PostProcess ; Class PostProcess.
     *                          )
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'        => false,
            'NNTP'        => null,
            'Nfo'         => null,
            'NZB'         => null,
            'Settings'    => null,
            'PostProcess' => null,
        ];
        $options += $defaults;

        $this->echooutput = ($options['Echo'] && config('nntmux.echocli'));
        $this->nntp = ($options['NNTP'] instanceof NNTP ? $options['NNTP'] : new NNTP(['Echo' => $this->echooutput]));
        $this->nfo = ($options['Nfo'] instanceof Nfo ? $options['Nfo'] : new Nfo());
        $this->pp = (
            $options['PostProcess'] instanceof PostProcess
            ? $options['PostProcess']
            : new PostProcess(['Echo' => $this->echooutput, 'Nfo' => $this->nfo])
        );
        $this->nzb = ($options['NZB'] instanceof NZB ? $options['NZB'] : new NZB());
        $this->lookuppar2 = (int) Settings::settingValue('..lookuppar2') === 1;
        $this->alternateNNTP = (int) Settings::settingValue('..alternate_nntp') === 1;
    }

    /**
     * Look for an .nfo file in the NZB, return the NFO message id.
     *
     *
     * @param $guid
     * @param $relID
     * @param $groupID
     * @param $groupName
     * @return bool|mixed
     *
     * @throws \Exception
     */
    public function getNfoFromNZB($guid, $relID, $groupID, $groupName)
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
     * @param $guid
     * @param $relID
     * @param $groupID
     * @param  bool  $nfoCheck
     * @return array|false
     *
     * @throws \Exception
     */
    public function parseNZB($guid, $relID, $groupID, $nfoCheck = false)
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

    /**
     * @param $guid
     * @return bool|\SimpleXMLElement
     */
    public function LoadNZB($guid)
    {
        // Fetch the NZB location using the GUID.
        $nzbPath = $this->nzb->NZBPath($guid);
        if ($nzbPath === false) {
            return false;
        }
        $nzbContents = Utility::unzipGzipFile($nzbPath);
        if (! $nzbContents) {
            if ($this->echooutput) {
                echo
                    PHP_EOL.
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
     * Attempts to get the releasename from a par2 file.
     *
     * @param  string  $guid
     * @param  int  $relID
     * @param  int  $groupID
     * @param  int  $nameStatus
     * @param  int  $show
     * @return bool
     *
     * @throws \Exception
     */
    public function checkPAR2($guid, $relID, $groupID, $nameStatus, $show): bool
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
