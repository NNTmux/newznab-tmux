<?php

require_once("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

<<<<<<< HEAD
$db = new DB();
$query = "SELECT COUNT(*) AS cnt from releases where consoleinfoID IS NULL and categoryID in ( select ID from category where parentID = 1000 );";

$i=1;
while($i=1)
{
  $result = $db->query($query);
  $count = $result[0]['cnt'];

  if ($count > 0) {
    $postprocess = new PostProcess(true);
    $postprocess->processGames();
  } else {
    printf("ConsPrc : Processing $count console releases\n");
    sleep(15);
  }
}

mysql_free_result($result);
=======
$postprocess = new PostProcess(true);
$postprocess->processGames();
>>>>>>> dev

?>

