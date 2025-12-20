<?php

namespace Blacklight;

use App\Models\UsenetGroup;
use App\Services\Releases\ReleaseManagementService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Class NZBExport.
 */
class NZBExport
{
    protected mixed $browser;

    protected $retVal;

    protected NZB $nzb;

    protected ReleaseManagementService $releaseManagement;

    protected bool $echoCLI;

    /**
     * NZBExport constructor.
     *
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->echoCLI = config('nntmux.echocli');
        $this->releaseManagement = app(ReleaseManagementService::class);
        $this->nzb = new NZB;
    }

    /**
     * @return bool|string
     */
    public function beginExport($params)
    {
        $gzip = false;
        if ($params[4] === true) {
            $gzip = true;
        }

        $fromDate = $toDate = '';
        $path = $params[0];

        // Check if the path ends with dir separator.
        if (! Str::endsWith($path, '/')) {
            $path .= '/';
        }

        // Check if it's a directory.
        if (! File::isDirectory($path)) {
            $this->echoOut('Folder does not exist: '.$path);

            return $this->returnValue();
        }

        // Check if we can write to it.
        if (! is_writable($path)) {
            $this->echoOut('Folder is not writable: '.$path);

            return $this->returnValue();
        }

        // Check if the from date is the proper format.
        if (isset($params[1]) && $params[1] !== '') {
            if (! $this->checkDate($params[1])) {
                return $this->returnValue();
            }
            $fromDate = $params[1];
        }

        // Check if the to date is the proper format.
        if (isset($params[2]) && $params[2] !== '') {
            if (! $this->checkDate($params[2])) {
                return $this->returnValue();
            }
            $toDate = $params[2];
        }

        // Check if the group_id exists.
        if (isset($params[3]) && $params[3] !== 0) {
            if (! is_numeric($params[3])) {
                $this->echoOut('The group ID is not a number: '.$params[3]);

                return $this->returnValue();
            }
            $groups = UsenetGroup::query()->where('id', $params['3'])->select(['id', 'name'])->get();
            if ($groups === null) {
                $this->echoOut('The group ID is not in the DB: '.$params[3]);

                return $this->returnValue();
            }
        } else {
            $groups = UsenetGroup::query()->select(['id', 'name'])->get();
        }

        $exported = 0;
        // Loop over groups to take less RAM.
        foreach ($groups as $group) {
            $currentExport = 0;
            // Get all the releases based on the parameters.
            $releases = $this->releaseManagement->getForExport($fromDate, $toDate, $group['id']);
            $totalFound = \count($releases);
            if ($totalFound === 0) {
                if ($this->echoCLI) {
                    echo 'No releases found to export for group: '.$group['name'].PHP_EOL;
                }

                continue;
            }
            if ($this->echoCLI) {
                echo 'Found '.$totalFound.' releases to export for group: '.$group['name'].PHP_EOL;
            }

            // Create a path to store the new NZB files.
            $currentPath = $path.$this->safeFilename($group['name']).'/';
            if (! File::isDirectory($currentPath) && ! File::makeDirectory($currentPath) && ! File::isDirectory($currentPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $currentPath));
            }
            foreach ($releases as $release) {
                // Get path to the NZB file.
                $nzbFile = $this->nzb->NZBPath($release['guid']);
                // Check if it exists.
                if ($nzbFile === false) {
                    if ($this->echoCLI) {
                        echo 'Unable to find NZB for release with GUID: '.$release['guid'];
                    }

                    continue;
                }

                // Create path to current file.
                $currentFile = $currentPath.$this->safeFilename($release['searchname']);

                // Check if the user wants them in gzip, copy it if so.
                if ($gzip) {
                    if (! copy($nzbFile, $currentFile.'.nzb.gz')) {
                        if ($this->echoCLI) {
                            echo 'Unable to export NZB with GUID: '.$release['guid'];
                        }

                        continue;
                    }
                    // If not, decompress it and create a file to store it in.
                } else {
                    $nzbContents = unzipGzipFile($nzbFile);
                    if (! $nzbContents) {
                        if ($this->echoCLI) {
                            echo 'Unable to export NZB with GUID: '.$release['guid'];
                        }

                        continue;
                    }
                    $fh = fopen($currentFile.'.nzb', 'w');
                    fwrite($fh, $nzbContents);
                    fclose($fh);
                }

                $currentExport++;

                if ($this->echoCLI && $currentExport % 10 === 0) {
                    echo 'Exported '.$currentExport.' of '.$totalFound.' nzbs for group: '.$group['name']."\r";
                }
            }
            if ($this->echoCLI && $currentExport > 0) {
                echo 'Exported '.$currentExport.' of '.$totalFound.' nzbs for group: '.$group['name'].PHP_EOL;
            }
            $exported += $currentExport;
        }
        if ($exported > 0) {
            $this->echoOut('Exported total of '.$exported.' NZB files to '.$path);
        }

        return $this->returnValue();
    }

    /**
     * @return true
     */
    protected function returnValue(): bool
    {
        return true;
    }

    protected function checkDate($date): bool
    {
        if (! preg_match('/^(\d{2}\/){2}\d{4}$/', $date)) {
            $this->echoOut('Wrong date format: '.$date);

            return false;
        }

        return true;
    }

    protected function echoOut($message): void
    {
        if ($this->echoCLI) {
            echo $message.PHP_EOL;
        }
    }

    protected function safeFilename($filename): string
    {
        return trim(preg_replace('/[^\w\s.-]*/i', '', $filename));
    }
}
