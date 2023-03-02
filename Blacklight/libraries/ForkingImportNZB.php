<?php

namespace Blacklight\libraries;

use Spatie\Async\Pool;

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

    private $deleteComplete;

    private $deleteFailed;

    private $useFileName;

    private $maxPerProcess;

    /**
     * ForkingImportNZB constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->importPath = PHP_BINARY.' misc/testing/nzb-import.php ';
    }

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
                'Multi-processing started at '.now()->toRfc2822String().' with '.$this->_workCount.
                ' job(s) to do using a max of '.$maxProcesses.' child process(es).'
            );
        }

        $this->deleteComplete = $deleteComplete;
        $this->deleteFailed = $deleteFailed;
        $this->useFileName = $useFileName;
        $this->maxPerProcess = $maxPerProcess;

        foreach ($directories as $directory) {
            $pool = Pool::create()->concurrency($maxProcesses)->timeout(3600);
            $pool->add(function () use ($directory) {
                $this->_executeCommand(
                    $this->importPath.'"'.
                    $directory.'" '.
                    $this->deleteComplete.' '.
                    $this->deleteFailed.' '.
                    $this->useFileName.' '.
                    $this->maxPerProcess
                );
            })->then(function () {
                $this->colorCli->header('Finished importing new nzbs', true);
            })->catch(function (\Throwable $exception) {
                // Handle exception
            });
            $pool->wait();
        }

        if (config('nntmux.echocli')) {
            $this->colorCli->header(
                'Multi-processing for import finished in '.(now()->timestamp - $startTime).
                    ' seconds at '.now()->toRfc2822String().'.'.PHP_EOL
            );
        }
    }
}
