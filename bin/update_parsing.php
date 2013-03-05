<?php

require(dirname(__FILE__)."/config.php");
require_once (WWW_DIR."/lib/framework/db.php");
require_once (WWW_DIR."/lib/nntp.php");
require_once (WWW_DIR."/lib/site.php");
require_once (WWW_DIR."/lib/util.php");
require_once (WWW_DIR."/lib/releases.php");
require_once (WWW_DIR."/lib/nfo.php");
require_once (WWW_DIR."/lib/category.php");
require_once (WWW_DIR."/lib/movie.php");

//This script updates release names for releases in 'TV > Other', 'Movie > Other', 'XXX > Other', and 'Other > Misc' categories.
//It first attempts to extract the release name from the NFO, falling back to rarset filename -- the ReleaseFiles.
//If the new releasename is the same as the old name, OR the name is different but still doesnt match a new category, it will skip the release.
//Configure the script using the options below. 

/// The following value either shows or hides the releases that failed to find a new name and category. 
$debug = false;
/// Set this to true to see fails

/// The following value sets either echo's the results or applies the changes.
$echo = false;
/// Set this to true to echo only

/// The following value sets the amount of time to either 24 hours or the Whole DB
$limited = false;
/// Set to true for 24 hours, false for whole db. 

//WARNING!!!!!!!!The following value runs update_parsing either against "other categories", or all categories
$othercats = true;
//WARNING!!!!!!!!Set to true(recommended!) to do "other" categories

$Nfocount = 0;
$Filecount = 0;
$nfoRes = NULL;
$chcount = 0;
$chchange = 0;
$nonfocount = 0;
$nonfochange = 0;
$notnforelcount = 0;
$notnforelchange = 0;
$rescount = 0;
$nopreCount = 0;
$test = 0;
$db = new DB();
$updated = 0;


if($othercats == true) //Only Other categories
{
        // Default query for both full db and last 24 hours.
        $sql = "SELECT r.searchname, r.name, r.ID as RID, r.categoryID, r.guid, r.postdate,
               rn.ID as nfoID,
               g.name as groupname,
               GROUP_CONCAT(rf.name) as filenames
        FROM releases r 
        LEFT JOIN releasenfo rn ON (rn.releaseID = r.ID)
        LEFT JOIN groups g ON (g.ID = r.groupID)
        LEFT JOIN releasefiles rf ON (rf.releaseID = r.ID)
        WHERE r.categoryID in (".Category::CAT_TV_OTHER.",".Category::CAT_MOVIE_OTHER.",".Category::CAT_MISC_OTHER.",".Category::CAT_XXX_OTHER.")
        %s
        GROUP BY r.ID";
}
else //All categories
{
        // Modified query to run against all categories, USE WITH CAUTION.
        $sql = "SELECT r.searchname, r.name, r.ID as RID, r.categoryID, r.guid, r.postdate,
               rn.ID as nfoID,
               g.name as groupname,
               GROUP_CONCAT(rf.name) as filenames
        FROM releases r 
        LEFT JOIN releasenfo rn ON (rn.releaseID = r.ID)
        LEFT JOIN groups g ON (g.ID = r.groupID)
        LEFT JOIN releasefiles rf ON (rf.releaseID = r.ID)
        WHERE r.categoryID in
        (".Category::CAT_GAME_NDS.",".Category::CAT_GAME_PSP.",".Category::CAT_MOVIE_HD.",
        ".Category::CAT_GAME_WII.",".Category::CAT_GAME_XBOX.",".Category::CAT_GAME_XBOX360.",
        ".Category::CAT_GAME_WIIWARE.",".Category::CAT_GAME_XBOX360DLC.",".Category::CAT_MOVIE_FOREIGN.",
        ".Category::CAT_MOVIE_OTHER.",".Category::CAT_MOVIE_SD.",".Category::CAT_MOVIE_BLURAY.",
        ".Category::CAT_MOVIE_3D.",".Category::CAT_MUSIC_MP3.",".Category::CAT_MUSIC_VIDEO.",
        ".Category::CAT_MUSIC_AUDIOBOOK.",".Category::CAT_MUSIC_LOSSLESS.",".Category::CAT_PC_0DAY.",
        ".Category::CAT_PC_ISO.",".Category::CAT_PC_MAC.",".Category::CAT_PC_MOBILEOTHER.",
        ".Category::CAT_PC_GAMES.",".Category::CAT_PC_MOBILEIOS.",".Category::CAT_PC_MOBILEANDROID.",
        ".Category::CAT_TV_FOREIGN.",".Category::CAT_TV_SD.",".Category::CAT_TV_HD.",
        ".Category::CAT_TV_OTHER.",".Category::CAT_TV_SPORT.",".Category::CAT_TV_ANIME.",
        ".Category::CAT_TV_DOCU.",".Category::CAT_XXX_DVD.",".Category::CAT_XXX_WMV.",
        ".Category::CAT_XXX_XVID.",".Category::CAT_XXX_X264.",".Category::CAT_XXX_IMAGESET.",
        ".Category::CAT_XXX_OTHER.",".Category::CAT_BOOK_MAGS.",".Category::CAT_BOOK_EBOOK.",".Category::CAT_BOOK_COMICS.")
        %s
        GROUP BY r.ID";
}

if($limited == true) //Last 24Hours
{
    $res = $db->query(sprintf($sql,"AND r.adddate BETWEEN NOW() - INTERVAL 1 DAY AND NOW()"));
}
else //Full DB
{
    $res = $db->query(sprintf($sql,''));
}

function determineCategory($rel,$foundName,$methodused)
{
    global $updated,$echo;
    $categoryID = null;
    $category = new Category();
    $categoryID = $category->determineCategory($rel['groupname'], $foundName);
    if(($methodused == 'a.b.hdtv.x264') && ($rel['groupname'] == 'alt.binaries.hdtv.x264')) { $categoryID = Category::CAT_MOVIE_HD; }
    if(($categoryID == $rel['categoryID'] || $categoryID == 8010 || $categoryID == 5050 || $categoryID == 2020 || $categoryID == 6070 || $categoryID == '7900') || ($foundName == $rel['name'] || $foundName == $rel['searchname']))
    {
        $foundName = null;
    }
    else
    {
        $db = new DB();
        $releases = new Releases();
        $foundName = html_entity_decode($foundName);
        $foundName = str_replace('&#x27;', '', $foundName);
        $name = str_replace(' ', '.', $foundName);
        $name = str_replace('_', '.', $foundName);
        $searchname = str_replace('_', ' ', $foundName);
        $searchname = str_replace('.', ' ', $foundName);
        echo "\n";
        echo 'ReleaseID:        '.$rel['RID']."\n";
        echo ' Group:       '. $rel['groupname']."\n";
        echo ' Old Name:        '.$rel['name']."\n";
        echo ' Old SearchName:  '.$rel['searchname']."\n";
        echo ' New Name:        '.$name."\n";
        echo ' New SearchName:  '.$searchname."\n";
        echo ' Old Cat:         '.$rel['categoryID']."\n";
        echo ' New Cat:         '.$categoryID."\n";
        echo ' Method:      '.$methodused."\n";
        echo " Status:      Release Changed, Updating Release\n\n\n";
        if($echo == false)
        {
            $db->query(sprintf("UPDATE releases SET name = %s, searchname = %s, categoryID = %d, imdbID = NULL, rageID = -1, bookinfoID = NULL, musicinfoID = NULL, consoleinfoID = NULL WHERE releases.ID = %d", $db->escapeString($name), $db->escapeString($searchname), $categoryID, $rel['RID']));
        }
        $updated = $updated + 1;
    }
}

