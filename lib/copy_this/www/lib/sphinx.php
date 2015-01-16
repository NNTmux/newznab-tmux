<?php
/**
 *
 * Sphinx search extension for Newznab.
 * ====================================
 *
 * @package Sphinx
 */

if (!defined("FS_ROOT")) { define('FS_ROOT', realpath(dirname(__FILE__))); }
require_once(FS_ROOT."/../../www/config.php");
require_once(FS_ROOT."/../../www/lib/framework/db.php");
require_once(FS_ROOT."/../../www/lib/category.php");
require_once(FS_ROOT."/../../www/lib/nzb.php");
require_once(FS_ROOT."/../../www/lib/releasefiles.php");
require_once(FS_ROOT."/../../www/lib/util.php");

// Every day
define("INDEX_MERGE_FREQ", 1440);

// TODO: Make this configurable?
define("MAX_MATCHES", 10000);

/**
 * This class manages indexing and searching the full-text indexes.
 */
class Sphinx
{
    /**
     * Constructor creates the necessary variables needed later on.
     *
     * @ignore
     * @access private
     */
    function __construct()
    {
        $s = new Sites;

		$this->site = $s->get();
		$this->nzb  = new Nzb;
		$this->indexes = array("releases", "releasefiles",
		                       "releasenfo", "nzbs", "predb");
    }

    /**
     * Runs a Sphinx command.  This suppresses various lines of output from the
     * Sphinx binaries.
     *
     * @param   string  $cmd    The command to run.
     * @param   boolean $stdout If ``true`` (default), print to stdout.
     * @return  array   An array of lines returned from the popen process.
     */
    public function runCmd($cmd, $stdout=true)
    {
        $output = array();
        $handle = popen($cmd, "r");
        while ($line = fgets($handle)) {
            if (startsWith($line, "Sphinx") ||
                startsWith($line, "Copyright") ||
                startsWith($line, "\n") || startsWith($line, "using config"))
            {
                continue;
            }

            $output[] = $line;
            if ($stdout) {
                printf("%s", $line);
            }
        }
        return $output;
    }

    /**
     * Returns the next merge date as a timestamp.  This value is suitable for
     * use as the `nextmergedate` value in the database.
     */
    public function getNextMergeDate()
    {
        return strtotime("tomorrow ".$this->site->sphinxmergefreq);
    }

    /**
     * Returns the next rebuild date as a timestamp.  This value is suitable
     * for use as the `nextrebuilddate` value in the database.
     */
    public function getNextRebuildDate()
    {
        $rebuildStr = $this->site->sphinxrebuildfreq_day." "
                    . $this->site->sphinxrebuildfreq;
        $rebuildDate = strtotime($rebuildStr);
        if ($rebuildDate < time(true)) {
            // Date is in the past, pick the next one
            $rebuildStr = "next ".$rebuildStr;
            $rebuildDate = strtotime($rebuildStr);
        }
        return $rebuildDate;
    }

    /**
     * Sets the values for ``lastrebuilddate`` and ``nextrebuilddate`` in the
     * database for the given ``$index``.
     *
     * @param  string    $index              The index to update.
     * @param  int       $lastRebuildDate    The timestamp of the update.
     * @return boolean
     */
    public function setRebuildDates($index, $lastRebuildDate=0)
    {
        if (!$this->isIndexEnabled($index)) {
            // This is an invalid index or isn't enabled
            return false;
        }

        $db = new DB();

        if ($lastRebuildDate == 0) {
            $lastRebuildDate = "NOW()";
        } else {
            $lastRebuildDate = sprintf("FROM_UNIXTIME(%d)", $lastRebuildDate);
        }

        $index = $db->escapeString($index);
        $nextRebuildDate = sprintf("FROM_UNIXTIME(%d)",
                                                $this->getNextRebuildDate());

        $sql = "UPDATE sphinx "
             . "SET lastrebuilddate = %s, "
             . "nextrebuilddate = %s "
             . "WHERE name = %s";
        $db->queryDirect(sprintf($sql, $lastRebuildDate, $nextRebuildDate,
                                 $index));
        return true;
    }

    /**
     * Sets the value for ``lastmergedate`` and ``nextmergedate`` in the
     * database for the given ``$index``.  If ``$lastMergedDate`` is supplied
     * it will be used as the value for ``lastmergedate``, else ``NOW()`` will
     * used.
     *
     * @param  string    $index          The index to update.
     * @param  int       $lastMergedDate The timestamp of the update.
     * @return boolean
     */
    public function setMergeDates($index, $lastMergedDate=0)
    {
        if (!$this->isIndexEnabled($index)) {
            // This is an invalid index or isn't enabled
            return false;
        }

        $db = new DB();

        if ($lastMergedDate == 0) {
            $lastMergedDate = "NOW()";
        } else {
            $lastMergedDate = sprintf("FROM_UNIXTIME(%d)", $lastMergedDate);
        }

        $index = $db->escapeString($index);
        $nextMergeDate = sprintf("FROM_UNIXTIME(%d)",
                                            $this->getNextMergeDate());

        $sql = "UPDATE sphinx "
             . "SET lastmergedate = %s, "
             . "nextmergedate = %s "
             . "WHERE name = %s";
        $db->queryDirect(sprintf($sql, $lastMergedDate, $nextMergeDate,
                                 $index));
        return true;
    }

    /**
     * Updates the given index $index.  If $full is `true`, then the
     * 'main' index is updated, otherwise the 'delta' index is updated.
     *
     * @param   string  $index
     * @param   boolean $full
     * @return  null
     */
    public function updateIndex($index, $full=false)
    {
        $index = strtolower($index);
        if (!$this->isIndexEnabled($index)) {
            // This is an invalid index or isn't enabled
            return false;
        }

        if (!$full) {
            // We're doing a delta update instead
            $index .= "_delta";
        }

        // Path to `indexer` binary
        $indexer = $this->getSphinxBinPath("indexer");
        $cmd = sprintf("%s --rotate %s", $indexer, $index);
        $this->runCmd($cmd);

        if ($full) {
            // Since we just updated the main index, we need to update dates.
            // Notice that we also update the merge dates--we do this because a
            // rebuild is essentially a "merge" and updating these dates will
            // force the delta indexes to be "reset" on their next update, which
            // is what we want.
            $this->setMergeDates($index);
            $this->setRebuildDates($index);
        }
    }

