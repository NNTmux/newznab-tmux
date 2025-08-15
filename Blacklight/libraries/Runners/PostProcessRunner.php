<?php

namespace Blacklight\libraries\Runners;

use App\Models\Settings;
use Blacklight\NZB;
use Spatie\Async\Output\SerializableException;
use Illuminate\Support\Facades\DB;

class PostProcessRunner extends BaseRunner
{
    private function runPostProcess(array $releases, int $maxProcesses, string $type, string $desc): void
    {
        if (empty($releases)) {
            $this->headerNone();
            return;
        }

        $pool = $this->createPool($maxProcesses);
        $count = count($releases);
        $this->headerStart('postprocess: '.$desc, $count, $maxProcesses);

        foreach ($releases as $release) {
            $pool->add(function () use ($release, $type) {
                return $this->executeCommand(PHP_BINARY.' misc/update/postprocess.php '.$type.$release->id);
            }, self::ASYNC_BUFFER_SIZE)->then(function ($output) use (&$count, $desc) {
                echo $output;
                $this->colorCli->primary('Finished task #'.$count.' for '.$desc);
                $count--;
            })->catch(function (\Throwable $exception) {
                echo $exception->getMessage();
            })->catch(static function (SerializableException $serializableException) {
                // swallow
            })->timeout(function () use ($desc, &$count) {
                $this->colorCli->notice('Task #'.$count.' ('.$desc.'): Timeout occurred.');
            });
        }

        $pool->wait();
    }

    public function processAdditional(): void
    {
        $ppAddMinSize = Settings::settingValue('minsizetopostprocess') !== '' ? (int) Settings::settingValue('minsizetopostprocess') : 1;
        $ppAddMinSize = ($ppAddMinSize > 0 ? ('AND r.size > '.($ppAddMinSize * 1048576)) : '');
        $ppAddMaxSize = (Settings::settingValue('maxsizetopostprocess') !== '') ? (int) Settings::settingValue('maxsizetopostprocess') : 100;
        $ppAddMaxSize = ($ppAddMaxSize > 0 ? ('AND r.size < '.($ppAddMaxSize * 1073741824)) : '');

        $sql = '
            SELECT r.leftguid AS id
            FROM releases r
            LEFT JOIN categories c ON c.id = r.categories_id
            WHERE r.nzbstatus = '.NZB::NZB_ADDED.'
            AND r.passwordstatus = -1
            AND r.haspreview = -1
            AND c.disablepreview = 0
            '.$ppAddMaxSize.' '.$ppAddMinSize.'
            GROUP BY r.leftguid
            LIMIT 16';
        $queue = DB::select($sql);

        $maxProcesses = (int) Settings::settingValue('postthreads');
        $this->runPostProcess($queue, $maxProcesses, 'additional true ', 'additional postprocessing');
    }

    public function processNfo(): void
    {
        if ((int) Settings::settingValue('lookupnfo') !== 1) {
            $this->headerNone();
            return;
        }

        $nfoQuery = \Blacklight\Nfo::NfoQueryString();

        $checkSql = 'SELECT r.id FROM releases r WHERE 1=1 '.$nfoQuery.' LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();
            return;
        }

        $sql = '
            SELECT r.leftguid AS id
            FROM releases r
            WHERE 1=1 '.$nfoQuery.'
            GROUP BY r.leftguid
            LIMIT 16';
        $queue = DB::select($sql);

        $maxProcesses = (int) Settings::settingValue('nfothreads');
        $this->runPostProcess($queue, $maxProcesses, 'nfo true ', 'nfo postprocessing');
    }

    public function processMovies(bool $renamedOnly): void
    {
        if ((int) Settings::settingValue('lookupimdb') <= 0) {
            $this->headerNone();
            return;
        }

        $condLookup = ((int) Settings::settingValue('lookupimdb') === 2 ? 'AND isrenamed = 1' : '');
        $condRenamedOnly = ($renamedOnly ? 'AND isrenamed = 1' : '');

        $checkSql = '
            SELECT id
            FROM releases
            WHERE categories_id BETWEEN 2000 AND 2999
            AND nzbstatus = '.NZB::NZB_ADDED.'
            AND imdbid IS NULL
            '.$condLookup.' '.$condRenamedOnly.'
            LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();
            return;
        }

        $renamedFlag = ($renamedOnly ? 2 : 1);
        $sql = '
            SELECT leftguid AS id, '.$renamedFlag.' AS renamed
            FROM releases
            WHERE categories_id BETWEEN 2000 AND 2999
            AND nzbstatus = '.NZB::NZB_ADDED.'
            AND imdbid IS NULL
            '.$condLookup.' '.$condRenamedOnly.'
            GROUP BY leftguid
            LIMIT 16';
        $queue = DB::select($sql);

        $maxProcesses = (int) Settings::settingValue('postthreadsnon');
        $this->runPostProcess($queue, $maxProcesses, 'movies true ', 'movies postprocessing');
    }

    public function processTv(bool $renamedOnly): void
    {
        if ((int) Settings::settingValue('lookuptv') <= 0) {
            $this->headerNone();
            return;
        }

        $condLookup = ((int) Settings::settingValue('lookuptv') === 2 ? 'AND isrenamed = 1' : '');
        $condRenamedOnly = ($renamedOnly ? 'AND isrenamed = 1' : '');

        $checkSql = '
            SELECT id
            FROM releases
            WHERE categories_id BETWEEN 5000 AND 5999
            AND nzbstatus = '.NZB::NZB_ADDED.'
            AND size > 1048576
            AND tv_episodes_id BETWEEN -2 AND 0
            '.$condLookup.' '.$condRenamedOnly;
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();
            return;
        }

        $renamedFlag = ($renamedOnly ? 2 : 1);
        $sql = '
            SELECT leftguid AS id, '.$renamedFlag.' AS renamed
            FROM releases
            WHERE categories_id BETWEEN 5000 AND 5999
            AND nzbstatus = '.NZB::NZB_ADDED.'
            AND tv_episodes_id BETWEEN -2 AND 0
            AND size > 1048576
            '.$condLookup.' '.$condRenamedOnly.'
            GROUP BY leftguid
            LIMIT 16';
        $queue = DB::select($sql);

        $maxProcesses = (int) Settings::settingValue('postthreadsnon');
        $this->runPostProcess($queue, $maxProcesses, 'tv true ', 'tv postprocessing');
    }
}