if ($res)
{
    $rescount = sizeof($res);
    echo "\nProcessing ".$rescount." releases in the Other-Misc categories\n";
    foreach($res as $rel)
    {
        $test = 0;
        if ($test == 0)
        {
            $methodused = '';
            $foundName = '';
            $tempname = '';
            
            ///
            ///Use some Magic on the Name to get the proper Release Name.
            ///

            //Knoc.One
            if (preg_match("/KNOC.ONE/i", $rel['name']))
            {
                $title = '';
                $items = preg_split( "/(\.| )/", $rel['name']);
                foreach ($items as $value) 
                {
                    if (preg_match("/^[a-z]+$/i", $value)) 
                    {
                        $len = strlen($value);
                        if ($len > 2) { $title.= substr($value,-2) . substr($value, 0, -2) ." "; }
                        elseif ($len = 2) { $title.= substr($value,-1) . substr($value, 0, -1) . " "; }
                        else { $title.= $value . " "; }
                    }
                    else {$title.= $value ." ";}
                }
                $foundName = $title;
                $methodused = "Knoc.One";
                determineCategory($rel,$foundName,$methodused);
            }
                        
            //QoQ releases
            if (preg_match('/^QoQ\-(.*)$/', $rel['name']))
            {
                $foundName = strrev( $rel['name'] );
                $methodused = "QoQ";
                determineCategory($rel,$foundName,$methodused);
            }
            
            ///
            ///Use the Nfo to try to get the proper Releasename.
            ///
            
            $nfo = $db->queryOneRow(sprintf("select uncompress(nfo) as nfo from releasenfo where releaseID = %d", $rel['RID']));
            if ($nfo && $foundName == "")
            {
                $nfo = $nfo['nfo'];
                $Nfocount ++;

                //LOUNGE releases
                if(preg_match('/([a-z0-9.]+\.MBLURAY)/i', $nfo, $matches))
                {
                    $foundName = $matches[1];
                    $methodused = "LOUNGE";
                    determineCategory($rel,$foundName,$methodused);

                }   

                //AsianDVDClub releases
                if(preg_match('/adc-[a-z0-9]{1,10}/', $rel['name']))
                {
                    if(preg_match('/.*\(\d{4}\).*/i', $nfo, $matches))
                    {
                        $foundName = $matches[0];
                        $methodused = "AsianDVDClub";
                        determineCategory($rel,$foundName,$methodused);
                    }
                }

            
                //ACOUSTiC  releases
                if(preg_match('/ACOUSTiC presents \.\.\..*?([a-z0-9].*?\(.*?\))/is', $nfo, $matches))
                {
                    $foundName = $matches[1].".MBLURAY";
                    $methodused = "ACOUSTiC ";
                    determineCategory($rel,$foundName,$methodused);                 
                }           

                //Japhson  releases
                if(preg_match('/Japhson/i', $nfo, $matches))
                {
                    $movie = new Movie();
                    $imdbID ="";
                    if(preg_match('/tt(\d{7})/i',$nfo,$matches))
                    {
                        $imdbId = $matches[1];
                        $movCheck = $movie->fetchImdbProperties($imdbId);
                        $foundName = $movCheck['title'];
                        if(!preg_match('/(19|20)\d{2}/i',$foundName))
                        {
                            $foundName = $foundName.".".$movCheck['year'];
                        }
                        if(preg_match('/language.*?\b([a-z0-9]+)\b/i',$nfo,$matches))
                        {
                            if(!preg_match('/English/i', $matches[1]))
                            {
                                $foundName = $foundName.".".$matches[1];    
                            }                           
                        }
                        if(preg_match('/audio.*?\b(\w+)\b/i',$nfo,$matches))
                        {
                            if(preg_match('/(Chinese|German|Dutch|Spanish|Hebrew|Finnish|Norwegian)/i', $matches[1]))
                            {
                                $foundName = $foundName.".".$matches[1];    
                            }   
                        }                       
                        if(preg_match('/(video|resolution|video res).*?(1080|720|816|820|272|1280 @|528|1920)/i',$nfo,$matches))
                        {
                            if($matches[2] == '1280 @')
                            {
                                $matches[2] = '720';
                            }
                            if($matches[2] == '1920')
                            {
                                $matches[2] = '1080';
                            }                           
                            $foundName = $foundName.".".$matches[2];
                        }                   
                        if(preg_match('/source.*?\b(DVD9|DVD5|BDRIP|DVD\-?RIP|BLURAY)\b/i',$nfo,$matches))
                        {
                            $foundName = $foundName.".".$matches[1];
                        }
                        if(preg_match('/(video|resolution|video res).*?(XVID|X264|WMV)/i',$nfo,$matches))
                        {
                            $foundName = $foundName.".".$matches[2];
                        }                           
                        if(preg_match('/audio.*?\b(DTS|AC3)\b/i',$nfo,$matches))
                        {
                            $foundName = $foundName.".".$matches[1];
                        }
                        $foundName = $foundName."-Japhson";
                        $methodused = "Japhson";
                        determineCategory($rel,$foundName,$methodused);                         
                    }           
                }               
                
                //AIHD  releases
                if(preg_match('/ALWAYS iN HiGH/i', $nfo, $matches))
                {
                    $movie = new Movie();
                    $imdbID ="";
                    if(preg_match('/tt(\d{7})/i',$nfo,$matches))
                    {
                        $imdbId = $matches[1];
                        $movCheck = $movie->fetchImdbProperties($imdbId);
                        $foundName = $movCheck['title'];
                        if(!preg_match('/(19|20)\d{2}/i',$foundName))
                        {
                            $foundName = $foundName.".".$movCheck['year'];
                        }
                        if(preg_match('/L\.([a-z0-9]+)\b/i',$nfo,$matches))
                        {
                            if(!preg_match('/En/i', $matches[1]))
                            {
                                $foundName = $foundName.".".$matches[1];    
                            }                           
                        }                   
                        if(preg_match('/(V).*?(1080|720|816|820|272|1280 @|528|1920)/i',$nfo,$matches))
                        {
                            if($matches[2] == '1280 @')
                            {
                                $matches[2] = '720';
                            }
                            if($matches[2] == '1920')
                            {
                                $matches[2] = '1080';
                            }                           
                            $foundName = $foundName.".".$matches[2];
                        }               
                        if(preg_match('/V.*?\b(DVD9|DVD5|BDRIP|DVD\-?RIP|BLURAY)\b/i',$nfo,$matches))
                        {
                            $foundName = $foundName.".".$matches[1];
                        }
                        if(preg_match('/(V).*?(XVID|X264|WMV)/i',$nfo,$matches))
                        {
                            $foundName = $foundName.".".$matches[2];
                        }                               
                        if(preg_match('/A.*?\b(DTS|AC3)\b/i',$nfo,$matches))
                        {
                            $foundName = $foundName.".".$matches[1];
                        }
                        $foundName = $foundName."-AIHD";
                        $methodused = "AIHD";
                        determineCategory($rel,$foundName,$methodused);                     
                    }           
                }       
                
                //IMAGiNE releases
                if(preg_match('/\*\s+([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- ]+ \- imagine)\s+\*/i', $nfo, $matches))
                {
                    $foundName = $matches[1];
                    $methodused = "imagine";
                    determineCategory($rel,$foundName,$methodused);                 
                }
                
                //LEGION releases
                if(preg_match('/([a-z0-9 \.\-]+LEGi0N)/is', $nfo, $matches) && $foundName == "")
                {
                    $foundName = $matches[1];
                    $methodused = "Legion";
                    determineCategory($rel,$foundName,$methodused);
                }               
                
                //SWAGGER releases
                if(preg_match('/(S  W  A  G  G  E  R|swg.*?nfo)/i', $nfo) && $foundName == "")
                {
                    if(preg_match('/presents.*?([a-z0-9].*?\((19|20)\d{2}\))/is',$nfo,$matches))
                        {
                            $foundName = $matches[1];
                        }                       
                    if(preg_match('/language.*?\b([a-z0-9]+)\b/i',$nfo,$matches))
                        {
                            if($matches[1] != "english")
                            {
                            $foundName = $foundName.".".$matches[1];    
                            }                           
                        }                       
                    if(preg_match('/resolution.*?(1080|720)/i',$nfo,$matches))
                        {
                            $foundName = $foundName.".BluRay.".$matches[1];
                        }
                    if(preg_match('/video.*?\b([a-z0-9]+)\b/i',$nfo,$matches))
                        {
                            $foundName = $foundName.".".$matches[1];
                        }
                    if(preg_match('/audio.*?\b([a-z0-9]+)\b/i',$nfo,$matches))
                        {
                            $foundName = $foundName.".".$matches[1];
                        }
                    $foundName = $foundName."-SWAGGER";
                    $methodused = "SWAGGER";
                    determineCategory($rel,$foundName,$methodused);                     
                }

                //cm8 releases
                if(preg_match('/([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- \'\)\(]+\-(futv|crimson|qcf|runner|clue|episode|momentum|PFA|topaz|vision|tdp|haggis|nogrp|shirk|imagine|santi|sys|deimos|ltu|ficodvdr|cm8|dvdr|Nodlabs|aaf|sprinter|exvid|flawl3ss|rx|magicbox|done|unveil))\b/i', $nfo, $matches) && $foundName == "")
                {
                    $foundName = $matches[1];
                    $methodused = "cm8";
                    determineCategory($rel,$foundName,$methodused);                     
                }
                
                //river
                if(preg_match('/([a-z0-9\.\_\-]+\-(webios|river|w4f|sometv|ngchd|C4|gf|bov|26k|ftw))\b/i', $nfo, $matches) && $foundName == "")
                {
                    $foundName = $matches[1];
                    $methodused = "river-1";
                    determineCategory($rel,$foundName,$methodused);                 
                }
                if(preg_match('/([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- \'\)\(]+\-(CiA|Anarchy|RemixHD|FTW|Revott|WAF|CtrlHD|Telly|Nif|Line|NPW|Rude|CRisC|SHK|AssAss1ns|Leverage|BBW|NPW))\b/i', $nfo, $matches) && $foundName == "")
                {
                    $foundName = $matches[1];
                    $methodused = "river-2";
                    determineCategory($rel,$foundName,$methodused);                 
                }
                if(preg_match('/([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- \'\)\(]+\-(XPD|RHyTM))\b/i', $nfo, $matches) && $foundName == "")
                {
                    $foundName = $matches[1];
                    $methodused = "river-3";
                    determineCategory($rel,$foundName,$methodused);                 
                }
                if(preg_match('/(-PROD$|-BOV$|-NMR$|$-HAGGiS|-JUST$|CRNTV$|-MCA$|int$|-DEiTY$|-VoMiT$|-iNCiTE$|-BRUTUS$|-DCN$|-saints$|-sfm$|-lol$|-fov$|-logies$|-c4tv$|-fqm$|-jetset$|-ils$|-miragetv$|-gfvid$|-btl$|-terra$)/i', $rel['searchname']) && $foundName == "")
                {
                    $foundName = $rel['searchname'];
                    $methodused = "river-4";
                    determineCategory($rel,$foundName,$methodused);                 
                }
                
                //SANTi releases
                if(preg_match('/\b([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- \']+\-santi)\b/i', $nfo, $matches) && $foundName == "")
                {
                    $foundName = $matches[1];
                    $methodused = "SANTi";
                    determineCategory($rel,$foundName,$methodused);                     
                }
                /*
                //NPW releases
                if(preg_match('/(.*?NPW).*?/i', $rel['searchname'], $matches) && $foundName == "")
                {
                    $foundName = $matches[1];
                    $methodused = "NPW";
                    determineCategory($rel,$foundName,$methodused);                 
                }*/

                //INSPiRAL releases
                if(preg_match('/^([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- ]+ \- INSPiRAL)\s+/im', $nfo, $matches) && $foundName == "")
                {
                    $foundName = $matches[1];
                    $methodused = "INSPiRAL";
                    determineCategory($rel,$foundName,$methodused);                     
                }

                //CIA releases
                if(preg_match('/Release NAME.*?\:.*?([a-z0-9][a-z0-9\.\ ]+)\b.*?([a-z0-9][a-z0-9\.\ ]+\-CIA)\b/is', $nfo, $matches) && $foundName == "")
                {
                    $foundName = $matches[1].$matches[2];
                    $methodused = "CIA";
                    determineCategory($rel,$foundName,$methodused);                     
                }

                //HDChina releases
                if(preg_match('/HDChina/', $nfo) && $foundName == "")
                {
                    if(preg_match('/Disc Title\:.*?\b([a-z0-9\ \.\-\_()]+\-HDChina)/i', $nfo, $matches))
                    {
                        $foundName = $matches[1];
                        $methodused = "HDChina";
                        determineCategory($rel,$foundName,$methodused);                     
                    }
                }
                
                //Pringles
                if(preg_match('/PRiNGLES/', $nfo) && $foundName == "")
                {
                    if(preg_match('/is giving you.*?\b([a-z0-9 ]+)\s/i', $nfo, $matches))
                    {
                        $foundName = $matches[1];
                        $foundName = rtrim($foundName);
                        $foundName = ltrim($foundName);
                    }
                    if(preg_match('/this release.*?((19|20)\d{2})/i', $nfo, $matches))
                    {
                        $foundName = $foundName.".".$matches[1];
                        $foundName = rtrim($foundName);
                    }
                    if(preg_match('/\[x\] (Danish|Norwegian|Swedish|Finish|Other)/i', $nfo, $matches))
                    {
                        $foundName = $foundName.".".$matches[1];
                    }
                    if(preg_match('/\[x\] (DVD9|DVD5)/i', $nfo, $matches))
                    {
                        $foundName = $foundName.".".$matches[1];
                    }
                    $foundName = $foundName."-PRiNGLES";
                    $methodused = "Pringles";
                    determineCategory($rel,$foundName,$methodused);                 
                }

                //Fairlight releases
                if(preg_match('/\/Team FairLight/',$nfo) && $foundName == "")
                {
                    $title = null;
                    $os = null;
                    $method = null;
                    if(preg_match('/\b([a-z0-9\ \- \_()\.]+) \(c\)/i', $nfo, $matches))
                    {
                        $title = $matches['1'];
                        $foundName = $title;
                    }
                    $foundName = $foundName."-FLT";
                    $methodused = "FairLight";
                    determineCategory($rel,$foundName,$methodused);                     
                }

                //CORE releases
                if(preg_match('/Supplied.*?\:.*?(CORE)/',$nfo) || preg_match('/Packaged.*?\:.*?(CORE)/',$nfo) && $foundName == "")
                {
                    $title = null;
                    $os = null;
                    $method = null;
                    if(preg_match('/\b([a-z0-9\.\-\_\+\ ]+) \*[a-z0-9]+\*/i', $nfo, $matches))
                    {
                        $title = $matches['1'];
                        $foundName = $title;
                    }
                    if(preg_match('/Crack\/.*?\:.*?([a-z]+)/i', $nfo, $matches))
                    {
                        $method = $matches['1'];
                        $foundName = $foundName." ".$method;
                    }
                    if(preg_match('/OS.*?\:.*?([a-z]+)/i', $nfo, $matches))
                    {
                        $os = $matches['1'];
                        $foundName = $foundName." ".$os;
                    }
                    $foundName = $foundName."-CORE";
                    $methodused = "CORE";
                    determineCategory($rel,$foundName,$methodused);                     
                }
                
                //CompleteRelease
                if(preg_match('/Complete name.*?([a-z0-9].*?\-[a-z0-9]+)\b/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = $matches[1];
                    $methodused = "CompleteRelease";
                    determineCategory($rel,$foundName,$methodused);                 
                }
                
                //Livesets
                if(preg_match('/\nLivesets.*?\n.*?\n.*?\n.*?\n.*?\n(?P<name>\w.*?)\n(?P<album>\w.*?)\n/im', $nfo, $matches) && $foundName == "")
                {
                    $artist = $matches['name'];
                    $title = $matches['album'];
                    if (preg_match('/Year.*?\:{1,2} ?(?P<year>(19|20)\d{2})/i', $nfo, $matches))
                    {
                        $year = $matches[1];
                    }
                    elseif (preg_match('/date.*?\:.*?(?P<year>(19|20)\d{2})/i', $nfo, $matches))
                    {
                        $year = $matches[1];
                    }
                    if (preg_match('/(web|cable|sat)/i', $title))
                    {
                        $source = "";
                    }
                    elseif (preg_match('/Source.*?\:{1,2} ?(?P<source>.*?)(\s{2,}|\s{1,})/i', $nfo, $matches))
                    {
                            $source = $matches[1];
                            if ($source = "Satellite")
                            {
                            $source = "Sat";
                            }
                    }
                    if ($artist)
                    {
                        $tempname = $artist;
                        if ($title)
                        {
                            $tempname = $tempname."-".$title;
                        }   
                        if ($source)
                        {
                           $tempname = $tempname."-".$source;
                        }
                        if ($year)
                        {
                           $tempname = $tempname."-".$year;
                        }                           
                        $tempname = preg_replace("/[^a-zA-Z,0-9,\-,\s]/", "", $tempname);
                        $foundName = $tempname;
                        $methodused = "Live Sets";
                        determineCategory($rel,$foundName,$methodused);                     
                    }
                }

                //Typical scene regex
                if (preg_match('/(?P<source>Source[\s\.]*?:|fix for nuke)?(?:\s|\]|\[)?(?P<name>[a-z0-9\'\-]+(?:\.|_)[a-z0-9\.\-_\'&]+\-[a-z0-9&]+)(?:\s|\[|\])/i', $nfo, $matches) && $foundName == "")
                {
                    if (empty($matches['source']))
                    {
                        if(!preg_match('/usenet\-space/i',$matches['name']))
                        {
                            $foundName = $matches['name'];
                            $methodused = "scene";
                            determineCategory($rel,$foundName,$methodused);                             
                        }
                    }
                }       
                /*
                //Predb Lookup
                if(preg_match('/tt(\d{7})/i',$nfo,$matches) && $foundName == "")
                {
                    $movie = new Movie();
                    $imdbId = $matches[1];
                    $matches = "";
                    $resolution ="";
                    $movCheck = $movie->fetchImdbProperties($imdbId);
                    if(preg_match('/(1920|1280)/i', $nfo,$matches))
                    {
                        if(preg_match('/1920/i', $matches['1']))
                        {
                            $matches['1'] = "1080p";
                        }
                        if(preg_match('/1280/i', $matches['1']))
                        {
                            $matches['1'] = "720p";
                        }
                        $resolution = $matches[1];
                        $matches = "";
                    }
                    $movCheck['title'] = preg_replace('/ /','+',$movCheck['title']);
                    $url = "http://predb.me/?search=".$movCheck['title']."&tag=".$resolution;
                    //echo $url."\n";
                    $ch = curl_init(); 
                    curl_setopt($ch, CURLOPT_URL, $url); 
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
                    $data = curl_exec($ch); 
                    curl_close($ch);
                    $pregall = "/post=.*?\>([a-z0-9.]+".$resolution."[a-z0-9.]+\-[a-z0-9]+)/i";
                    $res = preg_match_all($pregall,$data,$match);
                    if($res)
                    {
                        foreach($match['1'] as $rel2)
                        {
                            $rlsgroup=null;
                            preg_match('/\-([a-z0-9]+)$/i',$rel2,$match);
                            $rlsgroup = $match['1'];
                            if(preg_match("/".$rlsgroup."/i",$nfo))
                            {
                                $foundName = $rel2;
                                $methodused = "predb";
                                determineCategory($rel,$foundName,$methodused);                             
                            }
                            
                        }
                    }
                }
                */
            }
            
            //The Big One
            if(preg_match_all('/([a-z0-9\ ]+)\.{2,}(\:|\[)(?P<name>.*)(\s{2}|\s{1})/i',$nfo, $matches) && $foundName == "")
            {
                $lut = array();
                foreach ( $matches[1] as $key=>$k ) { $lut[str_replace(' ','',strtolower(trim($k)))] = trim($matches[3][$key]); }
                $year = null;
                $vidsource = null;
                $series = null;
                $season = null;
                $episode = null;
                $language = null;
                $artist = null;
                $source = null;
                //var_dump($lut);


                foreach ( $lut as $k=>$v )
                {
                    $v = rtrim($v);
                    if ( ! $year && preg_match('/((19|20)\d{2})/',$v,$matches) )
                    {
                        $year = $matches[1];
                    }
                    if ( ! $vidsource && preg_match('/(xvid|x264|h264|wmv|divx)/i',$v,$matches) )
                    {
                        $vidsource = $matches[1];
                    }

                    if ( ! $season && preg_match('/(season|seizon).*?(\d{1,3})/i',$v,$matches) )
                    {
                        $season = $matches[2];
                    }
                    if ( ! $episode && preg_match('/(Episode|ep).*?(\d{1,3})/i',$v,$matches) )
                    {
                        $episode = $matches[2];
                    }
                }
                
                if ( isset ( $lut['title'] ) )
                {
                    $del = " ";
                    if ( isset ($lut['series']))
                    {
                        $tempname = $lut['series'];
                    }
                    $tempname = $tempname.$del.$lut['title'];
                    if ( $season && $episode )
                    {
                        $tempname = $tempname.$del."S".str_pad($season,2,'0',STR_PAD_LEFT).'E'.str_pad($episode,2,'0',STR_PAD_LEFT);
                    }
                    else                            
                    {
                        if ($season)
                        {
                            $tempname = $tempname.$del."S".$season;
                        }
                        if ($episode)
                        {
                            $tempname = $tempname.$del."Ep".$episode;
                        }
                    }
                    if (isset ($lut['source']) && !preg_match('/SAT/i',$lut['title']))
                    {
                        $tempname = $tempname.$del.$lut['source'];
                    }
                    if (!preg_match('/(19|20)\d{2}/', $tempname) && $year)
                    {
                        $tempname = $tempname.$del.$year;
                    }
                    if ( isset($lut['language']))
                    {
                        $tempname = $tempname.$del.$lut['language'];
                    }
                    if ($vidsource)
                    {
                        $tempname = $tempname.$del.$vidsource;
                    }
                    $tempname = preg_replace("/ /", " ", $tempname);
                    $tempname = preg_replace("/[^a-zA-Z,0-9,\-,\&,\s]/", " ", $tempname);
                    $tempname = preg_replace("/[ ]+/"," ",$tempname);
                    $methodused = "The Big One Other";
                    $foundName = $tempname;
                    determineCategory($rel,$foundName,$methodused);                             
                }
            }
    
            //The Big One v2
            if(preg_match_all('/([a-z0-9\ ]+).{2,}(\:|\[)(?P<name>.*)(\s{1}|\s{0})/i',$nfo, $matches) && $foundName == "")
            {
                $lut = array();
                foreach ( $matches[1] as $key=>$k ) { $lut[str_replace(' ','',strtolower(trim($k)))] = trim($matches[3][$key]); }
                $year = null;
                $vidsource = null;
                $series = null;
                $season = null;
                $episode = null;
                $language = null;
                $artist = null;
                $source = null;
                $encoder = null;
                //var_dump($lut);


                foreach ( $lut as $k=>$v )
                {
                    $v = rtrim($v);
                    if ( ! $year && preg_match('/((19|20)\d{2})/',$v,$matches) )
                    {
                        $year = $matches[1];
                    }
                    if ( ! $vidsource && preg_match('/(xvid|x264|h264|wmv|divx)/i',$v,$matches) )
                    {
                        $vidsource = $matches[1];
                    }

                    if ( ! $season && preg_match('/(season|seizon).*?(\d{1,3})/i',$v,$matches) )
                    {
                        $season = $matches[2];
                    }
                    if ( ! $episode && preg_match('/(Episode|ep).*?(\d{1,3})/i',$v,$matches) )
                    {
                        $episode = $matches[2];
                    }
                }

                if ( isset ( $lut['artist'] ) )
                {
                    $del = "-";
                    if ( isset ($lut['artist']))
                    {
                        $lut['artist'] = trim($lut['artist']," ");
                        $tempname = $lut['artist'];
                    }
                    if ( isset ($lut['title']))
                    {
                        $tempname = $tempname.$del.$lut['title'];
                    }   
                    if ( isset ($lut['album']) && !isset ($lut['title']))
                    {
                        $tempname = $tempname.$del.$lut['album'];
                    }
                    if ( isset ($lut['track']) && !isset ($lut['title']) && !isset ($lut['album']))
                    {
                        $tempname = $tempname.$del.$lut['track'];
                    }                       
                    if (!isset ($lut['source']))
                    {
                        $lut['source'] = 'WEB';
                    }
                    if (isset ($lut['source']) && !preg_match('/SAT/i',$tempname))
                    {
                        $tempname = $tempname.$del.$lut['source'];
                    }
                    if (isset ($lut['encoder']))
                    {
                        if (preg_match('/lame/i',$lut['encoder']))
                        {
                            $lut['encoder'] = 'MP3';
                        }
                        if (preg_match('/flac/i',$lut['encoder']))
                        {
                            $lut['encoder'] = 'FLAC';
                        }
                    }
                    if (isset ($lut['encoder']))
                    {
                        $tempname = $tempname.$del.$lut['encoder'];
                    }
                    if (!preg_match('/(19|20)\d{2}/', $tempname) && $year)
                    {
                        $tempname = $tempname.$del.$year;
                    }
                    if (isset ($lut['ripper']))
                    {
                        $tempname = $tempname.$del.$lut['ripper'];
                    }   
                    $tempname = preg_replace("/[^a-zA-Z,0-9,\-,\&,\s]/", "", $tempname);
                    $tempname = preg_replace("/[ ]{2,}/","",$tempname);     
                    $methodused = "The Big One Music v2";
                    $foundName = $tempname;
                    determineCategory($rel,$foundName,$methodused);                             
                }
                else if ( isset ( $lut['title'] ) )
                {
                    $del = " ";
                    if ( isset ($lut['series']))
                    {
                        $tempname = $lut['series'];
                    }
                    $tempname = $tempname.$del.$lut['title'];
                    if ( $season && $episode )
                    {
                        $tempname = $tempname.$del."S".str_pad($season,2,'0',STR_PAD_LEFT).'E'.str_pad($episode,2,'0',STR_PAD_LEFT);
                    }
                    else                            
                    {
                        if ($season)
                        {
                            $tempname = $tempname.$del."S".$season;
                        }
                        if ($episode)
                        {
                            $tempname = $tempname.$del."Ep".$episode;
                        }
                    }
                    if (isset ($lut['source']) && !preg_match('/SAT/i',$lut['title']))
                    {
                        $tempname = $tempname.$del.$lut['source'];
                    }
                    if (!preg_match('/(19|20)\d{2}/', $tempname) && $year)
                    {
                        $tempname = $tempname.$del.$year;
                    }
                    if ( isset($lut['language']))
                    {
                        $tempname = $tempname.$del.$lut['language'];
                    }
                    if ($vidsource)
                    {
                        $tempname = $tempname.$del.$vidsource;
                    }
                    $tempname = preg_replace("/ /", " ", $tempname);
                    $tempname = preg_replace("/[^a-zA-Z,0-9,\-,\&,\s]/", " ", $tempname);
                    $tempname = preg_replace("/[ ]+/"," ",$tempname);
                    $methodused = "The Big One Other v2";
                    $foundName = $tempname;
                    determineCategory($rel,$foundName,$methodused);                             
                }
            }
    
            ///
            ///unable to extract releasename from nfo, try the rar file
            ///
            if($rel['filenames'] && $foundName == '')
            {
                $Filecount++;
                $files = explode( ',', $rel['filenames'] );
                if( !array($files) )
                {
                    $files = array( $files );
                }

                // Scene regex
                $sceneRegex = '/([a-z0-9\'\-\.\_\(\)\+\ ]+\-[a-z0-9\'\-\.\_\(\)\ ]+)(.*?\\\\.*?|)\.(?:\w{3,4})$/i';
                
                foreach( $files AS $file )
                {
                    // R&C
                    if (preg_match('/(.*?.1080i.*?DD5.1.*?MPEG2.*?-R&C(?=\.ts))/i', $file, $matches3) && $foundName == '')
                    {
                        $foundName = str_replace("MPEG2","MPEG2.HDTV",$matches3['0']);
                        $methodused = "Filename R&C";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    // NhaNc3
                    if (preg_match('/(.*?s[0-9]([0-9])?e[0-9]([0-9])?.*?480p.*?hdtv.*?nSD.*?x264.*?NhaNC3(?=\.mkv))/i', $file, $matches3) && $foundName == '')
                    {
                        $foundName = $matches3['0'];
                        $methodused = "Filename NhaNc3";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    // tvp 720p
                    if (preg_match('/(?<=\\\)?.*?tvp-.*?s[0-9]([0-9])?e[0-9]([0-9]).*?720p(?=\.mkv)/i', $file, $matches3) && $foundName == '')
                    {
                        $foundName = str_replace("720p","720p.HDTV.X264",$matches3['0']);
                        $methodused = "Filename tvp 720p";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    // tvp 1080p
                    if (preg_match('/(?<=\\\)?.*?tvp-.*?s[0-9]([0-9])?e[0-9]([0-9]).*?1080p(?=\.mkv)/i', $file, $matches3) && $foundName == '')
                    {
                        $foundName = str_replace("1080p","1080p.Bluray.X264",$matches3['0']);
                        $methodused = "Filename tvp 1080p";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    // tvp xvid
                    if (preg_match('/(?<=\\\)?.*?tvp-.*?s[0-9]([0-9])?e[0-9]([0-9]).*?xvid(?=\.avi)/i', $file, $matches3) && $foundName == '')
                    {
                        $foundName = str_replace("xvid","XVID.DVDrip",$matches3['0']);
                        $methodused = "Filename tvp xvid";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    // itouch
                    if (preg_match('/(?<=\\\)?.*?s[0-9]([0-9])?e[0-9]([0-9]).*?itouch-mw(?=\.mp4)/i', $file, $matches3) && $foundName == '')
                    {
                        $foundName = str_replace("itouch-mw","272p.x264.hdtv.itouch-mw",$matches3['0']);
                        $methodused = "Filename itouch (ipod releases)";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    // Directory\Title.Year.Format.Group.mkv
                    if (preg_match('/(?<=\\\).*?BLURAY.(1080|720)P.*?KNORLOADING(?=\.MKV)/i', $file, $matches3) && $foundName == '')
                    {
                        $foundName = $matches3['0'];
                        $methodused = "Filename 1";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    // ReleaseGroup.Title.Format.mkv
                    if (preg_match('/(?<=swg_|swghd\-|lost\-|veto\-|kaka\-|abd\-|airline\-|daa\-|data\-|japhson\-|ika\-|lng\-|nrdhd\-|saimorny\-|sparks\-|ulshd\-|nscrns\-|ifpd\-|invan\-|an0\-|besthd\-|muxhd\-|s7\-).*?(1080|720)(i|p)(?=\.MKV)/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 2";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    // Title.Format.ReleaseGroup.mkv
                    if (preg_match('/.*?(1080|720)(i|p).(SON)/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 3";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.SxxEx.EpTitle.source.vcodec.group.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.s[0-9]([0-9])?e[0-9]([0-9])?.*(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).*?(?=\.(avi|divx|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 4";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.SxxExx.EPtitle.resolution.source.vcodec.group.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.s[0-9]([0-9])?e[0-9]([0-9])?.*(480|720|1080)(i|p).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).*?(?=\.(avi|divx|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 5";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.SxxExx.source.vcodec.group.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.s[0-9]([0-9])?e[0-9]([0-9])?.(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).*?(?=\.(avi|divx|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 6";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.SxxExx.acodec.source.resolution.vcodec.group.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.s[0-9]([0-9])?e[0-9]([0-9]).(AAC( LC)?|AC-?3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(480|720|1080)(i|p).*?(?=\.(avi|divx|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 7";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.Sxx-Exx.eptitle.year.group.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.s[0-9]([0-9])?-?e[0-9]([0-9])?.*((19|20)\d\d).*?(?=\.(avi|divx|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 8";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.Sxx-Exx.res.src.vcod.group.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.s[0-9]([0-9])?e[0-9]([0-9]).(480|720|1080)(i|p).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).*?(?=\.(avi|divx|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 9";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.year.eptitle.res.vcod.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.((19|20)\d\d).*?(480|720|1080)(i|p).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).*?(?=\.(avi|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 10";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.year.###(season/episode).source.group.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.((19|20)\d\d).[0-9]{1,4}.(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).*?(?=\.(avi|divx|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 11";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.year.language.acodec.source.vcodec.group.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.((19|20)\d\d).(Brazilian|Chinese|Croatian|Danish|Deutsch|Dutch|Estonian|English|Finnish|Flemish|Francais|French|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish).(AAC( LC)?|AC-?3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).*?(?=\.(avi|divx|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 12";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.year.resolution.source.acodec.vcodec.group.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.((19|20)\d\d).(480|720|1080)(i|p).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(AAC( LC)?|AC-?3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3).(DivX|H264|MPEG2|X264|XviD).*?(?=\.(avi|divx|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 13";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.year.resolution.source.vcodec.group.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.((19|20)\d\d).(480|720|1080)(i|p).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).*?(?=\.(avi|divx|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 14";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.year.source.resolution.acodec.vcodec.group.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.((19|20)\d\d).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(480|720|1080)(i|p).(AAC( LC)?|AC-?3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3).*?(?=\.(avi|divx|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 15";
                        determineCategory($rel,$foundName,$methodused);
                    }
                    //Title.resolution.source.acodec.vcodec.group.extension
                    if (preg_match('/(?:\b\w+.\b){1,5}.(480|720|1080)(i|p).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(AAC( LC)?|AC-?3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).*?(?=\.(avi|divx|mp4|mkv|nfo|r[0-9]|rar|ts))/i', $file, $matches3) && $foundName == '') 
                    {
                        $foundName = str_replace("_",".",$matches3['0']);
                        $methodused = "Filename 16";
                        determineCategory($rel,$foundName,$methodused);
                    }

                    //Check rarfile contents for a scene name
                    if (preg_match($sceneRegex, $file, $matches) && $foundName == '')
                    {
                        //Simply Releases Toppers
                        if(preg_match('/(\\\\)(?P<name>.*?ReleaseS Toppers)/',$file,$matches1)  && $foundName == '')
                        {
                            $foundName = $matches1['name'];
                            $methodused = "Release Files-1";
                            determineCategory($rel,$foundName,$methodused);     
                            
                        }
                        //Scene format no folder.
                        if(preg_match('/^([a-z0-9\.\_\- ]+\-[a-z0-9\_]+)(\\\\|)$/i',$matches[1])  && $foundName == '' )
                        {
                            if (strlen($matches['1']) >= 15)
                            {
                                $foundName = $matches['1'];
                                $methodused = "Scene format no folder.";
                                determineCategory($rel,$foundName,$methodused); 
                            }
                        }
                        
                        //Check to see if file is inside of a folder. Use folder name if it is
                        if(preg_match('/^(.*?\\\\)(.*?\\\\|)(.*?)$/i', $file, $matches1)  && $foundName == '' )
                        {
                            If(preg_match('/^([a-z0-9\.\_\- ]+\-[a-z0-9\_]+)(\\\\|)$/i',$matches1['1'],$res))
                            {
                                $foundName = $res['1'];
                                $methodused = "Release Files-2";
                                determineCategory($rel,$foundName,$methodused);                             
                            }
                            If(preg_match('/(?!UTC)([a-z0-9]+[a-z0-9\.\_\- \'\)\(]+(\d{4}|HDTV).*?\-[a-z0-9]+)/i',$matches1['1'],$res) && $foundName == '')
                            {
                                $foundName = $res['1'];
                                $methodused = "Release Files-3";
                                determineCategory($rel,$foundName,$methodused);                             
                            }
                            If(preg_match('/^([a-z0-9\.\_\- ]+\-[a-z0-9\_]+)(\\\\|)$/i',$matches1['2'],$res) && $foundName == '')
                            {
                                $foundName = $res['1'];
                                $methodused = "Release Files-4";
                                determineCategory($rel,$foundName,$methodused);                                 
                            }
                        }
                        If(preg_match('/(?!UTC)([a-z0-9]+[a-z0-9\.\_\- \'\)\(]+(\d{4}|HDTV).*?\-[a-z0-9]+)/i',$file,$matches2)  && $foundName == '')
                        {
                            $foundName = $matches2['1'];
                            $methodused = "Release Files-5";
                            determineCategory($rel,$foundName,$methodused);                             
                        }
                    }
                    
                    //
                    //Check rarfile contents for a scene name with different regex
                    //
                    $sceneRegex2 = '/([a-z0-9\'\-\.\_\(\)\+\! ]+\.[a-z0-9\'\-\.\_\(\)\ ]+)(.*?\\\\.*?|)\.(?:\w{3,4})+\.([a-z0-9].*[a-z0-9])$/i';
                    if (preg_match($sceneRegex2, $file, $matches) && $foundName == '')
                    {
                        //Simply Releases Toppers
                        if(preg_match('/(\\\\)(?P<name>.*?ReleaseS Toppers)/',$file,$matches1)  && $foundName == '')
                        {
                            $foundName = $matches1['name'];
                            $methodused = "Release Files-1 v2";
                            determineCategory($rel,$foundName,$methodused);
                        }
                        //Scene format no folder.
                        if(preg_match('/^([a-z0-9\.\_\!\'(\\)\- ]+\-[a-z0-9\_]+)(\\\\|)$/i',$matches[1])  && $foundName == '' )
                        {
                            if (strlen($matches['1']) >= 15)
                            {
                                $foundName = $matches['1'];
                                $methodused = "Scene format no folder. v2";
                                determineCategory($rel,$foundName,$methodused); 
                            }
                        }

                        //Check to see if file is inside of a folder. Use folder name if it is
                        if(preg_match('/^(.*?\\\\)(.*?\\\\|)(.*?)$/i', $file, $matches1)  && $foundName == '' )
                        {
                            if(preg_match('/^([a-z0-9\.\_\- ]+\.[a-z0-9\_]+)(\\\\|)$/i',$matches1['1'],$res))
                            {
                                $foundName = $res['1'];
                                $methodused = "Release Files-2 v2";
                                determineCategory($rel,$foundName,$methodused);                             
                            }
                            if(preg_match('/(?!UTC)([a-z0-9]+[a-z0-9\.\_\- \'\)\(]+(\d{4}|HDTV).*?\-[a-z0-9]+)/i',$matches1['1'],$res) && $foundName == '')
                            {
                                $foundName = $res['1'];
                                $methodused = "Release Files-3 v2";
                                determineCategory($rel,$foundName,$methodused);                             
                            }
                            if(preg_match('/^([a-z0-9\.\_\!\'(\\)\- ]+\-[a-z0-9\_]+)(\\\\|)$/i',$matches1['2'],$res) && $foundName == '')
                            {
                                $foundName = $res['1'];
                                $methodused = "Release Files-4 v2";
                                determineCategory($rel,$foundName,$methodused);                                 
                            }
                        }
                        if(preg_match('/(?!UTC)([a-z0-9]+[a-z0-9\.\_\- \'\)\(]+(\d{4}|HDTV).*?\-[a-z0-9]+)/i',$file,$matches2)  && $foundName == '')
                        {
                            $foundName = $matches2['1'];
                            $methodused = "Release Files-5 v2";
                            determineCategory($rel,$foundName,$methodused);                             
                        }
                    }
                }
            }       
            
            ///
            /// This is a last ditch effort, build a ReleaseName from the Nfo
            ///
        
            if ($nfo && ($foundName == "" || $methodused == 'Scene format no folder.'))
            {           
                //LastNfoAttempt (IMDB)
                if(preg_match('/tt(\d{7})/i',$nfo,$matches) && $foundName == "")
                {
                    $movie = new Movie();
                    $imdbID ="";
                    $imdbId = $matches[1];
                    $movCheck = $movie->fetchImdbProperties($imdbId);
                    $buffer = getUrl('http://akas.imdb.com/title/tt'.$imdbId.'/');
                    if(!preg_match('/content\=\"video\.tv\_show\"/i',$buffer))
                    {
                        if(isset($movCheck['title']))
                        {
                            $foundName = $movCheck['title'];
                            if(!preg_match('/(19|20)\d{2}/i',$foundName))
                            {
                                $foundName = $foundName.".".$movCheck['year'];
                            }
                            if(preg_match('/(idiomas|lang|language|langue).*?\b(Brazilian|Chinese|Croatian|Danish|DE|Deutsch|Dutch|Estonian|ES|English|Finnish|Flemish|Francais|French|FR|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)\b/i',$nfo,$matches))
                            {
                                if($matches[2] == 'DE')
                                {
                                    $matches[2] = 'DUTCH';
                                }
                                if($matches[2] == 'FR')
                                {
                                    $matches[2] = 'FRENCH';
                                }
                                if($matches[2] == 'ES')
                                {
                                    $matches[2] = 'SPANISH';
                                }
                                $foundName = $foundName.".".$matches[2];
                            }
                            if(preg_match('/subtitles.*?\b(hardcoded)\b/i',$nfo,$matches))
                            {
                                if($matches[1] == 'hardcoded')
                                {
                                    $matches[1] = 'NL.SUB';
                                }
                                $foundName = $foundName.".".$matches[1];
                            }
                            if(preg_match('/audio.*?\b(\w+)\b/i',$nfo,$matches))
                            {
                                if(preg_match('/(Brazilian|Chinese|Croatian|Danish|Deutsch|Dutch|Estonian|English|Finnish|Flemish|Francais|French|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)/i', $matches[1]))
                                {
                                    if($matches[1] == 'DE')
                                    {
                                        $matches[1] = 'DUTCH';
                                    }
                                    if($matches[1] == 'FR')
                                    {
                                        $matches[1] = 'FRENCH';
                                    }
                                    if($matches[1] == 'ES')
                                    {
                                        $matches[1] = 'SPANISH';
                                    }
                                    $foundName = $foundName.".".$matches[1];
                                }   
                            }               
                            if(preg_match('/(hauteur|height|largeur|res|resolution|video|video res|width).*?(272|480|494|528|640|720|720x480|816|820|1080|1 080|1280 @|1280|1920|1 920|1920x1080)/i',$nfo,$matches))
                            {
                                if($matches[2] == '272')
                                {
                                    $matches[2] = '272p';
                                }
                                if($matches[2] == '480')
                                {
                                    $matches[2] = '480p';
                                }
                                if($matches[2] == '494')
                                {
                                    $matches[2] = '480p';
                                }
                                if($matches[2] == '640')
                                {
                                    $matches[2] = '480p';
                                }
                                if($matches[2] == '720')
                                {
                                    $matches[2] = '720p';
                                }
                                if($matches[2] == '720x480')
                                {
                                    $matches[2] = '480p';
                                }
                                if($matches[2] == '816')
                                {
                                    $matches[2] = '1080p';
                                }
                                if($matches[2] == '820')
                                {
                                    $matches[2] = '1080p';
                                }   
                                if($matches[2] == '1080')
                                {
                                    $matches[2] = '1080p';
                                }
                                if($matches[2] == '1280x720')
                                {
                                    $matches[2] = '720p';
                                }
                                if($matches[2] == '1280 @')
                                {
                                    $matches[2] = '720p';
                                }
                                if($matches[2] == '1280')
                                {
                                    $matches[2] = '720p';
                                }
                                if($matches[2] == '1920')
                                {
                                    $matches[2] = '1080p';
                                }   
                                if($matches[2] == '1 920')
                                {
                                    $matches[2] = '1080p';
                                }   
                                if($matches[2] == '1 080')
                                {
                                    $matches[2] = '1080p';
                                }       
                                if($matches[2] == '1920x1080')
                                {
                                    $matches[2] = '1080p';
                                }
                                $foundName = $foundName.".".$matches[2];
                            }                   
                            if(preg_match('/source.*?\b(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)\b/i',$nfo,$matches))
                            {   
                                if($matches[1] == 'BD')
                                {
                                    $matches[1] = 'Bluray.x264';
                                }
                                if($matches[1] == 'CAMRIP')
                                {
                                    $matches[1] = 'CAM';
                                }
                                if($matches[1] == 'DBrip')
                                {
                                    $matches[1] = 'BDRIP';
                                }
                                if($matches[1] == 'HD')
                                {
                                    $matches[1] = 'HDTV';
                                }
                                if($matches[1] == 'NTSC')
                                {
                                    $matches[1] = 'DVD';
                                }
                                if($matches[1] == 'PAL')
                                {
                                    $matches[1] = 'DVD';
                                }
                                if($matches[1] == 'Ripped ')
                                {
                                    $matches[1] = 'DVDRIP';
                                }
                                if($matches[1] == 'VOD')
                                {
                                    $matches[1] = 'DVD';
                                }
                                $foundName = $foundName.".".$matches[1];
                            }
                            if(preg_match('/(codec|codec name|codec code|format|MPEG-4 Visual|original format|res|resolution|video|video format|video res|tv system|type|writing library).*?\b(AVC|AVI|DBrip|DIVX|DVD|(H|X)(.)?(2)?64|NTSC|PAL|WMV|XVID)\b/i',$nfo,$matches))
                            {
                                if($matches[2] == 'AVI')
                                {
                                    $matches[2] = 'DVDRIP';
                                }
                                if($matches[2] == 'DBrip')
                                {
                                    $matches[2] = 'BDRIP';
                                }
                                 if($matches[2] == 'h.264')
                                {
                                    $matches[2] = 'H264';
                                }
                                if($matches[2] == 'MPEG-4 Visual')
                                {
                                    $matches[2] = 'x264';
                                }
                                if($matches[1] == 'NTSC')
                                {
                                    $matches[1] = 'DVD';
                                }
                                if($matches[1] == 'PAL')
                                {
                                    $matches[1] = 'DVD';
                                }
                                if($matches[2] == 'x.264')
                                {
                                    $matches[2] = 'x264';
                                }
                                $foundName = $foundName.".".$matches[2];
                            }
                            if(preg_match('/(audio|audio format|codec|codec name|format).*?\b(0x0055 MPEG-1 Layer 3|AAC( LC)?|AC-?3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3)\b/i',$nfo,$matches))
                            {
                                if($matches[2] == '0x0055 MPEG-1 Layer 3')
                                {
                                    $matches[2] = 'MP3';
                                }
                                if($matches[2] == 'AC-3')
                                {
                                    $matches[2] = 'AC3';
                                }
                                if($matches[2] == 'AAC LC')
                                {
                                    $matches[2] = 'AAC';
                                }
                                if($matches[2] == 'A_DTS')
                                {
                                    $matches[2] = 'DTS';
                                }
                                if($matches[2] == 'DTS-HD')
                                {
                                    $matches[2] = 'DTS';
                                }
                                 if($matches[2] == 'DTSHD')
                                {
                                    $matches[2] = 'DTS';
                                }
                                $foundName = $foundName.".".$matches[2];
                            }                       
                            if(preg_match('/posted.*?\b(joalba)\b/i',$nfo,$matches))
                            {   
                                if($matches[1] == 'joalba')
                                {
                                    $matches[1] = 'XVID';
                                }
                                $foundName = $foundName.".".$matches[1];
                            }
                            $foundName = $foundName."-NoGroup";
                            $methodused = "LastNfoAttempt"; 
                            determineCategory($rel,$foundName,$methodused);                 
                        }
                    }
                }
                //LastNfoAttempt Try to get from season / resolution / etc
                
                //Title.SxxEx.EpTitle.source.vcodec.group
                if(preg_match('/(?:\b\w+.\b){1,5}.s[0-9]([0-9])?e[0-9]([0-9])?.*(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 1"; 
                    determineCategory($rel,$foundName,$methodused); 
                }
                //Title.SxxExx.EPtitle.resolution.source.vcodec.group
                if(preg_match('/(?:\b\w+.\b){1,5}.s[0-9]([0-9])?e[0-9]([0-9])?.*(480|720|1080)(i|p).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 2"; 
                    determineCategory($rel,$foundName,$methodused); 
                }
                //Title.SxxExx.source.vcodec.group
                if(preg_match('/(?:\b\w+.\b){1,5}.s[0-9]([0-9])?e[0-9]([0-9])?.(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 3"; 
                    determineCategory($rel,$foundName,$methodused); 
                }
                //Title.SxxExx.acodec.source.resolution.vcodec.group
                if(preg_match('/(?:\b\w+.\b){1,5}.s[0-9]([0-9])?e[0-9]([0-9]).(AAC( LC)?|AC-?3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(480|720|1080)(i|p).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 4"; 
                    determineCategory($rel,$foundName,$methodused); 
                }
                //Title.Sxx-Exx.eptitle.year.group
                if(preg_match('/(?:\b\w+.\b){1,5}.s[0-9]([0-9])?-?e[0-9]([0-9])?.*((19|20)\d\d).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 5"; 
                    determineCategory($rel,$foundName,$methodused); 
                }
                //Title.Sxx-Exx.res.src.vcod.group
                if(preg_match('/(?:\b\w+.\b){1,5}.s[0-9]([0-9])?e[0-9]([0-9]).(480|720|1080)(i|p).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 6"; 
                    determineCategory($rel,$foundName,$methodused); 
                }
                //Title.year.eptitle.res.vcod
                if(preg_match('/(?:\b\w+.\b){1,5}.((19|20)\d\d).*?(480|720|1080)(i|p).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 7"; 
                    determineCategory($rel,$foundName,$methodused); 
                }
                //Title.year.###(season/episode).source.group
                if(preg_match('/(?:\b\w+.\b){1,5}.((19|20)\d\d).[0-9]{1,4}.(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 8"; 
                    determineCategory($rel,$foundName,$methodused); 
                }
                //Title.year.language.acodec.source.vcodec.group
                if(preg_match('/(?:\b\w+.\b){1,5}.((19|20)\d\d).(Brazilian|Chinese|Croatian|Danish|Deutsch|Dutch|Estonian|English|Finnish|Flemish|Francais|French|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish).(AAC( LC)?|AC-?3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 9"; 
                    determineCategory($rel,$foundName,$methodused); 
                }
                //Title.year.resolution.source.acodec.vcodec.group
                if(preg_match('/(?:\b\w+.\b){1,5}.((19|20)\d\d).(480|720|1080)(i|p).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(AAC( LC)?|AC-?3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3).(DivX|H264|MPEG2|X264|XviD).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 10";    
                    determineCategory($rel,$foundName,$methodused); 
                }
                //Title.year.resolution.source.vcodec.group
                if(preg_match('/(?:\b\w+.\b){1,5}.((19|20)\d\d).(480|720|1080)(i|p).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 11";    
                    determineCategory($rel,$foundName,$methodused); 
                }
                //Title.year.source.resolution.acodec.vcodec.group
                if(preg_match('/(?:\b\w+.\b){1,5}.((19|20)\d\d).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(480|720|1080)(i|p).(AAC( LC)?|AC-?3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 12";    
                    determineCategory($rel,$foundName,$methodused); 
                }
                //Title.resolution.source.acodec.vcodec.group
                if(preg_match('/(?:\b\w+.\b){1,5}.(480|720|1080)(i|p).(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL).(AAC( LC)?|AC-?3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3).(DivX|(H|X)(.)?(2)?64|MPEG2|XviD|WMV).(?:\b\w+.\b){1,3}/i',$nfo,$matches) && $foundName == "")
                {
                    $foundName = str_replace("_",".",$matches['0']);
                    $methodused = "LastNfoAttempt extra 13";    
                    determineCategory($rel,$foundName,$methodused); 
                }
            }
            
            if ($foundName == '' && $debug == true)
            {
                echo 'ReleaseID:        '.$rel['RID']."\n";
                echo ' Group:       '. $rel['groupname']."\n";
                echo ' Old Name:        '.$rel['name']."\n";
                echo ' Old SearchName:  '.$rel['searchname']."\n";
                echo " Status:      No New Name Found.\n\n";
            }
        }
    }
}
echo $rescount. " releases checked\n";
echo $Nfocount." of ".$rescount." releases had Nfo's processed\n";
echo $Filecount." of ".$rescount." releases had ReleaseFiles processed\n";
echo $updated. " releases Changed\n";

