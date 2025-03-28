<?php

namespace Blacklight;

use App\Models\Settings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Tmux pane shell exec functions for pane respawning.
 *
 *
 * Class TmuxRun
 */
class TmuxRun extends Tmux
{
    /**
     * @var mixed|string
     */
    protected mixed $_dateFormat;

    /**
     * TmuxRun constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->_dateFormat = '%Y-%m-%d %T';
    }

    /**
     * Main switch for running tmux panes.
     *
     *
     *
     * @throws \Exception
     */
    public function runPane($cmdParam, &$runVar): void
    {
        switch ((int) $runVar['constants']['sequential']) {
            case 0:
                switch ((string) $cmdParam) {
                    case 'amazon':
                        $this->_runAmazon($runVar);
                        break;
                    case 'dehash':
                        $this->_runDehash($runVar);
                        break;
                    case 'fixnames':
                        $this->_runFixReleaseNames($runVar);
                        break;
                    case 'main':
                        $this->_runMainNon($runVar);
                        break;
                    case 'nonamazon':
                        $this->_runNonAmazon($runVar);
                        break;
                    case 'notrunning':
                        $this->_notRunningNon($runVar);
                        break;
                    case 'ppadditional':
                        $this->_runPPAdditional($runVar);
                        break;
                    case 'removecrap':
                        $this->_runRemoveCrap($runVar);
                        break;
                    case 'scraper':
                        $this->_runIRCScraper(3, $runVar);
                        break;
                    case 'sharing':
                        $this->_runSharing(4, $runVar);
                        break;
                    case 'updatetv':
                        $this->_runUpdateTv($runVar);
                        break;
                }
                break;
            case 1:
                switch ($cmdParam) {
                    case 'amazon':
                        $this->_runAmazon($runVar);
                        break;
                    case 'dehash':
                        $this->_runDehash($runVar);
                        break;
                    case 'fixnames':
                        $this->_runFixReleaseNames($runVar);
                        break;
                    case 'main':
                        $this->_runMainBasic($runVar);
                        break;
                    case 'nonamazon':
                        $this->_runNonAmazon($runVar);
                        break;
                    case 'notrunning':
                        $this->_notRunningBasic($runVar);
                        break;
                    case 'ppadditional':
                        $this->_runPPAdditional($runVar);
                        break;
                    case 'removecrap':
                        $this->_runRemoveCrap($runVar);
                        break;
                    case 'scraper':
                        $this->_runIRCScraper(3, $runVar);
                        break;
                    case 'sharing':
                        $this->_runSharing(4, $runVar);
                        break;
                    case 'updatetv':
                        $this->_runUpdateTv($runVar);
                        break;
                }
                break;
        }
    }

