<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap.php';

use nntmux\Tmux;
use nntmux\db\DB;
use nntmux\ColorCLI;
use App\Models\Settings;
use nntmux\utility\Utility;

$pdo = new DB();
$DIR = NN_TMUX;
$t = new Tmux();
$tmux = $t->get();
$patch = Settings::settingValue('..sqlpatch');
$import = $tmux->import ?? 0;
$tmux_session = $tmux->tmux_session ?? 0;
$seq = $tmux->sequential ?? 0;
$powerline = $tmux->powerline ?? 0;
$colors = $tmux->colors ?? 0;
$delaytimet = Settings::settingValue('..delaytime');
$delaytimet = $delaytimet ? (int) $delaytimet : 2;

Utility::isPatched();
Utility::clearScreen();

echo 'Starting Tmux...'.PHP_EOL;
// Create a placeholder session so tmux commands do not throw server not found errors.
exec('tmux new-session -ds placeholder 2>/dev/null');
exec('tmux list-session', $session);

//check if session exists
$session = shell_exec("tmux list-session | grep $tmux_session");
// Kill the placeholder
exec('tmux kill-session -t placeholder');
if (count($session) !== 0) {
    exit(ColorCLI::error("tmux session: '".$tmux_session."' is already running, aborting.\n"));
}

//reset collections dateadded to now if dateadded > delay time check
echo ColorCLI::header('Resetting expired collections dateadded to now. This could take a minute or two. Really.');

$sql = 'SHOW table status';
$tables = $pdo->queryDirect($sql);
$ran = 0;
foreach ($tables as $row) {
    $tbl = $row['name'];
    if (preg_match('/(multigroup\_)?collections(_\d+)?/', $tbl)) {
        $run = $pdo->queryExec(
            'UPDATE '.$tbl.
            ' SET dateadded = now() WHERE dateadded < now() - INTERVAL '.
            $delaytimet.' HOUR'
        );
        if ($run !== false) {
            $ran += $run->rowCount();
        }
    }
}
echo ColorCLI::primary(number_format($ran).' collections reset.');
sleep(2);

function writelog($pane)
{
    $path = NN_RES.'logs';
    $getdate = gmdate('Ymd');
    $tmux = new Tmux();
    $logs = $tmux->get()->write_logs;
    if ((int) $logs === 1) {
        return "2>&1 | tee -a $path/$pane-$getdate.log";
    }

    return '';
}

function command_exist($cmd)
{
    $returnVal = exec("which $cmd 2>/dev/null");

    return empty($returnVal) ? false : true;
}

//check for apps
$apps = ['time', 'tmux', 'nice', 'python', 'tee'];
foreach ($apps as &$value) {
    if (! command_exist($value)) {
        exit(ColorCLI::error('Tmux scripts require '.$value.' but its not installed. Aborting.'.PHP_EOL));
    }
}

unset($value);

function python_module_exist($module)
{
    $output = $returnCode = '';
    exec("python -c \"import $module\"", $output, $returnCode);

    return (int) $returnCode === 0 ? true : false;
}

