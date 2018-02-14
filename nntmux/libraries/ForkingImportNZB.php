<?php

namespace nntmux\libraries;

use nntmux\db\DB;
use nntmux\ColorCLI;

/**
 * Class ForkingImportNZB.
 *
 * Multi-processing of NZB Import.
 */
class ForkingImportNZB extends Forking
{
    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $defaults = [
			'settings' => new DB(),
		];
        $options += $defaults;

        parent::__construct();
        $this->importPath = (PHP_BINARY.' '.NN_MISC.'testing'.DS.'nzb-import.php ');
        $this->pdo = $options['settings'];
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    private $deleteComplete;
    private $deleteFailed;
    private $useFileName;
    private $maxPerProcess;

    public function start($folder, $maxProcesses, $deleteComplete, $deleteFailed, $useFileName, $maxPerProcess)
    {
        $startTime = microtime(true);
        $directories = glob($folder.'/*', GLOB_ONLYDIR);

        $this->_workCount = count($directories);

        if ($this->_workCount == 0) {
            echo ColorCLI::error('No sub-folders were found in your specified folder ('.$folder.').');
            exit();
        }

        if (NN_ECHOCLI) {
            echo ColorCLI::header(
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

        if (NN_ECHOCLI) {
            ColorCLI::doEcho(
				ColorCLI::header(
					'Multi-processing for import finished in '.(microtime(true) - $startTime).
					' seconds at '.date(DATE_RFC2822).'.'.PHP_EOL
				), true
			);
        }
    }

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
