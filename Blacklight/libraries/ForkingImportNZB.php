<?php

namespace Blacklight\libraries;

use Blacklight\ColorCLI;

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
        $this->importPath = (PHP_BINARY.' '.NN_MISC.'testing'.DS.'nzb-import.php ');
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
        $startTime = microtime(true);
        $directories = glob($folder.'/*', GLOB_ONLYDIR);

        $this->_workCount = \count($directories);

        if ((int) $this->_workCount === 0) {
            ColorCLI::error('No sub-folders were found in your specified folder ('.$folder.').');
            exit();
        }

        if (config('nntmux.echocli')) {
            ColorCLI::header(
                'Multi-processing started at '.date(DATE_RFC2822).' with '.$this->_workCount.
                ' job(s) to do using a max of '.$maxProcesses.' child process(es).'
            );
        }

        $this->deleteComplete = $deleteComplete;
        $this->deleteFailed = $deleteFailed;
        $this->useFileName = $useFileName;
        $this->maxPerProcess = $maxPerProcess;

        $this->max_children_set($maxProcesses);
        $this->register_child_run([0 => $this, 1 => 'importChildWorker']);
        $this->child_max_run_time_set(86400);
        $this->addwork($directories);
        $this->process_work(true);

        if (config('nntmux.echocli')) {

                ColorCLI::header(
                    'Multi-processing for import finished in '.(microtime(true) - $startTime).
                    ' seconds at '.date(DATE_RFC2822).'.'.PHP_EOL
                );
        }
    }

    /**
     * @param        $directories
     * @param string $identifier
     */
    public function importChildWorker($directories, $identifier = '')
    {
        foreach ($directories as $directory) {
            $this->_executeCommand(
                $this->importPath.'"'.
                $directory.'" '.
                $this->deleteComplete.' '.
                $this->deleteFailed.' '.
                $this->useFileName.' '.
                $this->maxPerProcess
            );
        }
    }
}
