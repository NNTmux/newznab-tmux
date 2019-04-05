<?php

namespace Blacklight\libraries;

use Blacklight\Nfo;
use Blacklight\NZB;
use Blacklight\NNTP;
use Spatie\Async\Pool;
use App\Models\Settings;
use Blacklight\ColorCLI;
use App\Models\UsenetGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Opis\Closure\SerializableClosure;
use Blacklight\processing\PostProcess;

/**
 * Class Forking.
 *
 * This forks various newznab scripts.
 *
 * For example, you get all the ID's of the active groups in the groups table, you then iterate over them and spawn
 * processes of misc/update_binaries.php passing the group ID's.
 */
class Forking
{
    private const OUTPUT_NONE = 0; // Don't display child output.
    private const OUTPUT_REALTIME = 1; // Display child output in real time.
    private const OUTPUT_SERIALLY = 2; // Display child output when child is done.

    /**
     * @var \Blacklight\ColorCLI
     */
    public $colorCli;

    /**
     * @var int The type of output
     */
    protected $outputType;

    /**
     * Path to do not run folder.
     *
     * @var string
     */
    private $dnr_path;

    /**
     * Work to work on.
     *
     * @var array
     */
    private $work = [];

    /**
     * How much work do we have to do?
     *
     * @var int
     */
    public $_workCount = 0;

    /**
     * The type of work we want to work on.
     *
     * @var string
     */
    private $workType = '';

    /**
     * List of passed in options for the current work type.
     *
     * @var array
     */
    private $workTypeOptions = [];

    /**
     * Max amount of child processes to do work at a time.
     *
     * @var int
     */
    private $maxProcesses = 1;

    /**
     * Group used for safe backfill.
     *
     * @var string
     */
    private $safeBackfillGroup = '';
    /**
     * @var int
     */
    protected $maxSize;

    /**
     * @var int
     */
    protected $minSize;

    /**
     * @var int
     */
    protected $maxRetries;

    /**
     * @var int
     */
    protected $dummy;

    /**
     * @var bool
     */
    private $processAdditional = false; // Should we process additional?
    private $processNFO = false; // Should we process NFOs?
    private $processMovies = false; // Should we process Movies?
    private $processTV = false; // Should we process TV?