    /**
     * @throws \Exception
     */
    protected function _runDehash(&$runVar): void
    {
        switch ($runVar['settings']['dehash']) {
            case 1:
                $log = $this->writelog($runVar['panes']['one'][3]);
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:1.3 ' \
					{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/match_prefiles.php 3000 show $log; \
					date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['dehash_timer']}' 2>&1 1> /dev/null"
                );
                break;
            case 2:
                $log = $this->writelog($runVar['panes']['one'][3]);
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:1.3 ' \
					{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/tmux/bin/postprocess_pre.php {$runVar['constants']['pre_lim']} $log; \
					{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/match_prefiles.php 3000 show $log; \
					date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['dehash_timer']}' 2>&1 1> /dev/null"
                );
                break;
            case 3:
                $log = $this->writelog($runVar['panes']['one'][3]);
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:1.3 ' \
					{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/tmux/bin/postprocess_pre.php {$runVar['constants']['pre_lim']} $log; \
					{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/match_prefiles.php 300 show $log; \
					date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['dehash_timer']}' 2>&1 1> /dev/null"
                );
                break;
            default:
                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:1.3 'echo \"\033[38;5;${color}m\n{$runVar['panes']['one'][3]} has been disabled/terminated by Decrypt Hashes\"'");
        }
    }

    /**
     * @throws \Exception
     */
    protected function _runFixReleaseNames(&$runVar): void
    {
        if ((int) $runVar['settings']['fix_names'] === 1) {
            if ($runVar['counts']['now']['processrenames'] > 0) {
                $log = $this->writelog($runVar['panes']['one'][0]);
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:1.0 ' \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}testing/Releases/fixReleaseNames.php 3 true other yes show $log; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}testing/Releases/fixReleaseNames.php 5 true other yes show $log; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}testing/Releases/fixReleaseNames.php 7 true other yes show $log; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}testing/Releases/fixReleaseNames.php 9 true other yes show $log; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}testing/Releases/fixReleaseNames.php 11 true other yes show $log; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}testing/Releases/fixReleaseNames.php 13 true other yes show $log; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}testing/Releases/fixReleaseNames.php 15 true other yes show $log; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}testing/Releases/fixReleaseNames.php 17 true other yes show $log; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}testing/Releases/fixReleaseNames.php 19 true other yes show  $log; date +\"{$this->_dateFormat}\"; \
						{$runVar['commands']['_sleep']} {$runVar['settings']['fix_timer']}' 2>&1 1> /dev/null"
                );
            } else {
                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:1.0 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['one'][0]} has been disabled/terminated by no Fix Release Names to process\"'");
            }
        } else {
            $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:1.0 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['one'][0]} has been disabled/terminated by Fix Release Names\"'");
        }
    }

    /**
     * @throws \Exception
     */
    protected function _runAmazon(&$runVar): void
    {
        switch (true) {
            case (int) $runVar['settings']['post_amazon'] === 1 &&
            (
                (int) $runVar['counts']['now']['processmusic'] > 0 ||
                (int) $runVar['counts']['now']['processbooks'] > 0 ||
                (int) $runVar['counts']['now']['processconsole'] > 0 ||
                (int) $runVar['counts']['now']['processgames'] > 0 ||
                (int) $runVar['counts']['now']['processxxx'] > 0
            ) &&
            (
                (int) $runVar['settings']['processbooks'] === 1 ||
                (int) $runVar['settings']['processmusic'] === 1 ||
                (int) $runVar['settings']['processgames'] === 1 ||
                (int) $runVar['settings']['processxxx'] === 1
            ):

                $log = $this->writelog($runVar['panes']['two'][2]);
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:2.2 ' \
						{$runVar['commands']['_phpn']} {$runVar['paths']['misc']}update/postprocess.php amazon true $log; date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['post_timer_amazon']}' 2>&1 1> /dev/null"
                );
                break;
            case (int) $runVar['settings']['post_amazon'] === 1 && (int) $runVar['settings']['processbooks'] === 0
            && (int) $runVar['settings']['processmusic'] === 0 && (int) $runVar['settings']['processgames'] === 0
            && (int) $runVar['settings']['processxxx'] === 0:

                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:2.2 \
					'echo \"\033[38;5;{$color}m\n{$runVar['panes']['two'][2]} has been disabled/terminated in Admin Disable Music/Books/Console/XXX\"'");
                break;
            case (int) $runVar['settings']['post_amazon'] === 1 && (int) $runVar['counts']['now']['processmusic'] === 0 &&
            (int) $runVar['counts']['now']['processbooks'] === 0 && (int) $runVar['counts']['now']['processconsole'] === 0 && (int) $runVar['counts']['now']['processgames'] === 0 && (int) $runVar['counts']['now']['processxxx'] === 0:
                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:2.2 \
					'echo \"\033[38;5;{$color}m\n{$runVar['panes']['two'][2]} has been disabled/terminated by No Music/Books/Console/Games/XXX to process\"'");
                break;
            default:
                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:2.2 \
					'echo \"\033[38;5;{$color}m\n{$runVar['panes']['two'][2]} has been disabled/terminated by Postprocess Amazon\"'");
        }
    }

    /**
     * @throws \Exception
     */
    protected function _runAmazonFull(&$runVar): void
    {
        switch (true) {
            case ((int) $runVar['settings']['post_amazon'] === 1) && (((int) $runVar['counts']['now']['processmusic'] > 0)
                    || ((int) $runVar['counts']['now']['processbooks'] > 0) || ((int) $runVar['counts']['now']['processconsole'] > 0)
                        || ((int) $runVar['counts']['now']['processgames'] > 0) || ((int) $runVar['counts']['now']['processxxx'] > 0))
            && (((int) $runVar['settings']['processbooks'] !== 0) || ((int) $runVar['settings']['processconsole'] !== 0)
                || ((int) $runVar['settings']['processmusic'] !== 0) ||
                    ((int) $runVar['settings']['processgames'] !== 0)
                    || ((int) $runVar['settings']['processxxx'] !== 0)):

                $log = $this->writelog($runVar['panes']['one'][1]);
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:1.1 ' \
						{$runVar['commands']['_phpn']} {$runVar['paths']['misc']}update/multiprocessing/postprocess.php ama $log; \
						date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['post_timer_amazon']}' 2>&1 1> /dev/null"
                );
                break;
            case ((int) $runVar['settings']['post_amazon'] === 1) && ((int) $runVar['settings']['processbooks'] === 0)
            && ((int) $runVar['counts']['now']['processconsole'] === 0) && ((int) $runVar['settings']['processmusic'] === 0)
            && ((int) $runVar['settings']['processgames'] === 0):

                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec(
                    "tmux respawnp -k -t{$runVar['constants']['tmux_session']}:1.1 \
					'echo \"\033[38;5;{$color}m\n{$runVar['panes']['one'][1]} has been disabled/terminated in Admin Disable Music/Books/Console/XXX\"'"
                );
                break;
            case ((int) $runVar['settings']['post_amazon'] === 1) && ((int) $runVar['counts']['now']['processmusic'] === 0)
            && ((int) $runVar['counts']['now']['processbooks'] === 0) && ((int) $runVar['counts']['now']['processconsole'] === 0)
            && ((int) $runVar['counts']['now']['processgames'] === 0) && ((int) $runVar['counts']['now']['processxxx'] === 0):

                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec(
                    "tmux respawnp -k -t{$runVar['constants']['tmux_session']}:1.1 \
					'echo \"\033[38;5;{$color}m\n{$runVar['panes']['one'][1]} has been disabled/terminated by No Music/Books/Console/Games/XXX to process\"'"
                );
                break;
            default:
                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec(
                    "tmux respawnp -k -t{$runVar['constants']['tmux_session']}:1.1 \
					'echo \"\033[38;5;{$color}m\n{$runVar['panes']['one'][1]} has been disabled/terminated by Postprocess Amazon\"'"
                );
        }
    }

    /**
     * @throws \Exception
     */
    protected function _runNonAmazon(&$runVar): void
    {
        switch (true) {
            case (int) $runVar['settings']['post_non'] !== 0 && ((int) $runVar['counts']['now']['processmovies'] > 0 || (int) $runVar['counts']['now']['processtv'] > 0 || $runVar['counts']['now']['processanime'] > 0):
                $log = $this->writelog($runVar['panes']['two'][1]);
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:2.1 ' \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/multiprocessing/postprocess.php tv $log; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/multiprocessing/postprocess.php mov $log; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/postprocess.php anime true $log; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}testing/PostProc/check_covers.php true $log; \
						date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['post_timer_non']}' 2>&1 1> /dev/null"
                );
                break;
            case (int) $runVar['settings']['post_non'] !== 0 && (int) $runVar['counts']['now']['processmovies'] === 0 && (int) $runVar['counts']['now']['processtv'] === 0 && (int) $runVar['counts']['now']['processanime'] === 0:
                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:2.1 \
					'echo \"\033[38;5;{$color}m\n{$runVar['panes']['two'][1]} has been disabled/terminated by No Movies/TV/Anime to process\"'");
                break;
            default:
                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:2.1 \
					'echo \"\033[38;5;{$color}m\n{$runVar['panes']['two'][1]} has been disabled/terminated by Postprocess Non-Amazon\"'");
        }
    }

    /**
     * @throws \Exception
     */
    protected function _runNonUpdateBinaries(&$runVar): void
    {
        // run update_binaries
        // $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
        if (((int) $runVar['settings']['binaries_run'] !== 0) && ($runVar['killswitch']['pp'] === false)) {
            $log = $this->writelog($runVar['panes']['zero'][2]);
            shell_exec(
                "tmux respawnp -t{$runVar['constants']['tmux_session']}:0.2 ' \
					{$runVar['scripts']['binaries']} $log; date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['bins_timer']}' 2>&1 1> /dev/null"
            );
        } elseif ($runVar['killswitch']['pp'] === true) {
            $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:0.2 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['zero'][2]} has been disabled/terminated by Exceeding Limits\"'");
        } else {
            $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:0.2 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['zero'][2]} has been disabled/terminated by Binaries\"'");
        }
    }

    /**
     * @throws \Exception
     */
    protected function _runNonBackfill(&$runVar): void
    {
        // run backfill
        $backsleep = (
            (int) $runVar['settings']['progressive'] === 1 && floor($runVar['counts']['now']['collections_table'] / 500) > $runVar['settings']['back_timer']
            ? floor($runVar['counts']['now']['collections_table'] / 500)
            : $runVar['settings']['back_timer']
        );

        if (((int) $runVar['settings']['backfill'] !== 0) && ($runVar['killswitch']['coll'] === false) && ($runVar['killswitch']['pp'] === false)) {
            $log = $this->writelog($runVar['panes']['zero'][3]);
            shell_exec(
                "tmux respawnp -t{$runVar['constants']['tmux_session']}:0.3 ' \
				{$runVar['scripts']['backfill']} $log; date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} $backsleep' 2>&1 1> /dev/null"
            );
        } elseif (($runVar['killswitch']['coll'] === true) || ($runVar['killswitch']['pp'] === true)) {
            $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:0.3 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['zero'][3]} has been disabled/terminated by Exceeding Limits\"'");
        } else {
            $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:0.3 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['zero'][3]} has been disabled/terminated by Backfill\"'");
        }
    }

    /**
     * @throws \Exception
     */
    protected function _runNonUpdateReleases(&$runVar): void
    {
        // run update_releases
        if ((int) $runVar['settings']['releases_run'] !== 0) {
            $log = $this->writelog($runVar['panes']['zero'][4]);
            shell_exec(
                "tmux respawnp -t{$runVar['constants']['tmux_session']}:0.4 ' \
					{$runVar['scripts']['releases']} $log; date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['rel_timer']}' 2>&1 1> /dev/null"
            );
        } else {
            $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:0.4 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['zero'][4]} has been disabled/terminated by Releases\"'");
        }
    }

    /**
     * Run postprocess_releases additional.
     *
     *
     *
     * @throws \Exception
     */
    protected function _runPPAdditional(&$runVar): void
    {
        switch (true) {
            case ((int) $runVar['settings']['post'] === 1) && ((int) $runVar['counts']['now']['work'] > 0):
                $log = $this->writelog($runVar['panes']['two'][0]);
                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:2.0 'echo \"\033[38;5;{$color}m\"; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/multiprocessing/postprocess.php add $log; date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['post_timer']}' 2>&1 1> /dev/null"
                );
                $runVar['timers']['timer3'] = time();
                break;
            case ((int) $runVar['settings']['post'] === 2) && ((int) $runVar['counts']['now']['processnfo'] > 0):
                $log = $this->writelog($runVar['panes']['two'][0]);
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:2.0 ' \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/multiprocessing/postprocess.php nfo $log; date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['post_timer']}' 2>&1 1> /dev/null"
                );
                $runVar['timers']['timer3'] = time();
                break;
            case ((int) $runVar['settings']['post'] === 3) && (((int) $runVar['counts']['now']['processnfo'] > 0) || ((int) $runVar['counts']['now']['work'] > 0)):
                // run postprocess_releases additional
                $log = $this->writelog($runVar['panes']['two'][0]);
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:2.0 ' \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/multiprocessing/postprocess.php add $log; \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/multiprocessing/postprocess.php nfo $log; \
						date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['post_timer']}' 2>&1 1> /dev/null"
                );
                $runVar['timers']['timer3'] = time();
                break;
            case ((int) $runVar['settings']['post'] !== 0) && ((int) $runVar['counts']['now']['processnfo'] === 0) && ((int) $runVar['counts']['now']['work'] === 0):
                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:2.0 \
					'echo \"\033[38;5;{$color}m\n{$runVar['panes']['two'][0]} has been disabled/terminated by No Misc/Nfo to process\"'");
                break;
            default:
                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:2.0 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['two'][0]} has been disabled/terminated by Postprocess Additional\"'");
        }
    }

    /**
     * @throws \Exception
     */
    protected function _runRemoveCrap(&$runVar): void
    {
        switch ($runVar['settings']['fix_crap_opt']) {
            // Do all types up to 2 hours.
            case 'All':
                $log = $this->writelog($runVar['panes']['one'][1]);
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:1.1 ' \
						{$runVar['commands']['_php']} {$runVar['paths']['misc']}testing/Releases/removeCrapReleases.php true 2 $log; \
						date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['crap_timer']}' 2>&1 1> /dev/null"
                );
                break;
                // The user has specified custom types.
            case 'Custom':
                $log = $this->writelog($runVar['panes']['one'][1]);

                // Check how many types the user picked.
                $runVar['modsettings']['fc']['max'] = \count($runVar['modsettings']['fix_crap']);

                // Make sure the user actually selected some.
                if ($runVar['modsettings']['fc']['max'] > 0) {
                    // If this is the first run, do a full run, else run on last 2 hours of releases.
                    $runVar['modsettings']['fc']['time'] = '4';
                    if (($runVar['counts']['iterations'] == 1) || $runVar['modsettings']['fc']['firstrun']) {
                        $runVar['modsettings']['fc']['time'] = 'full';
                    }

                    // Check to see if the pane is dead, if so respawn it.
                    if (shell_exec("tmux list-panes -t{$runVar['constants']['tmux_session']}:1 | grep ^1 | grep -c dead") == 1) {
                        // Run remove crap releases.
                        shell_exec(
                            "tmux respawnp -t{$runVar['constants']['tmux_session']}:1.1 ' \
							echo \"\nRunning removeCrapReleases for {$runVar['modsettings']['fix_crap'][$runVar['modsettings']['fc']['num']]}\"; \
							{$runVar['commands']['_phpn']} {$runVar['paths']['misc']}testing/Releases/removeCrapReleases.php true  \
							{$runVar['modsettings']['fc']['time']} {$runVar['modsettings']['fix_crap'][$runVar['modsettings']['fc']['num']]} $log; \
							date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['crap_timer']}' 2>&1 1> /dev/null"
                        );

                        // Increment so we know which type to run next.
                        $runVar['modsettings']['fc']['num']++;
                    }

                    // If we reached the end, reset the type.
                    if ((int) $runVar['modsettings']['fc']['num'] === (int) $runVar['modsettings']['fc']['max']) {
                        $runVar['modsettings']['fc']['num'] = 0;
                        // And say we are not on the first run, so we run 2 hours the next times.
                        $runVar['modsettings']['fc']['firstrun'] = false;
                    }
                }
                break;
            case 'Disabled':
            default:
                $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
                shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:1.1 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['one'][1]} has been disabled/terminated by Remove Crap Releases\"'");
        }
    }

    /**
     * @throws \Exception
     */
    protected function _runUpdateTv(&$runVar): void
    {
        $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
        shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:1.2 \
		'echo \"\033[38;5;{$color}m\n{$runVar['panes']['one'][2]} has been disabled/terminated by Update TV/Theater\"'");
    }

    /**
     * @throws \Exception
     */
    protected function _runUpdateTvFull(&$runVar): void
    {
        $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
        shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:1.0 \
			'echo \"\033[38;5;{$color}m\n{$runVar['panes']['one'][0]} has been disabled/terminated by Update TV/Theater\"'");
    }

    /**
     * @throws \Exception
     */
    protected function _runMainNon(&$runVar): void
    {
        $this->_runNonUpdateBinaries($runVar);
        $this->_runNonUpdateReleases($runVar);
        $this->_runNonBackfill($runVar);
    }

    /**
     * @throws \Exception
     */
    protected function _runMainBasic(&$runVar): void
    {
        $log = $this->writelog($runVar['panes']['zero'][2]);
        if (($runVar['killswitch']['pp'] === false) && (time() - $runVar['timers']['timer5'] <= 4800)) {
            $date = 'date +"%Y-%m-%d %T";';
            $sleep = sprintf(
                '%s %s;',
                $runVar['commands']['_sleep'],
                $runVar['settings']['seq_timer']
            );

            $binaries = match ($runVar['settings']['binaries_run']) {
                0 => 'echo "\nbinaries has been disabled/terminated by Binaries"',
                1 => sprintf('%s %s;', $runVar['scripts']['binaries'], $log),
                default => '',
            };

            $backfill = match ($runVar['settings']['backfill']) {
                0 => 'echo "backfill is disabled in settings";',
                1 => sprintf('%s %s %s;', $runVar['scripts']['backfill'], $runVar['settings']['backfill_qty'], $log),
                2 => sprintf('%s %s %s;', $runVar['scripts']['backfill'], 'group', $log),
                4 => sprintf('%s %s;', $runVar['scripts']['backfill'], $log),
                default => '',
            };

            $releases = match ($runVar['settings']['releases_run']) {
                0 => 'echo PHP_EOL . "releases have been disabled/terminated by Releases"',
                1 => sprintf('%s %s;', $runVar['scripts']['releases'], $log),
                default => '',
            };

            shell_exec("tmux respawnp -t{$runVar['constants']['tmux_session']}:0.2 '$binaries $backfill $releases $date $sleep' 2>&1 1> /dev/null");
        } elseif (($runVar['killswitch']['pp'] === false) && (time() - $runVar['timers']['timer5'] >= 4800)) {
            // run backfill all once and resets the timer
            if ((int) $runVar['settings']['backfill'] !== 0) {
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:0.2 ' \
					{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/multiprocessing/backfill.php $log; \
					date +\"{$this->_dateFormat}\"; {$runVar['commands']['_sleep']} {$runVar['settings']['seq_timer']}' 2>&1 1> /dev/null"
                );
                $runVar['timers']['timer5'] = time();
            } else {
                $runVar['timers']['timer5'] = time();
            }
        } elseif (($runVar['killswitch']['pp'] === true) && (int) $runVar['settings']['releases_run'] !== 0) {
            $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
            shell_exec(
                "tmux respawnp -t{$runVar['constants']['tmux_session']}:0.2 'echo \"\033[38;5;{$color}m\"; \
				echo \"\nbinaries and backfill has been disabled/terminated by Exceeding Limits\"; \
				{$runVar['scripts']['releases']} $log; date +\"{$this->_dateFormat}\"; echo \"\nbinaries and backfill has been disabled/terminated by Exceeding Limits\"; \
				{$runVar['commands']['_sleep']} {$runVar['settings']['seq_timer']}' 2>&1 1> /dev/null"
            );
        } elseif ($runVar['killswitch']['pp'] === true) {
            $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
            shell_exec("tmux respawnp -t{$runVar['constants']['tmux_session']}:0.2 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['zero'][2]} has been disabled/terminated by Exceeding Limits\"'");
        }
    }

    /**
     * @throws \Exception
     */
    protected function _notRunningNon(&$runVar): void
    {
        $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
        for ($g = 1; $g <= 4; $g++) {
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:0.$g 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['zero'][$g]} has been disabled/terminated by Running\"'");
        }
        for ($g = 0; $g <= 3; $g++) {
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:1.$g 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['one'][$g]} has been disabled/terminated by Running\"'");
        }
        for ($g = 0; $g <= 2; $g++) {
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:2.$g 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['two'][$g]} has been disabled/terminated by Running\"'");
        }
    }

    /**
     * @throws \Exception
     */
    protected function _notRunningBasic(&$runVar): void
    {
        $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
        for ($g = 1; $g <= 2; $g++) {
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:0.$g 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['zero'][$g]} has been disabled/terminated by Running\"'");
        }
        for ($g = 0; $g <= 3; $g++) {
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:1.$g 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['one'][$g]} has been disabled/terminated by Running\"'");
        }
        for ($g = 0; $g <= 2; $g++) {
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:2.$g 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['two'][$g]} has been disabled/terminated by Running\"'");
        }
    }

    /**
     * @throws \Exception
     */
    protected function _notRunningFull(&$runVar): void
    {
        $color = $this->get_color($runVar['settings']['colors_start'], $runVar['settings']['colors_end'], $runVar['settings']['colors_exc']);
        for ($g = 1; $g <= 2; $g++) {
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:0.$g 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['zero'][$g]} has been disabled/terminated by Running\"'");
        }
        for ($g = 0; $g <= 1; $g++) {
            shell_exec("tmux respawnp -k -t{$runVar['constants']['tmux_session']}:1.$g 'echo \"\033[38;5;{$color}m\n{$runVar['panes']['one'][$g]} has been disabled/terminated by Running\"'");
        }
    }

    protected function _runIRCScraper($pane, &$runVar): void
    {
        if ((int) $runVar['constants']['run_ircscraper'] === 1) {
            // Check to see if the pane is dead, if so respawn it.
            if ((int) shell_exec("tmux list-panes -t{$runVar['constants']['tmux_session']}:{$pane} | grep ^0 | grep -c dead") === 1) {
                shell_exec(
                    "tmux respawnp -t{$runVar['constants']['tmux_session']}:{$pane}.0 ' \
					{$runVar['commands']['_phpn']} {$runVar['paths']['scraper']} true'"
                );
            }
        } else {
            shell_exec("tmux respawnp -t{$runVar['constants']['tmux_session']}:{$pane}.0 'echo \"\nIRCScraper has been disabled/terminated by IRCSCraper\"'");
        }
    }

    protected function _runSharing($pane, &$runVar): void
    {
        $sharing = (array) Arr::first(DB::select('SELECT enabled, posting, fetching FROM sharing'));

        if (! empty($sharing) && (int) $sharing['enabled'] === 1 && (int) $runVar['settings']['run_sharing'] === 1 && ((int) $sharing['posting'] === 1 || (int) $sharing['fetching'] === 1) && shell_exec("tmux list-panes -t{$runVar['constants']['tmux_session']}:{$pane} | grep ^0 | grep -c dead") == 1) {
            shell_exec(
                "tmux respawnp -t{$runVar['constants']['tmux_session']}:{$pane}.0 ' \
                    {$runVar['commands']['_php']} {$runVar['paths']['misc']}/update/multiprocessing/postprocess.php sha; \
                    {$runVar['commands']['_sleep']} {$runVar['settings']['sharing_timer']}' 2>&1 1> /dev/null"
            );
        }
    }
}