    /**
     * Merges a 'delta' index with a 'main' index, but only if the index is
     * enabled.
     *
     * @param   string  $index          The index to merge.
     * @param   boolean $updateDates    True (default) to update merge dates.
     * @return  null
     */
    public function mergeIndex($index, $updateDates=True)
    {
        $index = strtolower($index);
        if (!$this->isIndexEnabled($index)) {
            // This is an invalid index or isn't enabled
            return false;
        }
        $indexer = $this->getSphinxBinPath("indexer");
        $cmd = sprintf("%s --rotate --merge %s %s", $indexer, $index,
                                                    $index."_delta");
        $this->runCmd($cmd);

        if ($updateDates) {
            $this->setMergeDates($index);
        }

        // TODO: Return false if the merge fails
        return true;
    }

    /**
     * Extracts all of the information about a given ``$index`` via the
     * ``indextool`` program provided by Sphinx.
     *
     * @param string    $index  The index to query.
     * @param boolean   $main   If ``false`` (default), query delta index.
     * @return  Array of information about the index.
     */
    public function indexDetails($index, $main=False) {
        $details = array(
            "enabled"     => false,
            "field_list"  => array(),
            "attr_list"   => array()
        );

        if ($this->isIndexEnabled($index)) {
            $details["enabled"] = true;

            if (!$main) {
                $index .= "_delta";
            }

            $handle = fopen($this->site->sphinxconfpath, 'r');
            if ($handle) {
                $indexPath = "";
                while ($line = fgets($handle)) {
                    $pattern = sprintf("/^[ ]*path[ ]*=[ ]*(.*%s)$/", $index);
                    preg_match($pattern, $line, $matches);
                    if ($matches) {
                        $indexPath = $matches[1];
                    }
                }

                if ($indexPath) {
                    $indexer = $this->getSphinxBinPath("indextool");
                    $cmd = sprintf("%s --dumpheader %s.sph", $indexer,
                                        $indexPath);
                    foreach($this->runCmd($cmd, false) as $line) {
                        preg_match("/^[ ]*(?P<key>[\d\w- ]+)[ ]*:[ ]*(?P<value>.+)$/", $line, $matches);
                        if ($matches) {
                            preg_match("/(?P<key>attr|field)[ ]+(?P<num>[\d]+)/", $matches["key"], $m);
                            if ($m) {
                                switch($m["key"]) {
                                    case "attr":
                                        $details["attr_list"][] = $matches["value"];
                                        break;
                                    case "field":
                                        $details["field_list"][] = $matches["value"];
                                        break;
                                }
                            } else {
                                $details[$matches["key"]] = $matches["value"];
                            }
                        }
                    }
                }
            }
        }
        return $details;
    }

    /**
     * Updates indexes.  This function does quite a lot of stuff:
     *
     *  1) Rebuilds any indexes that are due
     *  2) Merges any indexes that are due
     *  3) Updates delta indexes
     *
     * The first two items listed occur conditionally--they must be "due" in
     * order to be processed.  The last item, updating of delta indexes, occurs
     * unconditionally and will happen every time this function is called.
     */
    public function update()
    {
        $db = new DB();

        if (!$this->site->sphinxenabled) {
            // Sphinx is disabled, so we don't do anything.
            return;
        }

        // Loop over the enabled indexes and see what operations are due
        $sql = "SELECT * FROM sphinx";
        $res = $db->queryDirect($sql);
        while ($row = $db->getAssocArray($res)) {
            $index = $row["name"];

            if (!$this->isIndexEnabled($row["name"])) {
                continue;
            }

            $indexDetails = $this->indexDetails($index);
            $isMergeDueDate = (strtotime($row['nextmergedate']) <= time(true));
            $isMergeDueSize = (intval($indexDetails["total-documents"]) >=
                               intval($this->site->sphinxmergefreq_count));

            if (strtotime($row['nextrebuilddate']) <= time(true) &&
                $this->site->sphinxrebuildfreq_day != "")
            {
                // Rebuild required
                printf("Index '%s' is going to be rebuilt.\n", $index);

                // TODO: Check for success or failure.
                $this->updateIndex($index, true);
            } else if ($isMergeDueDate || $isMergeDueSize) {
                // A merge is due, print out why
                if ($isMergeDueDate) {
                    printf("Index '%s_delta' is due for merging.\n", $index);
                } else if ($isMergeDueSize) {
                    printf("Index '%s_delta' has grown above critical size ".
                           "and will be merged (contains %s records).\n",
                           $index, $indexDetails["total-documents"]);
                }

                if ($this->mergeIndex($index)) {
                    printf("Merge successful.\n");
                } else {
                    // TODO: Error message
                    printf("[Error]: Merge failed.\n");
                }
            }
        }

        printf("Updating delta indexes\n");
        foreach($this->getAllEnabledIndexes() as $index) {
            // Index `nzbs` is never updated this way because it is a RT index
            // and doesn't use the main+delta scheme
            if ($index != "nzbs") {
                printf("Updating '%s'\n", $index);
                $this->updateIndex($index);
            }
        }
    }

    /**
     * Returns the path to the Sphinx binary ``$bin``.  For example, if the
     * path to Sphinx's "indexer" binary is desired, this function can be used
     * to get the full path to it.  It is also desirable to use this function
     * as it properly sets the ``sphinx.conf`` file to use.
     *
     * @param   string $bin The Sphinx binary to run (indexer, searchd, etc.).
     * @return  string      The path to the binary.
     */
    public function getSphinxBinPath($bin)
    {
        $path = rtrim($this->site->sphinxbinpath, DIRECTORY_SEPARATOR);
        $conf = $this->site->sphinxconfpath;

        if ($path) {
            // $path is a path to a directory.
            $path .= DIRECTORY_SEPARATOR ;
        }
        $path .= $bin;

        // The '-c' sets the config file (sphinx.conf).
		$path = sprintf("\"%s\" -c \"%s\"", trim($path), $conf);
        return $path;
    }

