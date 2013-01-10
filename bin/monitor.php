<?php

require("config.php");
require_once(WWW_DIR."/lib/postprocess.php");
$_php = getenv("PHP");

$db = new DB();
 /////////////amount of books left to do//////
$book_query = "select count(searchname), ID from releases use index (ix_releases_categoryID) where bookinfoID IS NULL and categoryID = 7020;";
 /////////////amount of games left to do//////
$console_query = "SELECT count(searchname), ID from releases use index (ix_releases_categoryID) where consoleinfoID IS NULL and categoryID in ( select ID from category where parentID = 1000 );";
 /////////////amount of movies left to do//////
$movies_query = "SELECT count(searchname), ID from releases use index (ix_releases_categoryID) where imdbID IS NULL and categoryID in ( select ID from category where parentID = 2000 );";
 /////////////amount of music left to do//////
$music_query = "SELECT count(searchname), ID from releases use index (ix_releases_categoryID) where musicinfoID IS NULL and categoryID in ( select ID from category where parentID = 3000 );";
 ///////////amount of post processing left/////
$postprocessing_count_remaining_query = "select count(*) from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0);";
 /////////////amount of post processing completed//
$postprocessing_completed_count_query = "select count(*) from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus=0);";
 //////////////amount of releases//////////////
$releases_query = "select count(*) from releases";
 /////////////amount of tv left to do/////////
$tvrage_query = "SELECT count(searchname), ID from releases where rageID = -1 and categoryID in ( select ID from category where parentID = 5000 );";


//////////////set up initial counts////
$book_count_start = mysql_query($book_query);
$console_count_start = mysql_query($console_query);
$movie_count_start = mysql_query($movies_query);
$music_count_start = mysql_query($music_query);
$tvrage_count_start = mysql_query($tvrage_query);
$postprocessing_remaining_count_start = mysql_query($postprocessing_count_remaining_query);
$postprocessing_completed_count_start = mysql_query($postprocessing_completed_count_query);
$releases_count_start = mysql_query($releases_query);

/////////////////bresult_begin////////////////////////
if (empty($book_count_start)) {
  $book_count_start = $db->queryDirect($book_query);
  if (empty($book_count_start)) {
    $bmessage = 'Invalid query: ' . mysql_error() . "\n";
    $bmessage .= 'Whole query: ' . $book_query;
    die($bmessage);
  }
}

while ($brow = mysql_fetch_assoc($book_count_start)) {
  $bcount_begin = $brow['count(searchname)'];
}

/////////////////cresult_begin////////////////////////
if (empty($cresult_begin)) {
  $cresult_begin = $db->queryDirect($releases_query);
  if (empty($cresult_begin)) {
    $cmessage = 'Invalid query: ' . mysql_error() . "\n";
    $cmessage .= 'Whole query: ' . $releases_query;
    die($cmessage);
  }
}

while ($crow = mysql_fetch_assoc($cresult_begin)) {
  $ccount_begin = $crow['count(*)'];
}

/////////////////gresult_begin////////////////////////
if (empty($console_count_start)) {
  $console_count_start = $db->queryDirect($console_query);
  if (empty($console_count_start)) {
    $gmessage = 'Invalid query: ' . mysql_error() . "\n";
    $gmessage .= 'Whole query: ' . $console_query;
    die($gmessage);
  }
}

while ($grow = mysql_fetch_assoc($console_count_start)) {
  $gcount_begin = $grow['count(searchname)'];
}

/////////////////moresult_begin////////////////////////
if (empty($movie_count_start)) {
  $movie_count_start = $db->queryDirect($movies_query);
  if (empty($movie_count_start)) {
    $momessage = 'Invalid query: ' . mysql_error() . "\n";
    $momessage .= 'Whole query: ' . $movies_query;
    die($momessage);
  }
}

while ($morow = mysql_fetch_assoc($movie_count_start)) {
  $mocount_begin = $morow['count(searchname)'];
}

/////////////////muresult_begin////////////////////////
if (empty($music_count_start)) {
  $music_count_start = $db->queryDirect($music_query);
  if (empty($music_count_start)) {
    $mumessage = 'Invalid query: ' . mysql_error() . "\n";
    $mumessage .= 'Whole query: ' . $music_query;
    die($mumessage);
  }
}

