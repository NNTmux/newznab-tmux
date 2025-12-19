<?php

namespace Blacklight\libraries;

use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\libraries\Runners\BackfillRunner;
use Blacklight\libraries\Runners\BinariesRunner;
use Blacklight\libraries\Runners\PostProcessRunner;
use Blacklight\libraries\Runners\ReleasesRunner;
use Blacklight\Nfo;
use Blacklight\processing\PostProcess;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Class Forking.
 *
 * This forks various scripts.
 *
 * For example, you get all the ID's of the active groups in the groups table, you then iterate over them and spawn
 * processes of misc/update_binaries.php passing the group ID's.
 */
class Forking
{
    public ColorCLI $colorCli;

    /**
     * @var int The type of output
     */
    protected int $outputType;

    /**
     * Work to work on.
     */
    private array $work = [];

    /**
     * How much work do we have to do?
     */
    public int $_workCount = 0;

    /**
     * The type of work we want to work on.
     */
    private string $workType = '';

    /**
     * List of passed in options for the current work type.
     */
    private array $workTypeOptions = [];

    /**
     * Max amount of child processes to do work at a time.
     */
    private int $maxProcesses = 1;

    /**
     * Group used for safe backfill.
     */
    private string $safeBackfillGroup = '';

    protected int $maxSize;

    protected int $minSize;

    protected int $maxRetries;

    protected int $dummy;

    private bool $processAdditional = false; // Should we process additional?

    private bool $processNFO = false; // Should we process NFOs?

    private bool $processMovies = false; // Should we process Movies?

    private bool $processTV = false; // Should we process TV?

    /** Runners **/
    private BackfillRunner $backfillRunner;

    private BinariesRunner $binariesRunner;

    private ReleasesRunner $releasesRunner;

    private PostProcessRunner $postProcessRunner;

    /**
     * Setup required parent / self vars.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->colorCli = new ColorCLI;

        // Using artisan commands for all multiprocessing operations

        $this->maxSize = (int) Settings::settingValue('maxsizetoprocessnfo');
        $this->minSize = (int) Settings::settingValue('minsizetoprocessnfo');
        $this->maxRetries = (int) Settings::settingValue('maxnforetries') >= 0 ? -((int) Settings::settingValue('maxnforetries') + 1) : Nfo::NFO_UNPROC;
        $this->maxRetries = max($this->maxRetries, -8);

        // init runners
        $this->backfillRunner = new BackfillRunner($this->colorCli);
        $this->binariesRunner = new BinariesRunner($this->colorCli);
        $this->releasesRunner = new ReleasesRunner($this->colorCli);
        $this->postProcessRunner = new PostProcessRunner($this->colorCli);
    }

    /**
     * Setup the class to work on a type of work, then process the work.
     * Valid work types:.
     *
     * @param  string  $type  The type of multiProcessing to do : backfill, binaries, releases, postprocess
     * @param  array  $options  Array containing arguments for the type of work.
     *
     * @throws \Exception
     */
    public function processWorkType(string $type, array $options = []): void
    {
        // Set/reset some variables.
        $startTime = now()->timestamp;
        $this->workType = $type;
        $this->workTypeOptions = $options;
        $this->processAdditional = $this->processNFO = $this->processTV = $this->processMovies = $this->ppRenamedOnly = false;
        $this->work = [];

        // Get work to fork via runners
        $this->getWork();

        // Process extra work that should not be forked and done after.
        $this->processEndWork();

        if (config('nntmux.echocli')) {
            $this->colorCli->header(
                'Multi-processing for '.$this->workType.' finished in '.(now()->timestamp - $startTime).
                ' seconds at '.now()->toRfc2822String().'.'.PHP_EOL
            );
        }
    }

    /**
     * Only post process renamed movie / tv releases?
     */
    private bool $ppRenamedOnly;


    /**
     * Helper to build artisan command from legacy command format.
     */
    private function buildDnrCommand(string $args): string
    {
        // Use the same conversion logic as BaseRunner
        return $this->backfillRunner->buildDnrCommandPublic($args);
    }

