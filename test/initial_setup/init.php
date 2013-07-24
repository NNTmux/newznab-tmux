<?php
require_once(dirname(__FILE__)."/../../bin/config.php");
require_once(WWW_DIR ."/lib/framework/db.php");

	
	function CreateTable()
	{			
		$db = new DB();
		return $db->query("CREATE TABLE IF NOT EXISTS `prehash` (
  								`ID` int(11) NOT NULL AUTO_INCREMENT,
								 `releasename` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
								 `hash` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
								 `predate` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
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