    /**
     * Returns ``true`` if ``$index`` is enabled, ``false`` otherwise.
     *
     * @param   string  $index  The index to check.
     * @return  boolean
     */
    public function isIndexEnabled($index)
    {
        // Is Sphinx enabled?
        if (!$this->site->sphinxenabled) {
            return false;
        }

        // Only check "main" index, since the delta will be enabled
        // if main is as well.
        $index = strtolower(str_replace("_delta", "", $index));
        switch ($index) {
            case "releases":
                return true;    // Always enabled, as long as Sphinx is
            case "releasenfo":
                return (bool)$this->site->sphinxindexnfos;
            case "releasefiles":
                return (bool)$this->site->sphinxindexreleasefiles;
            case "nzbs":
                return (bool)$this->site->sphinxindexnzbs;
            case "predb":
                return (bool)$this->site->sphinxindexpredb;
            default:
                return false;
        }
    }

    /**
     * Returns an array of all the indexes that are enabled.  If Sphinx support
     * isn't enabled, then this returns an empty array.
     *
     * @param   boolean Whether or not to include the delta indexes as well.
     * @return  array   An array of string of the enabled indexes.
     */
    public function getAllEnabledIndexes($deltas=false, $exclude=array())
    {
        $enabled = array();
        foreach($this->indexes as $index) {
            if ($this->isIndexEnabled($index) && !in_array($index, $exclude)) {
                $enabled[] = $index;
                if ($deltas && $index != "nzbs") {
                    $enabled[] = $index."_delta";
                }
            }
        }
        return $enabled;
    }

    /**
     * Generates and returns a string of the of ``sphinx.conf`` file.
     * ``$sphinxDir`` should be a string representing the directory in which to
     * store the indexes.
     *
     * @param   string $sphinxDir   Path to where indexes should be stored.
     * @return  string
     */
    public function generateSphinxConf($sphinxDir="", $sphinxConf="")
    {
        /**
         * Simple "python-like" key->value string substitution:
         *
         *  // Create a string: "hi there"
         *  subst("%(a)s %(b)s", array("a" => "hi", "b" => "there"));
         *
         * @param   string    $str
         * @param   array     $dict
         * @return  string
         */
        function subst($str, $dict)
        {
            $fn  = create_function('$a', 'return "/%\\($a\\)s/";');
            $map = array_map($fn, array_keys($dict));
            return preg_replace($map, array_values($dict), $str);
        }

        $nnRoot = realpath(FS_ROOT."/../../");

        if (!$sphinxDir) {
            $sphinxDir = $nnRoot."/db/sphinxdata";
        }

        if (!$sphinxConf) {
            $sphinxConf = $sphinxDir."/sphinx.conf";
        }

        $sphinxConfFile = fopen($sphinxConf, 'w');

        $filename = $nnRoot."/misc/sphinx/sphinx.tpl";
        $handle = fopen($filename, "r");
        $conf = fread($handle, filesize($filename));
        $vars = array(
            "db_host"           => DB_HOST,
            "db_user"           => DB_USER,
            "db_pass"           => DB_PASSWORD,
            "db_name"           => DB_NAME,
            "sphinx_dir"        => $sphinxDir
        );

        fwrite($sphinxConfFile, subst($conf, $vars));
        fclose($sphinxConfFile);

        return $sphinxConf;
    }

    /**
     * Indexing function for NZB contents.  Unlink the other indexes, 'nzbs'
     * can't be indexed from MySQL data directly since the data to be indexed
     * is contained within the compress NZB files on disk.  This function
     * decompresses the NZBs in memory, parses them, extracts the useful
     * bits which are to be store in the indexer and then updates the index
     * in real time.
     *
     * If ``$startingID`` is negative, then the last successfully indexed
     * release will be identified and this will resume indexing from that ID
     * onwards.  If this is a positive value, then only releases with an ID
     * larger than or equal to this will be indexed.  In order to re-index
     * everything, pass 0.
     *
     * Returns the number of of NZBs that were successfully indexed.
     *
     * @param   int $startingID The ID of the first release to index.
     * @return  int
     *
     */
    public function indexNZBs($startingID=-1)
    {

        // TODO: Use batching
        //       See: http://sphinxsearch.com/docs/2.0.1/rt-caveats.html
        //
        if (!$this->isIndexEnabled("nzbs")) {
            return 0;
        }

        $out = fopen("php://stdout", 'w');

        // Get connection to Sphinx's MySQL interface.
        // Connect to Sphinx
		$hostport = explode (":", $this->site->sphinxserverhost);
        $sdb = mysqli_connect($hostport[0], "root", "", "", $hostport[1]);
        if (!$sdb) {
            // Couldn't connect to Sphinx.
            return false;
        }

        // Get connection to Newznab's MySQL database
        $ndb = new DB;

        // Determine which release to start with
        if ($startingID < 0) {
            $sql = "SELECT id FROM nzbs ORDER BY id DESC LIMIT 1";
            $result = mysqli_query($sdb, $sql);
            if (!$result) {
                $startingID = 0;
            } else {
                $row = mysqli_fetch_row($result);
                $startingID = (int)$row[0];
            }
        }

        // Get the number of NZBs to do
        $sql = "SELECT COUNT(ID) as num FROM releases WHERE ID >= %d";
        $row = $ndb->queryOneRow(sprintf($sql, $startingID));
        $recordCount = (int)$row["num"];

        // Start looping over the releases and build the NZB index
        printf("starting from ID %d\n", $startingID);
        $numIndexed = 0;
        $sql = "SELECT ID, guid FROM releases "
             . "WHERE ID >= %d ORDER BY ID ASC";
        $result = $ndb->queryDirect(sprintf($sql, $startingID));
        $startTime = microtime(true);
        while ($row = $ndb->getAssocArray($result)) {
            $fileNames = "";
            $fileCount = 0;
            $nzbpath = $this->nzb->NZBPath($row['guid'], $this->site->nzbpath);
            if (file_exists($nzbpath)) {
            	$nzbfile = file_get_contents('compress.zlib://'.$nzbpath);
            	$files = $this->nzb->nzbFileList($nzbfile);
            	if ($files) {
            	    foreach($files as $f) {
                	    $fileNames .= $f['title']." ";
                	    $fileCount++;
            	    }
        	    }
            }
            $sql = sprintf("REPLACE INTO nzbs VALUES (%d, %s, %d)",
                            $row['ID'], $ndb->escapeString($fileNames),
                            $fileCount);
            if (!mysqli_query($sdb, $sql)) {
                printf("error indexing NZB: %s\n", mysqli_error($sdb));
                continue;
            }
            $numIndexed++;
            $nowTime = microtime(true);
            $msg = "\rindexed %d of %s NZBs (avg: %0.2f/sec, eta: %s)\033[0K";
            $avg = $numIndexed/($nowTime-$startTime);
            $eta = ($recordCount-$numIndexed) / $avg;
            fprintf($out, $msg, $numIndexed, $recordCount, $avg, $this->secs_to_h($eta));
            fflush($out);
        }
        return $numIndexed;
    }

