<?php
require_once(dirname(__FILE__)."/../../bin/config.php");
require_once(WWW_DIR ."/lib/framework/db.php");

	
	function CreateTable()
	{			
		$db = new DB();
		return $db->query("CREATE TABLE IF NOT EXISTS `prehash` (
  								`ID` int(11) NOT NULL AUTO_INCREMENT,
								 `releasename` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
                                 `nfo` VARCHAR( 500 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
                                 `size` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
                                 `category` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
								 `hash` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
								 `predate` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
                                 `source` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                                 `releaseID` INT( 11 ) NULL DEFAULT NULL,
								  PRIMARY KEY (`ID`),
								  UNIQUE KEY `hash` (`hash`),
								  KEY `release` (`releasename`(333))
								 ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");		
	}
		
		
		
		

	function getRelease($name)
	{			
		$db = new DB();

		return $db->queryOneRow(sprintf("SELECT count(*) as total FROM prehash WHERE releasename =  %s", $db->escapeString($name)));		
	}
	
	
	function AddRelease($name)
	{			
		$db = new DB();
		return $db->queryInsert(sprintf("INSERT INTO prehash (releasename, hash) VALUES (%s, %s)", $db->escapeString($name), $db->escapeString(md5($name))));		
	}
		
	
	
		CreateTable();
		if (isset($argv[1]))
		{
			$file = file_get_contents($argv[1]);
			
			$scene = explode('¬', $file);
			
			foreach($scene as $rel) 
			{
				if (trim(strlen($rel)) > 0)
				{
					$res  = getRelease($rel);
					if ($res['total'] == 0)
					{	
						AddRelease(trim($rel));
						
					}
				}
				
			}
			
			echo "done\n";
		}else
		{
			echo "No import file provided. Table created data not imported. Run again a pass the data file location\n";
		}
		
		

?>