function start_apps($tmux_session)
{
    $t = new Tmux();
    $tmux = $t->get();
    $htop = $tmux->htop;
    $vnstat = $tmux->vnstat;
    $vnstat_args = $tmux->vnstat_args;
    $tcptrack = $tmux->tcptrack;
    $tcptrack_args = $tmux->tcptrack_args;
    $nmon = $tmux->nmon;
    $bwmng = $tmux->bwmng;
    $mytop = $tmux->mytop;
    $showprocesslist = $tmux->showprocesslist;
    $processupdate = $tmux->processupdate;
    $console_bash = $tmux->console;

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

    if ((int) $showprocesslist === 1) {
        exec("tmux new-window -t $tmux_session -n showprocesslist 'printf \"\033]2;showprocesslist\033\" && watch -n .5 \"mysql -e \\\"SELECT time, state, info FROM information_schema.processlist WHERE command != \\\\\\\"Sleep\\\\\\\" AND time >= $processupdate ORDER BY time DESC \\\G\\\"\"'");
    }
    //exec("tmux new-window -t $tmux_session -n showprocesslist 'printf \"\033]2;showprocesslist\033\" && watch -n .2 \"mysql -e \\\"SELECT time, state, rows_examined, info FROM information_schema.processlist WHERE command != \\\\\\\"Sleep\\\\\\\" AND time >= $processupdate ORDER BY time DESC \\\G\\\"\"'");

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

function window_sharing($tmux_session)
{
    $pdo = new nntmux\db\DB();
    $sharing = $pdo->queryOneRow('SELECT enabled, posting, fetching FROM sharing');
    $t = new Tmux();
    $tmux = $t->get();
    $tmux_share = $tmux->run_sharing ?? 0;

    if ($tmux_share && (int) $sharing['enabled'] === 1 && ((int) $sharing['posting'] === 1 || (int) $sharing['fetching'] === 1)) {
        exec("tmux new-window -t $tmux_session -n Sharing 'printf \"\033]2;comment_sharing\033\"'");
    }
}

function attach($DIR, $tmux_session)
{
    $PHP = 'php';

    //get list of panes by name
    $panes_win_1 = exec("echo `tmux list-panes -t $tmux_session:0 -F '#{pane_title}'`");
    $panes0 = str_replace("\n", '', explode(' ', $panes_win_1));
    $log = writelog($panes0[0]);
    exec("tmux respawnp -t $tmux_session:0.0 '$PHP ".$DIR."monitor.php $log'");
    exec("tmux select-window -t $tmux_session:0; tmux attach-session -d -t $tmux_session");
}

//create tmux session
if ((int) $powerline === 1) {
    $tmuxconfig = $DIR.'powerline/tmux.conf';
} else {
    $tmuxconfig = $DIR.'tmux.conf';
}

if ((int) $seq === 1) {
    exec("cd ${DIR}; tmux -f $tmuxconfig new-session -d -s $tmux_session -n Monitor 'printf \"\033]2;\"Monitor\"\033\"'");
    exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -h -p 67 'printf \"\033]2;update_releases\033\"'");
    exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -v -p 25 'printf \"\033]2;nzb-import\033\"'");

    window_utilities($tmux_session);
    window_post($tmux_session);
    window_ircscraper($tmux_session);
    window_sharing($tmux_session);
    start_apps($tmux_session);
    attach($DIR, $tmux_session);
} elseif ((int) $seq === 2) {
    exec("cd ${DIR}; tmux -f $tmuxconfig new-session -d -s $tmux_session -n Monitor 'printf \"\033]2;\"Monitor\"\033\"'");
    exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -h -p 67 'printf \"\033]2;sequential\033\"'");
    exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -v -p 25 'printf \"\033]2;nzb-import\033\"'");

    window_stripped_utilities($tmux_session);
    window_ircscraper($tmux_session);
    window_sharing($tmux_session);
    start_apps($tmux_session);
    attach($DIR, $tmux_session);
} else {
    exec("cd ${DIR}; tmux -f $tmuxconfig new-session -d -s $tmux_session -n Monitor 'printf \"\033]2;Monitor\033\"'");
    exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -h -p 67 'printf \"\033]2;update_binaries\033\"'");
    exec("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -v -p 25 'printf \"\033]2;nzb-import\033\"'");
    exec("tmux selectp -t $tmux_session:0.2; tmux splitw -t $tmux_session:0 -v -p 67 'printf \"\033]2;backfill\033\"'");
    exec("tmux splitw -t $tmux_session -v -p 50 'printf \"\033]2;update_releases\033\"'");

    window_utilities($tmux_session);
    window_post($tmux_session);
    window_ircscraper($tmux_session);
    window_sharing($tmux_session);
    start_apps($tmux_session);
    attach($DIR, $tmux_session);
}
