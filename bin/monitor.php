<?php

require("config.php");
require_once(WWW_DIR."/lib/postprocess.php");
$_php = getenv("PHP");

$db = new DB();
 /////////////amount of books left to do//////
$book_query = "select count(searchname), ID from releases use index (ix_releases_categoryID) where bookinfoID IS NULL and categoryID = 7020;";
$book_query2 = "select count(*) from releases where categoryID = 7020;";

 /////////////amount of games left to do//////
$console_query = "SELECT count(searchname), ID from releases use index (ix_releases_categoryID) where consoleinfoID IS NULL and categoryID in ( select ID from category where parentID = 1000 );";
$console_query2 = "SELECT count(searchname), ID from releases use index (ix_releases_categoryID) where categoryID in ( select ID from category where parentID = 1000 );";

 /////////////amount of movies left to do//////
$movies_query = "SELECT count(searchname), ID from releases use index (ix_releases_categoryID) where imdbID IS NULL and categoryID in ( select ID from category where parentID = 2000 );";
$movies_query2 = "SELECT count(searchname), ID from releases use index (ix_releases_categoryID) where categoryID in ( select ID from category where parentID = 2000 );";

 /////////////amount of music left to do//////
$music_query = "SELECT count(searchname), ID from releases use index (ix_releases_categoryID) where musicinfoID IS NULL and categoryID in ( select ID from category where parentID = 3000 );";
$music_query2 = "SELECT count(searchname), ID from releases use index (ix_releases_categoryID) where categoryID in ( select ID from category where parentID = 3000 );";

 /////////////amount of movies left to do//////
$pc_query = "select count(*) from releases r left join category c on c.ID = r.categoryID where (categoryID in ( select ID from category where parentID = 4000)) and ((r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0));";
$pc_query2 = "SELECT count(searchname), ID from releases use index (ix_releases_categoryID) where categoryID in ( select ID from category where parentID = 4000);";

 /////////////amount of tv left to do/////////
$tvrage_query = "SELECT count(searchname), ID from releases where rageID = -1 and categoryID in ( select ID from category where parentID = 5000 );";
$tvrage_query2 = "SELECT count(searchname), ID from releases where categoryID in ( select ID from category where parentID = 5000 );";

 ///////////amount of post processing left/////
$postprocessing_count_remaining_query = "select count(*) from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0);";

 /////////////amount of post processing completed//
$postprocessing_completed_count_query = "select count(*) from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus=0);";

 //////////////amount of releases//////////////
$release_count_query = "select count(*) from releases";

//////////////set up initial counts////
$book_count_start = mysql_query($book_query);
$console_count_start = mysql_query($console_query);
$movie_count_start = mysql_query($movies_query);
$music_count_start = mysql_query($music_query);
$tvrage_count_start = mysql_query($tvrage_query);
$postprocessing_remaining_count_start = mysql_query($postprocessing_count_remaining_query);
$postprocessing_completed_count_start = mysql_query($postprocessing_completed_count_query);
$releases_count_start = mysql_query($release_count_query);

///////////////////presult_begin///////////////////////
if (empty($postprocessing_remaining_count_start)) {
  $postprocessing_remaining_count_start = $db->queryDirect($postprocessing_count_remaining_query);
  if (empty($postprocessing_remaining_count_start)) {
    $message = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $postprocessing_count_remaining_query;
    die($message);
  }
}

while ($row = mysql_fetch_assoc($postprocessing_remaining_count_start)) {
  $postprocessing_remaining_count_loop_start = $row['count(*)'];
}

/////////////////result_begin////////////////////////
if (empty($releases_count_start)) {
  $releases_count_start = $db->queryDirect($release_count_query);
  if (empty($releases_count_start)) {
    $message = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $release_count_query;
    die($message);
  }
}
while ($row = mysql_fetch_assoc($releases_count_start)) {
  $count_begin = $row['count(*)'];
}

 ////////////////////////time////////////////////////////
$time = TIME();