    /**
     * Query the indexer directly.  Returns an array of the results, unless
     * there was an error in which case ``false`` is returned.  However, if
     * Sphinx returns an "invalid query" error (1064), then an empty result
     * array is returned.  Note that an empty "result array" is not the same as
     * an empty array and will instead look like::
     *
     *      array({"_totalrows": 0})
     *
     * If  ``$lookupQuery`` is an empty string, then the results returned will
     * be the data from the index--this is not guaranteed to be the most recent
     * data that is in the MySQL database.  If you absolutely need the most
     * recent data from MySQL, then ``$lookupQuery`` should be a valid SQL
     * query that has contains "releases.ID IN (%s)".
     *
     * @param   string      $sphinxQuery    The raw SphinxQL query.
     * @param   string      $lookupQuery    The SQL to use to lookup the results.
     * @param   bool/int    $useCache    	The ttl to store the item in the cache.
     * @return  array|false
     */
    public function searchDirect($sphinxQuery, $lookupQuery="", $useCache=false)
    {
        $cache = new Cache;
		if ($useCache !== false && $cache->enabled && $cache->exists($sphinxQuery))
		{
			$ret = $cache->fetch($sphinxQuery);
			if ($ret !== false)
				return $ret;
		}

        // Connect to Sphinx
        $hostport = explode (":", $this->site->sphinxserverhost);
        $sdb = mysqli_connect($hostport[0], "root", "", "", $hostport[1]);
        if (!$sdb) {
            // Couldn't connect to Sphinx.
            return false;
        }

        // Get the results from Sphinx.
		$lev = error_reporting();
		error_reporting(0);
        $result = mysqli_query($sdb, $sphinxQuery);
		error_reporting($lev);

        $error = mysqli_error($sdb);
        // A 1064 error means that the query is invalid, so we don't care
        // about that.
        if ($error && mysqli_errno($sdb) != 1064) {
            // All other errors we will considered a failure.
            return false;
        }

        // Get the query metadata.
        $meta = array();
        $mresult = mysqli_query($sdb,"SHOW META");
        if (!$mresult) {
            return false;
        }
        while ($row = mysqli_fetch_row($mresult)) {
            $meta[$row[0]] = $row[1];
        }

        $results = array();
		if ($result)
		{
			while($row = mysqli_fetch_assoc($result)) {
				if ($lookupQuery) {
					// Save the IDs for a batch lookup.
					$results[] = $row["id"];
				} else {
					$results[] = $row;
				}
			}
		}

        if ($lookupQuery && count($results) > 0) {
            $ndb = new DB;
            $sql = sprintf($lookupQuery, implode(",", $results));
            $result = $ndb->queryDirect($sql);
			if ($result)
			{
				$results = array();
				while ($row = $ndb->getAssocArray($result)) {
					$results[] = $row;
				}
			}
        }

        $count = 0;
        if ( count($results) > 0 && array_key_exists("total", $meta) ) {
            $count = (int)$meta["total_found"];
            $results[0]["_totalrows"] = ($count > MAX_MATCHES) ? MAX_MATCHES : $count;
        }

        if ($useCache !== false && $cache->enabled)
			$cache->store($sphinxQuery, $results, $useCache);

        return $results;
    }
    /**
     * Constructs a SphinxQL query.
     *
     * @param   array   $search
     * @param   array   $cat
     * @param   int     $offset
     * @param   int     $limit
     * @param   array   $order
     * @param   int     $maxage
     * @param   array   $excludedcats
     * @param   array   $grp
     * @param   array   $indexes
     * @param   boolean $lookup
     * @param   array   $where
     * @param   string  $lookupQuery
     *
     */
    public function buildQuery($search, $cat=array(), $offset=0, $limit=100,
                           $order=array("postdate", "desc"), $maxage=-1,
                           $excludedcats=array(), $grp=array(),
                           $indexes=array(), $lookup=true, $where=array(),
                           &$lookupQuery="")
    {
        $ndb = new DB;

        $offset = intval($offset);
        $limit = intval($limit);

        if ($lookup) {
            // Since we're going to look up the data from MySQL, we don't need
            // to get all the fields from Sphinx.
            $select = "ID, name ";
        } else {
            $select = "* ";
        }

        // Create a comma separated string of indexes, but only of enabled
        // indexes
        $searchIndexes = array();
        foreach($indexes as $index) {
            if ($this->isIndexEnabled($index)) {
                $searchIndexes[] = $index;
            }
        }
        if (count($searchIndexes) == 0) {
            $searchIndexes = array_reverse($this->getAllEnabledIndexes(true));
        }
        $from = implode(", ", $searchIndexes);

        // Check to see if this is an extended query.  If it is, remove the
        // leading "!" and don't touch the rest of the query.
        if (count($search) > 0) {
            if (substr($search[0], 0, 2) == "! ") {
                /*
                    Extended Query
                 */
                $search[0] = substr($search[0], 2, strlen($search[0])-2);
            } else {
                /*
                    Basic Query
                 */

                // Check to see if the the query contains any field specifiers like:
                // "@(field1,field2)"--if not, add in the default search fields.
                foreach($search as $i => $q) {
                    if ($q && !preg_match('/@\(?\w+/i', $q)) {
                        // No field specifiers were found, so use the site-defaults.  This
                        // turns the query for "ubuntu" into something like:
                        //  "@(name,searchname) ubuntu"
                        $fields = str_replace(" ", "", $this->site->sphinxsearchfields);

                        // Remove single "-" so that Sphinx doesn't use it as an
                        // exclusion modifier
                        $q = preg_replace('/([^\-])\-/', '\1 ', $q);

                        // "basic" search syntax allows a "--" exclusion modifier,
                        // but Sphinx uses "-" as a the exclusion modifier
                        $q = str_replace("--", "-", $q);

						// always add on * to Season based searches
						$q = preg_replace('/([\. ]S\d{2})( |$)/i', '\1*\2', $q);

                        // Construct the basic query
                        $search[$i] = sprintf("@(%s) ", $fields).$q;
                    }
                }
            }
        }

        // Create the actual MATCH() query.  Per the Sphinx docs, each query
        // can only have one MATCH() statement.
        if (count($search) > 0) {
            if (!(count($search) == 1 && $search[0] == "")) {
                // If we are searching across multiple indexes, it is safer to prepend
                // "@@relaxed" to the query to ensure that Sphinx doesn't choke on
                // differences in schemas.  See:
                //      http://sphinxsearch.com/docs/2.0.2/extended-syntax.html
                if (count($searchIndexes) > 1 && strpos($search[0], "@@relaxed") !== 0) {
                    // We are searching across multiple indexes and the term
                    // "@@relaxed" wasn't found at the beginning,
                    $search[0] = "@@relaxed ".$search[0];
                }

                $where[] = sprintf("MATCH(%s)", $ndb->escapeString(implode(' ', $search)));
            }
        }

        // Build the category query.  If any of the categories are a "parent"
        // then we need to explicitly include their children as well.
        $categoryIDs = array();
        if (count($cat) > 0 && $cat[0] != -1) {
            foreach ($cat as $category) {
                if ($category != -1) {
                    if (!in_array($category, $categoryIDs)) {
                        $categoryIDs[] = $category;
                    }

                    $categ = new Category();
                    if ($categ->isParent($category)) {
                        // Inlcude all children
                        $children = $categ->getChildren($category);
                        foreach ($children as $child) {
                            if (!in_array($child["ID"], $categoryIDs)) {
                                $categoryIDs[] = $child["ID"];
                            }
						}
                    }
                }
            }
        }

        // Only include the category filter if we created one.
        if ($categoryIDs) {
            $where[] = sprintf("categoryID IN (%s)", implode(",", $categoryIDs));
        }

        // Filter on postdate.
        if ($maxage > 0) {
            // TODO: This probably isn't the best way to do this...
            $where[] = sprintf("postdate >= %d", time()-($maxage*86400));
        }

        // Categories to exclude.
        if (count($excludedcats) > 0) {
            $where[] = sprintf("categoryID NOT IN (%s)", implode(",",
                                                            $excludedcats));
        }

        // Usenet groups to include.
        if ($grp) {
            foreach($grp as $i => $g) {
                if (strpos($g, "a.b.") !== false) {
                    $sql = sprintf("SELECT ID FROM groups "
                                   ."WHERE name = %s",
                                   $ndb->escapeString(str_replace("a.b.",
                                                        "alt.binaries.", $g)));
                    $row = $ndb->queryOneRow($sql);
                    $grp[$i] = $row["ID"];
                }
            }
            $where[] = sprintf("groupID IN (%s)", implode(",", $grp));
        }

        // Order the results.
        // TODO: implement better default ordering behavior.
        $orderby = "";
        if (count($order) == 2) {
            $orderby = sprintf("ORDER BY %s %s", $order[0], $order[1]);

            // Add in the ORDER BY parameters if there is a $lookupQuery
            if ($lookupQuery) {
                $lookupQuery = $lookupQuery." ".$orderby;
            }
        }

        // Build the full query.
        $q = sprintf("SELECT %s FROM %s ".(!empty($where)?"WHERE ":"")." %s %s LIMIT %d,%d",
                        $select, $from, implode(" AND ", $where), $orderby,
                        $offset, $limit);

        // Sphinx imposes a 1000 result limit (max_matches) by default, so in
        // order to access results beyond this, we need to tell it to do so
        if ($offset >= 1000) {
            $q .= sprintf(" OPTION max_matches=%d", $offset+$limit);
        }

        return $q;
    }

