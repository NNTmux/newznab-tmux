<?php

$i=0;
while ($i==0) {
  $subdir_count = 0;

  $path = getenv('NZBS');
  $subdirs = array_filter(glob($path."/*", GLOB_ONLYDIR|GLOB_NOSORT));
  foreach($subdirs AS $subdir){
    $subdir_count++;
  }

  if($subdir_count != 0){
    foreach($subdirs AS $subdir){
      $filecount0[] = count(glob($subdir."/*.nzb"));
    }
    $subdir_count_loop = 0;
    system("clear");
    printf("\033[1;33m");
    $mask = "%16s %10.10s %10s \n";
    printf($mask, "Folder Name", "In Folder", "Processed");
    printf($mask, "===============", "==========", "==========\033[0m");
    foreach($subdirs AS $subdir){
      $folder=basename("$subdir");
      $filecount = count(glob($subdir."/*.nzb"));
      $processed=$filecount0[$subdir_count_loop]-$filecount;
      printf("\033[0m");
      printf($mask, "{$folder}","$filecount","$processed");
      $subdir_count_loop++;
    }
  } else {
    $filecount0 = count(glob($path."/*.nzb"));
    $filecount = count(glob($path."/*.nzb"));
    $processed=$filecount0-$filecount;
    $folder=basename("$path");
    system("clear");
    printf("\033[1;33m");
    $mask = "%16s %10.10s %10s \n";
    printf($mask, "Folder Name", "In Folder", "Processed");
    printf($mask, "===============", "==========", "==========\033[0m");
    printf($mask, "$folder","$filecount","$processed");
  }
  sleep(30);
}
