<?php
require(dirname(__FILE__)."/config.php");
require_once (WWW_DIR."/lib/framework/db.php");
require_once (WWW_DIR."/lib/nntp.php");
require_once (WWW_DIR."/lib/site.php");
require_once (WWW_DIR."/lib/util.php");
require_once (WWW_DIR."/lib/releases.php");
require_once (WWW_DIR."/lib/nfo.php");
require_once (WWW_DIR."/lib/categorymod.php");
require_once (WWW_DIR."/lib/movie.php");


//get variables from config.sh and defaults.sh
$path = dirname(__FILE__);
$varnames = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$varnames .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$vardata = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
$vardata .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
$varnames = explode("\n", $varnames);
$vardata = explode("\n", $vardata);
$array = array_combine($varnames, $vardata);
unset($array['']);


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
$limited = $array['WHOLE_DB'];
/// Set to true for 24 hours, false for whole db.

/// WARNING!!!!!!!!The following value runs update_parsing either against "other categories", or all categories, do not use this if $echo is false. This is for testing.
/// WARNING!!!!!!!!Make sure memory_limit in php has no limit ( -1 ) if you are running against all categories
$othercats = true;
/// WARNING!!!!!!!!Set to true(recommended!) to do "other" categories

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
        $sql = "SELECT r.searchname, r.name, r.fromname, r.ID as RID, r.categoryID, r.guid, r.postdate,
			   rn.ID as nfoID,
			   g.name as groupname,
			   GROUP_CONCAT(rf.name) as filenames
		FROM releases r 
		LEFT JOIN releasenfo rn ON (rn.releaseID = r.ID)
		LEFT JOIN groups g ON (g.ID = r.groupID)
		LEFT JOIN releasefiles rf ON (rf.releaseID = r.ID)
		WHERE r.categoryID in (".Category2::CAT_TV_OTHER.",".Category2::CAT_MOVIE_OTHER.",".Category2::CAT_MISC_OTHER.",".Category2::CAT_XXX_OTHER.")
		%s
		GROUP BY r.ID";
}
else //All categories
{
        // Modified query to run against all categories, USE WITH CAUTION.
        $sql = "SELECT r.searchname, r.name, r.fromname, r.ID as RID, r.categoryID, r.guid, r.postdate,
			   rn.ID as nfoID,
			   g.name as groupname,
			   GROUP_CONCAT(rf.name) as filenames
		FROM releases r 
		LEFT JOIN releasenfo rn ON (rn.releaseID = r.ID)
		LEFT JOIN groups g ON (g.ID = r.groupID)
		LEFT JOIN releasefiles rf ON (rf.releaseID = r.ID)
		WHERE r.categoryID in
        (".Category2::CAT_GAME_NDS.",".Category2::CAT_GAME_PSP.",".Category2::CAT_MOVIE_HD.",
        ".Category2::CAT_GAME_WII.",".Category2::CAT_GAME_XBOX.",".Category2::CAT_GAME_XBOX360.",
        ".Category2::CAT_GAME_WIIWARE.",".Category2::CAT_GAME_XBOX360DLC.",".Category2::CAT_MOVIE_FOREIGN.",
        ".Category2::CAT_MOVIE_OTHER.",".Category2::CAT_MOVIE_SD.",".Category2::CAT_MOVIE_BLURAY.",
        ".Category2::CAT_MOVIE_3D.",".Category2::CAT_MUSIC_MP3.",".Category2::CAT_MUSIC_VIDEO.",
        ".Category2::CAT_MUSIC_AUDIOBOOK.",".Category2::CAT_MUSIC_LOSSLESS.",".Category2::CAT_PC_0DAY.",
        ".Category2::CAT_PC_ISO.",".Category2::CAT_PC_MAC.",".Category2::CAT_PC_MOBILEOTHER.",
        ".Category2::CAT_PC_GAMES.",".Category2::CAT_PC_MOBILEIOS.",".Category2::CAT_PC_MOBILEANDROID.",
        ".Category2::CAT_TV_FOREIGN.",".Category2::CAT_TV_SD.",".Category2::CAT_TV_HD.",
        ".Category2::CAT_TV_OTHER.",".Category2::CAT_TV_SPORT.",".Category2::CAT_TV_ANIME.",
        ".Category2::CAT_TV_DOCU.",".Category2::CAT_XXX_DVD.",".Category2::CAT_XXX_WMV.",
        ".Category2::CAT_XXX_XVID.",".Category2::CAT_XXX_X264.",".Category2::CAT_XXX_IMAGESET.",
        ".Category2::CAT_XXX_OTHER.",".Category2::CAT_BOOK_MAGS.",".Category2::CAT_BOOK_EBOOK.",".Category2::CAT_BOOK_COMICS.")
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

function determineCategory($rel,$foundName)
{
	$categoryID = null;
	$category = new Category2();
	$categoryID = $category->determineCategory($rel['groupname'], $foundName);
	if(($categoryID == $rel['categoryID'] || $categoryID == '7900'))
	{
		return false;
	}
	else
	{
		return true;
	}
}

function updateCategory($rel,$foundName,$methodused)
{
	global $updated,$echo,$methodused,$foundName;
	$categoryID = null;
	$category = new Category2();
	$categoryID = $category->determineCategory($rel['groupname'], $foundName);
	if(($methodused == 'a.b.hdtv.x264') && ($rel['groupname'] == 'alt.binaries.hdtv.x264')) { $categoryID = Category2::CAT_MOVIE_HD; }
	if(($categoryID == $rel['categoryID'] || $categoryID == '7900'))
	{
		$foundName = null;
		$methodused = null;
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
		echo 'ReleaseID: 		'.$rel['RID']."\n";
		echo ' Group: 		'. $rel['groupname']."\n";
		echo ' Old Name: 		'.$rel['name']."\n";
		echo ' Old SearchName: 	'.$rel['searchname']."\n";
		echo ' New Name: 		'.$name."\n";
		echo ' New SearchName: 	'.$searchname."\n";
		echo ' Old Cat: 		'.$rel['categoryID']."\n";
		echo ' New Cat: 		'.$categoryID."\n";
		echo ' Method: 		'.$methodused."\n";
		echo " Status: 		Release Changed, Updating Release\n\n";
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

			//Use some Magic on the Name to get the proper Release Name.
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
				$methodused = "Release names 1: Knoc.One";
				if (determineCategory($rel,$foundName) === true)
				{
					updateCategory($rel,$foundName,$methodused);
				}
				else
				{
					$foundName = null;
				}
			}

			//Use the Nfo to try to get the proper release name using scene regex.	
			$nfo = $db->queryOneRow(sprintf("select uncompress(nfo) as nfo from releasenfo where releaseID = %d", $rel['RID']));
			if ($nfo && $foundName == "")
			{
				$nfo = $nfo['nfo'];
				$Nfocount ++;
				
				//Title.SxxEx.EpTitle.source.vcodec.group
				if(preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})[\w.\-\',;]+(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+
/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 1 Scene titles 1";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.SxxExx.EPtitle.resolution.source.vcodec.group
				if(preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})[\w.\-\',;]+(480|720|1080)(i|p)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 2 Scene titles 2";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}	
				}
				//Title.SxxExx.source.vcodec.group
				if(preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 3 Scene titles 3";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.SxxExx.acodec.source.resolution.vcodec.group
				if(preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)(AAC( LC)?|AC-?3|DD5((\.|_|\-)1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(480|720|1080)(i|p)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 4 Scene titles 4";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.Sxx-Exx.eptitle.year.group
				if(preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})[\w.\-\',;]+((19|20)\d\d)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 5 Scene titles 5";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.Sxx-Exx.res.src.vcod.group
				if(preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)(480|720|1080)(i|p)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 6 Scene titles 6";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.year.eptitle.res.vcod
				if(preg_match('/\b[\w.\-\',;]+((19|20)\d\d)[\w.\-\',;]+(480|720|1080)(i|p)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 7 Scene titles 7";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.year.###(season/episode).source.group
				if(preg_match('/\b[\w.\-\',;]+((19|20)\d\d)(\.|_|\-)((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 8 Scene titles 8";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.year.language.acodec.source.vcodec.group
				if(preg_match('/\b[\w.\-\',;]+(Brazilian|Chinese|Croatian|Danish|Deutsch|Dutch|Estonian|English|Finnish|Flemish|Francais|French|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)(\.|_|\-)(AAC( LC)?|AC-?3|DD5((\.|_|\-)1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 9 Scene titles 9";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.year.resolution.source.acodec.vcodec.group
				if(preg_match('/\b[\w.\-\',;]+((19|20)\d\d)(\.|_|\-)(480|720|1080)(i|p)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(AAC( LC)?|AC-?3|DD5((\.|_|\-)1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 10 Scene titles 10";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.year.resolution.source.vcodec.group
				if(preg_match('/\b[\w.\-\',;]+((19|20)\d\d)(\.|_|\-)(480|720|1080)(i|p)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 11 Scene titles 11";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.year.source.resolution.acodec.vcodec.group
				if(preg_match('/\b[\w.\-\',;]+((19|20)\d\d)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(480|720|1080)(i|p)(\.|_|\-)(AAC( LC)?|AC-?3|DD5((\.|_|\-)1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 12 Scene titles 12";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.year.source.res.group
				if(preg_match('/[\w.\-\',; ]+((19|20)\d\d)(\.|_|\-| )(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BR(RIP)?|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-| )(480|720|1080)(i|p)(\.|_|\-| )[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 13 Scene titles 13";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.resolution.source.acodec.vcodec.group
				if(preg_match('/[\w.\-\',;]+(480|720|1080)(i|p)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(AAC( LC)?|AC-?3|DD5((\.|_|\-)1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "Nfo 14 Scene titles 14";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Title.language.year.acodec.src - for these stupid arse ones XviD.iNTERNAL-CENTi
				if (preg_match('/\b[\w.\-\',;]+(Brazilian|Chinese|Croatian|Danish|Deutsch|Dutch|Estonian|English|Finnish|Flemish|Francais|French|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)(\.|_|\-)((19|20)\d\d)(\.|_|\-)(AAC( LC)?|AC-?3|DD5((\.|_|\-)1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[\w.\-\',;]+/i', $nfo, $matches) && $foundName == '') 
				{
					$foundName = str_replace("_",".",$matches['0']);
					$methodused = "NFO 15 XviD.iNTERNAL-CENTi";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				// Videogames 1
				if(preg_match('/[\w.\-\',;]+(ASIA|DLC|EUR|GOTY|JPN|KOR|MULTI\d{1}|NTSCU?|PAL|RF|Region(\.|_|-|( ))?Free|USA|XBLA)(\.|_|\-)(DLC(\.|_|\-)Complete|FRENCH|GERMAN|MULTI\d{1}|PROPER|PSN|READ(\.|_|-|( ))?NFO|UMD)?(\.|_|\-)?(GC|NDS|NGC|PS3|PSP|WII|XBOX(360)?)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_"," ",$matches['0']);
					$methodused = "Nfo 16 Videogames 1";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				// Videogames 2
				if(preg_match('/\b[\w.\-\',;]+(GC|NDS|NGC|PS3|WII|XBOX(360)?)(\.|_|\-)(DUPLEX|iNSOMNi|OneUp|STRANGE|SWAG|SKY)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_"," ",$matches['0']);
					$methodused = "Nfo 17 Videogames 2";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				// Apps 1
				if(preg_match('/\b[\w.\-\',;]+(\d{1,10}|Linux|UNIX)(\.|_|\-)(RPM)?(\.|_|\-)?(X64)?(\.|_|\-)?(Incl)(\.|_|\-)(Keygen)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = str_replace("_"," ",$matches['0']);
					$methodused = "Nfo 18 Apps 1";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				// Apps 2
				if(preg_match('/\b[\w.\-\',;]+(\d){1,8}(\.|_|\-)(winall-freeware)[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{	
					$foundName = str_replace("WinAll-Freeware","Software.WinAll-Freeware",$matches['0']);
					$foundName = str_replace("_"," ",$matches['0']);
					$methodused = "Nfo 19 Apps 2 WinALL-Freeware";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				// Sports 1 - year.month.day.sport.year?.text
				if(preg_match('/(19|20)\d\d(\.|_|\-)\d{2}(\.|_|\-)\d{2}(\.|_|\-)(IndyCar|NBA|NCW(T|Y)S|NNS|NSCS?)((\.|_|\-)(19|20)\d\d)?[\w.\-\',;]+/i',$nfo,$matches) && $foundName == "")
				{	
					$foundName = str_replace("_"," ",$matches['0']);
					$methodused = "Nfo 20 Sports 1";	
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
			}

			// Unable to extract releasename from nfo, try the rar file using scene regex.
			if($rel['filenames'] && $foundName == '')
			{
				$Filecount++;
				$files = explode( ',', $rel['filenames'] );
				if( !array($files) )
				{
					$files = array( $files );
				}
				
				foreach( $files AS $file )
				{
					// R&C
					if (preg_match('/\b[\w.\-\',;]+1080i(\.|_|\-)DD5(\.|_|\-)1(\.|_|\-)MPEG2-R&C(?=\.ts)/i', $file, $matches3) && $foundName == '')
					{
						$foundName = str_replace("MPEG2","MPEG2.HDTV",$matches3['0']);
						$methodused = "Filename 1 R&C";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					// NhaNc3
					if (preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)(480|720|1080)(i|p)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)nSD(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)(\.|_|\-)NhaNC3[\w.\-\',;]+/i', $file, $matches3) && $foundName == '')
					{
						$foundName = $matches3['0'];
						$methodused = "Filename 2 NhaNc3";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					// tvp 720p
					if (preg_match('/\btvp-[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)720p(?=\.mkv)/i', $file, $matches3) && $foundName == '')
					{
						$foundName = str_replace("720p","720p.HDTV.X264",$matches3['0']);
						$methodused = "Filename 3 tvp 720p";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					// tvp 1080p
					if (preg_match('/\btvp-[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)1080p(?=\.mkv)/i', $file, $matches3) && $foundName == '')
					{
						$foundName = str_replace("1080p","1080p.Bluray.X264",$matches3['0']);
						$methodused = "Filename 4 tvp 1080p";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					// tvp xvid
					if (preg_match('/\btvp-[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)xvid(?=\.avi)/i', $file, $matches3) && $foundName == '')
					{
						$foundName = str_replace("xvid","XVID.DVDrip",$matches3['0']);
						$methodused = "Filename 5 tvp xvid";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					// itouch
					if (preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)itouch-mw(?=\.mp4)/i', $file, $matches3) && $foundName == '')
					{
						$foundName = str_replace("itouch-mw","272p.x264.hdtv.itouch-mw",$matches3['0']);
						$methodused = "Filename 6 itouch (ipod releases)";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.SxxEx.EpTitle.source.vcodec.group.extension
					if (preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})[\w.\-\',;]+(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 7 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.SxxExx.EPtitle.resolution.source.vcodec.group.extension
					if (preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})[\w.\-\',; \[\]]+(480|720|1080)(i|p)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[\w.\-\',;]+(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 8 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.SxxExx.source.vcodec.group.extension
					if (preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 9 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.SxxExx.acodec.source.resolution.vcodec.group.extension
					if (preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)(AAC( LC)?|AC-?3|DD5((\.|_|\-)1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(480|720|1080)(i|p)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 10 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.Sxx-Exx.eptitle.year.group.extension
					if (preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})[\w.\-\',;]+((19|20)\d\d)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 11 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.Sxx-Exx.res.src.vcod.group.extension
					if (preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)(480|720|1080)(i|p)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 12 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.Sxx-Exx.eptitle.src.vcod.group.extension
					if (preg_match('/\b[\w.\-\',;]+((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})[\w.\-\',;]+(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)(\.|_|\-)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 13 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.year.eptitle.res.vcod.extension
					if (preg_match('/\b[\w.\-\',;]+((19|20)\d\d)[\w.\-\',;]+(480|720|1080)(i|p)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 14 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.year.###(season/episode).source.group.extension
					if (preg_match('/\b[\w.\-\',;]+((19|20)\d\d)(\.|_|\-)((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(\.|_|\-)?\d{2})(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 15 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.year.language.acodec.source.vcodec.group.extension
					if (preg_match('/\b[\w.\-\',;]+(Brazilian|Chinese|Croatian|Danish|Deutsch|Dutch|Estonian|English|Finnish|Flemish|Francais|French|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)(\.|_|\-)(AAC( LC)?|AC-?3|DD5((\.|_|\-)1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 16 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.year.resolution.source.acodec.vcodec.group.extension
					if (preg_match('/\b[\w.\-\',;]+((19|20)\d\d)(\.|_|\-)(480|720|1080)(i|p)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(AAC( LC)?|AC-?3|DD5((\.|_|\-)1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 17 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.year.resolution.source.vcodec.group.extension
					if (preg_match('/\b[\w.\-\',;]+((19|20)\d\d)(\.|_|\-)(480|720|1080)(i|p)?(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 18 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.year.source.resolution.acodec.vcodec.group.extension
					if (preg_match('/\b[\w.\-\',;]+((19|20)\d\d)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(480|720|1080)(i|p)(\.|_|\-)(AAC( LC)?|AC-?3|DD5((\.|_|\-)1)?|(A_)?DTS(-|(\.|_|\-))?(AC3|HD)?|(Dolby)?(( )?TrueHD)?|MP3)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 19 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Sue Thomas F.B.Eye - S02E04 - Cold Case.
					if (preg_match('/\S.*[\w.\-\',;]+\s\-\ss\d{2}e\d{2}\s\-\s[\w.\-\',;].+\./i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 20";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					//Title.resolution.source.acodec.vcodec.group.extension
					if (preg_match('/\b[\w.\-\',;]+(480|720|1080)(i|p)(\.|_|\-)(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)(\.|_|\-)(AAC( LC)?|AC-?3|DD5((\.|_|\-)1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|MP3)(\.|_|\-)(DivX|(H|X)(\.|_|\-)?264|MPEG2|XviD|WMV)[\w.\-\',;]+/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 21 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					// Videogames 1
					if(preg_match('/[\w.\-\',;]+(ASIA|DLC|EUR|GOTY|JPN|KOR|MULTI\d{1}|NTSCU?|PAL|RF|Region(\.|_|-|( ))?Free|USA|XBLA)(\.|_|\-)(DLC\.Complete|FRENCH|GERMAN|MULTI\d{1}|PROPER|PSN|READ(\.|_|-|( ))?NFO|UMD)?(\.|_|\-)?(GC|NDS|NGC|PS3|PSP|WII|XBOX(360)?)[\w.\-\',;]+/i',$file,$matches) && $foundName == "")
					{
						$foundName = str_replace("_"," ",$matches['0']);
						$methodused = "Filename 22 Videogames 1";	
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					// Videogames 2
					if(preg_match('/[\w.\-\',;]+(GC|NDS|NGC|PS3|WII|XBOX(360)?)(\.|_|\-)(DUPLEX|iNSOMNi|STRANGE|SWAG|SKY)[\w.\-\',;]+/i',$file,$matches) && $foundName == "")
					{
						$foundName = str_replace("_"," ",$matches['0']);
						$methodused = "Filename 23 Videogames 2";	
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					// Apps 1
					if(preg_match('/\b[\w.\-\',;]+(\d{1,10}|Linux|UNIX)(\.|_|\-)(RPM)?(\.|_|\-)?(X64)?(\.|_|\-)?(Incl)(\.|_|\-)(Keygen)[\w.\-\',;]+/i',$file,$matches) && $foundName == "")
					{
						$foundName = str_replace("_"," ",$matches['0']);
						$methodused = "Filename 24 Apps 1";	
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					// Apps 2
					if(preg_match('/\b[\w.\-\',;]+(\d){1,8}(\.|_|\-)(winall-freeware)[\w.\-\',;]+/i',$file,$matches) && $foundName == "")
					{
						$foundName = str_replace("WinAll-Freeware","Software.WinAll-Freeware",$matches['0']);
						$foundName = str_replace("_"," ",$matches['0']);
						$methodused = "Filename 25 Apps 2";	
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					// PC Games 1 OUTLAWS
					if(preg_match('/\b[A-Za-z0-9._\-\',;].+-OUTLAWS/',$file,$matches) && $foundName == "")
					{
						$foundName = str_replace("_"," ",$matches['0']);
						$foundName = str_replace("OUTLAWS","PC GAME OUTLAWS",$matches['0']);
						$methodused = "Filename 26 PC Games 1 OUTLAWS";	
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					// PC Games 2 ALiAS
					if(preg_match('/\b[A-Za-z0-9._\-\',;].+-ALiAS/',$file,$matches) && $foundName == "")
					{
						$foundName = str_replace("_"," ",$matches['0']);
						$foundName = str_replace("ALiAS","PC GAME ALiAS",$matches['0']);
						$methodused = "Filename 27 PC Games 1 ALiAS";	
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
					// ReleaseGroup.Title.Format.mkv
					if (preg_match('/(?<=swg_|swghd\-|lost\-|veto\-|kaka\-|abd\-|airline\-|daa\-|data\-|japhson\-|ika\-|lng\-|nrdhd\-|saimorny\-|sparks\-|ulshd\-|nscrns\-|ifpd\-|invan\-|an0\-|besthd\-|muxhd\-|s7\-).*?(1080|720|480)(i|p)(?=\.MKV)/i', $file, $matches3) && $foundName == '') 
					{
						$foundName = str_replace("_",".",$matches3['0']);
						$methodused = "Filename 28 Scene Releases";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
				}	
			}
			
			//The Big One
			if(preg_match_all('/([\w\d\ ]+)\.{2,}(\:|\[)(?P<name>.*)(\s{2}|\s{1})/i',$nfo, $matches) && $foundName == "")
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
					$methodused = "The Big One Music";
					$foundName = $tempname;
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}							
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
					$methodused = "The Big One Other";
					$foundName = $tempname;
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}								
				}
			}

			// unable to extract releasename from nfo, try the rar file
			if($rel['filenames'] && $foundName == '')
			{
				$Filecount++;
				$files = explode( ',', $rel['filenames'] );
				if( !array($files) )
				{
					$files = array( $files );
				}
				
				foreach( $files AS $file )
				{
					// Scene regex
					$sceneRegex = '/([\w\'\-\.\(\)\+\ ]+\-[\w\'\-\.\(\)\ ]+)(.*?\\\\.*?|)\.(?:\w{3,4})$/i';
					//Check rarfile contents for a scene name
					if (preg_match($sceneRegex, $file, $matches) && $foundName == '')
					{
						//Simply Releases Toppers
						if(preg_match('/(\\\\)(?P<name>.*?ReleaseS Toppers)/',$file,$matches1)  && $foundName == '')
						{
							$foundName = $matches1['name'];
							$methodused = "Filename 29 Simply ReleaseS Toppers";
							if (determineCategory($rel,$foundName) === true)
							{
								updateCategory($rel,$foundName,$methodused);
							}
							else
							{
								$foundName = null;
							}		
						}

						//Check to see if file is inside of a folder. Use folder name if it is
						if(preg_match('/^(.*?\\\\)(.*?\\\\|)(.*?)$/i', $file, $matches1)  && $foundName == '' )
						{
							If(preg_match('/^([\w\.\- ]+\-[\w]+)(\\\\|)$/i',$matches1['1'],$res))
							{
								$foundName = $res['1'];
								$methodused = "Filename 30 Folder Name";
								if (determineCategory($rel,$foundName) === true)
								{
									updateCategory($rel,$foundName,$methodused);
								}
								else
								{
									$foundName = null;
								}				
							}
							If(preg_match('/(?!UTC)([\w]+[\w\.\- \'\)\(]+(\d{4}|HDTV).*?\-[\w]+)/i',$matches1['1'],$res) && $foundName == '')
							{
								$foundName = $res['1'];
								$methodused = "Filename 31 Folder Name";
								if (determineCategory($rel,$foundName) === true)
								{
									updateCategory($rel,$foundName,$methodused);
								}
								else
								{
									$foundName = null;
								}							
							}
							If(preg_match('/^([\w\.\- ]+\-[\w]+)(\\\\|)$/i',$matches1['2'],$res) && $foundName == '')
							{
								$foundName = $res['1'];
								$methodused = "Filename 32 Folder Name";
								if (determineCategory($rel,$foundName) === true)
								{
									updateCategory($rel,$foundName,$methodused);
								}
								else
								{
									$foundName = null;
								}					
							}
						}
					}
					
					//Check rarfile contents for a scene name with different regex
					$sceneRegex2 = '/([\w\'\-\.\(\)\+\! ]+\.[\w\'\-\.\(\)\ ]+)(.*?\\\\.*?|)\.(?:\w{3,4})+\.([\w].*[\w])$/i';
					if (preg_match($sceneRegex2, $file, $matches) && $foundName == '')
					{
						//Simply Releases Toppers
						if(preg_match('/(\\\\)(?P<name>.*?ReleaseS Toppers)/',$file,$matches1)  && $foundName == '')
						{
							$foundName = $matches1['name'];
							$methodused = "Filename 33 Rar File";
							if (determineCategory($rel,$foundName) === true)
							{
								updateCategory($rel,$foundName,$methodused);
							}
							else
							{
								$foundName = null;
							}
						}
						//Scene format no folder.
						if(preg_match('/^([\w\.\!\'(\\)\- ]+\-[\w\.]+)(\\\\|)$/i',$matches[0])  && $foundName == '' )
						{
							if (strlen($matches['0']) >= 15)
							{
								$foundName = $matches['0'];
								$methodused = "Filename 34 No Folder";
								if (determineCategory($rel,$foundName) === true)
								{
									updateCategory($rel,$foundName,$methodused);
								}
								else
								{
									$foundName = null;
								}
							}
						}

						//Check to see if file is inside of a folder. Use folder name if it is
						if(preg_match('/^(.*?\\\\)(.*?\\\\|)(.*?)$/i', $file, $matches1) && !preg_match('/(\.|_|\-)x64(\.|_|\-)/i', $file)  && $foundName == '' )
						{
							if(preg_match('/^([\w\.\- ]+\.[\w]+)(\\\\|)$/i',$matches1['1'],$res))
							{
								$foundName = $res['1'];
								$methodused = "Filename 35 Folder Name";
								if (determineCategory($rel,$foundName) === true)
								{
									updateCategory($rel,$foundName,$methodused);
								}
								else
								{
									$foundName = null;
								}						
							}
							if(preg_match('/(?!UTC)([\w]+[\w\.\- \'\)\(]+(\d{4}|HDTV).*?\-[\w]+)/i',$matches1['1'],$res) && $foundName == '')
							{
								$foundName = $res['1'];
								$methodused = "Filename 36 Folder Name";
								if (determineCategory($rel,$foundName) === true)
								{
									updateCategory($rel,$foundName,$methodused);
								}
								else
								{
									$foundName = null;
								}						
							}
							if(preg_match('/^([\w\.\!\'(\\)\- ]+\-[\w]+)(\\\\|)$/i',$matches1['2'],$res) && $foundName == '')
							{
								$foundName = $res['1'];
								$methodused = "Filename 37 Folder Name";
								if (determineCategory($rel,$foundName) === true)
								{
									updateCategory($rel,$foundName,$methodused);
								}
								else
								{
									$foundName = null;
								}							
							}
						}
						if(preg_match('/(?!UTC)([\w]+[\w\.\- \'\)\(]+(\d{4}|HDTV).*?\-[\w]+)/i',$file,$matches2)  && $foundName == '')
						{
							$foundName = $matches2['1'];
							$methodused = "Filename 38 Folder Name";
							if (determineCategory($rel,$foundName) === true)
							{
								updateCategory($rel,$foundName,$methodused);
							}
							else
							{
								$foundName = null;
							}						
						}
					}
					// Petje Releases
					if (preg_match('/Petje \<petje\@pietamientje\.com\>/', $rel['fromname'], $matches3) && $foundName == '')
					{
						if (preg_match('/.*\.(mkv|avi|mp4|wmv|divx)/', $file, $matches4))
						{
							$array_new = explode('\\', $matches4[0]);
							foreach($array_new as $item)
							{
								if (preg_match('/.*\((19|20\d{2})\)$/', $item, $matched))
								{
										$foundName = $matched[0].".720p.x264-Petje";
										$methodused = "Petje";
										if (determineCategory($rel,$foundName) === true)
										{
											updateCategory($rel,$foundName,$methodused);
										}
										else
										{
											$foundName = null;
										}
										break 2;
								}
							}
						}
					}
				
					//3D Remux
					if (preg_match('/.*Remux\.mkv/', $file, $matches4))
					{
							$foundName = str_replace(".mkv", "", $matches4[0]);
							$methodused = "3D Remux";
							if (determineCategory($rel,$foundName) === true)
							{
								updateCategory($rel,$foundName,$methodused);
							}
							else
							{
								$foundName = null;
							}
					}													
					//QoQ Extended
					if (preg_match('/Q\-sbuSLN.*/i', $file, $matches4))
					{
						$new1 = preg_match('/( )?(\.wmv|\.divx|\.avi|\.mkv)/i', $matches4[0], $matched);
						$new2 = str_replace($matched[0], "", $matches4[0]);
						$foundName = strrev($new2);
						$methodused = "QoQ Extended";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
				}
			}		

			///	This is a last ditch effort, build a ReleaseName from the Nfo
			if ($nfo && ($foundName == "" || $methodused == 'Scene format no folder.'))
			{
				//Try to look for Titlle (Year).
				if(preg_match('/\b([\w.\-\',;"]+(( )| - )[\w.\-\',;"]+)+( )\((19|20)\d\d\)/i', $nfo,$matches) && $foundName == "")
				{
					$foundName = $matches[0];					
					if(preg_match('/(idiomas|lang|language|langue|sprache).*?\b(Brazilian|Chinese|Croatian|Danish|DE|Deutsch|Dutch|Estonian|ES|English|Englisch|Finnish|Flemish|Francais|French|FR|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)\b/i',$nfo,$matches))
					{
						if($matches[2] == 'DE')
					    {
						    $matches[2] = 'DUTCH';
					    }
					    if($matches[2] == 'Englisch')
					    {
						    $matches[2] = 'English';
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
					if(preg_match('/(frame size|res|resolution|video|video res).*?(272|480|494|528|640|\(640|688|704|720|720x480|816|820|1080|1 080|1280 @|1280|1920|1 920|1920x1080)/i',$nfo,$matches))
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
						if($matches[2] == '\(640')
						{
							$matches[2] = '480p';
						}
						if($matches[2] == '688')
						{
							$matches[2] = '480p';
						}
						if($matches[2] == '704')
						{
							$matches[2] = '480p';
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
						if($matches[2] == '720')
						{
							$matches[2] = '720p';
						}
						$foundName = $foundName.".".$matches[2];
					}
					if(preg_match('/(largeur|width).*?(640|\(640|688|704|720|1280 @|1280|1920|1 920)/i',$nfo,$matches))
					{
						if($matches[2] == '640')
						{
							$matches[2] = '480p';
						}
						if($matches[2] == '\(640')
						{
							$matches[2] = '480p';
						}
						if($matches[2] == '688')
						{
							$matches[2] = '480p';
						}
						if($matches[2] == '704')
						{
							$matches[2] = '480p';
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
						if($matches[2] == '720')
						{
							$matches[2] = '480p';
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
						if($matches[1] == 'DVD R1')
						{
							$matches[1] = 'DVD';
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
					if(preg_match('/(codec|codec name|codec code|format|MPEG-4 Visual|original format|res|resolution|video|video codec|video format|video res|tv system|type|writing library).*?\b(AVC|AVI|DBrip|DIVX|\(Divx|DVD|(H|X)(\.|_|\-)?264|NTSC|PAL|WMV|XVID)\b/i',$nfo,$matches))
					{
						if($matches[2] == 'AVI')
						{
							$matches[2] = 'DVDRIP';
						}
						if($matches[2] == 'DBrip')
						{
							$matches[2] = 'BDRIP';
						}
						if($matches[2] == '(Divx')
						{
							$matches[2] = 'DIVX';
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
					if(preg_match('/(audio|audio format|codec|codec name|format).*?\b(0x0055 MPEG-1 Layer 3|AAC( LC)?|AC-?3|\(AC3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|FLAC|MP3)\b/i',$nfo,$matches))
					{
						if($matches[2] == '0x0055 MPEG-1 Layer 3')
						{
							$matches[2] = 'MP3';
						}
						if($matches[2] == 'AC-3')
						{
							$matches[2] = 'AC3';
						}
						if($matches[2] == '(AC3')
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
					$methodused = "Nfo 21 - Title (Year)";
					$foundName = $foundName."-NoGroup";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}					
				}
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
							if(preg_match('/(idiomas|lang|(audio )?language|langue).*?\b(Brazilian|Chinese|Croatian|Danish|DE|Deutsch|Dutch|Estonian|ES|English|Finnish|Flemish|Francais|French|FR|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)\b/i',$nfo,$matches))
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
								if($matches[2] == '720')
								{
									$matches[2] = '720p';
								}
								$foundName = $foundName.".".$matches[2];
							}					
							if(preg_match('/source.*?\b(BD(-?(25|50|RIP))?|Blu(-)?Ray( )?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD(\-| )?(5|9|(R(1|IP)?|scr(eener)?))?|(H|P|S)D?(RIP|TV(RIP)?)?|NTSC|PAL|R\d|Retail R\d|Ripped |(S)?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)\b/i',$nfo,$matches))
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
								if($matches[1] == 'DVD R1')
								{
									$matches[1] = 'DVD';
								}
								if($matches[1] == 'DVD ')
								{
									$matches[1] = 'DVD';
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
								if($matches[1] == 'R\d')
								{
									$matches[1] = 'DVD';
								}
								if($matches[1] == 'Retail R\d')
								{
									$matches[1] = 'DVD';
								}
								$foundName = $foundName.".".$matches[1];
							}
							if(preg_match('/(codec( code| name)?|MPEG-4 Visual|(original )?format|res(olution)?|video( codec| format| res)?|tv system|type|writing library).*?\b(AVC|AVI|DBrip|DIVX|DVDR?|(H|X)(\.|_|\-)?264|MPEG4|NTSC|PAL|WMV|XVID)\b/i',$nfo,$matches))
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
								if($matches[2] == 'MPEG4')
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
							if(preg_match('/(Audio( Bitrate| format| codec)|codec|codec name|format).*?\b(0x0055 MPEG-1 Layer 3|AAC( LC)?|AC-?3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|Dolby Digital \(AC-3\)|MP3)\b/i',$nfo,$matches))
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
								if($matches[2] == 'Dolby Digital \(AC-3\)')
								{
									$matches[2] = 'AC3';
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
							$methodused = "Nfo 22 - IMDB.com";	
							if (determineCategory($rel,$foundName) === true)
							{
								updateCategory($rel,$foundName,$methodused);
							}
							else
							{
								$foundName = null;
							}				
						}
					}
				}
				//LastNfoAttempt 3 (Specific nfo type where there isn't much info.)
				if(preg_match('/\b([\w.\-\',;]+( - |( )))?([\w.\-\',;]+)+( )\(?(19|20)\d\d\)?/i', $nfo) && preg_match('/\b(Brazilian|Chinese|Croatian|Danish|DE|Deutsch|Dutch|Estonian|ES|English|Englisch|Finnish|Flemish|Francais|French|FR|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)\ (0x0055 MPEG-1 Layer 3|AAC( LC)?|AC-?3|\(AC3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|FLAC|MP3)\ [0-9]{3,5}\ ?kbps\b/i', $nfo) && $foundName == "")
				{
					if(preg_match('/\b([\w.\-\',;]+( - |( )))?([\w.\-\',;]+)+( )\(?(19|20)\d\d\)?/i',$nfo,$matches))
					{
						$foundName = $matches[0];
					}
					if(preg_match('/\b(Brazilian|Chinese|Croatian|Danish|DE|Deutsch|Dutch|Estonian|ES|English|Englisch|Finnish|Flemish|Francais|French|FR|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)\ (0x0055 MPEG-1 Layer 3|AAC( LC)?|AC-?3|\(AC3|DD5(.1)?|(A_)?DTS(-)?(HD)?|(Dolby)?(( )?TrueHD)?|FLAC|MP3)\ [0-9]{3,5}\ ?kbps\b/i',$nfo,$matches))
					{
						$foundName = $foundName.".".$matches[1];
						$foundName = $foundName.".".$matches[2];
					}
					if(preg_match('/(640x|1280x|1920x)/i',$nfo,$matches))
					{
						if($matches[0] == '640x')
						{
							$matches[0] = '480p.XVID';
						}
						if($matches[0] == '1280x')
						{
							$matches[0] = '720p.x264';
						}
						if($matches[0] == '1920x')
						{
							$matches[0] = '1080p.x264';
						}
						$foundName = $foundName.".".$matches[0];
					}
					$methodused = "Nfo 23 Specific type of NFO";
					$foundName = $foundName.".NoGroup";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}						
				}
				//LastNfoAttempt 3 Iguana NFO's
				if(preg_match('/\bSupplier.+IGUANA\b/i', $nfo) && $foundName == "")
				{
					if(preg_match('/\b([\w.\-\',;]+(( )| - )[\w.\-\',;]+)+( )\(?(19|20)\d\d\)?/i',$nfo,$matches))
					{
						$foundName = $matches[0];
					}
					if(preg_match('/\s\[\*\] (English|Dutch|French|German|Spanish)\b/i',$nfo,$matches))
					{
						$foundName = $foundName.".".$matches[1];
					}
					if(preg_match('/\s\[\*\] (DTS 6(\.|_|\-)1|DS 5(\.|_|\-)1|DS 2(\.|_|\-)0|DS 2(\.|_|\-)0 MONO)\b/i',$nfo,$matches))
					{
						$foundName = $foundName.".".$matches[1];
					}
					if(preg_match('/\bFormat.+(DVD(5|9|R)?|(h|x)(\.|_|\-)?264)\b/i',$nfo,$matches))
					{
						$foundName = $foundName.".".$matches[1];
					}
					if(preg_match('/\[(640x.+|1280x.+|1920x.+)\] Resolution\b/i',$nfo,$matches))
					{
						if($matches[1] == '640x.+')
						{
							$matches[1] = '480p';
						}
						if($matches[1] == '1280x.+')
						{
							$matches[1] = '720p';
						}
						if($matches[1] == '1920x.+')
						{
							$matches[1] = '1080p';
						}
						$foundName = $foundName.".".$matches[1];
					}
					$methodused = "Nfo 24 IGUANA NFOs";
					$foundName = $foundName.".IGUANA";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}	
				}
			}
			
			//The Big One v2
			if(preg_match_all('/([\w\ ]+).{2,}(\:|\[)(?P<name>.*)(\s{1}|\s{0})/i',$nfo, $matches) && $foundName == "")
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
					$methodused = "The Big One Other v2";
					$foundName = $tempname;
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}							
				}
				//LOUNGE releases
				if(preg_match('/([\w.]+\.MBLURAY)/i', $nfo, $matches))
				{
					$foundName = $matches[1];
					$methodused = "Nfo 25 LOUNGE";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//AsianDVDClub releases
				if(preg_match('/adc-[\w]{1,10}/', $rel['name']))
				{
					if(preg_match('/.*\(\d{4}\).*/i', $nfo, $matches))
					{
						$foundName = $matches[0];
						$methodused = "Nfo 26 AsianDVDClub";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
				}
				//ACOUSTiC  releases
				if(preg_match('/ACOUSTiC presents \.\.\..*?([\w].*?\(.*?\))/is', $nfo, $matches))
				{
					$foundName = $matches[1].".MBLURAY";
					$methodused = "Nfo 27 ACOUSTiC ";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}				
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
						if(preg_match('/language.*?\b([\w]+)\b/i',$nfo,$matches))
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
						$methodused = "Nfo 28 Japhson";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
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
						if(preg_match('/L\.([\w]+)\b/i',$nfo,$matches))
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
						$methodused = "Nfo 29 AIHD";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
				}
				//IMAGiNE releases
				if(preg_match('/\*\s+([\w]+(?:\.|_| )[\w\.\- ]+ \- imagine)\s+\*/i', $nfo, $matches))
				{
					$foundName = $matches[1];
					$methodused = "Nfo 30 imagine";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//LEGION releases
				if(preg_match('/([\w \.\-]+LEGi0N)/is', $nfo, $matches) && $foundName == "")
				{
					$foundName = $matches[1];
					$methodused = "Nfo 31 Legion";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//SWAGGER releases
				if(preg_match('/(S  W  A  G  G  E  R|swg.*?nfo)/i', $nfo) && $foundName == "")
				{
					if(preg_match('/presents.*?([\w].*?\((19|20)\d{2}\))/is',$nfo,$matches))
						{
							$foundName = $matches[1];
						}						
					if(preg_match('/language.*?\b([\w]+)\b/i',$nfo,$matches))
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
					if(preg_match('/video.*?\b([\w]+)\b/i',$nfo,$matches))
						{
							$foundName = $foundName.".".$matches[1];
						}
					if(preg_match('/audio.*?\b([\w]+)\b/i',$nfo,$matches))
						{
							$foundName = $foundName.".".$matches[1];
						}
					$foundName = $foundName."-SWAGGER";
					$methodused = "Nfo 32 SWAGGER";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//cm8 releases
				if(preg_match('/([\w]+(?:\.|_| )[\w\.\- \'\)\(]+\-(futv|crimson|qcf|runner|clue|episode|momentum|PFA|topaz|vision|tdp|haggis|nogrp|shirk|imagine|santi|sys|deimos|ltu|ficodvdr|cm8|dvdr|Nodlabs|aaf|sprinter|exvid|flawl3ss|rx|magicbox|done|unveil))\b/i', $nfo, $matches) && $foundName == "")
				{
					$foundName = $matches[1];
					$methodused = "Nfo 33 cm8";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//river
				if(preg_match('/([\w\.\-]+\-(webios|river|w4f|sometv|ngchd|C4|gf|bov|26k|ftw))\b/i', $nfo, $matches) && $foundName == "")
				{
					$foundName = $matches[1];
					$methodused = "Nfo 34 river-1";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				if(preg_match('/([\w]+(?:\.|_| )[\w\.\- \'\)\(]+\-(CiA|Anarchy|RemixHD|FTW|Revott|WAF|CtrlHD|Telly|Nif|Line|NPW|Rude|CRisC|SHK|AssAss1ns|Leverage|BBW|NPW))\b/i', $nfo, $matches) && $foundName == "")
				{
					$foundName = $matches[1];
					$methodused = "Nfo 35 river-2";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				if(preg_match('/([\w]+(?:\.|_| )[\w\.\- \'\)\(]+\-(XPD|RHyTM))\b/i', $nfo, $matches) && $foundName == "")
				{
					$foundName = $matches[1];
					$methodused = "Nfo 36 river-3";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				if(preg_match('/(-PROD$|-BOV$|-NMR$|$-HAGGiS|-JUST$|CRNTV$|-MCA$|int$|-DEiTY$|-VoMiT$|-iNCiTE$|-BRUTUS$|-DCN$|-saints$|-sfm$|-lol$|-fov$|-logies$|-c4tv$|-fqm$|-jetset$|-ils$|-miragetv$|-gfvid$|-btl$|-terra$)/i', $rel['searchname']) && $foundName == "")
				{
					$foundName = $rel['searchname'];
					$methodused = "Nfo 37 river-4";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//SANTi releases
				if(preg_match('/\b([\w]+(?:\.|_| )[\w\.\- \']+\-santi)\b/i', $nfo, $matches) && $foundName == "")
				{
					$foundName = $matches[1];
					$methodused = "Nfo 38 SANTi";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//INSPiRAL releases
				if(preg_match('/^([\w]+(?:\.|_| )[\w\.\- ]+ \- INSPiRAL)\s+/im', $nfo, $matches) && $foundName == "")
				{
					$foundName = $matches[1];
					$methodused = "Nfo 39 INSPiRAL";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//CIA releases
				if(preg_match('/Release NAME.*?\:.*?([\w][\w\.\ ]+)\b.*?([\w][\w\.\ ]+\-CIA)\b/is', $nfo, $matches) && $foundName == "")
				{
					$foundName = $matches[1].$matches[2];
					$methodused = "Nfo 40 CIA";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//HDChina releases
				if(preg_match('/HDChina/', $nfo) && $foundName == "")
				{
					if(preg_match('/Disc Title\:.*?\b([\w\ \.\-\(\)]+\-HDChina)/i', $nfo, $matches))
					{
						$foundName = $matches[1];
						$methodused = "Nfo 41 DChina";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
				}
				//Pringles
				if(preg_match('/PRiNGLES/', $nfo) && $foundName == "")
				{
					if(preg_match('/is giving you.*?\b([\w ]+)\s/i', $nfo, $matches))
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
					$methodused = "Nfo 42 Pringles";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Fairlight releases
				if(preg_match('/\/Team FairLight/',$nfo) && $foundName == "")
				{
					$title = null;
					$os = null;
					$method = null;
					if(preg_match('/\b([\w\ \- ()\.]+) \(c\)/i', $nfo, $matches))
					{
						$title = $matches['1'];
						$foundName = $title;
					}
					$foundName = $foundName."-FLT";
					$methodused = "Nfo 43 FairLight";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//CORE releases
				if(preg_match('/Supplied.*?\:.*?(CORE)/',$nfo) || preg_match('/Packaged.*?\:.*?(CORE)/',$nfo) && $foundName == "")
				{
					$title = null;
					$os = null;
					$method = null;
					if(preg_match('/\b([\w\.\-\+\ ]+) \*[\w]+\*/i', $nfo, $matches))
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
					$methodused = "Nfo 44 CORE";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//CompleteRelease
				if(preg_match('/Complete name.+?([\w\.\-\!\*\&\^\%\$\#\@\(\)\[\]\ - \+\=\'\"\:\;\{\}\,\<\>\?]+?\d\w\b[\w\\ \/\.\-\!\*\&\^\%\$\#\@\(\)\[\]\-\+\=\'\"\:\;\{\}\,\<\>\?]+)\b/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = $matches[1];
					$methodused = "Nfo 45 CompleteRelease";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
				}
				//Post-Titel (ghost11)
				if(preg_match('/Post-Titel.+?([\w\.\-\!\*\&\^\%\$\#\@\(\)\[\]\ - \+\=\'\"\:\;\{\}\,\<\>\?]+?\d\w\b[\w\\ \/\.\-\!\*\&\^\%\$\#\@\(\)\[\]\-\+\=\'\"\:\;\{\}\,\<\>\?]+)\b/i',$nfo,$matches) && $foundName == "")
				{
					$foundName = $matches[1];
					$methodused = "Nfo 46 Post-Titel (ghost11 releases)";
					if (determineCategory($rel,$foundName) === true)
					{
						updateCategory($rel,$foundName,$methodused);
					}
					else
					{
						$foundName = null;
					}
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
						$methodused = "Nfo 47 Live Sets";
						if (determineCategory($rel,$foundName) === true)
						{
							updateCategory($rel,$foundName,$methodused);
						}
						else
						{
							$foundName = null;
						}
					}
				}
				//Typical scene regex
				if (preg_match('/(?P<source>Source[\s\.]*?:|fix for nuke)?(?:\s|\]|\[)?(?P<name>[\w\'\-\*\.]+(?:\.|_)[\w\.\-\'&]+\-[\w&]+)(?:\s|\[|\])/i', $nfo, $matches) && $foundName == "")
				{
					if (empty($matches['source']))
					{
						if(!preg_match('/usenet\-space/i',$matches['name']))
						{
							$foundName = $matches['name'];
							$methodused = "Nfo 48 scene";
							if (determineCategory($rel,$foundName) === true)
							{
								updateCategory($rel,$foundName,$methodused);
							}
							else
							{
								$foundName = null;
							}
						}
					}
				}
			}
			if ($foundName == '' && $debug == true)
			{
				echo 'ReleaseID: 		'.$rel['RID']."\n";
				echo ' Group: 		'. $rel['groupname']."\n";
				echo ' Old Name: 		'.$rel['name']."\n";
				echo ' Old SearchName: 	'.$rel['searchname']."\n";
				echo " Status: 		No New Name Found.\n\n";
			}
		}
	}
}
echo $rescount. " releases checked\n";
echo $Nfocount." of ".$rescount." releases had Nfo's processed\n";
echo $Filecount." of ".$rescount." releases had ReleaseFiles processed\n";
echo $updated. " releases Changed\n";