    /**
     * Search ``$indexes`` for ``$search`` term(s).  ``$search`` should be a
     * string containing a valid Sphinx query, see `query syntax`_ for
     * examples.  The default behavior is to query the indexes for release IDs
     * and then return the releases from MySQL, but if ``$lookup`` is ``false``
     * then the data from the indexes will be returned.  This will bypass MySQL
     * all together, but may result in stale data.
     *
     * .. _query syntax: http://sphinxsearch.com/docs/2.0.1/extended-syntax.html
     *
     * @param   string          $search         Search term to query for.
     * @param   array           $cat            Category IDs to filter for.
     * @param   int             $offset         Offset from first result.
     * @param   int             $limit          Number of results to return.
     * @param   string          $orderby        Field to order results by.
     * @param   int             $maxage         Don't include results older than this (in days).
     * @param   array           $excludedcats   Category IDs to exclude.
     * @param   array           $grp            Group IDs to filter for.
     * @param   string|array    $indexes        Only search within certain indexes.
     * @param   boolean         $lookup         Retrieve latest data from database.
     * @return  array|false
     */
    public function search($search, $cat=array(), $offset=0, $limit=1000,
                           $order=array("postdate", "desc"), $maxage=-1,
                           $excludedcats=array(), $grp=array(),
                           $indexes=array(), $lookup=true, $minsize=-1, $maxsize=-1)
    {
        if (count($indexes) == 0) {
            // Remove predb from the default list of indexes to search
            $indexes = $this->getAllEnabledIndexes(true, array('predb'));
        }

        $lookupQuery = "";
        if ($lookup) {
            $lookupQuery .= "SELECT releases.*, "
                          . "CONCAT(cp.title, ' > ', c.title) AS category_name, "
                          . "CONCAT(cp.ID, ',', c.ID) AS category_ids, "
                          . "groups.name as group_name, rn.ID AS nfoID, "
                          . "re.releaseID as reID, cp.ID AS categoryParentID, "
                          . "pre.ctime, pre.nuketype, "
                          . "COALESCE(movieinfo.ID, 0) AS movieinfoID "
                          . "FROM releases "
                          . "LEFT OUTER JOIN movieinfo "
                          . "ON movieinfo.imdbID = releases.imdbID "
                          . "LEFT OUTER JOIN releasevideo re "
                          . "ON re.releaseID = releases.ID "
                          . "LEFT OUTER JOIN releasenfo rn "
                          . "ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL "
                          . "LEFT OUTER JOIN groups "
                          . "ON groups.ID = releases.groupID "
                          . "LEFT OUTER JOIN category c "
                          . "ON c.ID = releases.categoryID "
                          . "LEFT OUTER JOIN category cp "
                          . "ON cp.ID = c.parentID "
                          . "LEFT OUTER JOIN predb pre "
                          . "ON pre.ID = releases.preID "
                          . "WHERE releases.passwordstatus <= (SELECT value "
                          .      "FROM site WHERE setting='showpasswordedrelease') "
                          . "AND releases.ID IN (%s)";
        }

		$where = array();
		if ($minsize != -1) {
			$where[] = sprintf("size > %d ", $minsize);
		}
		if ($maxsize != -1) {
		   $where[] = sprintf("size < %d ", $maxsize);
		}

        $sphinxQuery = $this->buildQuery(array($search), $cat, $offset, $limit,
                                         $order, $maxage, $excludedcats, $grp,
                                         $indexes, $lookup, $where, $lookupQuery);
        return $this->searchDirect($sphinxQuery, $lookupQuery, 180);
    }

