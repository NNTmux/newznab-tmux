<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR ."/lib/framework/db.php");
require_once(WWW_DIR ."/lib/util.php");
require_once ("hashcompare.php");
		
	
	$src = "http://rss.omgwtfnzbs.org/rss-info.php";	
	
	echo "omgwtfnzbs.org - request...";
	$apiresponse = getUrl($src); 
		
	if ($apiresponse)
	{
			
			if (strlen($apiresponse) > 0) 
			{
				echo "response\n";
				$preinfo = simplexml_load_string($apiresponse);
		
				foreach($preinfo->channel->item as $item) 
				{
					$cleanname = trim($item->title);
					$res  = getRelease($cleanname);
					
					if ($res['total'] == 0)
					{	
						AddRelease($cleanname, $item->pubDate);
					}
		
				}

			}else{
				echo "response was zero length :( \n";
			}
	}else{
			echo "nothing came :( \n";
	}
	
?>