while ($murow = mysql_fetch_assoc($music_count_start)) {
  $mucount_begin = $murow['count(searchname)'];
}

///////////////////presult_begin///////////////////////
if (empty($postprocessing_remaining_count_start)) {
  $postprocessing_remaining_count_start = $db->queryDirect($postprocessing_count_remaining_query);
  if (empty($postprocessing_remaining_count_start)) {
    $pmessage = 'Invalid query: ' . mysql_error() . "\n";
    $pmessage .= 'Whole query: ' . $postprocessing_count_remaining_query;
    die($pmessage);
  }
}

while ($prow = mysql_fetch_assoc($postprocessing_remaining_count_start)) {
  $postprocessing_remaining_count_loop_start = $prow['count(*)'];
}

///////////////////plresult_begin///////////////////////
if (empty($postprocessing_completed_count_start)) {
  $postprocessing_completed_count_start = $db->queryDirect($postprocessing_completed_count_query);
  if (empty($postprocessing_completed_count_start)) {
    $plmessage = 'Invalid query: ' . mysql_error() . "\n";
    $plmessage .= 'Whole query: ' . $postprocessing_completed_count_query;
    die($plmessage);
  }
}

while ($plrow = mysql_fetch_assoc($postprocessing_completed_count_start)) {
  $plcount_begin = $plrow['count(*)'];
}

/////////////////result_begin////////////////////////
if (empty($releases_count_start)) {
  $releases_count_start = $db->queryDirect($releases_query);
  if (empty($releases_count_start)) {
    $message = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $releases_query;
    die($message);
  }
}

while ($row = mysql_fetch_assoc($releases_count_start)) {
  $count_begin = $row['count(*)'];
}

/////////////////tresult_begin////////////////////////
if (empty($tvrage_count_start)) {
  $tvrage_count_start = $db->queryDirect($tvrage_query);
  if (empty($releases_count_start)) {
    $tmessage = 'Invalid query: ' . mysql_error() . "\n";
    $tmessage .= 'Whole query: ' . $tvrage_query;
    die($tmessage);
  }
}

while ($trow = mysql_fetch_assoc($tvrage_count_start)) {
  $tcount_begin = $trow['count(searchname)'];
}

 ////////////////////////time////////////////////////////
$time = TIME();