    /**
     * Setup required parent / self vars.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        SerializableClosure::removeSecurityProvider();
        $this->colorCli = new ColorCLI();

        $this->dnr_path = PHP_BINARY.' misc/update/multiprocessing/.do_not_run/switch.php "php  ';

        switch (config('nntmux.multiprocessing_child_output_type')) {
                case 0:
                    $this->outputType = self::OUTPUT_NONE;
                    break;
                case 1:
                    $this->outputType = self::OUTPUT_REALTIME;
                    break;
                case 2:
                    $this->outputType = self::OUTPUT_SERIALLY;
                    break;
                default:
                    $this->outputType = self::OUTPUT_REALTIME;
            }

        $this->dnr_path = PHP_BINARY.' misc/update/multiprocessing/.do_not_run/switch.php "php  ';

        $this->maxSize = (int) Settings::settingValue('..maxsizetoprocessnfo');
        $this->minSize = (int) Settings::settingValue('..minsizetoprocessnfo');
        $this->maxRetries = (int) Settings::settingValue('..maxnforetries') >= 0 ? -((int) Settings::settingValue('..maxnforetries') + 1) : Nfo::NFO_UNPROC;
        $this->maxRetries = $this->maxRetries < -8 ? -8 : $this->maxRetries;
    }

    /**
     * Setup the class to work on a type of work, then process the work.
     * Valid work types:.
     *
     * @param string $type The type of multiProcessing to do : backfill, binaries, releases, postprocess
     * @param array $options Array containing arguments for the type of work.
     *
     * @throws \Exception
     */
    public function processWorkType($type, array $options = [])
    {
        // Set/reset some variables.
        $startTime = now()->timestamp;
        $this->workType = $type;
        $this->workTypeOptions = $options;
        $this->processAdditional = $this->processNFO = $this->processTV = $this->processMovies = $this->ppRenamedOnly = false;
        $this->work = [];

        // Process extra work that should not be forked and done before forking.
        $this->processStartWork();

        // Get work to fork.
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
     *
     * @var bool
     */
    private $ppRenamedOnly;

    /**
     * Get work for our workers to work on, set the max child processes here.
     *
     * @throws \Exception
     */
    private function getWork()
    {
        $this->maxProcesses = 0;

        switch ($this->workType) {

            case 'backfill':
                $this->backfill();
                break;

            case 'binaries':
                $this->binaries();
                break;

            case 'fixRelNames_standard':
            case 'fixRelNames_predbft':
                $this->fixRelNames();
                break;

            case 'releases':
                $this->releases();
                break;

            case 'postProcess_ama':
                $this->processSingle();
                break;

            case 'postProcess_add':
                $this->postProcessAdd();
                break;

            case 'postProcess_mov':
                $this->ppRenamedOnly = (isset($this->workTypeOptions[0]) && $this->workTypeOptions[0] === true);
                $this->postProcessMov();
                break;

            case 'postProcess_nfo':
                $this->postProcessNfo();
                break;

            case 'postProcess_sha':
                $this->processSharing();
                break;

            case 'postProcess_tv':
                $this->ppRenamedOnly = (isset($this->workTypeOptions[0]) && $this->workTypeOptions[0] === true);
                $this->postProcessTv();
                break;

            case 'safe_backfill':
                $this->safeBackfill();
                break;

            case 'safe_binaries':
                $this->safeBinaries();
                break;

            case 'update_per_group':
                $this->updatePerGroup();
                break;
        }
    }

    /**
     * Process work if we have any.
     */
    private function processWork()
    {
        $this->_workCount = \count($this->work);
        if ($this->_workCount > 0 && config('nntmux.echocli') === true) {
            $this->colorCli->header(
                'Multi-processing started at '.now()->toRfc2822String().' for '.$this->workType.' with '.$this->_workCount.
                ' job(s) to do using a max of '.$this->maxProcesses.' child process(es).'
            );
        }
        if (empty($this->_workCount) && config('nntmux.echocli') === true) {
            $this->colorCli->header('No work to do!');
        }
    }

    /**
     * Process any work that does not need to be forked, but needs to run at the start.
     */
    private function processStartWork()
    {
        switch ($this->workType) {
            case 'safe_backfill':
            case 'safe_binaries':
                $this->_executeCommand(PHP_BINARY.' misc/update/tmux/bin/update_groups.php');
                break;
        }
    }

    /**
     * Process any work that does not need to be forked, but needs to run at the end.
     */
    private function processEndWork()
    {
        switch ($this->workType) {
            case 'releases':

                $this->_executeCommand($this->dnr_path.'releases  '.\count($this->work).'_"');

                break;
            case 'update_per_group':
                $this->_executeCommand($this->dnr_path.'releases  '.\count($this->work).'_"');

                break;
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////// All backFill code here ////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function backfill()
    {
        // The option for backFill is for doing up to x articles. Else it's done by date.
        $this->work = DB::select(
            sprintf(
                'SELECT name %s FROM usenet_groups WHERE backfill = 1',
                ($this->workTypeOptions[0] === false ? '' : (', '.$this->workTypeOptions[0].' AS max'))
            )
        );

        $pool = Pool::create()->concurrency($this->maxProcesses)->timeout(config('nntmux.multiprocessing_max_child_time'));
        $this->processWork();
        $maxWork = \count($this->work);
        foreach ($this->work as $group) {
            $pool->add(function () use ($group) {
                $this->_executeCommand(PHP_BINARY.' misc/update/backfill.php '.$group->name.(isset($group->max) ? (' '.$group->max) : ''));
            })->then(function () use ($group, $maxWork) {
                $this->colorCli->primary('Task #'.$maxWork.' Backfilled group '.$group->name);
            })->catch(function (\Throwable $exception) {
                // Handle exception
            });
            $maxWork--;
        }
        $pool->wait();
    }

    private function safeBackfill()
    {
        $backfill_qty = (int) Settings::settingValue('site.tmux.backfill_qty');
        $backfill_order = (int) Settings::settingValue('site.tmux.backfill_order');
        $backfill_days = (int) Settings::settingValue('site.tmux.backfill_days');
        $maxmssgs = (int) Settings::settingValue('..maxmssgs');
        $threads = (int) Settings::settingValue('..backfillthreads');

        $orderby = 'ORDER BY a.last_record ASC';
        switch ($backfill_order) {
            case 1:
                $orderby = 'ORDER BY first_record_postdate DESC';
                break;

            case 2:
                $orderby = 'ORDER BY first_record_postdate ASC';
                break;

            case 3:
                $orderby = 'ORDER BY name ASC';
                break;

            case 4:
                $orderby = 'ORDER BY name DESC';
                break;

            case 5:
                $orderby = 'ORDER BY a.last_record DESC';
                break;
        }

        $backfilldays = '';
        if ($backfill_days === 1) {
            $days = 'backfill_target';
            $backfilldays = now()->diffInDays(Carbon::createFromDate($days));
        } elseif ($backfill_days === 2) {
            $backfilldays = now()->diffInDays(Carbon::createFromFormat('Y-m-d', Settings::settingValue('..safebackfilldate')));
        }

        $data = DB::select(
            sprintf(
                'SELECT g.name,
				g.first_record AS our_first,
				MAX(a.first_record) AS their_first,
				MAX(a.last_record) AS their_last
				FROM usenet_groups g
				INNER JOIN short_groups a ON g.name = a.name
				WHERE g.first_record IS NOT NULL
				AND g.first_record_postdate IS NOT NULL
				AND g.backfill = 1
				AND %s < g.first_record_postdate
				GROUP BY a.name, a.last_record, g.name, g.first_record
				%s LIMIT 1',
                $backfilldays,
                $orderby
            )
        );

        $count = 0;
        if ($data[0]->name) {
            $this->safeBackfillGroup = $data[0]->name;

            $count = ($data[0]->our_first - $data[0]->their_first);
        }

        if ($count > 0) {
            if ($count > ($backfill_qty * $threads)) {
                $geteach = ceil(($backfill_qty * $threads) / $maxmssgs);
            } else {
                $geteach = $count / $maxmssgs;
            }

            $queues = [];
            for ($i = 0; $i <= $geteach - 1; $i++) {
                $queues[$i] = sprintf('get_range  backfill  %s  %s  %s  %s', $data[0]->name, $data[0]->our_first - $i * $maxmssgs - $maxmssgs, $data[0]->our_first - $i * $maxmssgs - 1, $i + 1);
            }

            $pool = Pool::create()->concurrency($threads)->timeout(config('nntmux.multiprocessing_max_child_time'));

            $this->processWork();
            foreach ($queues as $queue) {
                $pool->add(function () use ($queue) {
                    $this->_executeCommand($this->dnr_path.$queue.'"');
                })->then(function () use ($data) {
                    $this->colorCli->primary('Backfilled group '.$data[0]->name);
                })->catch(function (\Throwable $exception) {
                    // Handle exception
                });
            }
            $pool->wait();
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////// All binaries code here ////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function binaries()
    {
        $this->work = DB::select(
            sprintf(
                'SELECT name, %d AS max FROM usenet_groups WHERE active = 1',
                $this->workTypeOptions[0]
            )
        );

        $this->maxProcesses = (int) Settings::settingValue('..binarythreads');

        $pool = Pool::create()->concurrency($this->maxProcesses)->timeout(config('nntmux.multiprocessing_max_child_time'));

        $maxWork = \count($this->work);

        $this->processWork();
        foreach ($this->work as $group) {
            $pool->add(function () use ($group) {
                $this->_executeCommand(PHP_BINARY.' misc/update/update_binaries.php '.$group->name.' '.$group->max);
            })->then(function () use ($group, $maxWork) {
                $this->colorCli->primary('Task #'.$maxWork.' Updated group '.$group->name);
            })->catch(function (\Throwable $exception) {
                echo $exception->getMessage();
            });
            $maxWork--;
        }

        $pool->wait();
    }

    /**
     * @throws \Exception
     */
    private function safeBinaries()
    {
        $maxheaders = (int) Settings::settingValue('..max_headers_iteration') ?: 1000000;
        $maxmssgs = (int) Settings::settingValue('..maxmssgs');
        $this->maxProcesses = (int) Settings::settingValue('..binarythreads');

        $this->work = DB::select(
            '
			SELECT g.name AS groupname, g.last_record AS our_last,
				a.last_record AS their_last
			FROM usenet_groups g
			INNER JOIN short_groups a ON g.active = 1 AND g.name = a.name
			ORDER BY a.last_record DESC'
        );

        if (! empty($this->work)) {
            $i = 1;
            $queues = [];
            foreach ($this->work as $group) {
                if ((int) $group->our_last === 0) {
                    $queues[$i] = sprintf('update_group_headers  %s', $group->groupname);
                    $i++;
                } else {
                    //only process if more than 20k headers available and skip the first 20k
                    $count = $group->their_last - $group->our_last - 20000;
                    //echo "count: " . $count . "maxmsgs x2: " . ($maxmssgs * 2) . PHP_EOL;
                    if ($count <= $maxmssgs * 2) {
                        $queues[$i] = sprintf('update_group_headers  %s', $group->groupname);
                        $i++;
                    } else {
                        $queues[$i] = sprintf('part_repair  %s', $group->groupname);
                        $i++;
                        $geteach = floor(min($count, $maxheaders) / $maxmssgs);
                        $remaining = min($count, $maxheaders) - $geteach * $maxmssgs;
                        //echo "maxmssgs: " . $maxmssgs . " geteach: " . $geteach . " remaining: " . $remaining . PHP_EOL;
                        for ($j = 0; $j < $geteach; $j++) {
                            $queues[$i] = sprintf('get_range  binaries  %s  %s  %s  %s', $group->groupname, $group->our_last + $j * $maxmssgs + 1, $group->our_last + $j * $maxmssgs + $maxmssgs, $i);
                            $i++;
                        }
                        //add remainder to queue
                        $queues[$i] = sprintf('get_range  binaries  %s  %s  %s  %s', $group->groupname, $group->our_last + ($j + 1) * $maxmssgs + 1, $group->our_last + ($j + 1) * $maxmssgs + $remaining + 1, $i);
                        $i++;
                    }
                }
            }
            $pool = Pool::create()->concurrency($this->maxProcesses)->timeout(config('nntmux.multiprocessing_max_child_time'));

            $this->processWork();
            foreach ($queues as $queue) {
                preg_match('/alt\..+/i', $queue, $match);
                $pool->add(function () use ($queue) {
                    $this->_executeCommand($this->dnr_path.$queue.'"');
                })->then(function () use ($match) {
                    if (! empty($match)) {
                        $this->colorCli->primary('Updated group '.$match[0]);
                    }
                })->catch(function (\Throwable $exception) {
                    // Handle exception
                });
            }

            $pool->wait();
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////// All fix release names code here ///////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function fixRelNames()
    {
        $this->maxProcesses = (int) Settings::settingValue('..fixnamethreads');
        $maxperrun = (int) Settings::settingValue('..fixnamesperrun');

        if ($this->maxProcesses > 16) {
            $this->maxProcesses = 16;
        } elseif ($this->maxProcesses === 0) {
            $this->maxProcesses = 1;
        }

        $leftGuids = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'];

        // Prevent PreDB FT from always running
        if ($this->workTypeOptions[0] === 'predbft') {
            $preCount = DB::select(
                sprintf(
                    "
					SELECT COUNT(p.id) AS num
					FROM predb p
					WHERE LENGTH(p.title) >= 15
					AND p.title NOT REGEXP '[\"\<\> ]'
					AND p.searched = 0
					AND p.predate < (NOW() - INTERVAL 1 DAY)"
                )
            );
            if ($preCount[0]->num > 0) {
                $leftGuids = \array_slice($leftGuids, 0, (int) ceil($preCount[0]->num / $maxperrun));
            } else {
                $leftGuids = [];
            }
        }

        $count = 0;
        $queues = [];
        foreach ($leftGuids as $leftGuid) {
            $count++;
            if ($maxperrun > 0) {
                $queues[$count] = sprintf('%s %s %s %s', $this->workTypeOptions[0], $leftGuid, $maxperrun, $count);
            }
        }

        $this->work = $queues;

        $pool = Pool::create()->concurrency($this->maxProcesses)->timeout(config('nntmux.multiprocessing_max_child_time'));

        $maxWork = \count($queues);

        $this->processWork();
        foreach ($this->work as $queue) {
            $pool->add(function () use ($queue) {
                $this->_executeCommand(PHP_BINARY.' misc/update/tmux/bin/groupfixrelnames.php "'.$queue.'"'.' true');
            })->then(function () use ($maxWork) {
                $this->colorCli->primary('Task #'.$maxWork.' Finished fixing releases names');
            })->catch(function (\Throwable $exception) {
                // Handle exception
            });
            $maxWork--;
        }
        $pool->wait();
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////// All releases code here ////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function releases()
    {
        $this->work = DB::select('SELECT id, name FROM usenet_groups WHERE (active = 1 OR backfill = 1)');
        $this->maxProcesses = (int) Settings::settingValue('..releasethreads');

        $uGroups = [];
        foreach ($this->work as $group) {
            try {
                if (! empty(DB::select(sprintf('SELECT id FROM collections LIMIT 1')))) {
                    $uGroups[] = ['id' => $group->id, 'name' => $group->name];
                }
            } catch (\PDOException $e) {
                if (config('app.debug') === true) {
                    Log::debug($e->getMessage());
                }
            }
        }

        $maxWork = \count($uGroups);

        $pool = Pool::create()->concurrency($this->maxProcesses)->timeout(config('nntmux.multiprocessing_max_child_time'));

        $this->processWork();
        foreach ($uGroups as $group) {
            $pool->add(function () use ($group) {
                $this->_executeCommand($this->dnr_path.'releases  '.$group['id'].'"');
            })->then(function () use ($maxWork) {
                $this->colorCli->primary('Task #'.$maxWork.' Finished performing release processing');
            })->catch(function (\Throwable $exception) {
                // Handle exception
            });
            $maxWork--;
        }

        $pool->wait();
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////// All post process code here /////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Only 1 exit method is used for post process, since they are all similar.
     *
     *
     * @param array $releases
     * @param int   $maxProcess
     */
    public function postProcess($releases, $maxProcess)
    {
        $type = $desc = '';
        if ($this->processAdditional) {
            $type = 'pp_additional  ';
            $desc = 'additional postprocessing';
        } elseif ($this->processNFO) {
            $type = 'pp_nfo  ';
            $desc = 'nfo postprocessing';
        } elseif ($this->processMovies) {
            $type = 'pp_movie  ';
            $desc = 'movies postprocessing';
        } elseif ($this->processTV) {
            $type = 'pp_tv  ';
            $desc = 'tv postprocessing';
        }
        $pool = Pool::create()->concurrency($maxProcess)->timeout(config('nntmux.multiprocessing_max_child_time'));
        $count = \count($releases);
        $this->processWork();
        foreach ($releases as $release) {
            if ($type !== '') {
                $pool->add(function () use ($release, $type) {
                    $this->_executeCommand($this->dnr_path.$type.$release->id.(isset($release->renamed) ? ('  '.$release->renamed) : '').'"');
                })->then(function () use ($desc, $count) {
                    $this->colorCli->primary('Finished task #'.$count.' for '.$desc);
                })->catch(function (\Throwable $exception) {
                    // Handle exception
                })->timeout(function () use ($count) {
                    $this->colorCli->notice('Task #'.$count.': Timeout occurred.');
                });
                $count--;
            }
        }
        $pool->wait();
    }

    /**
     * @throws \Exception
     */
    private function postProcessAdd()
    {
        $ppAddMinSize = Settings::settingValue('..minsizetopostprocess') !== '' ? (int) Settings::settingValue('..minsizetopostprocess') : 1;
        $ppAddMinSize = ($ppAddMinSize > 0 ? ('AND r.size > '.($ppAddMinSize * 1048576)) : '');
        $ppAddMaxSize = (Settings::settingValue('..maxsizetopostprocess') !== '') ? (int) Settings::settingValue('..maxsizetopostprocess') : 100;
        $ppAddMaxSize = ($ppAddMaxSize > 0 ? ('AND r.size < '.($ppAddMaxSize * 1073741824)) : '');
        $this->maxProcesses = 1;
        $ppQueue = DB::select(
            sprintf(
                '
					SELECT r.leftguid AS id
					FROM releases r
					LEFT JOIN categories c ON c.id = r.categories_id
					WHERE r.nzbstatus = %d
					AND r.passwordstatus BETWEEN -6 AND -1
					AND r.haspreview = -1
					AND c.disablepreview = 0
					%s %s
					GROUP BY r.leftguid
					LIMIT 16',
                NZB::NZB_ADDED,
                $ppAddMaxSize,
                $ppAddMinSize
            )
        );
        if (\count($ppQueue) > 0) {
            $this->processAdditional = true;
            $this->work = $ppQueue;
            $this->maxProcesses = (int) Settings::settingValue('..postthreads');
        }

        $this->postProcess($this->work, $this->maxProcesses);
    }

    private $nfoQueryString = '';

    /**
     * Check if we should process NFO's.
     *
     * @return bool
     * @throws \Exception
     */
    private function checkProcessNfo(): bool
    {
        if ((int) Settings::settingValue('..lookupnfo') === 1) {
            $this->nfoQueryString = Nfo::NfoQueryString();

            return DB::select(sprintf('SELECT r.id FROM releases r WHERE 1=1 %s LIMIT 1', $this->nfoQueryString)) > 0;
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    private function postProcessNfo()
    {
        $this->maxProcesses = 1;
        if ($this->checkProcessNfo()) {
            $this->processNFO = true;
            $this->work = DB::select(
                sprintf(
                    '
					SELECT r.leftguid AS id
					FROM releases r
					WHERE 1=1 %s
					GROUP BY r.leftguid
					LIMIT 16',
                    $this->nfoQueryString
                )
            );
            $this->maxProcesses = (int) Settings::settingValue('..nfothreads');
        }

        $this->postProcess($this->work, $this->maxProcesses);
    }

    /**
     * @return bool
     * @throws \Exception
     */
    private function checkProcessMovies(): bool
    {
        if (Settings::settingValue('..lookupimdb') > 0) {
            return DB::select(sprintf('
						SELECT id
						FROM releases
						WHERE categories_id BETWEEN 5000 AND 5999
						AND nzbstatus = %d
						AND imdbid IS NULL
						%s %s
						LIMIT 1', NZB::NZB_ADDED, ((int) Settings::settingValue('..lookupimdb') === 2 ? 'AND isrenamed = 1' : ''), ($this->ppRenamedOnly ? 'AND isrenamed = 1' : ''))) > 0;
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    private function postProcessMov()
    {
        $this->maxProcesses = 1;
        if ($this->checkProcessMovies()) {
            $this->processMovies = true;
            $this->work = DB::select(
                sprintf(
                    '
					SELECT leftguid AS id, %d AS renamed
					FROM releases
					WHERE categories_id BETWEEN 5000 AND 5999
					AND nzbstatus = %d
					AND imdbid IS NULL
					%s %s
					GROUP BY leftguid
					LIMIT 16',
                    ($this->ppRenamedOnly ? 2 : 1),
                    NZB::NZB_ADDED,
                    ((int) Settings::settingValue('..lookupimdb') === 2 ? 'AND isrenamed = 1' : ''),
                    ($this->ppRenamedOnly ? 'AND isrenamed = 1' : '')
                )
            );
            $this->maxProcesses = (int) Settings::settingValue('..postthreadsnon');
        }

        $this->postProcess($this->work, $this->maxProcesses);
    }

    /**
     * Check if we should process TV's.
     * @return bool
     * @throws \Exception
     */
    private function checkProcessTV()
    {
        if ((int) Settings::settingValue('..lookuptvrage') > 0) {
            return DB::select(sprintf('
						SELECT id
						FROM releases
						WHERE categories_id BETWEEN 5000 AND 5999
						AND nzbstatus = %d
						AND size > 1048576
						AND tv_episodes_id BETWEEN -2 AND 0
						%s %s
						', NZB::NZB_ADDED, (int) Settings::settingValue('..lookuptvrage') === 2 ? 'AND isrenamed = 1' : '', $this->ppRenamedOnly ? 'AND isrenamed = 1' : '')) > 0;
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    private function postProcessTv()
    {
        $this->maxProcesses = 1;
        if ($this->checkProcessTV()) {
            $this->processTV = true;
            $this->work = DB::select(
                sprintf(
                    '
					SELECT leftguid AS id, %d AS renamed
					FROM releases
					WHERE categories_id BETWEEN 3000 AND 3999
					AND nzbstatus = %d
					AND tv_episodes_id BETWEEN -2 AND 0
					AND size > 1048576
					%s %s
					GROUP BY leftguid
					LIMIT 16',
                    ($this->ppRenamedOnly ? 2 : 1),
                    NZB::NZB_ADDED,
                    (int) Settings::settingValue('..lookuptvrage') === 2 ? 'AND isrenamed = 1' : '',
                    ($this->ppRenamedOnly ? 'AND isrenamed = 1' : '')
                )
            );
            $this->maxProcesses = (int) Settings::settingValue('..postthreadsnon');
        }

        $this->postProcess($this->work, $this->maxProcesses);
    }

    /**
     * Process sharing.
     * @return bool
     * @throws \Exception
     */
    private function processSharing()
    {
        $sharing = DB::select('SELECT enabled FROM sharing');
        if ($sharing > 0 && (int) $sharing[0]->enabled === 1) {
            $nntp = new NNTP();
            if ((int) (Settings::settingValue('..alternate_nntp') === 1 ? $nntp->doConnect(true, true) : $nntp->doConnect()) === true) {
                (new PostProcess(['ColorCLI' => $this->colorCli]))->processSharing($nntp);
            }

            return true;
        }

        return false;
    }

    /**
     * Process all that require a single thread.
     *
     * @throws \Exception
     */
    private function processSingle()
    {
        $postProcess = new PostProcess(['ColorCLI' => $this->colorCli]);
        //$postProcess->processAnime();
        $postProcess->processBooks();
        $postProcess->processConsoles();
        $postProcess->processGames();
        $postProcess->processMusic();
        $postProcess->processXXX();
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////// All "update_per_Group" code goes here ////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @throws \Exception
     */
    private function updatePerGroup()
    {
        $this->work = DB::select('SELECT id , name FROM usenet_groups WHERE (active = 1 OR backfill = 1)');

        $maxProcess = (int) Settings::settingValue('..releasethreads');

        $pool = Pool::create()->concurrency($maxProcess)->timeout(config('nntmux.multiprocessing_max_child_time'));
        $this->processWork();
        foreach ($this->work as $group) {
            $pool->add(function () use ($group) {
                $this->_executeCommand($this->dnr_path.'update_per_group  '.$group->id.'"');
            })->then(function () use ($group) {
                $name = UsenetGroup::getNameByID($group->id);
                $this->colorCli->primary('Finished updating binaries, processing releases and additional postprocessing for group:'.$name);
            })->catch(function (\Throwable $exception) {
                // Handle exception
            });
        }

        $pool->wait();
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////// Various methods ///////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Execute a shell command.
     *
     * @param string $command
     */
    protected function _executeCommand($command)
    {
        switch ($this->outputType) {
            case self::OUTPUT_NONE:
                exec($command);
                break;
            case self::OUTPUT_REALTIME:
                passthru($command);
                break;
            case self::OUTPUT_SERIALLY:
                echo shell_exec($command);
                break;
        }
    }

    /**
     * Echo a message to CLI.
     *
     * @param string $message
     */
    public function logger($message)
    {
        if (config('nntmux.echocli')) {
            echo $message.PHP_EOL;
        }
    }

    /**
     * This method is executed whenever a child is finished doing work.
     *
     * @param string $pid        The PID numbers.
     */
    public function exit($pid)
    {
        if (config('nntmux.echocli')) {
            $this->colorCli->header(
                'Process ID #'.$pid.' has completed.'.PHP_EOL.
                'There are '.($this->maxProcesses - 1).' process(es) still active with '.
                (--$this->_workCount).' job(s) left in the queue.',
                true
            );
        }
    }
}
