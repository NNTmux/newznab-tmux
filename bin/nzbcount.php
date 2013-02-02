<?php

$subdir_count_now = 0;
$filecount=0;

//get variables from defaults.sh
$varnames = shell_exec("cat ../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$vardata = shell_exec('cat ../defaults.sh | grep ^export | cut -d \" -f2 | awk "{print $1;}"');
$varnames = explode("\n", $varnames);
$vardata = explode("\n", $vardata);
$array = array_combine($varnames, $vardata);
unset($array['']);

$i=0;
while ($i==0) {
    system('clear');
    printf("\n\033[1;33m");
    $mask = "%20s %10.10s %10s \n";
    printf($mask, "Folder Name", "In Folder", "Imported");
    printf($mask, "===============", "==========", "==========\033[0m");

    $subdir_count = 0;
    $path = $array['NZBS'];
    $subdirs = array_filter(glob($path.'/*', GLOB_ONLYDIR|GLOB_NOSORT));
    foreach($subdirs AS $subdir){
        $subdir_count++;
    }

    if ( $subdir_count_now == 0 ) {
        $subdir_count_now=$subdir_count;
    }

    if ($subdir_count_now != $subdir_count ) {
        system('clear');
        printf("\n\n\033[1;41;33mYour folder contents changed.");
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
            printf("\033[0m");
            if ( $filecount > 0)
                printf($mask, "{$folder}","$filecount","$processed");
            $totalproc=$totalproc+$processed;
            $toprocess=$toprocess+$filecount;
            $subdir_count_loop++;
        }
        echo ("\033[1;33m");
        printf($mask, "Total","$toprocess","$totalproc");
    } else {
        $filecount0 = count(glob($path.'/*.nzb'));
        if ( $filecount == 0 ) { $filecount = $filecount0; }
        $processed=$filecount-$filecount0;
        $folder=basename("$path");
        printf($mask, "$folder","$filecount0","$processed");
    }
    sleep(90);
}

