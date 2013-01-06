<?php

class DB
{
	private static $initialized = false;

	function DB()
	{
		if (DB::$initialized === false)
		{
			// initialize db connection
			mysql_pconnect(DB_HOST, DB_USER, DB_PASSWORD)
			or die("fatal error: could not connect to database! Check your config.");
			
			mysql_select_db(DB_NAME)
			or die("fatal error: could not select database! Check your config.");
			
			mysql_set_charset('utf8');

			DB::$initialized = true;
		}

        $this->mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        if (mysqli_connect_errno()) {
            printf("Connect failed: %s\n", mysqli_connect_error());
            exit();
        }
    }
				
	public function escapeString($str)
	{
		return "'".mysql_real_escape_string($str)."'";
	}		

	public function makeLookupTable($rows, $keycol)
	{
		$arr = array();
		foreach($rows as $row)
			$arr[$row[$keycol]] = $row;			
		return $arr;
	}	
	
	public function queryInsert($query, $returnlastid=true)
	{
		$result = mysql_query($query);
		return ($returnlastid) ? mysql_insert_id() : $result;
	}
	
	public function queryOneRow($query)
	{
		$rows = $this->query($query);
		
		if (!$rows)
			return false;
		
		if ($rows)
			return $rows[0];
		else
			return $rows;		
	}	
		
	public function query($query)
	{
		$result = mysql_query($query);
		
		if ($result === false || $result === true)
			return array();
		
		$rows = array();

		while ($row = mysql_fetch_assoc($result)) 
			$rows[] = $row;	
		
		mysql_free_result($result);
		return $rows;
	}	
	
	public function queryDirect($query)
	{
		$result = mysql_query($query);
		return $result;
	}	


	public function optimise() 
	{
		$ret = array();
		$alltables = $this->query("SHOW TABLES"); 

		foreach ($alltables as $tablename) 
		{
			$ret[] = $tablename['Tables_in_'.DB_NAME];
			$this->queryDirect("REPAIR TABLE `".$tablename['Tables_in_'.DB_NAME]."`"); 
			$this->queryDirect("OPTIMIZE TABLE `".$tablename['Tables_in_'.DB_NAME]."`"); 
		}
			
		return $ret;
	}

    /* Mysqli Functions */
    public function multiQueryTransaction($query)
    {
        $this->mysqli->autocommit(FALSE);
        $this->mysqli->multi_query($query);

        while($this->mysqli->next_result()) {
            $result = $this->mysqli->use_result();
            if($result instanceof mysqli_result)
                $result->free();
        }

        $this->mysqli->commit();
    }

    public function disableAutoCommit()
    {
        $this->mysqli->autocommit(FALSE);
    }

    public function mysqliQuery($sql)
    {
        $result = $this->mysqli->query($sql);
        if ($result === false || $result === true)
            return array();

        $rows = array();

        while ($row = mysqli_fetch_assoc($result))
            $rows[] = $row;

        mysqli_free_result($result);
        return $rows;
    }

    public function mysqliQueryOneRow($query)
    {
        $rows = $this->mysqliQuery($query);

        if (!$rows)
            return false;

        if ($rows)
            return $rows[0];
        else
            return $rows;
    }

    public function mysqliQueryInsert($query, $returnlastid=true)
    {
        $result = $this->mysqli->query($query);
        return ($returnlastid) ? $this->mysqli->insert_id : $result;
    }

    public function commit()
    {
        $this->mysqli->commit();
    }

    public function rollback()
    {
        $this->mysqli->rollback();
    }

    public function mysqliQueryDirect($query)
    {
        return $this->mysqli->query($query);
    }


    public function enableAutoCommit()
    {
        $this->mysqli->autocommit(TRUE);
    }
}
?>
