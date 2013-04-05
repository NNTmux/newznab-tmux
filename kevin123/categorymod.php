<?php
require_once(WWW_DIR."/lib/framework/db.php");

/*
 * This class manages the site wide categories.
 */
class Category2
{
	const CAT_GAME_NDS = 1010;
	const CAT_GAME_PSP = 1020;
	const CAT_GAME_WII = 1030;
	const CAT_GAME_XBOX = 1040;
	const CAT_GAME_XBOX360 = 1050;
	const CAT_GAME_WIIWARE = 1060;
	const CAT_GAME_XBOX360DLC = 1070;
	const CAT_GAME_PS3 = 1080;
	const CAT_MOVIE_FOREIGN = 2010;
	const CAT_MOVIE_OTHER = 2020;
	const CAT_MOVIE_SD = 2030;
	const CAT_MOVIE_HD = 2040;
	const CAT_MOVIE_BLURAY = 2050;
	const CAT_MOVIE_3D = 2060;
	const CAT_MUSIC_MP3 = 3010;
	const CAT_MUSIC_VIDEO = 3020;
	const CAT_MUSIC_AUDIOBOOK = 3030;
	const CAT_MUSIC_LOSSLESS = 3040;
	const CAT_PC_0DAY = 4010;
	const CAT_PC_ISO = 4020;
	const CAT_PC_MAC = 4030;
	const CAT_PC_MOBILEOTHER = 4040;
	const CAT_PC_GAMES = 4050;
	const CAT_PC_MOBILEIOS = 4060;
	const CAT_PC_MOBILEANDROID = 4070;	
	const CAT_TV_FOREIGN = 5020;
	const CAT_TV_SD = 5030;
	const CAT_TV_HD = 5040;
	const CAT_TV_OTHER = 5050;
	const CAT_TV_SPORT = 5060;
	const CAT_TV_ANIME = 5070;
	const CAT_TV_DOCU = 5080;
	const CAT_XXX_DVD = 6010;
	const CAT_XXX_WMV = 6020;
	const CAT_XXX_XVID = 6030;
	const CAT_XXX_X264 = 6040;
	const CAT_XXX_PACK = 6050;
	const CAT_XXX_IMAGESET = 6060;
	const CAT_XXX_OTHER = 6070;
	const CAT_BOOK_MAGS = 7010;
	const CAT_BOOK_EBOOK = 7020;
	const CAT_BOOK_COMICS = 7030;
	
	const CAT_MISC_OTHER = 8010;
	
	const CAT_PARENT_GAME = 1000;
	const CAT_PARENT_MOVIE = 2000;
	const CAT_PARENT_MUSIC = 3000;
	const CAT_PARENT_PC = 4000;
	const CAT_PARENT_TV = 5000;
	const CAT_PARENT_XXX = 6000;
	const CAT_PARENT_BOOK = 7000;
	const CAT_PARENT_MISC = 8000;
	
	const CAT_NOT_DETERMINED =7900;
	
	const STATUS_INACTIVE = 0;
	const STATUS_ACTIVE = 1;

	private $tmpCat = 0;

	/**
	 * Get a list of categories.
	 */
	public function get($activeonly=false, $excludedcats=array())
	{
		$db = new DB();

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and c.ID not in (".implode(",", $excludedcats).")";

		$act = "";
		if ($activeonly)
			$act = sprintf(" where c.status = %d ", Category::STATUS_ACTIVE) ;

		if ($exccatlist != "")
			$act.=$exccatlist;
			
		return $db->query("select c.ID, concat(cp.title, ' > ',c.title) as title, cp.ID as parentID, c.status from category c inner join category cp on cp.ID = c.parentID ".$act." ORDER BY c.ID", true);
	}

	/**
	 * Determine if a category is a parent.
	 */
	public function isParent($cid)
	{
		$db = new DB();
		$ret = $db->queryOneRow(sprintf("select count(*) as count from category where ID = %d and parentID is null", $cid), true);
		if ($ret['count'])
			return true;
		else
			return false;
	}

	/**
	 * Get a list of categories and their parents.
	 */
	public function getFlat($activeonly=false)
	{
		$db = new DB();
		$act = "";
		if ($activeonly)
			$act = sprintf(" where c.status = %d ", Category::STATUS_ACTIVE ) ;
		return $db->query("select c.*, (SELECT title FROM category WHERE ID=c.parentID) AS parentName from category c ".$act." ORDER BY c.ID");
	}

	/**
	 * Get a list of all child categories for a parent.
	 */
	public function getChildren($cid)
	{
		$db = new DB();
		return $db->query(sprintf("select c.* from category c where parentID = %d", $cid), true);
	}

	/**
	 * Get a category row by its ID.
	 */
	public function getById($id)
	{
		$db = new DB();
		return $db->queryOneRow(sprintf("SELECT c.disablepreview, c.ID, c.description, c.minsizetoformrelease, c.maxsizetoformrelease, CONCAT(COALESCE(cp.title,'') , CASE WHEN cp.title IS NULL THEN '' ELSE ' > ' END , c.title) as title, c.status, c.parentID from category c left outer join category cp on cp.ID = c.parentID where c.ID = %d", $id));
	}