    /**
     * Returns an array of all the releases matching the given query.  This
     * presents the same interface as :php:meth:`Releases::searchbyRageId`.
     */
    public function searchbyRageId($rageId, $series="", $episode="", $offset=0,
                                   $limit=100, $name="", $cat=array(-1),
                                   $maxage=-1, $indexes=array(), $lookup=true)
    {
        $db = new DB();
        $order = array("postdate", "desc");
        $search = array($name);
        if ($series != "") {
			//
			// Exclude four digit series, which will be the year 2010 etc
			//
			if (is_numeric($series) && strlen($series) != 4) {
			    $series = sprintf('S%02d', $series);
			}
			$search[] = sprintf("@season %s", $db->escapeString($series));
		}

		if ($episode != "") {
			if (is_numeric($episode)) {
			    $episode = sprintf('E%02d', $episode);
			}
			$search[] = sprintf("@episode %s", $db->escapeString($episode));
		}

		$where = array();
		if ($rageId != "-1") {
		    $where[] = "rageid = ".$rageId;
		}

		if (count($indexes) == 0) {
            // Remove predb from the default list of indexes to search
            $indexes = $this->getAllEnabledIndexes(true, array('predb'));
        }

        $lookupQuery = "";
        if ($lookup) {
            $lookupQuery .= "SELECT releases.*, "
                          . "CONCAT(cp.title, ' > ', c.title) AS category_name, "
                          . "CONCAT(cp.ID, ',', c.ID) AS category_ids, "
                          . "groups.name AS group_name, rn.ID AS nfoID, "
                          . "re.releaseID AS reID "
                          . "FROM releases "
                          . "LEFT OUTER JOIN category c "
                          . "ON c.ID = releases.categoryID "
                          . "LEFT OUTER JOIN groups "
                          . "ON groups.ID = releases.groupID "
                          . "LEFT OUTER JOIN releasevideo re "
                          . "ON re.releaseID = releases.ID "
                          . "LEFT OUTER JOIN releasenfo rn "
                          . "ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL "
                          . "LEFT OUTER JOIN category cp "
                          . "ON cp.ID = c.parentID "
                          . "WHERE releases.passwordstatus <= (SELECT value "
                          .      "FROM site WHERE setting='showpasswordedrelease') "
                          . "AND releases.ID IN (%s)";
        }
        $sphinxQuery = $this->buildQuery($search, $cat, $offset, $limit, $order,
                                         $maxage, array(), array(), $indexes,
                                         $lookup, $where, $lookupQuery);
        return $this->searchDirect($sphinxQuery, $lookupQuery, 180);
    }

