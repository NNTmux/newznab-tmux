<?php

namespace Blacklight\libraries;

/**
 * Class ForkingImportNZB.
 *
 * Multi-processing of NZB Import.
 */
class ForkingImportNZB extends Forking
{
    /**
     * @var string
     */
    private $importPath;

    /**
     * @var
     */
    private $deleteComplete;

    /**
     * @var
     */
    private $deleteFailed;

    /**
     * @var
     */
    private $useFileName;

    /**
     * @var
     */
    private $maxPerProcess;

    /**
     * ForkingImportNZB constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->importPath = 'php misc/testing/nzb-import.php ';
    }

    /**
     * @param $folder
     * @param $maxProcesses
     * @param $deleteComplete
     * @param $deleteFailed
     * @param $useFileName
     * @param $maxPerProcess
     */
    public function start($folder, $maxProcesses, $deleteComplete, $deleteFailed, $useFileName, $maxPerProcess): void
    {
        $startTime = now()->timestamp;
        $directories = glob($folder.'/*', GLOB_ONLYDIR);

        $this->_workCount = \count($directories);

        if ((int) $this->_workCount === 0) {
            $this->colorCli->error('No sub-folders were found in your specified folder ('.$folder.').');
            exit();
        }

        if (config('nntmux.echocli')) {
            $this->colorCli->header(
                'Multi-processing started at '.now()->toDayDateTimeString().' with '.$this->_workCount.
                ' job(s) to do using a max of '.$maxProcesses.' child process(es).'
            );
        }

        $this->deleteComplete = $deleteComplete;
        $this->deleteFailed = $deleteFailed;
        $this->useFileName = $useFileName;
        $this->maxPerProcess = $maxPerProcess;

        foreach ($directories as $directory) {
            $pool = Pool::create();
            $pool->concurrency($maxProcesses);
            $pool->add(function () use ($directory) {
                $this->importPath.'"'.
                        $directory.'" '.
                        $this->deleteComplete.' '.
                        $this->deleteFailed.' '.
                        $this->useFileName.' '.
                        $this->maxPerProcess;
            })->then(function () use ($pool) {
                echo $pool->status();
            })->catch(function (Throwable $exception) {
                // Handle exception
            });
            $pool->wait();
        }

        if (config('nntmux.echocli')) {
            $this->colorCli->header(
                'Multi-processing for import finished in '.(now()->timestamp - $startTime).
                    ' seconds at '.now()->toDayDateTimeString().'.'.PHP_EOL
                );
        }
    }
}
