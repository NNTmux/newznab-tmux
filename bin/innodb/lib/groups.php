<?php
require_once("framework/db.php");

/**
 * This class handles data access for groups.
 */
class Groups
{	
	/**
	 * Get all active group rows.
	 */
	public function getActive()
	{			
		$db = new DB();
		return $db->query("SELECT * FROM groups WHERE active = 1 ORDER BY name");		
	}
	
	/**
	 * Get a group row by name.
	 */
	public function getByName($grp)
	{			
		$db = new DB();
		return $db->queryOneRow(sprintf("select * from groups where name = '%s' ", $grp));		
	}	

}
