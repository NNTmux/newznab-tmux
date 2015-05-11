<?php
//
// This script is allows you to perform post blacklist scanning
//  Read about the flags below before using this script.
//
//  The intent of this script is to try and eliminate garbage
//  entries.
//
// by l2g
//
require_once dirname(__FILE__) . '/../../www/config.php';

use newznab\db\DB;


# If satisfied with what is matched, set this to true and have
# matched content removed.
# This is a DANGEROUS switch to change.  Be sure your absolutely
# satisfied with the regular expressions being matched are bad.
# You'd ideally want to change this flag back to false when you
# resume testing.
$purgeMatched=false;

# When this flag is set to true, the database blacklist is applied
# to your existing nn db.  If you set this to false, then the
# blacklist array below is applied instead
$use_NNRegexDB=false;

# By default it looks at all the headers in the database, but sometimes
# if your database is just too big and you want to expermeint with different
# regular expressions, you can set this flag to false, and instead start
# playing with the $res table below labled as Test DB data
$use_NNReleasesDB=true;

# Categorized content can be futher subjected to size restrictions 'IF'
# your database is configured for such.
# This allows you to filter out crap that makes no sense being
# idenified as the category it is based on the category sizing
# Note: This is expermental at this point and requires an update to the DB to
#       work correctly.
#
#         ALTER TABLE  `category` ADD  `minsize` INT UNSIGNED NOT NULL DEFAULT  '0' AFTER  `status` ,
#                  ADD  `maxsize` INT UNSIGNED NOT NULL DEFAULT  '0' AFTER  `minsize` ;
#
#         ;; Update tvshows.SD to set a min value of 30MB (in bytes)
#         UPDATE  `newznab`.`category` SET  `minsize` =  '31457280' WHERE  `category`.`ID` =5030 LIMIT 1 ;
#         ;; Update movies.SD to set a min value of 50MB (in bytes)
#         UPDATE  `newznab`.`category` SET  `minsize` =  '52428800' WHERE  `category`.`ID` =2030 LIMIT 1 ;
#
#       This also requires a few functions in the lib/category.php
#       Better just wait before enabling this :)
$use_CategorySizeRestrictions=true;
# This flag is only used if $use_CategorySizeRestrictions (above) is set to true.
# setting this value to false, will have all category restrictions defined in the
# static table below take effect... otherwise, the script will query the database (it's assumed
# that all the proposed changes were accepted and pushed into the DB).  Leave this value to
# false until it is certain the database changes have been pushed. When these chagnes are pushed
# this flag will be merged with the above.
$use_NNCategorySizeRestrictions=false;

# Completion Restriction allows you to remove content that is older then
# X hrs and is less then 'Y%' complete.
# Why keep content in the database that can't be put back together
$use_CompletionRestrictions=true;
# The precentage *must be less then (or equal to) 100* to accept 'up to'
$completionAllowable=90;
# The number of hours the release has aged and still hasn't met the allowable
# completion percentage (in seconds) 10800 is equal to 3 hrs
$completionSec=10800;

# check or the article is considered bogus. This greatly increasese
# the processing time against the database.
# Be warned that if your cataloging music, almost every band out there
# spells their name with something that does not coincide with a dictionary
# value.
$use_spellCheck=false;

# Identify the number of dictionaries you want to check the spelling
# against.  International spell checking might include 'ge', '
$spellCheckLang=array('en');

# International (uncomment the below)
#$spellCheckLang=array('en', 'fr', 'it', 'ge', 'es', 'nl');

# Exceptions are bad spellings that exist in a filename but still
# mark it valid;  This list is not case sensitive, so add any
# keywords that should not be considered while searching
$spellExceptions=array(
   # Some good tokens
   'xvid', 'DVD', 'DVDRip', 'Bluray', 'x264', '480p', '720p', '1080p',
   'HDRIP',
   # TV Episode Tags
   'S[0-9]{1,2}([. -]*E[0-9]{1,2}$|$)',

   # Tv Series release groups
   "BARC0DE", "DIMENSION", "LOL", "WEB-DL", "SUBLiME", "2HD", "FMQ",
   "BIA",

   # MP3/FLAC Groups
   "FiH", "SDR",

   # Software Groups
   "LAXiTY",
);

