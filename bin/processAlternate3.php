<?php

require_once("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

$db = new DB();
$query = "select count(*) from releases r left join category c on c.ID = r.categoryID where ((r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0))";

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
    $count = $row['count(*)'];
  }

  if ($count > 200) {
    $postprocess = new PostProcess(true);
    $postprocess->processAdditional();
  } else {
    echo "$count releases left to process\n";
    sleep(15);
  }
}

mysql_free_result($result);

?>
