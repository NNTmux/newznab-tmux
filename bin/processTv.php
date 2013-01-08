<?php

require_once("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

$db = new DB();
$query = "SELECT count(searchname), ID from releases where rageID = -1 and categoryID in ( select ID from category where parentID = 5000 );";

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
    $count = $row['count(searchname)'];
  }
  if ($count > 0) {
    $postprocess = new PostProcess(true);
    $postprocess->processTv();
  } else {
    printf("TVRage  : no work to be done\n");
    sleep(15);
  }
}

mysql_free_result($result);

?>