# Some default category restrictions to play with for those who haven't
# updated their database yet and would still like size restrictions
# in place.
$categoryRestrictions=array(
   # TV Standard Def (SD) set a 30MB Min restriction
   Category::CAT_TV_SD=>array('min'=>31457280, 'max'=>PHP_INT_MAX),
   # TV High Def (HD) set a 40MB Min restriction
   Category::CAT_TV_HD=>array('min'=>41943040, 'max'=>PHP_INT_MAX),
   # Movies Standard Def (SD) set a 50MB Min restriction
   Category::CAT_MOVIE_SD=>array('min'=>52428800, 'max'=>PHP_INT_MAX),
   # Movies High Def (HD) set a 80MB Min restriction
   Category::CAT_MOVIE_HD=>array('min'=>83886080, 'max'=>PHP_INT_MAX),
   # Movies Other set a 30MB Min restriction
   Category::CAT_MOVIE_OTHER=>array('min'=>31457280, 'max'=>PHP_INT_MAX),
   # Bluray movies, 350MB Min restriction
   Category::CAT_MOVIE_BLURAY=>array('min'=>367001600, 'max'=>PHP_INT_MAX),
);

# Throttle cleanup/searching by performing batches... the larger
# the number, the faster processing will go, but will demand more
# system memory...
$batch=500;

# Throttle
# The throttle allows a scan of your database without impacting performance
# to the public who may or may not be using your database. it also reduces
# the cpu demand of the cleanup.  Set this value to 0 if your system is
# private and dedicated to yourself for the best speed. It identifies
# the number of seconds to sleep for between each batch
$throttle=0.5;

# This blacklist is an aray of regular expressions that you can use
# to test with (sometimes it's easier to update a table infront of
# you as a developer then going back and making a database change
# and then returning to run the script.
$blacklist = array(
	# random string of crap
	array("groupname"=>"alt.binaries.*", "regex"=>'^[a-z0-9]{1,80}([0-9-]+$|$)'),
	# Missing blacklist catching of foreign content
	array("groupname"=>"alt.binaries.*", "regex"=>'[-.](FR|DE|ITA)[-.]'),
	# Common German Keywords
	array("groupname"=>"alt.binaries.*", "regex"=>'(^|[.\/ \-]+)(ohne|das|der|und|fuer|ersten|leicht|meinem|zum|aus|dem|blitzlicht|alle|grosse|blitzen|ich|zed|sed)([.\/ \-]+|$)'),
	# random string of crap in alt.binaries.sounds.mp3, and alt.binaries.multimedia
	array("groupname"=>"alt.binaries.*", "regex"=>'^[a-z0-9]{5} [0-9]{8} [0-9]{3}$'),
	# random crap like: acef826f-6c8b-405c-b7f2-c9c143d726a8
	array("groupname"=>"alt.binaries.*", "regex"=>'^[a-z0-9]{8}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{12}$'),
	array("groupname"=>"alt.binaries.*", "regex"=>'^[a-z0-9.]+\.BDR$'),
	array("groupname"=>"alt.binaries.*", "regex"=>'^\([0-9.]+\)$'),

	# Password protected content that was marked by the poster
	array("groupname"=>"alt.binaries.*", "regex"=>'\[password\]'),
);

# Test DB Data; if you plan on using this, you need to set the $use_NNReleasesDB to false
$test_headers = array(
	# Set id's to 0 so id's are purged unessisarily when testing
	array('ID'=>0, 'groupname'=>"alt.binaries.multimedia", 'name'=>"1204odayrtgh6j7app"),
	array('ID'=>0, 'groupname'=>"alt.binaries.multimedia", 'name'=>"BB555"),
	array('ID'=>0, 'groupname'=>"alt.binaries.multimedia", 'name'=>"bnedhe8utrh5tnbg9"),
	array('ID'=>0, 'groupname'=>"alt.binaries.multimedia", 'name'=>"Uoaaqunio-396653289-201212201118"),
	array('ID'=>0, 'groupname'=>"alt.binaries.multimedia", 'name'=>"acef826f-6c8b-405c-b7f2-c9c143d726a8"),
	array('ID'=>0, 'groupname'=>"alt.binaries.multimedia", 'name'=>"ich26389"),
	array('ID'=>0, 'groupname'=>"alt.binaries.multimedia", 'name'=>"GkTNPvg3"),
	array('ID'=>0, 'groupname'=>"alt.binaries.multimedia", 'name'=>"RVL 25 12 2012 G O M S BDR"),
	array('ID'=>0, 'groupname'=>"alt.binaries.multimedia", 'name'=>"25 12 2012 M S R BDR"),
	array('ID'=>0, 'groupname'=>"alt.binaries.multimedia", 'name'=>"23YKC 20121212 013"),
);

