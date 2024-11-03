<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Collection;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

$tmuxPath = base_path().'/misc/update/tmux/';
$tmux_session = Settings::settingValue('site.tmux.tmux_session') ?? 0;
$seq = Settings::settingValue('site.tmux.sequential') ?? 0;
$delaytime = Settings::settingValue('..delaytime');
$delaytime = $delaytime ? (int) $delaytime : 2;
$colorCli = new ColorCLI;

Process::run('clear');

//reset collections dateadded to now if dateadded > delay time check
$colorCli->header('Resetting collections that have expired to this moment. This could take some time if many collections need to be reset');

DB::transaction(function () use ($delaytime) {
    Collection::query()->where('dateadded', '<', now()->subHours($delaytime))->update(['dateadded' => now()]);
}, 10);

function command_exist($cmd): bool
{
    $returnVal = Process::run("which $cmd 2>/dev/null");

    return $returnVal->seeInOutput($cmd);
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

function start_apps($tmux_session): void
{
    $processupdate = Settings::settingValue('site.tmux.processupdate');
    $console_bash = Settings::settingValue('site.tmux.console');

    if ((int) $console_bash === 1) {
        Process::run("tmux new-window -t $tmux_session -n bash 'printf \"\033]2;Bash\033\" && bash -i'");
    }
}

function window_utilities($tmux_session)
{
    Process::run("tmux new-window -t $tmux_session -n utils 'printf \"\033]2;fixReleaseNames\033\"'");
    Process::run("tmux splitw -t $tmux_session:1 -v -l 50% 'printf \"\033]2;updateTVandTheaters\033\"'");
    Process::run("tmux selectp -t $tmux_session:1.0; tmux splitw -t $tmux_session:1 -h -l 50% 'printf \"\033]2;removeCrapReleases\033\"'");
    Process::run("tmux selectp -t $tmux_session:1.2; tmux splitw -t $tmux_session:1 -h -l 50% 'printf \"\033]2;decryptHashes\033\"'");
}

function window_stripped_utilities($tmux_session)
{
    Process::run("tmux new-window -t $tmux_session -n utils 'printf \"\033]2;updateTVandTheaters\033\"'");
    Process::run("tmux selectp -t $tmux_session:1.0; tmux splitw -t $tmux_session:1 -h -l 50% 'printf \"\033]2;postprocessing_amazon\033\"'");
}

function window_ircscraper($tmux_session)
{
    Process::run("tmux new-window -t $tmux_session -n IRCScraper 'printf \"\033]2;scrapeIRC\033\"'");
}

function window_post($tmux_session)
{
    Process::run("tmux new-window -t $tmux_session -n post 'printf \"\033]2;postprocessing_additional\033\"'");
    Process::run("tmux splitw -t $tmux_session:2 -v -l 67% 'printf \"\033]2;postprocessing_non_amazon\033\"'");
    Process::run("tmux splitw -t $tmux_session:2 -v -l 50% 'printf \"\033]2;postprocessing_amazon\033\"'");
}

function attach($tmuxPath, $tmux_session): void
{
    Process::run("tmux respawnp -t $tmux_session:0.0 'php ".$tmuxPath."monitor.php'");
    Process::run("tmux select-window -t $tmux_session:0; tmux attach-session -d -t $tmux_session");
}

//create tmux session

$tmuxConfig = $tmuxPath.'tmux.conf';

if ((int) $seq === 1) {
    Process::run("cd {$tmuxPath}; tmux -f $tmuxConfig new-session -d -s $tmux_session -n Monitor 'printf \"\033]2;\"Monitor\"\033\"'");
    Process::run("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -h -l 67% 'printf \"\033]2;update_releases\033\"'");

    window_utilities($tmux_session);
    window_post($tmux_session);
} elseif ((int) $seq === 2) {
    Process::run("cd {$tmuxPath}; tmux -f $tmuxConfig new-session -d -s $tmux_session -n Monitor 'printf \"\033]2;\"Monitor\"\033\"'");
    Process::run("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -h -l 67% 'printf \"\033]2;sequential\033\"'");
    window_stripped_utilities($tmux_session);
} else {
    Process::run("cd {$tmuxPath}; tmux -f $tmuxConfig new-session -d -s $tmux_session -n Monitor 'printf \"\033]2;Monitor\033\"'");
    Process::run("tmux selectp -t $tmux_session:0.0; tmux splitw -t $tmux_session:0 -h -l 67% 'printf \"\033]2;update_binaries\033\"'");
    Process::run("tmux selectp -t $tmux_session:0.2; tmux splitw -t $tmux_session:0 -v -l 67% 'printf \"\033]2;backfill\033\"'");
    Process::run("tmux splitw -t $tmux_session -v -l 50% 'printf \"\033]2;update_releases\033\"'");

    window_utilities($tmux_session);
    window_post($tmux_session);
}
window_ircscraper($tmux_session);
start_apps($tmux_session);
attach($tmuxPath, $tmux_session);
