<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Collection;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\utility\Utility;

$tmuxPath = base_path().'/misc/update/tmux/';
$import = Settings::settingValue('site.tmux.import') ?? 0;
$tmux_session = Settings::settingValue('site.tmux.tmux_session') ?? 0;
$seq = Settings::settingValue('site.tmux.sequential') ?? 0;
$delaytimet = Settings::settingValue('..delaytime');
$delaytimet = $delaytimet ? (int) $delaytimet : 2;
$colorCli = new ColorCLI();

Utility::clearScreen();

echo 'Starting Tmux...'.PHP_EOL;

//check if session exists
$session = shell_exec("tmux list-session | grep $tmux_session");
if ($session !== null) {
    $colorCli->error("tmux session: '".$tmux_session."' is already running, aborting.");
    exit();
}

//reset collections dateadded to now if dateadded > delay time check
$colorCli->header('Resetting collections that have expired to this moment. This could take some time if many collections need to be reset');

Collection::query()->where('dateadded', '<', now()->subHours($delaytimet))->update(['dateadded' => now()]);

function writelog($pane)
{
    $path = NN_RES.'logs';
    $getDate = now()->format('Y_m_d');
    $logs = Settings::settingValue('site.tmux.write_logs');
    if ((int) $logs === 1) {
        return "2>&1 | tee -a $path/$pane-$getDate.log";
    }

    return '';
}

function command_exist($cmd)
{
    $returnVal = exec("which $cmd 2>/dev/null");

    return ! empty($returnVal);
}

//check for apps
$apps = ['time', 'tmux', 'nice', 'tee'];
foreach ($apps as &$value) {
    if (! command_exist($value)) {
        $colorCli->error('Tmux scripts require '.$value.' but its not installed. Aborting.');
        exit();
    }
}

unset($value);

function start_apps($tmux_session)
{
    $htop = Settings::settingValue('site.tmux.htop');
    $vnstat = Settings::settingValue('site.tmux.vnstat');
    $vnstat_args = Settings::settingValue('site.tmux.vnstat_args');
    $tcptrack = Settings::settingValue('site.tmux.tcptrack');
    $tcptrack_args = Settings::settingValue('site.tmux.tcptrack_args');
    $nmon = Settings::settingValue('site.tmux.nmon');
    $bwmng = Settings::settingValue('site.tmux.bwmng');
    $mytop = Settings::settingValue('site.tmux.mytop');
    $redis = Settings::settingValue('site.tmux.redis');
    $showprocesslist = Settings::settingValue('site.tmux.showprocesslist');
    $processupdate = Settings::settingValue('site.tmux.processupdate');
    $console_bash = Settings::settingValue('site.tmux.console');

    if ((int) $htop === 1 && command_exist('htop')) {
        exec("tmux new-window -t $tmux_session -n htop 'printf \"\033]2;htop\033\" && htop'");
    }

    if ((int) $nmon === 1 && command_exist('nmon')) {
        exec("tmux new-window -t $tmux_session -n nmon 'printf \"\033]2;nmon\033\" && nmon -t'");
    }

    if ((int) $vnstat === 1 && command_exist('vnstat')) {
        exec("tmux new-window -t $tmux_session -n vnstat 'printf \"\033]2;vnstat\033\" && watch -n10 \"vnstat ${vnstat_args}\"'");
    }

    if ((int) $tcptrack === 1 && command_exist('tcptrack')) {
        exec("tmux new-window -t $tmux_session -n tcptrack 'printf \"\033]2;tcptrack\033\" && tcptrack ${tcptrack_args}'");
    }

    if ((int) $bwmng === 1 && command_exist('bwm-ng')) {
        exec("tmux new-window -t $tmux_session -n bwm-ng 'printf \"\033]2;bwm-ng\033\" && bwm-ng'");
    }

    if ((int) $mytop === 1 && command_exist('mytop')) {
        exec("tmux new-window -t $tmux_session -n mytop 'printf \"\033]2;mytop\033\" && mytop -u'");
    }

    if ((int) $redis === 1 && command_exist('redis-cli')) {
        exec("tmux new-window -t $tmux_session -n redis-stat 'printf \"\033]2;redis-stat\033\" && redis-stat --verbose --server=63790'");
    }

    if ((int) $showprocesslist === 1) {
        exec("tmux new-window -t $tmux_session -n showprocesslist 'printf \"\033]2;showprocesslist\033\" && watch -n .5 \"mysql -e \\\"SELECT time, state, info FROM information_schema.processlist WHERE command != \\\\\\\"Sleep\\\\\\\" AND time >= $processupdate ORDER BY time DESC \\\G\\\"\"'");
    }

    if ((int) $console_bash === 1) {
        exec("tmux new-window -t $tmux_session -n bash 'printf \"\033]2;Bash\033\" && bash -i'");
    }
}