    /*
    * Return min/max size range (in array(min, max)) otherwise, none is returned
    * if no size restrictions are set
    */
	public function getSizeRangeById($id)
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("SELECT c.minsizetoformrelease, c.maxsizetoformrelease, cp.minsizetoformrelease as p_minsizetoformrelease, cp.maxsizetoformrelease as p_maxsizetoformrelease".
								" from category c left outer join category cp on cp.ID = c.parentID where c.ID = %d", $id));
		if(!$res)
			return null;

		$min = intval($res['minsizetoformrelease']);
		$max = intval($res['maxsizetoformrelease']);
		if($min == 0 && $max == 0){
			# Size restriction disabled; now check parent
			$min = intval($res['p_minsizetoformrelease']);
			$max = intval($res['p_maxsizetoformrelease']);
			if($min == 0 && $max == 0){
				# no size restriction
				return null;
			}
			else if($max > 0)
			{
				$min = 0;
				$max = intval($res['p_maxsizetoformrelease']);
			}
			else
			{
				$min = intval($res['p_minsizetoformrelease']);
				$max = PHP_INT_MAX;
			}
		}
		else if($max > 0)
		{
			$min = 0;
			$max = intval($res['maxsizetoformrelease']);
		}
		else
		{
			$min = intval($res['minsizetoformrelease']);
			$max = PHP_INT_MAX;
		}

		# If code reaches here, then content is enabled
		return array('min'=>$min, 'max'=>$max);
	}

	/**
	 * Get a list of categories by an array of IDs.
	 */
	public function getByIds($ids)
	{
		$db = new DB();
		return $db->query(sprintf("SELECT concat(cp.title, ' > ',c.title) as title from category c inner join category cp on cp.ID = c.parentID where c.ID in (%s)", implode(',', $ids)));
	}

	/**
	 * Update a category.
	 */
	public function update($id, $status, $desc, $disablepreview, $minsize, $maxsize)
	{
		$db = new DB();
		return $db->query(sprintf("update category set disablepreview = %d, status = %d, minsizetoformrelease = %d, maxsizetoformrelease = %d, description = %s where ID = %d", $disablepreview, $status, $minsize, $maxsize, $db->escapeString($desc), $id));
	}

	/**
	 * Get the categories in a format for use by the headermenu.tpl.
	 */
	public function getForMenu($excludedcats=array())
	{
		$db = new DB();
		$ret = array();

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and ID not in (".implode(",", $excludedcats).")";

		$arr = $db->query(sprintf("select * from category where status = %d %s", Category::STATUS_ACTIVE, $exccatlist), true);
		foreach ($arr as $a)
			if ($a["parentID"] == "")
				$ret[] = $a;

		foreach ($ret as $key => $parent)
		{
			$subcatlist = array();
			$subcatnames = array();
			foreach ($arr as $a)
			{
				if ($a["parentID"] == $parent["ID"])
				{
					$subcatlist[] = $a;
					$subcatnames[] = $a["title"];
				}
			}

			if (count($subcatlist) > 0)
			{
				array_multisort($subcatnames, SORT_ASC, $subcatlist);
				$ret[$key]["subcatlist"] = $subcatlist;
			}
			else
			{
				unset($ret[$key]);
			}
		}
		return $ret;
	}

	/**
	 * Return a list of categories for use in a dropdown.
	 */
	public function getForSelect($blnIncludeNoneSelected = true)
	{
		$categories = $this->get();
		$temp_array = array();

		if ($blnIncludeNoneSelected)
		{
			$temp_array[-1] = "--Please Select--";
		}

		foreach($categories as $category)
			$temp_array[$category["ID"]] = $category["title"];

		return $temp_array;
	}

	/**
	 * Work out which category is applicable for either a group or a binary.
	 * Returns -1 if no category is appropriate from the group name.
	 */
	function determineCategory($group, $releasename = "")
	{
		//
		// Try and determine based on group - First Pass
		//

	    if (preg_match('/alt\.binaries\.0day/i', $group))
		{
			if($this->isPC($releasename)){ return $this->tmpCat; }
			return Category::CAT_PC_0DAY;
		}

		if (preg_match('/alt\.binaries\.ath/i', $group))
		{
		    if($this->isXXX($releasename)){ return $this->tmpCat; }
			if($this->isConsole($releasename)){ return $this->tmpCat; }
			if($this->isPC($releasename)){ return $this->tmpCat; }
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
			if($this->isMusic($releasename)){ return $this->tmpCat; }
			return Category::CAT_MISC_OTHER;
		}
			
		if (preg_match('/alt\.binaries\.b4e/', $group))
		{
			if($this->isPC($releasename)){ return $this->tmpCat; }
			if($this->isBook($releasename)){ return $this->tmpCat; }                                
		}                                  
		                                  
		if (preg_match('/alt\.binaries\..*?audiobook.*?/i', $group))
			return Category::CAT_MUSIC_AUDIOBOOK;
				
		if (preg_match('/lossless|flac/i', $group))
		{
			return Category::CAT_MUSIC_LOSSLESS;
		} 
		
		if (preg_match('/alt\.binaries\.sounds.*?|alt\.binaries\.mp3.*?|alt\.binaries.*?\.mp3/i', $group))
		{
			if($this->isMusic($releasename)){ return $this->tmpCat; }
			return Category::CAT_MISC_OTHER;
		}
		
		if (preg_match('/alt\.binaries\.console.ps3/i', $group))
		{
			if($this->isConsole($releasename)){ return $this->tmpCat; }
			return Category::CAT_GAME_PS3;
		}
		
		if (preg_match('/alt\.binaries\.games\.xbox*/i', $group))
		{
            if($this->isConsole($releasename)){ return $this->tmpCat; }
            if($this->isXXX($releasename)){ return $this->tmpCat; }
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
		}
		
		if (preg_match('/alt\.binaries\.games$/i', $group))
		{
			if($this->isConsole($releasename)){ return $this->tmpCat; }
			return Category::CAT_PC_GAMES;
		}
		
		if (preg_match('/alt\.binaries\.games\.wii/i', $group))
		{
			if($this->isConsole($releasename)) { return $this->tmpCat; }
		}                            
		if (preg_match('/alt\.binaries\.dvd.*?/i', $group))
		{
			if($this->isBook($releasename)){ return $this->tmpCat; }
			if($this->isPC($releasename)){ return $this->tmpCat; }		
			if($this->isXxx($releasename)){ return $this->tmpCat; }     
			if($this->isTv($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
		}
		if (preg_match('/alt\.binaries\.hdtv*|alt\.binaries\.x264|alt\.binaries\.tv$/i', $group))
		{
			if($this->isMusicVideo($releasename)){ return $this->tmpCat; }			
			if($this->isXXX($releasename)){ return $this->tmpCat; }
			if($this->isTv($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
		}
		if (preg_match('/alt\.binaries\.nospam\.cheerleaders/i', $group))
		{
			if($this->isMusicVideo($releasename)){ return $this->tmpCat; }			
			if($this->isXXX($releasename)){ return $this->tmpCat; }
			if($this->isTv($releasename)){ return $this->tmpCat; }
			if($this->isPC($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
		}
		
		if (preg_match('/alt\.binaries\.classic\.tv.*?/i', $group))
		{
			if($this->isTv($releasename)){ return $this->tmpCat; }
			return Category::CAT_TV_OTHER;
		}
		
		if (preg_match('/alt\.binaries\.multimedia\.anime(\.highspeed)?/i', $group))
		{
			return Category::CAT_TV_ANIME;
		}
			
		if (preg_match('/alt\.binaries\.anime/i', $group))
		{
			return Category::CAT_TV_ANIME;
		}
			
		if (preg_match('/alt\.binaries\.e(-|)book*?/i', $group))
		{
			if($this->isBook($releasename)){ return $this->tmpCat; }
			return Category::CAT_BOOK_EBOOK;
		}
			
		if (preg_match('/alt\.binaries\.comics.*?/i', $group))
		{
			return Category::CAT_BOOK_COMICS;
		}
			
		if (preg_match('/alt\.binaries\.cores.*?/i', $group))
		{
			if($this->isBook($releasename)){ return $this->tmpCat; }
			if($this->isXXX($releasename)){ return $this->tmpCat; }
			if($this->isConsole($releasename)){ return $this->tmpCat; }	
			if($this->isPC($releasename)){ return $this->tmpCat; }
			if($this->isMusic($releasename)){ return $this->tmpCat; }
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
			return Category::CAT_MISC_OTHER;
		}   
		
		if (preg_match('/alt\.binaries\.lou/i', $group))
		{
			if($this->isBook($releasename)){ return $this->tmpCat; }
			if($this->isXXX($releasename)){ return $this->tmpCat; }
			if($this->isConsole($releasename)){ return $this->tmpCat; }
			if($this->isPC($releasename)){ return $this->tmpCat; }
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
			if($this->isMusic($releasename)){ return $this->tmpCat; }
			return Category::CAT_MISC_OTHER;
		}

		if (preg_match('/alt\.binaries\.cd.image|alt\.binaries\.audio\.warez/i', $group))
		{
			if($this->isXXX($releasename)){ return $this->tmpCat; }
			if($this->isPC($releasename)){ return $this->tmpCat; }
			return Category::CAT_PC_0DAY;
		}     
		
        if (preg_match('/alt\.binaries\.pro\-wrestling/i', $group))
        {
			return Category::CAT_TV_SPORT;
		}

		if (preg_match('/alt\.binaries\.sony\.psp/i', $group))
		{
			return Category::CAT_GAME_PSP;
		}
				
		if (preg_match('/alt\.binaries\.nintendo\.ds|alt\.binaries\.games\.nintendods/i', $group))
		{
			return Category::CAT_GAME_NDS;
		}
				
		if (preg_match('/alt\.binaries\.mpeg\.video\.music/i', $group))
		{
			return Category::CAT_MUSIC_VIDEO;
		}
				
		if (preg_match('/alt\.binaries\.mac/i', $group))
		{
			return Category::CAT_PC_MAC;
		}
			
		if (preg_match('/linux/i', $group))
		{
			return Category::CAT_PC_ISO;
		}

		if (preg_match('/alt\.binaries\.illuminaten/i', $group))
        {
			if($this->isPC($releasename)){ return $this->tmpCat; }
            if($this->isXXX($releasename)){ return $this->tmpCat; }
            if($this->isMusic($releasename)){ return $this->tmpCat; }
            if($this->isConsole($releasename)){ return $this->tmpCat; }
            if($this->isTV($releasename)){ return $this->tmpCat; }
            if($this->isMovie($releasename)){ return $this->tmpCat; }
            return Category::CAT_MISC_OTHER;
        }
		
		if (preg_match('/alt\.binaries\.ipod\.videos\.tvshows/i', $group))
		{
			return Category::CAT_TV_OTHER;
		}
			
		if (preg_match('/alt\.binaries\.documentaries/i', $group))
		{
			if($this->isXxx($releasename)){ return $this->tmpCat; }     
			if($this->isDocuTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
			return Category::CAT_MISC_OTHER;
		}
		
		if (preg_match('/alt\.binaries\.drummers/i', $group))
		{
			if($this->isBookEbook($releasename)){ return $this->tmpCat; }
			if($this->isXxx($releasename)){ return $this->tmpCat; }
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
		}

		if (preg_match('/alt\.binaries\.tv\.swedish/i', $group))
        {
		    if($this->isForeignTV($releasename)){ return $this->tmpCat; }
            return Category::CAT_MISC_OTHER;
		}
			
		if (preg_match('/alt\.binaries\.tv\.deutsch/i', $group))
		{
			if($this->isForeignTV($releasename)){ return $this->tmpCat; }
            return Category::CAT_MISC_OTHER;
		}

		if (preg_match('/alt\.binaries\.erotica\.divx/i', $group))
		{
			if($this->isXXX($releasename)){ return $this->tmpCat; }
			return Category::CAT_XXX_OTHER; 
		}
			
		if (preg_match('/alt\.binaries\.ghosts/i', $group))	
		{
		    if($this->isBook($releasename)){ return $this->tmpCat; }
			if($this->isXXX($releasename)){ return $this->tmpCat; }
			if($this->isPC($releasename)){ return $this->tmpCat; }
			if($this->isMusic($releasename)){ return $this->tmpCat; }
			if($this->isConsole($releasename)){ return $this->tmpCat; }
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
		}

		if (preg_match('/alt\.binaries\.mom/i', $group))
		{
		    if($this->isBook($releasename)){ return $this->tmpCat; }
			if($this->isXXX($releasename)){ return $this->tmpCat; }
			if($this->isPC($releasename)){ return $this->tmpCat; }
		    if($this->isMusic($releasename)){ return $this->tmpCat; }
			if($this->isConsole($releasename)){ return $this->tmpCat; }
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }

			return Category::CAT_MISC_OTHER;
		}  
		
		if (preg_match('/alt\.binaries\.mma|alt\.binaries\.multimedia\.sports.*?/i', $group))
		{
			return Category::CAT_TV_SPORT;
		}
			
		if (preg_match('/alt\.binaries\.b4e$/i', $group))
		{
			if($this->isPC($releasename)){ return $this->tmpCat; }
		}
			 
		if (preg_match('/alt\.binaries\.warez\.smartphone/i', $group))
		{
			if($this->isPC($releasename)){ return $this->tmpCat; }  
		}
			
		if (preg_match('/alt\.binaries\.warez\.ibm\-pc\.0\-day|alt\.binaries\.warez/i', $group))
		{
 			if($this->isConsole($releasename)){ return $this->tmpCat; }
			if($this->isBook($releasename)){ return $this->tmpCat; } 
			if($this->isXxx($releasename)){ return $this->tmpCat; }                           
			if($this->isMusic($releasename)){ return $this->tmpCat; }
			if($this->isPC($releasename)){ return $this->tmpCat; }
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
			return Category::CAT_PC_0DAY;
		}
			
		if (preg_match('/erotica|ijsklontje|kleverig/i', $group))
		{
			if($this->isXxx($releasename)){ return $this->tmpCat; }
			return Category::CAT_XXX_OTHER;
		}
		
		if (preg_match('/french/i', $group))
		{
			if($this->isXxx($releasename)){ return $this->tmpCat; }
			if($this->isTV($releasename)){ return $this->tmpCat; }
			return Category::CAT_MOVIE_FOREIGN;
		}
		
		if (preg_match('/alt\.binaries\.movies\.xvid|alt\.binaries\.movies\.divx|alt\.binaries\.movies/i', $group))
		{
			if($this->isBook($releasename)){ return $this->tmpCat; }
			if($this->isConsole($releasename)){ return $this->tmpCat; }
			if($this->isXxx($releasename)){ return $this->tmpCat; }  
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
			if($this->isPC($releasename)){ return $this->tmpCat; }
			return Category::CAT_MISC_OTHER;
		}
		
		if (preg_match('/wmvhd/i', $group))
		{
			if($this->isXxx($releasename)){ return $this->tmpCat; }  
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
		}
		
		if (preg_match('/inner\-sanctum/i', $group))
		{
			if($this->isPC($releasename)){ return $this->tmpCat; }
			if($this->isBook($releasename)){ return $this->tmpCat; }
			if($this->isMusic($releasename)){ return $this->tmpCat; }
			if($this->isTV($releasename)){ return $this->tmpCat; }
			return Category::CAT_MISC_OTHER;
		}	

		if (preg_match('/alt\.binaries\.worms/i', $group))
		{
			if($this->isXxx($releasename)){ return $this->tmpCat; }  
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMusicVideo($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
		} 		

		if (preg_match('/alt\.binaries\.x264/i', $group))
		{
			if($this->isXxx($releasename)){ return $this->tmpCat; }  
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
			return Category::CAT_MOVIE_OTHER;
		} 

		if (preg_match('/dk\.binaer\.ebooks/i', $group))
		{
			if($this->isBookEbook($releasename)){ return $this->tmpCat; }  
			return Category::CAT_BOOK_EBOOK;
		}
		
		if (preg_match('/dk\.binaer\.film/i', $group))
		{
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
			return Category::CAT_MISC_OTHER;
		}
		
		if (preg_match('/dk\.binaer\.musik/i', $group))
		{
			if($this->isMusic($releasename)){ return $this->tmpCat; }
			return Category::CAT_MISC_OTHER;				
		}
		
		if (preg_match('/alt\.binaries\.(teevee|multimedia|tv|tvseries).*?/i', $group))
		{
			if($this->isXxx($releasename)){ return $this->tmpCat; }
			if($this->isConsole($releasename)){ return $this->tmpCat; }
			if($this->isMusic($releasename)){ return $this->tmpCat; }
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isPC($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
			if($this->isSportTV($releasename)){ return $this->tmpCat; }
			if($this->isBook($releasename)){ return $this->tmpCat; }
			return Category::CAT_MISC_OTHER;
		}		

		//
		// if a category hasnt been set yet, then try against all 
		// functions and if still nothing, return Cat Misc.
		//
			if($this->isXXX($releasename)){ return $this->tmpCat; }
			if($this->isBook($releasename)){ return $this->tmpCat; }			
			if($this->isPC($releasename)){ return $this->tmpCat; }                            	
			if($this->isConsole($releasename)){ return $this->tmpCat; }
			if($this->isMusic($releasename)){ return $this->tmpCat; }
			if($this->isTV($releasename)){ return $this->tmpCat; }
			if($this->isMovie($releasename)){ return $this->tmpCat; }
			return Category::CAT_MISC_OTHER;
	}

	//
	// Beginning of functions to determine category by release name
	//

	/**
	* Work out if a release is Hashed/Encrypted/Etc
	*/
	
	public function isHashed($releasename)
	{
		if(!preg_match('/( |\.|\-)/i', $releasename) && preg_match('/^[a-z0-9]+$/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MISC_OTHER;
			return true;
		}	
	}
	/**
	 * Work out if a release is TV
	 */	
	public function isTV($releasename)
	{
		//echo "tv";
		if($this->isHashed($releasename)){ return true; }
		if(preg_match('/(((s\d{1,2}(\.|_|\-)?(b|d|e)\d{1,2})|\d{1,2}x\d{2}|ep(_|\-)?\d{2})[\. _-]+)|(dsr|pdtv|hdtv)[\.\-_]/i', $releasename))
		{
			//echo "tv1";
			if($this->isForeignTV($releasename)){ return true; }
			if($this->isSportTV($releasename)){ return true; }
			if($this->isDocuTV($releasename)){ return true; }
			if($this->isHDTV($releasename)){ return true; }
			if($this->isSDTV($releasename)){ return true; }
			$this->tmpCat = Category::CAT_TV_OTHER;
			return true;
		}
		else if (preg_match('/( S\d{1,2} |\.S\d{2}\.|\.S\d{2}|s\d{1,2}e\d{1,2}|(\.| |\b|\-)EP\d{1,2}\.|\.E\d{1,2}\.|special.*?HDTV|HDTV.*?special|PDTV|\.\d{3}\.DVDrip|History( |\.|\-)Channel|trollhd|trollsd|HDTV.*?BTL|C4TV|WEB DL|web\.dl|WWE|season \d{1,2}|\.TV\.|\.dtv\.|UFC|TNA|staffel|episode|special\.\d{4})/i', $releasename))
		{
			//echo "tv2";
			if($this->isForeignTV($releasename)){ return true; }
			if($this->isSportTV($releasename)){ return true; }
			if($this->isDocuTV($releasename)){ return true; }
			if($this->isHDTV($releasename)){ return true; }
			if($this->isSDTV($releasename)){ return true; }
			$this->tmpCat = Category::CAT_TV_OTHER;
			return true;
		}
		else if (preg_match('/seizoen/i', $releasename))
		{
			if($this->isForeignTV($releasename)){ return true; }
		}
		return false;
	}

	/**
	 * Work out if a release is Foreign TV
	 */	
	public function isForeignTV($releasename)
	{
		
		if(preg_match('/(seizoen|staffel|danish|flemish|(\.| |\b|\-)(HU|NZ)|dutch|Deutsch|nl\.?subbed|nl\.?sub|\.NL|\.ITA|norwegian|swedish|swesub|french|german|spanish)[\.\- \b]/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_FOREIGN;
			return true;
		}
		else if(preg_match('/\.des\.(?!moines)|Chinese\.Subbed|vostfr|Hebrew\.Dubbed|\.HEB\.|Nordic|Hebdub|NLSubs|NL\-Subs|NLSub|Deutsch| der |German | NL |staffel|videomann/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_FOREIGN;
			return true;
		}
		else if(preg_match('/(danish|flemish|nlvlaams|dutch|nl\.?sub|swedish|swesub|icelandic|finnish|french|truefrench[\.\- ](?:.dtv|dvd|br|bluray|720p|1080p|LD|dvdrip|internal|r5|bdrip|sub|cd\d|dts|dvdr)|german|nl\.?subbed|deutsch|espanol|SLOSiNH|VOSTFR|norwegian|[\.\- ]pl|pldub|norsub|[\.\- ]ITA)[\.\- ]/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_FOREIGN;
			return true;
		}
		else if(preg_match('/(french|german)$/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_FOREIGN;
			return true;
		}
		return false;
	}
	/**
	 * Work out if a release is Sport TV
	 */	
	public function isSportTV($releasename)
	{
		if(preg_match('/(f1\.legends|epl|motogp|bellator|strikeforce|the\.ultimate\.fighter|supercup|wtcc|red\.bull.*?race|tour\.de\.france|bundesliga|la\.liga|uefa|EPL|ESPN|WWE\.|WWF\.|WCW\.|MMA\.|UFC\.|(^|[\. ])FIA\.|PGA\.|NFL\.|NCAA\.)/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_SPORT;
			return true;
		}
		else if(preg_match('/Twenty20|IIHF|wimbledon|Kentucky\.Derby|WBA|Rugby\.|TNA\.|DTM\.|NASCAR|SBK|NBA(\.| )|NHL\.|NRL\.|MLB\.|Playoffs|FIFA\.|Serie.A|netball\.anz|formula1|indycar|Superleague|V8\.Supercars|((19|20)\d{2}.*?olympics?|olympics?.*?(19|20)\d{2})|x(\ |\.|\-)games/i', $releasename))
		{	
			$this->tmpCat = Category::CAT_TV_SPORT;
			return true;
		}
		else if(preg_match('/(\b|\_|\.| )(Daegu|AFL|La.Vuelta|BMX|Gymnastics|IIHF|NBL|FINA|Drag.Boat|HDNET.Fights|Horse.Racing|WWF|World.Championships|Tor.De.France|Le.Triomphe|Legends.Of.Wrestling)(\b|\_|\.| )/i', $releasename))
		{	
			$this->tmpCat = Category::CAT_TV_SPORT;
			return true;
		}
		else if(preg_match('/(\b|\_|\.| )(Fighting.Championship|tour.de.france|Boxing|Cycling|world.series|Formula.Renault|FA.Cup|WRC|GP3|WCW|Road.Racing|AMA|MFC|Grand.Prix|Basketball|MLS|Wrestling|World.Cup)(\b|\_|\.| )/i', $releasename))
		{	
			$this->tmpCat = Category::CAT_TV_SPORT;
			return true;
		}
		else if(preg_match('/(\b|\_|\.| )(Swimming.*?Men|Swimming.*?Women|swimming.*?champion|WEC|World.GP|CFB|Rally.Challenge|Golf|Supercross|WCK|Darts|SPL|Snooker|League Cup|Ligue1|Ligue)(\b|\_|\.| )/i', $releasename))
		{	
			
			$this->tmpCat = Category::CAT_TV_SPORT;
			return true;
		}
		else if(preg_match('/(\b|\_|\.| )(Copa.del.rey|League.Cup|Carling.Cup|Cricket|The.Championship|World.Max|KNVB|GP2|Soccer|PGR3|Cage.Contender|US.Open|CFL|Weightlifting|New.Delhi|Euro|WBC)(\b|\_|\.| )/i', $releasename))
		{	
			$this->tmpCat = Category::CAT_TV_SPORT;
			return true;
		}
		else if(preg_match('/(\b|\_|\.| )(NCW(T|Y)S|NNS|NSCS?)(\b|\_|\.| )/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_SPORT;
			return true;
		}
		else if(preg_match('/^london(\.| )2012/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_SPORT;
			return true;
		}

		return false;
	}

	/**
	 * Work out if a release is Documentary TV
	 */	
	public function isDocuTV($releasename)
	{
		if (preg_match('/\-DOCUMENT/', $releasename))  //The DOCUMENT posting group does not actually do Documentary's
		{
			return false;
		}
		else if (preg_match('/(?!.*?S\d{2}.*?)(?!.*?EP?\d{2}.*?)(48\.Hours\.Mystery|Discovery.Channel|BBC|History.Channel|National.Geographic|Nat Geo|Shark.Week)/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_DOCU;
			return true;
		}
		else if(preg_match('/(?!.*?S\d{2}.*?)(?!.*?EP?\d{2}.*?)((\b|_)(docu|BBC|document|a.and.e|National.geographic|Discovery.Channel|History.Channel|Travel.Channel|Science.Channel|Biography|Modern.Marvels|Inside.story|Hollywood.story|E.True|Documentary)(\b|_))/i', $releasename))		
		{
			$this->tmpCat = Category::CAT_TV_DOCU;
			return true;
		}
		else if(preg_match('/(?!.*?S\d{2}.*?)(?!.*?EP?\d{2}.*?)((\b|_)(Science.Channel|National.geographi|History.Chanel|Colossal|Discovery.travel|Planet.Science|Animal.Planet|Discovery.Sci|Regents|Discovery.World|Discovery.truth|Discovery.body|Dispatches|Biography|The.Investigator|Private.Life|Footballs.Greatest|Most.Terrifying)(\b|_))/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_DOCU;
			return true;
		}

		return false;
	}
	
	/**
	 * Work out if a release is HD TV
	 */	
	public function isHDTV($releasename)
	{
		if (preg_match('/1080|720/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_HD;
			return true;
		}
		else if (preg_match('/(\.720p-yestv|-\d{3}-720p|[\w\.\-]+s\d{2}e\d{2}(\.|\-|\_)(720|1080)p(\.|\-|\_)hdtv(\.|\-|\_)(h|x)264[\w\.\-]+)/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_HD;
			return true;
		}
		
		return false;
	}

	/**
	 * Work out if a release is SD TV
	 */	
	public function isSDTV($releasename)
	{
		if (preg_match('/(SDTV|HDTV|XVID|DIVX|PDTV|WEBDL|DVDR|DVD-RIP|WEB-DL|x264|dvd)/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_SD;
			return true;
		}
		else if (preg_match('/(\.s\d{2}e\d{2}\.avi|[\w\.\-]+s\d{2}e\d{2}(\.|\-|\_)dvdrip(\.|\-|\_)xvid[\w\.\-]+)/i', $releasename))
		{
			$this->tmpCat = Category::CAT_TV_SD;
			return true;
		}
		
		return false;
	}


	/**
	 * Work out if a release is a Movie
	 */	
	public function isMovie($releasename)
	{
		if($this->isHashed($releasename)){ return true; }
		if($this->isMovieForeign($releasename)){ return true; }
		if($this->isMovieSD($releasename)){ return true; }
		if($this->isMovie3D($releasename)){ return true; }		
		if($this->isMovieHD($releasename)){ return true; }
		//if($this->isMovieSD($releasename)){ return true; }
		if($this->isMovieBluRay($releasename)){ return true; }
		if (preg_match('/xvid/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MOVIE_OTHER;
			return true;
		}
		return false;
	}

	/**
	 * Work out if a release is a Foreign Movie
	 */	
	public function isMovieForeign($releasename)
	{
		if(preg_match('/(\.des\.|danish|flemish|dutch|(\.| |\b|\-)(HU|FINA)|Deutsch|nl\.?subbed|nl\.?sub|\.NL|\.ITA|norwegian|swedish|swesub|french|german|spanish)[\.\- |\b]/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MOVIE_FOREIGN;
			return true;
		}
		else if(preg_match('/Chinese\.Subbed|vostfr|Hebrew\.Dubbed|\.Heb\.|Hebdub|NLSubs|NL\-Subs|NLSub|Deutsch| der |German| NL |turkish/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MOVIE_FOREIGN;
			return true;
		}
		else if (preg_match('/(danish|flemish|nlvlaams|dutch|nl\.?sub|swedish|swesub|icelandic|finnish|french|truefrench[\.\- ](?:dvd|br|bluray|720p|1080p|LD|dvdrip|internal|r5|bdrip|sub|cd\d|dts|dvdr)|german|nl\.?subbed|deutsch|espanol|SLOSiNH|VOSTFR|norwegian|[\.\- ]pl|pldub|norsub|[\.\- ]ITA)[\.\- ]/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MOVIE_FOREIGN;
			return true;
		}
		return false;
	}

	/**
	 * Work out if a release is a SD Movie
	 */	
	public function isMovieSD($releasename)
	{
		if(preg_match('/(dvdscr|extrascene|dvdrip|\.CAM|dvdr|dvd9|dvd5|[\.\-\ ]ts)[\.\-\ ]/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MOVIE_SD;
			return true;
		}	
		else if(preg_match('/(divx|xvid|(\.| )r5(\.| ))/i', $releasename) && !preg_match('/(720|1080)/i', $releasename)) 
		{
			$this->tmpCat = Category::CAT_MOVIE_SD;
			return true;
		}
		return false;
	}

	/**
	 * Work out if a release is a 3D Movie
	 */	
	public function isMovie3D($releasename)
	{
		if(preg_match('/3D/i', $releasename) && preg_match('/[\-\. _](H?SBS|OU)([\-\. _]|$)/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MOVIE_3D;
			return true;
		}
		
		return false;
	}

	/**
	 * Work out if a release is a HD Movie
	 */	
	public function isMovieHD($releasename)
	{
		if(preg_match('/x264|wmvhd|web\-dl|XvidHD|BRRIP|HDRIP|HDDVD|bddvd|BDRIP|webscr/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MOVIE_HD;
			return true;
		}
		
		return false;
	}
	
	/**
	 * Work out if a release is a Bluray Movie
	 */	
	public function isMovieBluRay($releasename)
	{
		if(preg_match('/bluray|bd?25|bd?50|blu-ray|VC1|VC\-1|AVC|BDREMUX/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MOVIE_BLURAY;
			return true;
		}
		
		return false;
	}

	/**
	 * Work out if a release is PC App
	 */	
	public function isPC($releasename)
	{
		if($this->isHashed($releasename)){ return true; }
		if($this->isMobileAndroid($releasename)){ return true; }
		if($this->isMobileiOS($releasename)){ return true; }
		if($this->isMobileOther($releasename)){ return true; }
		if($this->isISO($releasename)){ return true; }
		if($this->isMac($releasename)){ return true; }
		if($this->isPCGame($releasename)){ return true; }
		if($this->is0day($releasename)){ return true; }
		return false;
	}

	/**
	 * Work out if a release is Mobile Android App
	 */	
	public function isMobileAndroid($releasename)
	{
		if (preg_match('/Android/i', $releasename))
		{
			$this->tmpCat = Category::CAT_PC_MOBILEANDROID;
			return true;
		}
		return false;
	}
	
	/**
	 * Work out if a release is Mobile iOS App
	 */	
	public function isMobileiOS($releasename)
	{
		if (preg_match('/(?!.*?Winall.*?)(IPHONE|ITOUCH|IPAD|Ipod)/i', $releasename))
		{
			$this->tmpCat = Category::CAT_PC_MOBILEIOS;
			return true;
		}
		return false;
	}
	
	/**
	 * Work out if a release is Mobile Other App
	 */	
	public function isMobileOther($releasename)
	{
		if (preg_match('/COREPDA|symbian|xscale|wm5|wm6|J2ME/i', $releasename))
		{
			$this->tmpCat = Category::CAT_PC_MOBILEOTHER;
			return true;
		}
		return false;
	}
	
	/**
	 * Work out if a release is 0day App
	 */	
	public function is0day($releasename)
	{
		if(preg_match('/DVDRIP|XVID.*?AC3|DIVX\-GERMAN/i', $releasename))
		{
			return false;
		}

		if(preg_match('/[\.\-_ ](x32|x64|x86|win64|winnt|win9x|win2k|winxp|winnt2k2003serv|win9xnt|win9xme|winnt2kxp|win2kxp|win2kxp2k3|keygen|regged|keymaker|winall|win32|template|Patch|GAMEGUiDE|unix|irix|solaris|freebsd|hpux|linux|windows|multilingual|software|Pro v\d{1,3})[\.\-_ ]/i', $releasename))
		{
			$this->tmpCat = Category::CAT_PC_0DAY;
			return true;
		}
		else if (preg_match('/(?!MDVDR).*?\-Walmart|PHP|\-SUNiSO|\.Portable\.|Adobe|CYGNUS|GERMAN\-|v\d{1,3}.*?Pro|MULTiLANGUAGE|Cracked|lz0|\-BEAN|MultiOS|\-iNViSiBLE|\-SPYRAL|WinAll|Keymaker|Keygen|Lynda\.com|FOSI|Keyfilemaker|DIGERATI|\-UNION|\-DOA|Laxity/i', $releasename))
		{
			$this->tmpCat = Category::CAT_PC_0DAY;
			return true;
		}
		else if (preg_match('/Zero-G[\w\.\-]+(-ASSiGN|_SE)/i', $releasename))
		{
			$this->tmpCat = Category::CAT_PC_0DAY;
			return true;
		}
		return false;
	}

	/**
	 * Work out if a release is Mac App
	 */	
	public function isMac($releasename)
	{
		if(preg_match('/osx|os\.x|\.mac\.|MacOSX/i', $releasename))
		{
			$this->tmpCat = Category::CAT_PC_MAC;
			return true;
		}
		return false;
	}

	/**
	 * Work out if a release is ISO App
	 */	
	public function isISO($releasename)
	{
		if(preg_match('/\-DYNAMiCS/', $releasename))
		{
			$this->tmpCat = Category::CAT_PC_ISO;
			return true;
		}
		return false;
	}
	
	/**
	 * Work out if a release is PC Game
	 */	
	public function isPCGame($releasename)
	{
		if (preg_match('/\-Heist|\-RELOADED|\.GAME\-|\-SKIDROW|PC GAME|FASDOX|v\d{1,3}.*?\-TE|RIP\-unleashed|Razor1911/i', $releasename))
		{
			$this->tmpCat = Category::CAT_PC_GAMES;
			return true;
		}
		return false;
	}


	/**
	 * Work out if a release is XXX
	 */	
	public function isXxx($releasename)
	{
		if($this->isHashed($releasename)){ return true; }
		if(preg_match('/(\.JAV\.| JAV |\.Jav\.|Girls.*?Gone.*?Wild|\-MotTto|-Nukleotide|XXX|PORNOLATiON|SWE6RUS|swe6|SWE6|NYMPHO|DETOXATiON|DivXfacTory|TESORO|STARLETS|xxx|XxX|PORNORIP|PornoRip)/', $releasename))
		{
			if($this->isXxxDVD($releasename)){ return true; }
			if($this->isXxxImageset($releasename)){ return true; }
			if($this->isXxxPack($releasename)){ return true; }
			if($this->isXxxWMV($releasename)){ return true; }
			if($this->isXxx264($releasename)){ return true; }
			if($this->isXxxXvid($releasename)){ return true; }
			$this->tmpCat = Category::CAT_XXX_XVID;
			return true;
		}
		else if(preg_match('/^Penthouse/i', $releasename))
		{
			if($this->isXxxDVD($releasename)){ return true; }
			if($this->isXxxImageset($releasename)){ return true; }
			if($this->isXxxPack($releasename)){ return true; }
			if($this->isXxxWMV($releasename)){ return true; }
			if($this->isXxx264($releasename)){ return true; }
			if($this->isXxxXvid($releasename)){ return true; }
			$this->tmpCat = Category::CAT_XXX_XVID;
			return true;
		}

		return false;
	}

	/**
	 * Work out if a release is HD XXX
	 */	
	public function isXxx264($releasename)
	{
		if (preg_match('/x264|720|1080/i', $releasename))
		{
			$this->tmpCat = Category::CAT_XXX_X264;
			return true;
		}
		return false;
	}

	/**
	 * Work out if a release is SD XXX
	 */	
	public function isXxxXvid($releasename)
	{
		if (preg_match('/xvid|dvdrip|bdrip|brrip|pornolation|swe6|nympho|detoxication|tesoro|mp4/i', $releasename))
		{
			$this->tmpCat = Category::CAT_XXX_XVID;
			return true;
		}
		
		return false;
	}

	/**
	 * Work out if a release is Other XXX
	 */	
	public function isXxxWMV($releasename)
	{
		if (preg_match('/wmv|f4v|flv|mov(?!ie)|mpeg|isom|realmedia|multiformat/i', $releasename))
		{
			$this->tmpCat = Category::CAT_XXX_WMV;
			return true;
		}
		
		return false;
	}

	/**
	 * Work out if a release is XXX DVDR
	 */	
	public function isXxxDVD($releasename)
	{
		if (preg_match('/dvdr[^ip]|dvd5|dvd9/i', $releasename))
		{
			$this->tmpCat = Category::CAT_XXX_DVD;
			return true;
		}
		
		return false;
	}
	
	/**
	 * Work out if a release is XXX Pack
	 */	
	public function isXxxPack($releasename)
	{
		if (preg_match('/[\._](pack)[\.\-_]/i', $releasename))
		{
			$this->tmpCat = Category::CAT_XXX_PACK;
			return true;
		}
		return false;
	}

	/**
	 * Work out if a release is XXX ImageSet
	 */	
	public function isXxxImageset($releasename)
	{
		if (preg_match('/imageset/i', $releasename))
		{
			$this->tmpCat = Category::CAT_XXX_IMAGESET;
			return true;
		}
		return false;
	}
	
	/**
	 * Work out if a release is Console App
	 */	
	public function isConsole($releasename)
	{
		if($this->isHashed($releasename)){ return true; }
		if($this->isGameNDS($releasename)){return true;}
		if($this->isGamePS3($releasename)){ return true; }
		if($this->isGamePSP($releasename)){ return true; }
		if($this->isGameWiiWare($releasename)){ return true; }
		if($this->isGameWii($releasename)){ return true; }
		if($this->isGameXBOX360DLC($releasename)){ return true; }
		if($this->isGameXBOX360($releasename)){ return true; }
		if($this->isGameXBOX($releasename)){ return true; }
		
		return false;
	}

	/**
	 * Work out if a release is NDS App
	 */	
	public function isGameNDS($releasename)
	{
		if (preg_match('/(\b|\-| |\.)(3DS|NDS)(\b|\-| |\.)/i', $releasename))
		{
			$this->tmpCat = Category::CAT_GAME_NDS;
			return true;
		}
		
		return false;
	}

	/**
	 * Work out if a release is PS3 App
	 */	
	public function isGamePS3($releasename)
	{
		if (preg_match('/PS3\-/', $releasename))
		{
			$this->tmpCat = Category::CAT_GAME_PS3;
			return true;
		}
		
		return false;
	}

	/**
	 * Work out if a release is PSP App
	 */	
	public function isGamePSP($releasename)
	{
		if (preg_match('/PSP\-/i', $releasename))
		{
			$this->tmpCat = Category::CAT_GAME_PSP;
			return true;
		}
		
		return false;
	}

	/**
	 * Work out if a release is WiiWare App
	 */	
	public function isGameWiiWare($releasename)
	{
		if (preg_match('/WIIWARE|WII.*?VC|VC.*?WII|WII.*?DLC|DLC.*?WII|WII.*?CONSOLE|CONSOLE.*?WII/i', $releasename))
		{
			$this->tmpCat = Category::CAT_GAME_WIIWARE;
			return true;
		}
		
		return false;
	}

	/**
	 * Work out if a release is Wii App
	 */	
	public function isGameWii($releasename)
	{
		if (preg_match('/WWII.*?(?!WII)/i', $releasename))
		{
			return false;
		}

		else if (preg_match('/Wii/i', $releasename))
		{
			$this->tmpCat = Category::CAT_GAME_WII;
			return true;
		}
		
		return false;
	}

	/**
	 * Work out if a release is 360DLC App
	 */	
	public function isGameXBOX360DLC($releasename)
	{
		if (preg_match('/(DLC.*?xbox360|xbox360.*?DLC|XBLA.*?xbox360|xbox360.*?XBLA)/i', $releasename))
		{
			$this->tmpCat = Category::CAT_GAME_XBOX360DLC;
			return true;
		}
		
		return false;
	}

	/**
	 * Work out if a release is 360 App
	 */	
	public function isGameXBOX360($releasename)
	{
		if (preg_match('/XBOX360|x360/i', $releasename))
		{
			$this->tmpCat = Category::CAT_GAME_XBOX360;
			return true;
		}
		
		return false;
	}

	/**
	 * Work out if a release is XBOX1 App
	 */	
	public function isGameXBOX($releasename)
	{
		if (preg_match('/XBOX/i', $releasename))
		{
			$this->tmpCat = Category::CAT_GAME_XBOX;
			return true;
		}
		
		return false;
	}


	/**
	 * Work out if a release is Music
	 */	
	public function isMusic($releasename)
	{
		if($this->isHashed($releasename)){ return true; }	
        if($this->isMusicVideo($releasename)){ return true; }
		if($this->isMusicLossless($releasename)){ return true; }
        if($this->isMusicAudiobook($releasename)){ return true; }
		if($this->isMusicMP3($releasename)){ return true; }
		return false;
	}

	/**
	* Work out if a release is Music Video
	*/
	public function isMusicVideo($releasename)
	{
		
		if (preg_match('/(HDTV|S\d{1,2}|\-1920)/i', $releasename))
		{
			return false;
		}

		else if (preg_match('/\-DDC\-|mbluray|\-VFI|m4vu|retail.*?(?!bluray.*?)x264|\-assass1ns|\-uva|(?!HDTV).*?\-SRP|x264.*?Fray|JESTERS|iuF|MDVDR|(?!HDTV).*?\-BTL|\-WMVA|\-GRMV|\-iLUV|x264\-(19|20)\d{2}/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MUSIC_VIDEO;
			return true;
		}

		return false;
	}

	/**
	* Work out if a release is Music Audiobook
	*/
	public function isMusicAudiobook($releasename)
	{
		if (preg_match('/(audiobook|\bABOOK\b)/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MUSIC_AUDIOBOOK;
			return true;
		}

		return false;
	}


	/**	
	* Work out if a release is MP3 Music
	*/	
	public function isMusicMP3($releasename)
	{
		if (preg_match('/dvdrip|xvid|(x|h)264|720p|1080(i|p)|Bluray/i', $releasename))
		{
			return false;
		}

		if (preg_match('/( |\_)Int$|\-(19|20)\d{2}\-[a-z0-9]+$|^V A |Top.*?Charts|Promo CDS|Greatest(\_| )Hits|VBR|NMR|CDM|WEB(STREAM|MP3)|\-DVBC\-|\-CD\-|\-CDR\-|\-TAPE\-|\-Live\-\d{4}|\-DAB\-|\-LINE\-|CDDA|-Bootleg-|WEB\-\d{4}|\-CD\-|(\-|)EP\-|\-FM\-|2cd|\-Vinyl\-|\-SAT\-|\-LP\-|\-DE\-|\-cable\-|Radio\-\d{4}|Radio.*?Live\-\d{4}|\-SBD\-|\d{1,3}(CD|TAPE)/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MUSIC_MP3;
			return true;
		}
		else if (preg_match('/^VA(\-|\_|\ )/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MUSIC_MP3;
			return true;
		}
		return false;
	}

	/**
	 * Work out if a release is FLAC Music
	 */	
	public function isMusicLossless($releasename)
	{
		if (preg_match('/dvdrip|xvid|264|720p|1080|Bluray/i', $releasename))
		{
			return false;
		}
		
		if (preg_match('/Lossless|FLAC/i', $releasename))
		{
			$this->tmpCat = Category::CAT_MUSIC_LOSSLESS;
			return true;
		}
		
		return false;
	}
	
	/**
	 * Work out if a release is an Ebook/Comic/Mag
	 */	
	public function isBook($releasename)
	{
		if($this->isHashed($releasename)){ return true; }
        if (preg_match('/dvdrip|xvid|x264/i', $releasename)){return false;}
		if($this->isBookComic($releasename)){ return true; }
		if($this->isBookMag($releasename)){ return true; }
		if($this->isBookEbook($releasename)){ return true; }
		return false;
	}
	
	/**
	* Work out if a release is a Comic
	*/	
	public function isBookComic($releasename)
	{
		if (preg_match('/(\.|_|\-| )comic(\.|_|\-| )/i', $releasename))
		{
			$this->tmpCat = Category::CAT_BOOK_COMICS;
			return true;
		}
		return false;
	}
	
	/**
	* Work out if a release is a Magazine
	*/	
	public function isBookMag($releasename)
	{
		if (preg_match('/(\.|_|\-| )(Mag(s|azin|azine|azines))(\.|_|\-| )/i', $releasename))
		{
			$this->tmpCat = Category::CAT_BOOK_MAGS;
			return true;
		}
		return false;
	}
	
	/**
	* Work out if a release is a Ebook
	*/	
	public function isBookEbook($releasename)
	{
		if (preg_match('/(\.|_|\-| )(Ebook|E?\-book|\) WW|\[Springer\]| epub|ISBN)(\.|_|\-| )/i', $releasename))
		{
			$this->tmpCat = Category::CAT_BOOK_EBOOK;
			return true;
		}
		return false;
	}	
	
}
