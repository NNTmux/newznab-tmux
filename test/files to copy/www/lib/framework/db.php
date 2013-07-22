<?php
require_once(WWW_DIR . "/lib/framework/cache.php");

class DB
{
  //
        // the element relstatus of table releases is used to hold the status of the release
        // The variable is a bitwise AND of status
        // List of processed constants - used in releases table. Constants need to be powers of 2: 1, 2, 4, 8, 16 etc...
        const NFO_PROCESSED_NAMEFIXER     = 1;  // We have processed the release against its .nfo file in the namefixer
        const PREHASH_PROCESSED_NAMEFIXER   = 2;  // We have processed the release against a predb name
    private static $initialized = false;
    private static $mysqli = null;
    private static $usingInnoDB = null;
    private static $batchSize = 1000;

    function DB()
    {
        if (DB::$initialized === false) {
			if(defined('DB_PORT')){
				DB::$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
			}else{
				DB::$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
			}

            if (mysqli_connect_errno()) {
                printf("Fatal error: %s", mysqli_connect_error());
                exit();
            }

            DB::$mysqli->select_db(DB_NAME)
                or die("Fatal error: could not select database! Check your config.");

            DB::$mysqli->set_charset("utf8");

            DB::$usingInnoDB = defined('DB_INNODB') ? DB_INNODB : false;

            DB::$initialized = true;
        }
    }

    public function getBatchSize()
    {
        return DB::$batchSize;
    }

    public function escapeString($str)
    {
        return "'" . DB::$mysqli->real_escape_string($str) . "'";
    }

    public function makeLookupTable($rows, $keycol)
    {
        $arr = array();
        foreach ($rows as $row)
            $arr[$row[$keycol]] = $row;
        return $arr;
    }

    public function queryInsert($query, $returnlastid = true)
    {
        if($query=="")
            return false;

        $result = DB::$mysqli->query($query);
        return ($returnlastid) ? DB::$mysqli->insert_id : $result;
    }

    public function queryOneRow($query, $useCache = false, $cacheTTL = '')
    {
        if($query=="")
            return false;

        $rows = $this->query($query, $useCache, $cacheTTL);
        return ($rows ? $rows[0] : false);
    }

    public function query($query, $useCache = false, $cacheTTL = '')
    {
        if($query=="")
            return false;

        if ($useCache) {
            $cache = new Cache();
            if ($cache->enabled && $cache->exists($query)) {
                $ret = $cache->fetch($query);
                if ($ret !== false)
                    return $ret;
            }
        }

        $result = DB::$mysqli->query($query);


        if ($result === false || $result === true)
            return array();

        $rows = array();

        while ($row = $this->getAssocArray($result))
            $rows[] = $row;

        $this->freeResult($result);

        if ($useCache)
            if ($cache->enabled)
                $cache->store($query, $rows, $cacheTTL);

        return $rows;
    }
    public function queryDirect($query, $unbuffered = false)
    {
        if($query=="")
            return false;

        if($unbuffered)
        {
            $ret = DB::$mysqli->query($query, MYSQLI_USE_RESULT);
        }
        else
        {
            $ret = DB::$mysqli->query($query);
        }

        return $ret;
    }

    public function freeResult($result)
    {
        $result->free_result();
    }
    //*addedd from nZEDb for testing
    public function fetchArray($result)
	{
		return (is_null($result) ? null : $result->fetch_array());
	}
    //* end of insert for testing
    public function getNumRows($result)
    {
        return $result->num_rows;
    }

    public function disableAutoCommit()
    {
        if (DB::$usingInnoDB == false)
            return;

        DB::$mysqli->autocommit(false);
    }

    public function disableForeignKeyChecks()
    {
        if (DB::$usingInnoDB == false)
            return;

        $this->query("SET foreign_key_checks=0;");
    }

    public function enableForeignKeyChecks()
    {
        if (DB::$usingInnoDB == false)
            return;

        $this->query("SET foreign_key_checks=1;");
    }

    public function commit($enableAutoCommit = true)
    {
        if (DB::$usingInnoDB == false)
            return;

        DB::$mysqli->commit();

        if ($enableAutoCommit == true)
            $this->enableAutoCommit();
    }

    public function rollback($enableAutoCommit = true)
    {
        if (DB::$usingInnoDB == false)
            return;

        DB::$mysqli->rollback();

        if ($enableAutoCommit == true)
            $this->enableAutoCommit();
    }

    public function enableAutoCommit()
    {
        if (DB::$usingInnoDB == false)
            return;

        DB::$mysqli->autocommit(true);
    }

    public function usingInnoDB()
    {
        return DB::$usingInnoDB;
    }

    public function getAssocArray($result)
    {
        return $result->fetch_assoc();
    }

    public function getRow($result)
    {
        return $result->fetch_row();
    }

    public function optimise($force = false)
    {
        $ret = array();
        if ($force)
            $alltables = $this->query("show table status");
        else
            $alltables = $this->query("show table status where Data_free != 0");

        foreach ($alltables as $tablename) 
        {
            $ret[] = $tablename['Name'];
            if (strtolower($tablename['Engine']) == "myisam")
            	$this->queryDirect("REPAIR TABLE `" . $tablename['Name'] . "`");

            $this->queryDirect("OPTIMIZE TABLE `" . $tablename['Name'] . "`");
            $this->queryDirect("ANALYZE TABLE `" . $tablename['Name'] . "`");
        }

        return $ret;
    }
    
    public function getAffectedRows()
    {
        return DB::$mysqli->affected_rows;
    }

    public function getLastError()
    {
        return DB::$mysqli->error;
    }
}
