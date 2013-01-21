<?php

require_once("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

<<<<<<< HEAD
$db = new DB();
$query = "SELECT COUNT(*) AS cnt from releases where bookinfoID IS NULL and categoryID = 7020;";

$i=1;
while($i=1)
{
  $result = $db->query($query);
  $count = $result[0]['cnt'];

  if ($count > 0) {
    $postprocess = new PostProcess(true);
    $postprocess->processBooks();
  } else {
    printf("BookPrc : Processing $count book releases\n");
    sleep(15);
  }
}

mysql_free_result($result);
=======
$postprocess = new PostProcess(true);
$postprocess->processBooks();
>>>>>>> dev

?>