function handleError($errno, $errstr, $errfile, $errline, array $errcontext)
{
	// this function makes it easier to catch regular expression errors
	// found
	if (0 === error_reporting()) {
		 return false;
	}
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler('handleError');

function get_block($offset, $batch)
{
	global $use_NNReleasesDB;
	static $retrieved=false;
	if(!$use_NNReleasesDB){
	    // use a retrieved boolean for test data so content
	    // is only fetched once.
		if(!$retrieved && $offset > 0){
			return Null;
		}
		global $test_headers;
		// toggle retrieved content on test data
        $retrieved=true;
		return $test_headers;
	}

	$catsql = "SELECT releases.*,groups.name as groupname FROM "
		 ."releases LEFT JOIN groups on releases.groupID = groups.ID ";
	$db = new DB();
	return $db->query($catsql.sprintf(' LIMIT %d,%d', $offset, $batch));
}

$db = new DB();

$errcnt=0;
$total=0;
$offset=0;

# Dictionary
$pspelldict=array();

# Completion Refernece Time in past
$completionRef = strtotime("-$completionSec seconds");

# Scan header for spelling...; at least 1 word has to match the spelling
if($use_spellCheck && !function_exists('pspell_check')){
   echo "Note: Disabling spell checking as it is not available in your PHP installation.\n";
   echo "      Consider the following to enable it:";
   echo "             ubuntu #> sudo apt-get install php5-pspell\n";
   echo "             redhat #> yum install php-pspell\n";
   echo "             other  #> # re-compile php with --with-pspell flag\n";
   $use_spellCheck=false;
}

$binaries = new Binaries();
if($use_NNRegexDB)
	# Over-ride Blacklist up top and use database
	$blacklist = $binaries->getBlacklist(true);

while($res=get_block($offset, $batch)){

	$subtotal=count($res);
	$total+=$subtotal;

	$release = new Releases();
	$category = new Category();

    // Track error count changes
    $_errcnt=$errcnt;
	foreach ($res as $header)
	{
		// if a white list is detected we now are required to
		// only accept the entry if it matches at least 1 whitelist
		// while a blacklist will over-ride all
		$whitelist = array();
		$matches_whitelist = false;

		foreach ($blacklist as $bl)
		{
			// opttype = 1 -> Blacklist (Default)
			// opttype = 2 -> Whitelist
			$opttype = Binaries::OPTYPE_BLACKLIST;
			if (array_key_exists ('opttype' , $bl)){
				$opttype = intval($bl['opttype']);
			}

			// Regular expression
			$regex = $bl['regex'];

			// Default msgcol is subject
			$msgcol = Binaries::BLACKLIST_FIELD_SUBJECT;
			$parse = $header['name'];
			if (array_key_exists ('msgcol' , $bl)){
				$msgcol = intval($bl['msgcol']);
			}

			switch($msgcol)
			{
				case Binaries::BLACKLIST_FIELD_FROM:
					if(!array_key_exists ('fromname' , $bl)){
						# we only process fromname if we found it... otherwise we keep going
						continue;
					}
					$parse = $header['fromname'];
					break;
				case Binaries::BLACKLIST_FIELD_MESSAGEID:
					# unsupported; you can't get this information from an already
					# populated database
					continue;
			};

			if ($opttype == Binaries::OPTYPE_WHITELIST){
				// at least 1 whitelist found...
				// this means we have to match it or be rejected
				$whitelist[] = $regex;
			}

			try
			{
				$group_check = preg_match('/^'.$bl['groupname'].'$/i', $header['groupname']);
			}
			catch(Exception $e)
			{
				# Support letting the user know when there is a bad
				# regular expression entry abort futher checks
				$strerr=str_pad((int) $errcnt,2," ",STR_PAD_LEFT);
				echo "$strerr. id=".$bl["ID"].
					", group=".$header["groupname"]."\n";
				echo "	 regex='".$bl["groupname"]."'\n";
				echo "	 error=".$e->getMessage()."\n\n";
				exit(1);
			}

			if ($group_check)
			{
				try
				{
					$blacklist_check = preg_match('/'.$regex.'/i', $parse);
					// White lists react in the invert manner... something that
					// doesn't match causes us to keep going
					if($opttype == Binaries::OPTYPE_WHITELIST && $blacklist_check){
						// invert check since it's a good thing we matched
						$blacklist_check=false;
						// Flag that we matched the white list
						$matches_whitelist = true;
					}
				}
				catch(Exception $e)
				{
					# Support letting the user know when there is a bad
					# regular expression entry abort futher checks
					$strerr=str_pad((int) $errcnt,2," ",STR_PAD_LEFT);
                    if(isset($bl["ID"]))
					   echo "$strerr. id=".$bl["ID"].", ";
				    echo " name=".$header["name"]."\n";
					echo "	 regex=$regex\n";
					echo "	 error=".$e->getMessage()."\n\n";
					exit(1);
				}

				if ($blacklist_check)
				{
					$errcnt++;
					echo 'BL: /'.$bl['regex'].'/i matched '.$header['ID'].'/'.$header['name'].
						" (".$header['groupname'].")\n";
					if ($purgeMatched && isset($header['ID']) && $header['ID'] > 0){
						// Support purging if enabled
						$release->delete($header['ID']);
					}
					# ensure whitelist flags are off to avoid extra processing
					# at the end of this loop (Below)
					$whitelist = array();
					break;
				}

				if($use_CategorySizeRestrictions == true && $header['categoryID'])
				{
					$sizes = null;
					if($use_NNCategorySizeRestrictions){
						$sizes = $category->getSizeRangeById($header['categoryID']);
					}else if (array_key_exists ($header['categoryID'], $categoryRestrictions)){
						$sizes = $categoryRestrictions[$header['categoryID']];
					}
					if(is_array($sizes)){
						# Restrictions are in place
						$totalSize=intval($header['size']);
						if(!($totalSize >= $sizes['min'] && $totalSize < $sizes['max']))
						{
							$errcnt++;
							echo 'BL: size restrictions '.$header['ID'].'/'.$header['name'].
								" (".$header['groupname'].")\n";
							if ($purgeMatched && isset($header['ID']) && $header['ID'] > 0){
								// Support purging if enabled
								$release->delete($header['ID']);
							}
							# ensure whitelist flags are off to avoid extra processing
							# at the end of this loop (Below)
							$whitelist = array();
							break;
						}
					}
				}

				if($use_CompletionRestrictions == true){
					$lastUpdate=strtotime($header['updatedate']);
					$completion=intval($header['completion']);

					if($completion < $completionAllowable && $lastUpdate < $completionRef)
					{
						$errcnt++;
						echo 'BL: incomplete '.$header['ID'].'/'.$header['name'].
							" (".$header['groupname'].")\n";
						if ($purgeMatched && isset($header['ID']) && $header['ID'] > 0){
							// Support purging if enabled
							$release->delete($header['ID']);
						}
						# ensure whitelist flags are off to avoid extra processing
						# at the end of this loop (Below)
						$whitelist = array();
						break;
					}
				}

				if($use_spellCheck == true &&
						$opttype == Binaries::OPTYPE_BLACKLIST &&
						$msgcol == Binaries::BLACKLIST_FIELD_SUBJECT){

					# Track matched
					$matched=0;
					# First extract the words
					$keywords = preg_split('/[^a-z0-9]+/i', $parse);
					foreach($spellCheckLang as $lang){
						if (!array_key_exists ($lang , $pspelldict))
					    	$pspelldict[$lang] = pspell_new($lang);
						foreach($keywords as $kw){
							foreach($spellExceptions as $exception){
								if(preg_match('/'.$exception.'/i', $kw)){
									$matched++;
									break;
								}
							}
							if($matched>0)break;

							if(pspell_check($pspelldict[$lang], $kw)){
								$matched++;
								break;
							}
						}
						# we only need to match once to presume the data
						# is good
						if($matched>0)break;
					}
					# how did we do for spelling matches
					if(!$matched){
						$errcnt++;
						echo 'BL: due to bad spelling '.$header['ID'].'/'.$header['name'].
							" (".$header['groupname'].")\n";
						if ($purgeMatched && isset($header['ID']) && $header['ID'] > 0){
							// Support purging if enabled
							$release->delete($header['ID']);
						}
						# ensure whitelist flags are off to avoid extra processing
						# at the end of this loop (Below)
						$whitelist = array();
						break;
					}
				}
			}
		}

		# We parsed entire matching list entries at this point.. now we need
		# to handle the whitelist (if it was enabled)
		if(count($whitelist) > 0 && !$matches_whitelist)
		{
			$errcnt++;
			$first_loop=true;
			foreach ($whitelist as $entry){
				if($first_loop)
				{
					$first_loop=false;
				}
				else
				{
					echo '\n';
				}
				echo 'WL: /'.$bl['regex'].'/i';
			}
			echo ' failed to match '.$header['ID'].'/'.$header['name'].
				" (".$header['groupname'].")\n";

			if ($purgeMatched && $header['ID'] > 0){
				// Support purging if enabled
				$release->delete($header['ID']);
			}
		}
	}
	if(!$purgeMatched || $_errcnt==$errcnt){
	   # No changes... update batch
	   $offset+=$batch;
	}

	if($subtotal == $batch){
		// Presume there is more content to come
		// throttle request
		if($throttle > 0.0){
			sleep($throttle);
		}
	}
}
echo "Scanned $total record(s), $errcnt match(es) found.\n";
if($errcnt >0){
	if(!$purgeMatched){
		echo "Note: This scan was performed in a safe read-only mode.\n";
		echo "      If you are satisfied with the output as being 'garbage/spam'\n".
		     "      then consider editing this file and setting the \$purgeMatched flag to 'true'\n";
	}else{
		echo "Note: This scan was performed in a write mode; all content matched was removed.\n";
	}
}
exit(($errcnt>0)?1:0);
