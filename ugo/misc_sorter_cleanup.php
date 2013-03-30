<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/releases.php");
require_once(WWW_DIR."/lib/movie.php");
require_once(WWW_DIR."/lib/tvrage.php");
require_once(WWW_DIR."/lib/amazon.php");
require_once(WWW_DIR."/lib/anidb.php");
require_once(WWW_DIR."/lib/nzbinfo.php");
require_once(WWW_DIR."/lib/nzb.php");
require_once(WWW_DIR."/lib/book.php");
require_once(WWW_DIR."/lib/music.php");

$movie = new Movie();
$movie->processMovieReleases();
$movie->updateUpcoming();

$music = new Music(true);
$music->processMusicReleases();

$book = new Book(true);
$book->processBookReleases();

$anidb = new AniDB(true);
$anidb->animetitlesUpdate();
$anidb->processAnimeReleases();

$tvrage = new TVRage(true);
$tvrage->processTvReleases(true);

$thetvdb = new TheTVDB(true);
$thetvdb->processReleases();

echo "cleaning 5000\n";

$db = new DB();

$query = "update releases set  releases.categoryID= 5060 WHERE imdbID IN ( SELECT movieinfo.imdbID FROM movieinfo WHERE movieinfo.genre REGEXP 'sport' AND NOT movieinfo.genre REGEXP 'comedy' AND NOT movieinfo.genre REGEXP 'romance' AND NOT movieinfo.genre REGEXP 'drama' )";

$db->query($query);

$query = "update releases set  releases.categoryID= 5000 WHERE imdbID IN ( SELECT movieinfo.imdbID FROM movieinfo WHERE genre REGEXP 'tv|talk\-show|reality|episode' ) AND categoryID NOT IN ( SELECT ID FROM category WHERE parentID = 5000 )";

$db->query($query);

$query = "UPDATE `releases` SET `categoryID`=2050  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `imdbID` > 0) OR `categoryID` = 2000)  AND size > 10500000000";

$db->query($query);

$query = "UPDATE `releases` SET `categoryID`=2040  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `imdbID` > 0) OR `categoryID` = 2000) AND size > 2500000000";

$db->query($query);

$query = "UPDATE `releases` SET `categoryID`=2030  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `imdbID` > 0) OR `categoryID` = 2000)  AND size > 500000000";

$db->query($query);

$query = "UPDATE `releases` SET `categoryID`=2020  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `imdbID` > 0) OR `categoryID` = 2000)";

$db->query($query);

$query = "UPDATE `releases` SET `categoryID`=2040  WHERE `categoryID` = 2050 AND size <10000000000";

$db->query($query);

$query = "UPDATE `releases` SET `categoryID`=2030  WHERE `categoryID` = 2040 AND size < 2500000000";

$db->query($query);

$query = "UPDATE `releases` SET `categoryID`=5040  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `rageID` > 0) OR `categoryID` = 5000) AND size > 1500000000";

$db->query($query);

$query = "UPDATE `releases` SET `categoryID`=5030  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `rageID` > 0) OR `categoryID` = 5000)  AND size > 100000000";

$db->query($query);

$query = "UPDATE `releases` SET `categoryID`=5050  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `rageID` > 0) OR `categoryID` = 5000)";

$db->query($query);

$query = "UPDATE `releases` SET `categoryID`=5030  WHERE  `categoryID` = 5040 AND size < 1500000000";

$db->query($query);

$query = "UPDATE `releases` SET `categoryID`=5050  WHERE  `categoryID` = 5030 AND size < 100000000";

$db->query($query);

$query = "UPDATE releases SET categoryID = 5070 WHERE rageID IN ( SELECT tvrage.rageID FROM tvrage WHERE tvrage.genre REGEXP 'animat' )";

$db->query($query);

$query = "update releases set  releases.categoryID= 5080 WHERE imdbID IN ( SELECT movieinfo.imdbID FROM movieinfo WHERE genre REGEXP 'docum' )";

$db->query($query);

?>