$i=1;
while($i>0)
{

  $secs = TIME() - $time;
  $mins = floor($secs / 60);
  $hrs = floor($mins / 60);
  $days = floor($hrs / 24);
  $sec = floor($secs % 60);
  $min = ($mins % 60);
  $hr = ($hrs % 60);
  $day = ($days % 24);

  $book_count_loop_start = mysql_query($book_query);
  $console_count_loop_start = mysql_query($console_query);
  $movies_count_loop_start = mysql_query($movies_query);
  $music_count_loop_start = mysql_query($music_query);
  $postprocessing_count_remaining_loop_start = mysql_query($postprocessing_count_remaining_query);
  $postprocessing_count_completed_loop_start = mysql_query($postprocessing_completed_count_query);
  $releases_count_loop_start = mysql_query($releases_query);
  $tvrage_count_loop_start = mysql_query($tvrage_query);

  //////////////////Change this for sleep time////////////
  $sleeptime = "60";
  if ($i!=1) {
    sleep($sleeptime);
  }

  $book_count_inner_loop = mysql_query($book_query);
  $console_count_inner_loop = mysql_query($console_query);
  $movies_count_inner_loop = mysql_query($movies_query);
  $music_count_inner_loop = mysql_query($music_query);
  $postprocessing_count_remaining_inner_loop = mysql_query($postprocessing_count_remaining_query);
  $postprocessing_count_completed_inner_loop = mysql_query($postprocessing_completed_count_query);
  $releases_count_inner_loop = mysql_query($releases_query);
  $tvrage_count_inner_loop = mysql_query($tvrage_query);

  ///////////////////bresult_loop/////////////////////////
  if (empty($book_count_inner_loop)) {
    $book_count_inner_loop = $db->queryDirect($book_query);
    if (empty($book_count_inner_loop)) {
      $bmessage = 'Invalid query: ' . mysql_error() . "\n";
      $bmessage .= 'Whole query: ' . $book_query;
      die($bmessage);
    }
  }

  while ($brow = mysql_fetch_assoc($book_count_inner_loop)) {
    $book_count_now = $brow['count(searchname)'];
  }

  ///////////////////gresult_loop/////////////////////////
  if (empty($console_count_inner_loop)) {
    $console_count_inner_loop = $db->queryDirect($console_query);
    if (empty($console_count_inner_loop)) {
      $gmessage = 'Invalid query: ' . mysql_error() . "\n";
      $gmessage .= 'Whole query: ' . $console_query;
      die($gmessage);
    }
  }

  while ($grow = mysql_fetch_assoc($console_count_inner_loop)) {
    $console_count_now = $grow['count(searchname)'];
  }

  ///////////////////moresult_loop/////////////////////////
  if (empty($movies_count_inner_loop)) {
    $movies_count_inner_loop = $db->queryDirect($movies_query);
    if (empty($movies_count_inner_loop)) {
      $momessage = 'Invalid query: ' . mysql_error() . "\n";
      $momessage .= 'Whole query: ' . $movies_query;
      die($momessage);
    }
  }

  while ($morow = mysql_fetch_assoc($movies_count_inner_loop)) {
    $movies_count_now = $morow['count(searchname)'];
  }

  ///////////////////muresult_loop/////////////////////////
  if (empty($music_count_inner_loop)) {
    $music_count_inner_loop = $db->queryDirect($music_query);
    if (empty($music_count_inner_loop)) {
      $mumessage = 'Invalid query: ' . mysql_error() . "\n";
      $mumessage .= 'Whole query: ' . $releases_query;
      die($mumessage);
    }
  }

  while ($murow = mysql_fetch_assoc($music_count_inner_loop)) {
    $music_count_now = $murow['count(searchname)'];
  }

  ////////////////presult_inner_loop///////////////////////
  if (empty($postprocessing_count_remaining_loop_start)) {
    $postprocessing_count_remaining_loop_start = $db->queryDirect($postprocessing_count_remaining_query);
    if (empty($postprocessing_count_remaining_loop_start)) {
      $pmessage = 'Invalid query: ' . mysql_error() . "\n";
      $pmessage .= 'Whole query: ' . $postprocessing_count_remaining_query;
      die($pmessage);
    }
 }

  while ($prow = mysql_fetch_assoc($postprocessing_count_remaining_loop_start)) {
    $pcount_inner_loop = $prow['count(*)'];
  }

  ///////////////////presult_loop////////////////////////
  if (empty($postprocessing_count_remaining_inner_loop)) {
    $postprocessing_count_remaining_inner_loop = $db->queryDirect($postprocessing_count_remaining_query);
    if (empty($releases_count_inner_loop)) {
      $pmessage = 'Invalid query: ' . mysql_error() . "\n";
      $pmessage .= 'Whole query: ' . $postprocessing_count_remaining_query;
      die($pmessage);
    }
  }

  while ($prow = mysql_fetch_assoc($postprocessing_count_remaining_inner_loop)) {
    $postprocessing_count_remaining_this_loop = $prow['count(*)'];
  }

  /////////////////////plresult_loop//////////////////////
  if (empty($pdresult_loop)) {
    $postprocessing_count_completed_inner_loop = $db->queryDirect($postprocessing_completed_count_query);
    if (empty($postprocessing_count_completed_inner_loop)) {
      $plmessage = 'Invalid query: ' . mysql_error() . "\n";
      $plmessage .= 'Whole query: ' . $postprocessing_completed_count_query;
      die($plmessage);
    }
  }

  while ($plrow = mysql_fetch_assoc($postprocessing_count_completed_inner_loop)) {
    $plcount_loop = $plrow['count(*)'];
  }

 ////////////////result_inner_loop///////////////////////
  if (empty($releases_count_loop_start)) {
    $releases_count_loop_start = $db->queryDirect($releases_query);
    if (empty($releases_count_loop_start)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $releases_query;
      die($message);
    }
 }

  while ($row = mysql_fetch_assoc($releases_count_loop_start)) {
    $count_inner_loop = $row['count(*)'];
  }

  ///////////////////result_loop/////////////////////////
  if (empty($releases_count_inner_loop)) {
    $releases_count_inner_loop = $db->queryDirect($releases_query);
    if (empty($releases_count_inner_loop)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $releases_query;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($releases_count_inner_loop)) {
    $count_loop = $row['count(*)'];
  }

  ///////////////////tresult_loop/////////////////////////
  if (empty($tvrage_count_inner_loop)) {
    $tvrage_count_inner_loop = $db->queryDirect($tvrage_query);
    if (empty($releases_count_inner_loop)) {
      $tmessage = 'Invalid query: ' . mysql_error() . "\n";
      $tmessage .= 'Whole query: ' . $tvrage_query;
      die($tmessage);
    }
  }

  while ($trow = mysql_fetch_assoc($tvrage_count_inner_loop)) {
    $tvrage_count_now = $trow['count(searchname)'];
  }

  $btotal_start = $bcount_begin - $book_count_now;//if ($btotal_start < 0) $btotal_start = 0;
  $gtotal_start = $gcount_begin - $console_count_now;//if ($gtotal_start < 0) $gtotal_start = 0;
  $mototal_start = $mocount_begin - $movies_count_now;//if ($mototal_start < 0) $mototal_start = 0;
  $mutotal_start = $mucount_begin - $music_count_now;//if ($mutotal_start < 0) $mutotal_start = 0;
  $ttotal_start = $tcount_begin - $tvrage_count_now;//if ($ttotal_start < 0) $ttotal_start = 0;
  $ptotal_start = $postprocessing_remaining_count_loop_start - $postprocessing_count_remaining_this_loop;//if ($ptotal_start < 0) $ptotal_start = 0;

  //calculate the difference from start to now
  $ptotal_loop = $postprocessing_count_remaining_this_loop - $pcount_inner_loop;if ($ptotal_loop < 0) $ptotal_loop = 0;
  $total_start = $count_loop - $count_begin;if ($total_start < 0) $total_start = 0;
  $total_loop = $count_loop - $count_inner_loop;if ($total_loop < 0) $total_loop = 0;

  $sleeptext = "in the past $sleeptime seconds.";

  passthru('clear');

  printf("Monitoring the releases in your database.\n\n");
  printf("The script was started: \033[38;5;160m$day\033[0m");printf(" Days ");
  printf("\033[38;5;208m$hr\033[0m");printf(" Hours ");
  printf("\033[38;5;020m$min\033[0m");printf(" Minutes ");
  printf("\033[38;5;063m$sec\033[0m");printf(" Seconds Ago.\n");
  printf("The script updates every $sleeptime seconds.\n");
  printf("$ptotal_loop releases post processed since last update.\n");
  printf("$total_loop releases added since last update.\n\n");

  printf("$ccount_begin releases at start.\n");
  printf("$count_loop releases in your database.\n");
  printf("$total_start releases have been added.\n\n");

  $mask = "%8s %6s %6s %6s \n";
  printf($mask, "Cat", "Proc", "Remain", "In DB");
  printf($mask, "========", "======", "======", "======");
  printf($mask, "Books", "$btotal_start", "$book_count_now", "0");
  printf($mask, "Console", "$gtotal_start", "$console_count_now", "0");
  printf($mask, "Movies", "$mototal_start", "$movies_count_now", "0");
  printf($mask, "Music", "$mutotal_start", "$music_count_now", "0");
  printf($mask, "TVShows", "$ttotal_start", "$tvrage_count_now", "0");
  printf($mask, "Add'l", "$ptotal_start", "$postprocessing_count_remaining_this_loop", "0");



  $i=$i+1;
}

mysql_free_result($book_count_inner_loop);
mysql_free_result($console_count_inner_loop);
mysql_free_result($movies_count_inner_loop);
mysql_free_result($music_count_inner_loop);
mysql_free_result($postprocessing_remaining_count_loop_start);
mysql_free_result($plcount_begin);
mysql_free_result($postprocessing_count_completed_inner_loop);
mysql_free_result($postprocessing_count_remaining_inner_loop);
mysql_free_result($releases_count_start);
mysql_free_result($releases_count_inner_loop);
mysql_free_result($tvrage_count_inner_loop);

?>