function window_utilities($tmux_session)
{
    exec("tmux new-window -t $tmux_session -n utils 'printf \"\033]2;fixReleaseNames\033\"'");
    exec("tmux splitw -t $tmux_session:1 -v -p 50 'printf \"\033]2;updateTVandTheaters\033\"'");
    exec("tmux selectp -t $tmux_session:1.0; tmux splitw -t $tmux_session:1 -h -p 50 'printf \"\033]2;removeCrapReleases\033\"'");
    exec("tmux selectp -t $tmux_session:1.2; tmux splitw -t $tmux_session:1 -h -p 50 'printf \"\033]2;decryptHashes\033\"'");
}

function window_stripped_utilities($tmux_session)
{
    exec("tmux new-window -t $tmux_session -n utils 'printf \"\033]2;updateTVandTheaters\033\"'");
    exec("tmux selectp -t $tmux_session:1.0; tmux splitw -t $tmux_session:1 -h -p 50 'printf \"\033]2;postprocessing_amazon\033\"'");
}

function window_ircscraper($tmux_session)
{
    exec("tmux new-window -t $tmux_session -n IRCScraper 'printf \"\033]2;scrapeIRC\033\"'");
}

function window_post($tmux_session)
{
    exec("tmux new-window -t $tmux_session -n post 'printf \"\033]2;postprocessing_additional\033\"'");
    exec("tmux splitw -t $tmux_session:2 -v -p 67 'printf \"\033]2;postprocessing_non_amazon\033\"'");
    exec("tmux splitw -t $tmux_session:2 -v -p 50 'printf \"\033]2;postprocessing_amazon\033\"'");
}

function window_optimize($tmux_session)
{
    exec("tmux new-window -t $tmux_session -n optimize 'printf \"\033]2;update_Tmux\033\"'");
    exec("tmux splitw -t $tmux_session:3 -v -p 50 'printf \"\033]2;optimize\033\"'");
}

function attach($tmuxPath, $tmux_session)
{
    exec("tmux respawnp -t $tmux_session:0.0 'php ".$tmuxPath."monitor.php'");
    exec("tmux select-window -t $tmux_session:0; tmux attach-session -d -t $tmux_session");
}

//create tmux session

$tmuxconfig = $tmuxPath.'tmux.conf';

if ((int) $seq === 1) {
    exec("cd ${tmuxPath}; tmux -f $tmuxconfig new-session -d -s $tmux_session -n Monitor 'printf \"\033]2;\"Monitor\"\033\"'");
    exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -h -p 67 'printf \"\033]2;update_releases\033\"'");
    exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -v -p 25 'printf \"\033]2;nzb-import\033\"'");

    window_utilities($tmux_session);
    window_post($tmux_session);
    window_ircscraper($tmux_session);
    start_apps($tmux_session);
    attach($tmuxPath, $tmux_session);
} elseif ((int) $seq === 2) {
    exec("cd ${tmuxPath}; tmux -f $tmuxconfig new-session -d -s $tmux_session -n Monitor 'printf \"\033]2;\"Monitor\"\033\"'");
    exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -h -p 67 'printf \"\033]2;sequential\033\"'");
    exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -v -p 25 'printf \"\033]2;nzb-import\033\"'");

    window_stripped_utilities($tmux_session);
    window_ircscraper($tmux_session);
    start_apps($tmux_session);
    attach($tmuxPath, $tmux_session);
} else {
    exec("cd ${tmuxPath}; tmux -f $tmuxconfig new-session -d -s $tmux_session -n Monitor 'printf \"\033]2;Monitor\033\"'");
    exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -h -p 67 'printf \"\033]2;update_binaries\033\"'");
    exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -v -p 25 'printf \"\033]2;nzb-import\033\"'");
    exec("tmux selectp -t $tmux_session:0.2; tmux splitw -t $tmux_session:0 -v -p 67 'printf \"\033]2;backfill\033\"'");
    exec("tmux splitw -t $tmux_session -v -p 50 'printf \"\033]2;update_releases\033\"'");

    window_utilities($tmux_session);
    window_post($tmux_session);
    window_ircscraper($tmux_session);
    start_apps($tmux_session);
    attach($tmuxPath, $tmux_session);
}
