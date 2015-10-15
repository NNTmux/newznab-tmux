<?php
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\controllers\NNTP;

$grp = "a.b.grp";
$msgid = "2df9e8a3$1$2314$6d4158fb@reader.xsnews.nl";

$nntp = new NNTP;
$nntp->doConnect();
$sampleBinary = $nntp->getMessage($grp, $msgid);
$nntp->doQuit();

if ($sampleBinary === false)
{
	echo "-Couldnt fetch binary \n";
}
else
{
	file_put_contents("test.part", $sampleBinary);
}