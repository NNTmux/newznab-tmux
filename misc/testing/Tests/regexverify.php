<?php
//
// This script is used to test the regular expressions identified
// within a nntmux database are valid.
//
// by l2g
//
require_once realpath(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nntmux\Releases;
use nntmux\db\DB;


function handleError($errno, $errstr, $errfile, $errline, array $errcontext)
{
    // error was suppressed with the @-operator
    if (0 === error_reporting()) {
        return false;
    }

    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler('handleError');

$regs = array(
    "empty" => "",
    "illformed" => "[](9()))))))))) [34543/34]",
    "simple" => '"data.mp3',
 );

$releases = new Releases();
$db = new DB();
# fetch enabled regular expression
$catsql = "select ID,groupname,regex from releaseregex where status = 1";
$res = $db->query($catsql);
$total=count($res);
$errcnt=0;

echo "\n";
foreach ($res as $regexrow)
{
   foreach ($regs as $regex){
      try {
         $res=preg_match($regexrow["regex"], $regex);
      }catch(Exception $e){
         $errcnt++;
         $strerr=str_pad((int) $errcnt,2," ",STR_PAD_LEFT);
         echo "$strerr. id=".$regexrow["id"].
            ", group=".$regexrow["groupname"]."\n";
         echo "    regex='".$regexrow["regex"]."'\n";
         echo "    error=".$e->getMessage()."\n\n";
         break;
      }
   }
}
echo "Scanned $total record(s), $errcnt error(s) found.\n";
exit(($errcnt>0)?1:0);