    /**
     * Returns an array of all the releases matching the given query.  This
     * presents the same interface as :php:meth:`Releases::searchbyImdbId`.
     */
    public function searchbyImdbId($imdbId, $offset=0, $limit=100, $name="",
                                   $cat=array(-1), $genre="", $maxage=-1,
                                   $indexes=array(), $lookup=true)
    {
        $db = new DB();
        $search = array();
        $order = array("postdate", "desc");
        $where = array();
        if ($imdbId != "-1" && is_numeric($imdbId)) {
			// Pad ID with zeros just in case.
			$imdbId = str_pad($imdbId, 7, "0", STR_PAD_LEFT);
			$where[] = sprintf("imdbid = %d", $imdbId);
		} else {
			$where[] = sprintf("imdbid != 0", $imdbId);
		}

		if ($genre != "") {
		    $search[] = sprintf("@movieinfo_genre %s", $db->escapeString($genre));
		}

		if (count($indexes) == 0) {
            // Remove predb from the default list of indexes to search
            $indexes = $this->getAllEnabledIndexes(true, array('predb'));
        }

        $lookupQuery = "";
        if ($lookup) {
            $lookupQuery .= "SELECT releases.*, "
                          . "movieinfo.title AS moi_title, "
                          . "movieinfo.tagline AS moi_tagline, "
                          . "movieinfo.rating AS moi_rating, "
                          . "movieinfo.plot AS moi_plot, "
                          . "movieinfo.year AS moi_year, "
                          . "movieinfo.genre AS moi_genre, "
                          . "movieinfo.director AS moi_director, "
                          . "movieinfo.actors AS moi_actors, "
                          . "movieinfo.cover AS moi_cover, "
                          . "movieinfo.backdrop AS moi_backdrop, "
                          . "CONCAT(cp.title, ' > ', c.title) AS category_name, "
                          . "CONCAT(cp.ID, ',', c.ID) AS category_ids, "
                          . "groups.name AS group_name, "
                          . "rn.ID AS nfoID FROM releases "
                          . "LEFT OUTER JOIN groups "
                          . "ON groups.ID = releases.groupID "
                          . "LEFT OUTER JOIN category c "
                          . "ON c.ID = releases.categoryID "
                          . "LEFT OUTER JOIN releasenfo rn "
                          . "ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL "
                          . "LEFT OUTER JOIN category cp "
                          . "ON cp.ID = c.parentID "
                          . "LEFT OUTER JOIN movieinfo "
                          . "ON releases.imdbID = movieinfo.imdbID "
                          . "WHERE releases.passwordstatus <= (SELECT value "
                          .      "FROM site WHERE setting='showpasswordedrelease') "
                          . "AND releases.ID IN (%s)";
        }
        $sphinxQuery = $this->buildQuery($search, $cat, $offset, $limit, $order,
                                         $maxage, array(), array(), $indexes,
                                         $lookup, $where, $lookupQuery);
        return $this->searchDirect($sphinxQuery, $lookupQuery, 180);
    }

    /**
     * Returns an array of all the releases matching the given query.  This
     * presents the same interface as :php:meth:`Releases::searchAudio`.
     */
    public function searchAudio($artist, $album, $label, $track, $year,
                                $genre=array(-1), $offset=0, $limit=100,
                                $cat=array(-1), $maxage=-1, $indexes=array(),
                                $lookup=true)
    {
        $db = new DB();
        $search = array();
        $where = array();
        $order = array("postdate", "desc");
        if ($artist != "") {
            $search[] = sprintf("@musicinfo_artist %s", $db->escapeString($artist));
        }

		if ($album != "") {
		    $search[] = sprintf("@musicinfo_title %s", $db->escapeString($album));
		}

		if ($label != "") {
		    $search[] = sprintf("@musicinfo_publisher %s", $db->escapeString($label));
		}

		if ($track != "") {
		    $search[] = sprintf("@musicinfo_tracks %s", $db->escapeString($track));
		}

		if ($year != "") {
		    $where[] = sprintf("musicinfo_year = %d", $year);
		}

		if (count($genre) > 0 && $genre[0] != -1) {
		    $where[] = sprintf("musicinfo_genreID IN (%s)", implode(",", $genre));
		}

		if (count($indexes) == 0) {
            // Remove predb from the default list of indexes to search
            $indexes = $this->getAllEnabledIndexes(true, array('predb'));
        }

        $lookupQuery = "";
        if ($lookup) {
            $lookupQuery .= "SELECT releases.*, "
                          . "musicinfo.cover AS mi_cover, "
                          . "musicinfo.review AS mi_review, "
                          . "musicinfo.tracks AS mi_tracks, "
                          . "musicinfo.publisher AS mi_publisher, "
                          . "musicinfo.title AS mi_title, "
                          . "musicinfo.artist AS mi_artist, "
                          . "genres.title AS music_genrename, "
                          . "CONCAT(cp.title, ' > ', c.title) AS category_name, "
                          . "CONCAT(cp.ID, ',', c.ID) AS category_ids, "
                          . "groups.name AS group_name, "
                          . "rn.ID AS nfoID FROM releases "
                          . "LEFT OUTER JOIN musicinfo "
                          . "ON musicinfo.ID = releases.musicinfoID "
                          . "LEFT JOIN genres "
                          . "ON genres.ID = musicinfo.genreID "
                          . "LEFT OUTER JOIN groups "
                          . "ON groups.ID = releases.groupID "
                          . "LEFT OUTER JOIN category c "
                          . "ON c.ID = releases.categoryID "
                          . "LEFT OUTER JOIN releasenfo rn "
                          . "ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL "
                          . "LEFT OUTER JOIN category cp "
                          . "ON cp.ID = c.parentID "
                          . "WHERE releases.passwordstatus <= (SELECT value "
                          .      "FROM site WHERE setting='showpasswordedrelease') "
                          . "AND releases.ID IN (%s)";
        }
        $sphinxQuery = $this->buildQuery($search, $cat, $offset, $limit, $order,
                                         $maxage, array(), array(), $indexes,
                                         $lookup, $where, $lookupQuery);
        return $this->searchDirect($sphinxQuery, $lookupQuery, 180);
    }