///while loop
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

  $postprocessing_count_remaining_loop_start = mysql_query($postprocessing_count_remaining_query);
  $releases_count_loop_start = mysql_query($release_count_query);


  //////////////////Change this for sleep time////////////
  $sleeptime = "15";
  if ($i!=1) {
    sleep($sleeptime);
  }

  $book_count_inner_loop = mysql_query($book_query);
  $console_count_inner_loop = mysql_query($console_query);
  $movies_count_inner_loop = mysql_query($movies_query);
  $music_count_inner_loop = mysql_query($music_query);
  $postprocessing_count_remaining_inner_loop = mysql_query($postprocessing_count_remaining_query);
  $postprocessing_count_completed_inner_loop = mysql_query($postprocessing_completed_count_query);
  $releases_count_inner_loop = mysql_query($release_count_query);
  $tvrage_count_inner_loop = mysql_query($tvrage_query);
  $pc_count_inner_loop = mysql_query($pc_query);


  //////////////counts in loop////
  $book_releases_now = mysql_query($book_query2);
  $console_releases_now = mysql_query($console_query2);
  $movie_releases_now = mysql_query($movies_query2);
  $music_releases_now = mysql_query($music_query2);
  $tvrage_relases_now = mysql_query($tvrage_query2);
  $pc_releases_now = mysql_query($pc_query2);

  ///////////////////pc releases to process/////////////////////////
  if (empty($pc_count_inner_loop)) {
    $pc_count_inner_loop = $db->queryDirect($pc_query);
    if (empty($pc_count_inner_loop)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $pc_query;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($pc_count_inner_loop)) {
    $pc_count_now = $row['count(*)'];
  }

  ///////////////////pc releases in database/////////////////////////
  if (empty($pc_releases_now)) {
    $pc_releases_now = $db->queryDirect($pc_query2);
    if (empty($pc_releases_now)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $pc_query2;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($pc_releases_now)) {
    $pc_releases_now = $row['count(searchname)'];
  }

  ///////////////////bresult_loop/////////////////////////
  if (empty($book_count_inner_loop)) {
    $book_count_inner_loop = $db->queryDirect($book_query);
    if (empty($book_count_inner_loop)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $book_query;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($book_count_inner_loop)) {
    $book_count_now = $row['count(searchname)'];
  }

  ///////////////////gresult_loop/////////////////////////
  if (empty($console_count_inner_loop)) {
    $console_count_inner_loop = $db->queryDirect($console_query);
    if (empty($console_count_inner_loop)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $console_query;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($console_count_inner_loop)) {
    $console_count_now = $row['count(searchname)'];
  }

  ///////////////////moresult_loop/////////////////////////
  if (empty($movies_count_inner_loop)) {
    $movies_count_inner_loop = $db->queryDirect($movies_query);
    if (empty($movies_count_inner_loop)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $movies_query;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($movies_count_inner_loop)) {
    $movies_count_now = $row['count(searchname)'];
  }

  ///////////////////muresult_loop/////////////////////////
  if (empty($music_count_inner_loop)) {
    $music_count_inner_loop = $db->queryDirect($music_query);
    if (empty($music_count_inner_loop)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $release_count_query;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($music_count_inner_loop)) {
    $music_count_now = $row['count(searchname)'];
  }

  ///////////////////presult_loop////////////////////////
  if (empty($postprocessing_count_remaining_inner_loop)) {
    $postprocessing_count_remaining_inner_loop = $db->queryDirect($postprocessing_count_remaining_query);
    if (empty($releases_count_inner_loop)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $postprocessing_count_remaining_query;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($postprocessing_count_remaining_inner_loop)) {
    $postprocessing_count_remaining_this_loop = $row['count(*)'];
  }

 ////////////////result_inner_loop///////////////////////
  if (empty($releases_count_loop_start)) {
    $releases_count_loop_start = $db->queryDirect($release_count_query);
    if (empty($releases_count_loop_start)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $release_count_query;
      die($message);
    }
 }

  while ($row = mysql_fetch_assoc($releases_count_loop_start)) {
    $count_inner_loop = $row['count(*)'];
  }

  ///////////////////result_loop/////////////////////////
  if (empty($releases_count_inner_loop)) {
    $releases_count_inner_loop = $db->queryDirect($release_count_query);
    if (empty($releases_count_inner_loop)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $release_count_query;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($releases_count_inner_loop)) {
    $total_release_now = $row['count(*)'];
  }

  ///////////////////tresult_loop/////////////////////////
  if (empty($tvrage_count_inner_loop)) {
    $tvrage_count_inner_loop = $db->queryDirect($tvrage_query);
    if (empty($releases_count_inner_loop)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $tvrage_query;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($tvrage_count_inner_loop)) {
    $tvrage_count_now = $row['count(searchname)'];
  }

  /////////////////book total////////////////////////
  if (empty($book_releases_now)) {
    $book_releases_now = $db->queryDirect($book_query2);
    if (empty($book_releases_now)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $book_query2;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($book_releases_now)) {
    $book_count_begin = $row['count(*)'];
  }

  /////////////////console total////////////////////////
  if (empty($console_releases_now)) {
    $console_releases_now = $db->queryDirect($console_query2);
    if (empty($console_releases_now)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $console_query2;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($console_releases_now)) {
    $console_count_begin = $row['count(searchname)'];
  }

  /////////////////movie total////////////////////////
  if (empty($movie_releases_now)) {
    $movie_releases_now = $db->queryDirect($movies_query2);
    if (empty($movie_releases_now)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $movies_query2;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($movie_releases_now)) {
    $movie_count_begin = $row['count(searchname)'];
  }

  /////////////////music total////////////////////////
  if (empty($music_releases_now)) {
    $music_releases_now = $db->queryDirect($music_query2);
    if (empty($music_releases_now)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $music_query2;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($music_releases_now)) {
    $music_count_begin = $row['count(searchname)'];
  }

  /////////////////tv total////////////////////////
  if (empty($tvrage_relases_now)) {
    $tvrage_relases_now = $db->queryDirect($tvrage_query2);
    if (empty($tvrage_relases_now)) {
      $message = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $tvrage_query2;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($tvrage_relases_now)) {
    $tv_count_begin = $row['count(searchname)'];
  }

  $other_total = $total_release_now - $tv_count_begin - $music_count_begin - $movie_count_begin - $console_count_begin - $book_count_begin - $pc_releases_now;

  //calculate the difference from start to now
  $total_release_added = $total_release_now - $count_begin;if ($total_release_added < 0) $total_release_added = 0;
  $total_release_added_loop = $total_release_now - $count_inner_loop;if ($total_release_added_loop < 0) $total_release_added_loop = 0;

  $sleeptext = "in the past $sleeptime seconds.";

  passthru('clear');

  printf("Monitoring the releases in your database.\n\n");
  printf("The script was started: \033[38;5;160m$day\033[0m");printf(" Days ");
  printf("\033[38;5;208m$hr\033[0m");printf(" Hours ");
  printf("\033[38;5;020m$min\033[0m");printf(" Minutes ");
  printf("\033[38;5;063m$sec\033[0m");printf(" Seconds Ago.\n");
  printf("The script updates every $sleeptime seconds.\n");
  printf("$total_release_added_loop releases added since last update.\n\n");

  printf("$count_begin releases at start.\n");
  printf("$total_release_now releases in your database.\n");
  printf("$total_release_added releases have been added.\n\n");

  $mask = "%16s %10s %10s \n";
  printf($mask, "Category", "In Process", "In Database");
  printf($mask, "===============", "==========", "==========");
  printf($mask, "Books(7020)", "$book_count_now", "$book_count_begin");
  printf($mask, "Console(1000)", "$console_count_now", "$console_count_begin");
  printf($mask, "Movie(2000)", "$movies_count_now", "$movie_count_begin");
  printf($mask, "Audio(3000)", "$music_count_now", "$music_count_begin");
  printf($mask, "PC(4000)", "$pc_count_now", "$pc_releases_now");
  printf($mask, "TVShows(5000)", "$tvrage_count_now", "$tv_count_begin");
  printf($mask, "Additional Proc", "$postprocessing_count_remaining_this_loop", "$other_total");



  $i=$i+1;
}

mysql_free_result($pc_count_now);
mysql_free_result($pc_releases_now);
mysql_free_result($book_count_inner_loop);
mysql_free_result($console_count_inner_loop);
mysql_free_result($movies_count_inner_loop);
mysql_free_result($music_count_inner_loop);
mysql_free_result($postprocessing_remaining_count_loop_start);
mysql_free_result($postprocessing_count_completed_inner_loop);
mysql_free_result($postprocessing_count_remaining_inner_loop);
mysql_free_result($releases_count_start);
mysql_free_result($releases_count_inner_loop);
mysql_free_result($tvrage_count_inner_loop);
mysql_free_result($total_release_added_loop);
mysql_free_result($count_begin);
mysql_free_result($total_release_now);
mysql_free_result($total_release_added);
mysql_free_result($postprocessing_count_remaining_this_loop);
mysql_free_result($other_total);
mysql_free_result($tvrage_count_now);
mysql_free_result($tv_count_begin);
mysql_free_result($music_count_now);
mysql_free_result($music_count_begin);
mysql_free_result($book_count_begin);
mysql_free_result($book_count_now);
mysql_free_result($console_count_begin);
mysql_free_result($console_count_now);
mysql_free_result($movie_count_begin);
mysql_free_result($movies_count_now);


?>

