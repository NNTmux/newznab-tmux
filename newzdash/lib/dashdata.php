<?php
use newznab\db\Settings;

class DashData
{

	public function time_elapsed($secs)
	{

		$d = [];
		$d[0] = [1, "sec"];
		$d[1] = [60, "min"];
		$d[2] = [3600, "hr"];
		$d[3] = [86400, "day"];
		$d[4] = [31104000, "yr"];

		$w = [];

		$return = '';
		$now = time();
		$diff = ($now - ($secs >= $now ? $secs - 1 : $secs));
		$secondsLeft = $diff;

		for ($i = 4; $i > -1; $i--) {
			$w[$i] = intval($secondsLeft / $d[$i][0]);
			$secondsLeft -= ($w[$i] * $d[$i][0]);
			if ($w[$i] != 0) {
				$return .= $w[$i] . " " . $d[$i][1] . (($w[$i] > 1) ? 's' : '') . " ";
			}
		}
		return $return;
	}

	/**
	 * getLastGroupUpdate
	 */
	public function getLastGroupUpdate()
	{
		$sql = sprintf("SELECT UNIX_TIMESTAMP(last_updated) AS age FROM groups ORDER BY age DESC LIMIT 1");
		$pdo = new Settings();
		$data = $pdo->queryOneRow($sql);
		$age_of_package = DashData::time_elapsed($data['age']);


		printf('<span class="icon32 icon-blue icon-clock"></span>
			<div>Last Group Update</div>
			<div>%s ago</div>', $age_of_package
		);
	}

	/**
	 * getLastBinaryAdded
	 */
	public function getLastBinaryAdded()
	{
		/*
		$sql=sprintf("select relname,dateadded from binaries order by dateadded desc limit 0,5");
		$pdo = new Settings();
		$data = $pdo->queryOneRow($sql);
		*/
		$sql = sprintf("SELECT UNIX_TIMESTAMP(dateadded) AS age FROM binaries ORDER BY id DESC LIMIT 1");
		$pdo = new Settings();
		$data = $pdo->queryOneRow($sql);
		$age_of_package = DashData::time_elapsed($data['age']);
		printf('<span class="icon32 icon-blue icon-clock"></span>
			<div>Last Binary Added</div>
			<div>%s ago</div>', $age_of_package
		);
	}

	/**
	 * getLastReleaseCreated
	 */
	public function getLastReleaseCreated()
	{
		$sql = sprintf("SELECT UNIX_TIMESTAMP(adddate) AS age FROM releases ORDER BY id DESC LIMIT 1");
		$pdo = new Settings();
		$data = $pdo->queryOneRow($sql);
		$age_of_package = DashData::time_elapsed($data['age']);

		printf('<span class="icon32 icon-blue icon-clock"></span>
			<div>Last Release Created</div>
			<div>%s ago</div>', $age_of_package
		);
	}


	/**
	 * getGitInfo
	 */
	public function getGitInfo()
	{

		if (file_exists(NN_ROOT . '.git/HEAD')) {
			$stringfromfile = file(NN_ROOT . '.git/HEAD', FILE_USE_INCLUDE_PATH);

			$stringfromfile = $stringfromfile[0]; //get the string from the array

			$explodedstring = explode("/", $stringfromfile); //seperate out by the "/" in the string

			$branchname = $explodedstring[2]; //get the one that is always the branch name
			$branchname = trim($branchname);

			if (file_exists(NN_ROOT . '.git/refs/heads/' . $branchname)) {
				$gitversion = file_get_contents(NN_ROOT . '.git/refs/heads/' . $branchname);
			} else {
				$gitversion = "unknown";
			}

			printf('<span class="icon32 icon-blue icon-gear"></span>
			    <div>Branch: %s</div>
			    <div>Revision: %s</div>', $branchname, $gitversion
			);
		} else {
			printf('<span class="icon32 icon-blue icon-gear"></span>
			    <div>Branch: %s</div>
			    <div>Revision: %s</div>', "unknown", "unknown"
			);
		}
	}

	/**
	 * getDatabaseInfo
	 */
	public function getDatabaseInfo()
	{
		$sql = sprintf("select * from settings where setting = 'sqlpatch'");
		$pdo = new Settings();
		$data = $pdo->queryOneRow($sql);

		printf('<span class="icon32 icon-blue icon-gear"></span>
			<div>Database Version: %s</div>', $data['value']
		);
	}

	/**
	 * count of releases
	 */
	public function getReleaseCount()
	{
		$r = new Releases;
		$total_releases = $r->getCount();

		printf('<span class="icon32 icon-blue icon-star-on"></span>
			<div>Total Releases</div>
			<div>%s</div>', $total_releases
		);
	}

	/**
	 * count of releases
	 */
	public function getNewestRelease()
	{
		$pdo = new Settings();
		$newest = $pdo->queryOneRow(sprintf('SELECT searchname  AS newestrelname FROM releases ORDER BY id DESC LIMIT 1'));

		printf('<span class="icon32 icon-blue icon-star-on"></span>
			<div>Newest Release Added</div>
			<div>%s</div>', $newest['newestrelname']
		);
	}

	public function getActiveGroupCount()
	{
		$g = new Groups;
		$active_groups = $g->getCount("", true);

		printf('<span class="icon32 icon-blue icon-comment"></span>
			<div>Active Groups</div>
			<div>%s</div>', $active_groups
		);
	}

	public function getPendingProcessingCount()
	{
		$pdo = new Settings();

		$sql_query = sprintf('select count(*) as todo from releases where (bookinfoid IS NULL AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0 and categoryid IN (7010, 7020, 7040, 7060)) OR
		(consoleinfoid IS NULL AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0 and categoryid in ( select id from category where parentid = 1000 AND categoryid != 1050)) OR
		(imdbid IS NULL AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0 and categoryid in ( select id from category where parentid = 2000 AND categoryid != 2020)) OR
		(musicinfoid IS NULL AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0 and categoryid in ( select id from category where parentid = 3000 AND categoryid != 3050)) OR
		(rageid = -1 AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0 and categoryid in ( select id from category where parentid = 5000  AND categoryid != 5050)) OR
		(xxxinfo_id = 0 AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0 and categoryid in (select id from category where parentid = 6000 AND categoryid != 6070)) OR
		(gamesinfo_id = 0 AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0 and categoryid = 4050)');

		$data = $pdo->query($sql_query);
		$total = $data[0]['todo'];

		printf('<span class="icon32 icon-blue icon-star-off"></span>
			<div>Pending Processing</div>
			<div>%s</div>', $total
		);
	}
}