<?php
define('FS_ROOT', realpath(dirname(__FILE__)));
require_once (FS_ROOT . "/../../www/config.php");
require_once (FS_ROOT . "/../../www/lib/framework/db.php");
require_once (FS_ROOT . "/../../www/lib/util.php");
require_once ("hashcompare.php");

 
     function match_all_key_value($regex, $str, $keyIndex = 1, $valueIndex = 2){
        $arr = array();
        preg_match_all($regex, $str, $matches, PREG_SET_ORDER);
        foreach($matches as $m){
            $arr[$m[$keyIndex]] = $m[$valueIndex];
        }
        return $arr;
    }
     
     function match_all($regex, $str, $i = 0){
        if(preg_match_all($regex, $str, $matches) === false)
            return false;
        else
            return $matches[$i];
    }
 
     function match($regex, $str, $i = 0){
        if(preg_match($regex, $str, $match) == 1)
            return $match[$i];
        else
            return false;
    }
	
			
			$src = "http://www.prelist.ws/?start=0";	

			echo "prelist.ws - request...";
			$apiresponse = getUrl($src); 
		
			if ($apiresponse)
			{
			
				if (strlen($apiresponse) > 0) 
				{
					echo "response\n";
					  foreach(match_all('/<tt id="(.*?)">(.*?)<\/tt>/ms',$apiresponse, 2) as $r) {
						   $tdcount = 0;
						   foreach(match_all('/<a href="(.*?)">(.*?)<\/a>/ms',$r, 2) as $p) {
							
							$cleanname = '';
							if ($tdcount == 1)
							{
								
								$res  = getRelease($p);
								if (strlen($p) > 0)
								{
									if ($res['total'] == 0)
									{	
										AddRelease(trim($p), '');
									//	echo "Added - ".$p."\n";
									}
								}
								
							}
							
							$tdcount = $tdcount + 1;
					
						   }
						   
					   }
				
				}else{
					echo "response was zero length :( \n";
				}
			}else{
				echo "nothing came :( \n";
			}

	
	
?>