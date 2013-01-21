<?php
require_once("framework/db.php");

class Sites
{	
	const REGISTER_STATUS_OPEN = 0;
	const REGISTER_STATUS_INVITE = 1;
	const REGISTER_STATUS_CLOSED = 2;

	const ERR_BADUNRARPATH = -1;
	const ERR_BADFFMPEGPATH = -2;
	const ERR_BADMEDIAINFOPATH = -3;
	const ERR_BADNZBPATH = -4;
	const ERR_DEEPNOUNRAR = -5;
	const ERR_BADTMPUNRARPATH = -6;	
	const ERR_BADLAMEPATH = -7;	
	const ERR_SABCOMPLETEPATH = -8;	
	
	public function version()
	{
		return "0.2.3p";
	}
	
	public function update($form)
	{		
		$db = new DB();
		$site = $this->row2Object($form);

		if (substr($site->nzbpath, strlen($site->nzbpath) - 1) != '/')
			$site->nzbpath = $site->nzbpath."/";

		//
		// Validate site settings
		//
		if ($site->mediainfopath != "" && !is_file($site->mediainfopath))
			return Sites::ERR_BADMEDIAINFOPATH;

		if ($site->ffmpegpath != "" && !is_file($site->ffmpegpath))
			return Sites::ERR_BADFFMPEGPATH;

		if ($site->unrarpath != "" && !is_file($site->unrarpath))
			return Sites::ERR_BADUNRARPATH;

		if ($site->nzbpath != "" && !file_exists($site->nzbpath))
			return Sites::ERR_BADNZBPATH;		

		if ($site->checkpasswordedrar == 2 && !is_file($site->unrarpath))
			return Sites::ERR_DEEPNOUNRAR;				
			
		if ($site->tmpunrarpath != "" && !file_exists($site->tmpunrarpath))
			return Sites::ERR_BADTMPUNRARPATH;				

		if ($site->lamepath != "" && !file_exists($site->lamepath))
			return Sites::ERR_BADLAMEPATH;				
			
		if ($site->sabcompletedir != "" && !file_exists($site->sabcompletedir))
			return Sites::ERR_SABCOMPLETEPATH;				
			
		$sql = $sqlKeys = array();
		foreach($form as $settingK=>$settingV)
		{
			$sql[] = sprintf("WHEN %s THEN %s", $db->escapeString($settingK), $db->escapeString(trim($settingV)));
			$sqlKeys[] = $db->escapeString($settingK);
		}
		
		$db->query(sprintf("UPDATE site SET value = CASE setting %s END WHERE setting IN (%s)", implode(' ', $sql), implode(', ', $sqlKeys)));	
		
		return $site;
	}	

	public function get()
	{			
		$db = new DB();
		$rows = $db->query("select * from site");			

		if ($rows === false)
			return false;
		
		return $this->rows2Object($rows);
	}	
	
	public function rows2Object($rows)
	{
		$obj = new stdClass;
		foreach($rows as $row)
			$obj->{$row['setting']} = $row['value'];
	
		$obj->{'version'} = $this->version();
		return $obj;
	}
	
	public function row2Object($row)
	{
		$obj = new stdClass;
		$rowKeys = array_keys($row);
		foreach($rowKeys as $key)
			$obj->{$key} = $row[$key];
		
		return $obj;
	}
	
	public function getUnappliedPatches($site)
	{
		preg_match("/\d+/", $site->dbversion, $matches);
		$currentrev = $matches[0];
		
		$patchpath = WWW_DIR."../db/patch/0.2.3/";
		$patchfiles = glob($patchpath."*.sql");
		$missingpatch = array();
		foreach($patchfiles as $file) 	
		{
			$filecontents = file_get_contents($file);
			if (preg_match("/Rev\: (\d+)/", $filecontents, $matches))
			{
				$patchrev = $matches[1];
				if ($patchrev > $currentrev)
					$missingpatch[] = $file;
			}
		}	
		return $missingpatch;
	}
	
	public function updateItem($setting, $value)
	{
		$db = new DB();
		$sql = sprintf("update site set value = %s where setting = %s", $db->escapeString($value), $db->escapeString($setting));
		return $db->query($sql);
	}	
	
	public function updateLatestRegexRevision($rev)
	{
		return $this->updateItem("latestregexrevision", $rev);
	}
	
	public function getLicense($html=false)
	{
		$n = "\r\n";
		if ($html)
			$n = "<br/>";
	
		return $n."newznab ".$this->version()." Copyright (C) ".date("Y")." newznab.com".$n."

This program is distributed with a commercial licence. See LICENCE.txt for 
further details.".$n;
	}
}