    /**
     * Get work for our workers to work on, set the max child processes here.
     *
     * @throws \Exception
     */
    private function getWork(): void
    {
        $this->maxProcesses = 0;

        switch ($this->workType) {
            case 'backfill':
                $this->backfillRunner->backfill($this->workTypeOptions);
                break;

            case 'binaries':
                $maxPerGroup = (int) ($this->workTypeOptions[0] ?? 0);
                $this->binariesRunner->binaries($maxPerGroup);
                break;

            case 'fixRelNames_standard':
                $this->releasesRunner->fixRelNames('standard', (int) Settings::settingValue('fixnamesperrun'), (int) Settings::settingValue('fixnamethreads'));
                break;

            case 'fixRelNames_predbft':
                $this->releasesRunner->fixRelNames('predbft', (int) Settings::settingValue('fixnamesperrun'), (int) Settings::settingValue('fixnamethreads'));
                break;

            case 'releases':
                $this->releasesRunner->releases();
                break;

            case 'postProcess_ama':
                $this->postProcessRunner->processBooks();
                break;

            case 'postProcess_add':
                $this->postProcessRunner->processAdditional();
                break;

            case 'postProcess_ani':
                $this->postProcessRunner->processAnime();
                break;

            case 'postProcess_mov':
                $renamedOnly = (isset($this->workTypeOptions[0]) && $this->workTypeOptions[0] === true);
                $this->postProcessRunner->processMovies($renamedOnly);
                break;

            case 'postProcess_nfo':
                $this->postProcessRunner->processNfo();
                break;

            case 'postProcess_tv':
                $renamedOnly = (isset($this->workTypeOptions[0]) && $this->workTypeOptions[0] === true);
                if ($this->postProcessRunner->hasTvWork($renamedOnly)) {
                    $this->postProcessRunner->processTv($renamedOnly);
                } else {
                    $this->logger('No TV work to do.');
                }
                break;

            case 'safe_backfill':
                $this->backfillRunner->safeBackfill();
                break;

            case 'safe_binaries':
                $this->binariesRunner->safeBinaries();
                break;

            case 'update_per_group':
                $this->releasesRunner->updatePerGroup();
                break;
        }
    }

    /**
     * Process any work that does not need to be forked, but needs to run at the end.
     */
    private function processEndWork(): void
    {
        switch ($this->workType) {
            case 'update_per_group':
            case 'releases':
                $count = $this->getReleaseWorkCount();
                $this->_executeCommand($this->buildDnrCommand('releases  '.$count.'_'));
                break;
        }
    }

    /**
     * Compute count of groups which currently have collections pending (used for DNR signalling).
     */
    private function getReleaseWorkCount(): int
    {
        $groups = DB::select('SELECT id FROM usenet_groups WHERE (active = 1 OR backfill = 1)');
        $count = 0;
        foreach ($groups as $g) {
            try {
                $q = DB::select(sprintf('SELECT id FROM collections WHERE groups_id = %d LIMIT 1', $g->id));
                if (! empty($q)) {
                    $count++;
                }
            } catch (\PDOException $e) {
                if (config('app.debug') === true) {
                    \Log::debug($e->getMessage());
                }
            }
        }

        return $count;
    }

    // The remaining methods are kept for BC and potential direct usage within the codebase,
    // but are not used when processWorkType delegates to runner classes.

    private function backfill(): void {}

    private function safeBackfill(): void {}

    private function binaries(): void {}

    private function safeBinaries(): void {}

    private function fixRelNames(): void {}

    private function releases(): void {}

    public function postProcess(array $releases, int $maxProcess): void {}

    private function postProcessAdd(): void {}

    private function postProcessNfo(): void {}

    private function postProcessMov(): void {}

    private function postProcessTv(): void {}

    private function processSingle(): void
    {
        $postProcess = new PostProcess;
        $postProcess->processBooks();
        $postProcess->processConsoles();
        $postProcess->processGames();
        $postProcess->processMusic();
        $postProcess->processXXX();
    }

    protected function _executeCommand(string $command): string
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(1800);
        $process->run(function ($type, $buffer) {
            if ($type === Process::ERR) {
                echo $buffer;
            }
        });

        return $process->getOutput();
    }

    public function logger(string $message): void
    {
        if (config('nntmux.echocli')) {
            echo $message.PHP_EOL;
        }
    }

    public function exit(string $pid): void
    {
        if (config('nntmux.echocli')) {
            $this->colorCli->header(
                'Process ID #'.$pid.' has completed.'.PHP_EOL.
                'There are '.(max(1, $this->maxProcesses) - 1).' process(es) still active with '.
                (--$this->_workCount).' job(s) left in the queue.',
                true
            );
        }
    }
}
