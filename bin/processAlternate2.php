<?php

$newzpath = getenv('NEWZPATH');
require_once("$newzpath/www/config.php");
require_once("lib/postprocess2.php");

$db = new DB();
$query = "select format(count(*),0) from releases r left join category c on c.ID = r.categoryID where ((r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0))";

$i=1;
while($i=1)

{
  $result = mysql_query($query);

  if (empty($result)) {
    $result = $db->queryDirect($query);
    if (empty($result)) {
      $message  = 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $query;
      die($message);
    }
  }

  while ($row = mysql_fetch_assoc($result)) {
    $count = $row['format(count(*),0)'];
  }

  if ($count > 50) {
    $postprocess = new PostProcess2(true);
    $postprocess->processAdditional2();
  } else {
    echo "$count releases left to process\n";
    sleep(10);
  }
}

mysql_free_result($result);

?>