    /**
     * Returns an array of all the releases matching the given query.  This
     * presents the same interface as :php:meth:`Releases::searchBook`.
     */
    public function searchBook($author, $title, $offset=0, $limit=100,
                               $maxage=-1, $indexes=array(), $lookup=true)
    {
        $db = new DB();
        $order = array("postdate", "desc");
        $search = array();
        if ($author != "") {
            $search[] = sprintf("@bookinfo_author %s", $db->escapeString($author));
        }

		if ($title != "") {
		    $search[] = sprintf("@bookinfo_title %s", $db->escapeString($title));
		}

		if (count($indexes) == 0) {
            // Remove predb from the default list of indexes to search
            $indexes = $this->getAllEnabledIndexes(true, array('predb'));
        }

		$lookupQuery = "";
		if ($lookup) {
		    $lookupQuery .= "SELECT releases.*, "
		                  . "bookinfo.cover AS bi_cover, "
		                  . "bookinfo.review AS bi_review, "
		                  . "bookinfo.publisher AS bi_publisher, "
		                  . "bookinfo.pages AS bi_pages, "
		                  . "bookinfo.publishdate AS bi_publishdate, "
		                  . "bookinfo.title AS bi_title, "
		                  . "bookinfo.author AS bi_author, "
		                  . "genres.title AS book_genrename, "
		                  . "concat(cp.title, ' > ', c.title) AS category_name, "
		                  . "concat(cp.ID, ',', c.ID) AS category_ids, "
		                  . "groups.name AS group_name, "
		                  . "rn.ID AS nfoID "
		                  . "FROM releases "
		                  . "LEFT OUTER JOIN bookinfo "
		                  . "ON bookinfo.ID = releases.bookinfoID "
		                  . "LEFT JOIN genres "
		                  . "ON genres.ID = bookinfo.genreID "
		                  . "LEFT OUTER JOIN groups "
		                  . "ON groups.ID = releases.groupID "
		                  . "LEFT OUTER JOIN category c "
		                  . "ON c.ID = releases.categoryID "
		                  . "LEFT OUTER JOIN releasenfo rn "
		                  . "ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL "
		                  . "LEFT OUTER JOIN category cp "
		                  . "ON cp.ID = c.parentID "
		                  . "WHERE releases.passwordstatus <= (SELECT value "
		                  .     "FROM site WHERE setting='showpasswordedrelease')"
		                  . "AND releases.ID IN (%s)";
		}

		$sphinxQuery = $this->buildQuery($search, array(-1), $offset, $limit,
		                                 $order, $maxage, array(), array(),
		                                 $indexes, $lookup, array(), $lookupQuery);
		return $this->searchDirect($sphinxQuery, $lookupQuery, 180);
    }

    /**
     * Returns an array of all the releases matching the given query.  This
     * presents the same interface as :php:meth:`Releases::searchbyAnidbId`.
     */
    public function searchbyAnidbId($anidbID, $epno='', $offset=0, $limit=100,
                                    $name='', $maxage=-1, $indexes=array(),
                                    $lookup=true)
    {
        $db = new DB();
        $where = array();
        $search = array();
        $order = array("postdate", "desc");
        if ($anidbID > -1) {
            $where[] = sprintf("anidbid = %d", $anidbID);
        }

        if (is_numeric($epno)) {
			$search[] = sprintf("@episode %s", $db->escapeString($epno));
		}

		if (count($indexes) == 0) {
            // Remove predb from the default list of indexes to search
            $indexes = $this->getAllEnabledIndexes(true, array('predb'));
        }

		$lookupQuery = "";
		if ($lookup) {
		    $lookupQuery .= "SELECT releases.*, "
		                  . "CONCAT(cp.title, ' > ', c.title) AS category_name, "
		                  . "CONCAT(cp.ID, ',', c.ID) AS category_ids, "
		                  . "groups.name AS group_name, "
		                  . "rn.ID AS nfoID "
			              . "FROM releases "
			              . "LEFT OUTER JOIN category c "
			              . "ON c.ID = releases.categoryID "
			              . "LEFT OUTER JOIN groups "
			              . "ON groups.ID = releases.groupID "
			              . "LEFT OUTER JOIN releasenfo rn "
			              . "ON rn.releaseID = releases.ID AND rn.nfo IS NOT NULL "
			              . "LEFT OUTER JOIN category cp "
			              . "ON cp.ID = c.parentID "
			              . "WHERE releases.passwordstatus <= (SELECT value "
			              .     "FROM site WHERE setting='showpasswordedrelease')"
			              . "AND releases.ID IN (%s)";
		}
		$sphinxQuery = $this->buildQuery($search, array(-1), $offset, $limit,
		                                 $order, $maxage, array(), array(),
		                                 $indexes, $lookup, $where, $lookupQuery);
		return $this->searchDirect($sphinxQuery, $lookupQuery, 180);
    }

    /**
     * Get predb rows by limit and filter.  This presents the same interface as
     * :php:meth:`PreDB::getPreRange`.
     */
    public function getPreRange($start=0, $num, $dirname='', $category='')
    {
        $db = new DB();
        $dirname = empty($category) ? $dirname : $dirname." @category =".$category;
        $sphinxQuery = sprintf("SELECT id "
                               ."FROM predb, predb_delta "
                               ."WHERE MATCH(%s) "
                               ."ORDER BY ctime DESC "
                               ."LIMIT %d,%d",
                               $db->escapeString($dirname),
                               $start, $num);
        $lookupQuery = "SELECT predb.*, r.guid "
                     . "FROM predb "
                     . "LEFT OUTER JOIN releases r "
                     . "ON r.preID = predb.ID "
                     . "WHERE predb.ID IN (%s) "
                     . "ORDER BY predb.ctime DESC";
        return $this->searchDirect($sphinxQuery, $lookupQuery, 120);
    }

    /**
     * Get count of all predb rows that match the query.  This presents the
     * same interface as :php:meth:`PreDB::getPreCount`.  If there is an issue
     * running the query (i.e. if the query fails), then this returns -1.
     *
     * @param   string  $dirname    The query; this is passed into MATCH().
     * @param   string  $category   The category to limit to.
     * @return  int                 The number of results, or -1 on error.
     */
    public function getPreCount($dirname='', $category='')
    {
        $results = $this->getPreRange(0, 1, $dirname, $category);
        if (is_array($results)) {
            return $results[0]['_totalrows'];
        }
        return -1;
    }

    /**
     * Convert seconds to human readable text.  From:
     * http://csl.sublevel3.org/php-secs-to-human-text/
     *
     * @ignore
     * @param   int     $secs
     * @return  string
     */
    public function secs_to_h($secs)
    {
        $units = array(
                "week"   => 7*24*3600,
                "day"    =>   24*3600,
                "hour"   =>      3600,
                "minute" =>        60,
        );

    	// specifically handle zero
        if ( $secs == 0 ) return "0 seconds";
        $s = "";
        foreach ( $units as $name => $divisor ) {
            if ( $quot = intval($secs / $divisor) ) {
                $s .= "$quot $name";
                $s .= (abs($quot) > 1 ? "s" : "") . ", ";
                $secs -= $quot * $divisor;
            }
        }

        return substr($s, 0, -2);
    }
}