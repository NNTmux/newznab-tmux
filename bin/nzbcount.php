<?php

$subdir_count_now = 0;
$filecount=0;

//get variables from config.sh and defaults.sh
$path = dirname(__FILE__);
$varnames = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$varnames .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$vardata = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
$vardata .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
$varnames = explode("\n", $varnames);
$vardata = explode("\n", $vardata);
$array = array_combine($varnames, $vardata);
unset($array['']);

$current = $array['NZB_THREADS'];

$i=0;
while ($i==0) {
    system('clear');

    printf("\033[1;33m");
    $mask = "%-15.15s %22.22s %22.22s\n";
    printf($mask, "Folder Name", "In Folder", "Imported");
    printf($mask, "====================", "====================", "====================");
    printf("\033[38;5;214m");

    $subdir_count = 0;
    $subpath = $array['NZBS'];
    $subdirs = array_filter(glob($subpath.'/*', GLOB_ONLYDIR|GLOB_NOSORT));
    foreach($subdirs AS $subdir){
        $subdir_count++;
    }

    if ( $subdir_count_now == 0 ) {
        $subdir_count_now=$subdir_count;
    }

    //get variables from config.sh and defaults.sh
    $path = dirname(__FILE__);
    $varnames = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
    $varnames .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
    $vardata = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
    $vardata .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
    $varnames = explode("\n", $varnames);
    $vardata = explode("\n", $vardata);
    $array = array_combine($varnames, $vardata);
    unset($array['']);

    if (($subdir_count_now != $subdir_count ) || ( $current != $array['NZB_THREADS'] )) {
        system('clear');
        printf("\n\033[1;41;33mYour imports settings have changed.");
        break;
    }

    if($subdir_count != 0){
        foreach($subdirs AS $subdir){
            $filecount0[] = count(glob($subdir.'/*.nzb'));
        }
        $subdir_count_loop = 0;
        $totalproc=0;
        $toprocess=0;
        foreach($subdirs AS $subdir){
            $folder=basename("$subdir");
            $filecount = count(glob($subdir.'/*.nzb'));
            $processed=$filecount0[$subdir_count_loop]-$filecount;
            printf("\033[38;5;214m");
            if ( $filecount > 0)
                printf($mask, "{$folder}","$filecount","$processed");
            $totalproc=$totalproc+$processed;
            $toprocess=$toprocess+$filecount;
            $subdir_count_loop++;
        }
        echo ("\033[1;33m");
        printf($mask, "____________________", "____________________", "____________________");
        printf($mask, "Total","$toprocess","$totalproc");
    } else {
        $filecount0 = count(glob($subpath.'/*.nzb'));
        if ( $filecount == 0 ) { $filecount = $filecount0; }
        $processed=$filecount-$filecount0;
        $folder=basename("$subpath");
        printf($mask, "$folder","$filecount0","$processed");
    }
    sleep(90);
}

