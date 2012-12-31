<?php

require("config.php");
require_once(WWW_DIR."/lib/postprocess.php");

$i=1;
while($i=1)
{
  {
    $postprocess = new PostProcess(true);
    $postprocess->processTv();
  }
  {
    $postprocess = new PostProcess(true);
    $postprocess->processGames();
  }
  {
    $postprocess = new PostProcess(true);
    $postprocess->processMovies();
  }
  {
    $postprocess = new PostProcess(true);
    $postprocess->processMusic();
  }
  {
    $postprocess = new PostProcess(true);
    $postprocess->processBooks();
  }
  {
    $postprocess = new PostProcess(true);
    $postprocess->processUnwanted();
  }
  {
    $postprocess = new PostProcess(true);
    $postprocess->processMusicFromMediaInfo();
  }
  {
    $postprocess = new PostProcess(true);
    $postprocess->processOtherMiscCategory();
  }
  {
    $postprocess = new PostProcess(true);
    $postprocess->processUnknownCategory();
  }
}

?>
