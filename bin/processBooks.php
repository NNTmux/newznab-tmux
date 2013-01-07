<?php

require_once("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

$db = new DB();
$query = "SELECT count(searchname), ID from releases use index (ix_releases_categoryID) where bookinfoID IS NULL and categoryID = 7020;";

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
    $postprocess->processBooks();
  } else {
    sleep(15);
  }
}

mysql_free_result($result);

?>

