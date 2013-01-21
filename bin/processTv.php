<?php

require_once("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

<<<<<<< HEAD
$db = new DB();
$query = "SELECT COUNT(*) AS cnt, ID from releases where rageID = -1 and categoryID in ( select ID from category where parentID = 5000 );";

$i=1;
while($i=1)
{
  $result = $db->query($query);
  $count = $result[0]['cnt'];

  if ($count > 0) {
    $postprocess = new PostProcess(true);
    $postprocess->processTv();
  } else {
    printf("TVRage  : no work to be done\n");
    sleep(15);
  }
}

mysql_free_result($result);
=======
$postprocess = new PostProcess(true);
$postprocess->processTv();
>>>>>>> dev

?>

