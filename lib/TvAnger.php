<?php
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/category.php");
require_once(WWW_DIR . "/lib/Tmux.php");
require_once(WWW_DIR . "/lib/util.php");
require_once(WWW_DIR . "/lib/ColorCLI.php");
require_once("TraktTv.php");

class TvAnger
{
	const APIKEY = '7FwjZ8loweFcOhHfnU3E';
	const MATCH_PROBABILITY = 75;

	/**
	 * @var ColorCLI objct
	 */
	public $c;

	/**
	 * @var Database object
	 */
	public $db;

	public $echooutput;
	public $rageqty;
	public $showInfoUrl = 'http://www.tvrage.com/shows/id-';
	public $showQuickInfoURL = 'http://services.tvrage.com/tools/quickinfo.php?show=';
	public $xmlFullSearchUrl = 'http://services.tvrage.com/feeds/full_search.php?show=';
	public $xmlShowInfoUrl = 'http://services.tvrage.com/feeds/showinfo.php?sid=';
	public $xmlFullShowInfoUrl = 'http://services.tvrage.com/feeds/full_show_info.php?sid=';
	public $xmlEpisodeInfoUrl;
	public $xmlFullScheduleUrl = 'http://services.tvrage.com/feeds/fullschedule.php?country=';

	function __construct($echooutput = false)
	{
		$this->db = new DB();
		$s = new Sites();
		$site = $s->get();
		$t = new Tmux();
		$tmux = $t->get();
		$this->rageqty = (!empty($tmux->maxrageprocessed)) ? $tmux->maxrageprocessed : 75;
		$this->echooutput = $echooutput;
		$this->c = new ColorCLI();
		$this->util = new Utility();

		$this->xmlEpisodeInfoUrl =
			"http://services.tvrage.com/myfeeds/episodeinfo.php?key=" . TvAnger::APIKEY;
	}

	public function getByID($id)
	{
		return $this->db->queryOneRow(sprintf("SELECT * FROM tvrage WHERE ID = %d", $id));
	}

	public function getByRageID($id)
	{
		return $this->db->query(sprintf("SELECT * FROM tvrage WHERE rageID = %d", $id));
	}

	public function getByTitle($title)
	{
		// Set string to differentiate between mysql and PG for string replacement matching operations
		$string = (DB_TYPE === 'mysql' ? '"\'"' : "E'\''");

		// Check if we already have an entry for this show.
		$res = $this->db->queryOneRow(sprintf("SELECT rageID FROM tvrage WHERE LOWER(releasetitle) = LOWER(%s)", $this->db->escapeString($title)));
		if (isset($res['rageID'])) {
			return $res['rageID'];
		}

		$title2 = str_replace(' and ', ' & ', $title);
		if ($title != $title2) {
			$res = $this->db->queryOneRow(sprintf("SELECT rageID FROM tvrage WHERE LOWER(releasetitle) = LOWER(%s)", $this->db->escapeString($title2)));
			if (isset($res['rageID'])) {
				return $res['rageID'];
			}
			$pieces = explode(' ', $title2);
			$title4 = '%';
			foreach ($pieces as $piece) {
				$title4 .= str_replace(array("'", "!"), "", $piece) . '%';
			}
			$res = $this->db->queryOneRow(sprintf("SELECT rageID FROM tvrage WHERE replace(replace(releasetitle, %s, ''), '!', '') LIKE %s", $string, $this->db->escapeString($title4)));
			if (isset($res['rageID'])) {
				return $res['rageID'];
			}
		}

		// Some words are spelled correctly 2 ways
		// example theatre and theater
		$title3 = str_replace('er', 're', $title);
		if ($title != $title3) {
			$res = $this->db->queryOneRow(sprintf("SELECT rageID FROM tvrage WHERE LOWER(releasetitle) = LOWER(%s)", $this->db->escapeString($title3)));
			if (isset($res['rageID'])) {
				return $res['rageID'];
			}
			$pieces = explode(' ', $title3);
			$title4 = '%';
			foreach ($pieces as $piece) {
				$title4 .= str_replace(array("'", "!"), "", $piece) . '%';
			}
			$res = $this->db->queryOneRow(sprintf("SELECT rageID FROM tvrage WHERE replace(replace(releasetitle, %s, ''), '!', '') LIKE %s", $string, $this->db->escapeString($title4)));
			if (isset($res['rageID'])) {
				return $res['rageID'];
			}
		}

		// If there was not an exact title match, look for title with missing chars
		// example release name :Zorro 1990, tvrage name Zorro (1990)
		$pieces = explode(' ', $title);
		$title4 = '%';
		foreach ($pieces as $piece) {
			$title4 .= str_replace(array("'", "!"), "", $piece) . '%';
		}
		$res = $this->db->queryOneRow(sprintf("SELECT rageID FROM tvrage WHERE replace(replace(releasetitle, %s, ''), '!', '') LIKE %s", $string, $this->db->escapeString($title4)));
		if (isset($res['rageID'])) {
			return $res['rageID'];
		}

		return false;
	}

	public function countryCode($country)
	{
		if (!is_array($country) && strlen($country) > 2) {
			$code = $this->db->queryOneRow('SELECT code FROM country WHERE LOWER(name) = LOWER('
				. $this->db->escapeString($country) . ')'
			);
			if (isset($code['code'])) {
				return $code['code'];
			}
		}

		return $country;
	}

	public function add($rageid, $releasename, $desc, $genre, $country, $imgbytes)
	{
		$releasename = str_replace(array('.', '_'), array(' ', ' '), $releasename);
		$country = $this->countryCode($country);

		if ($rageid != -2) {
			$ckid = $this->db->queryOneRow('SELECT ID FROM tvrage WHERE rageID = ' . $rageid);
		} else {
			$ckid = $this->db->queryOneRow('SELECT ID FROM tvrage WHERE releasetitle = ' . $this->db->escapeString($releasename));
		}

		if (DB_TYPE === 'mysql') {
			if (!isset($ckid['ID'])) {
				$this->db->queryExec(sprintf('INSERT INTO tvrage (rageID, releasetitle, description, genre, country, createddate, imgdata) VALUES (%s, %s, %s, %s, %s, NOW(), %s)', $rageid, $this->db->escapeString($releasename), $this->db->escapeString(substr($desc, 0, 10000)), $this->db->escapeString(substr($genre, 0, 64)), $this->db->escapeString($country), $this->db->escapeString($imgbytes)));
			} else {
				$this->db->queryExec(sprintf('UPDATE tvrage SET releasetitle = %s, description = %s, genre = %s, country = %s, createddate = NOW(), imgdata = %s WHERE ID = %d', $this->db->escapeString($releasename), $this->db->escapeString(substr($desc, 0, 10000)), $this->db->escapeString(substr($genre, 0, 64)), $this->db->escapeString($country), $this->db->escapeString($imgbytes), $ckid['ID']));
			}
		} else {
			if (!isset($ckid['ID'])) {
				$id = $this->db->queryInsert(sprintf('INSERT INTO tvrage (rageID, releasetitle, description, genre, country, createddate) VALUES (%d, %s, %s, %s, %s, NOW())', $rageid, $this->db->escapeString($releasename), $this->db->escapeString(substr($desc, 0, 10000)), $this->db->escapeString(substr($genre, 0, 64)), $this->db->escapeString($country)));
			} else {
				$id = $ckid['ID'];
				$this->db->queryExec(sprintf('UPDATE tvrage SET releasetitle = %s, description = %s, genre = %s, country = %s, createddate = NOW() WHERE ID = %d', $this->db->escapeString($releasename), $this->db->escapeString(substr($desc, 0, 10000)), $this->db->escapeString(substr($genre, 0, 64)), $this->db->escapeString($country), $id));
			}
			if ($imgbytes != '') {
				$path = WWW_DIR . 'covers/preview/' . $id . '.jpg';
				if (file_exists($path)) {
					unlink($path);
				}
				$check = file_put_contents($path, $imgbytes);
				if ($check !== false) {
					$this->db->queryExec("UPDATE tvrage SET imgdata = 'x' WHERE ID = " . $id);
					chmod($path, 0755);
				}
			}
		}
	}

	public function update($id, $rageid, $releasename, $desc, $genre, $country, $imgbytes)
	{
		$country = $this->countryCode($country);
		if (DB_TYPE === 'mysql') {
			if ($imgbytes != '') {
				$imgbytes = ', imgdata = ' . $this->db->escapeString($imgbytes);
			}

			$this->db->queryExec(sprintf('UPDATE tvrage SET rageID = %d, releasetitle = %s, description = %s, genre = %s, country = %s %s WHERE ID = %d', $rageid, $this->db->escapeString($releasename), $this->db->escapeString(substr($desc, 0, 10000)), $this->db->escapeString($genre), $this->db->escapeString($country), $imgbytes, $id));
		} else {
			$this->db->queryExec(sprintf('UPDATE tvrage SET rageID = %d, releasetitle = %s, description = %s, genre = %s, country = %s WHERE ID = %d', $rageid, $this->db->escapeString($releasename), $this->db->escapeString(substr($desc, 0, 10000)), $this->db->escapeString($genre), $this->db->escapeString($country), $id));
			if ($imgbytes != '') {
				$path = WWW_DIR . 'covers/preview/' . $id . '.jpg';
				if (file_exists($path)) {
					unlink($path);
				}
				$check = file_put_contents($path, $imgbytes);
				if ($check !== false) {
					$this->db->Exec("UPDATE tvrage SET imgdata = 'x' WHERE ID = " . $id);
					chmod($path, 0755);
				}
			}
		}
	}

	public function delete($id)
	{
		return $this->db->queryExec(sprintf("DELETE FROM tvrage WHERE ID = %d", $id));
	}

	public function fetchShowQuickInfo($show, array $options = array())
	{
		$defaults = array('exact' => '', 'episode' => '');
		$options += $defaults;
		$ret = [];

		if (!$show) {
			return false;
		}

		$url = $this->showQuickInfoURL . urlencode($show);
		$url .= !empty($options['episode']) ? '&ep=' . urlencode($options['episode']) : '';
		$url .= !empty($options['exact']) ? '&exact=' . urlencode($options['exact']) : '';
		if ($fp = fopen($url, "r")) {
			while (!feof($fp)) {
				$line = fgets($fp, 1024);
				list ($sec, $val) = explode('@', $line, 2);
				$val = trim($val);

				switch ($sec) {
					case 'Show ID':
						$ret['rageID'] = $val;
						break;
					case 'Show Name':
						$ret['name'] = $val;
						break;
					case 'Show URL':
						$ret['url'] = $val;
						break;
					case 'Premiered':
						$ret['premier'] = $val;
						break;
					case 'Country':
						$ret['country'] = $val;
						break;
					case 'Status':
						$ret['status'] = $val;
						break;
					case 'Classification':
						$ret['classification'] = $val;
						break;
					case 'Genres':
						$ret['genres'] = $val;
						break;
					case 'Network':
						$ret['network'] = $val;
						break;
					case 'Airtime':
						$ret['airtime'] = $val;
						break;
					case 'Latest Episode':
						list ($ep, $title, $airdate) = explode('^', $val);
						$ret['episode']['latest'] =
							$ep . ", \"" . $title . "\" aired on " . $airdate;
						break;
					case 'Next Episode':
						list ($ep, $title, $airdate) = explode('^', $val);
						$ret['episode']['next'] = $ep . ", \"" . $title . "\" airs on " . $airdate;
						break;
					case 'Episode Info':
						list ($ep, $title, $airdate) = explode('^', $val);
						$ret['episode']['info'] = $ep . ", \"" . $title . "\" aired on " . $airdate;
						break;
					case 'Episode URL':
						$ret['episode']['url'] = $val;
						break;
					case '':
						break;

					default:
						break;
				}
			}
			fclose($fp);

			return $ret;
		}
	}

	public function getRange($start, $num, $ragename = "")
	{
		if ($start === false) {
			$limit = "";
		} else {
			$limit = " LIMIT " . $num . " OFFSET " . $start;
		}

		$rsql = '';
		if ($ragename != "") {
			$like = 'ILIKE';
			if (DB_TYPE === 'mysql') {
				$like = 'LIKE';
			}
			$rsql .= sprintf("AND tvrage.releasetitle %s %s ", $like, $this->db->escapeString("%" . $ragename . "%"));
		}

		return $this->db->query(sprintf("SELECT ID, rageID, releasetitle, description, createddate FROM tvrage WHERE 1=1 %s ORDER BY rageID ASC" . $limit, $rsql));
	}

	public function getCount($ragename = "")
	{
		$rsql = '';
		if ($ragename != "") {
			$like = 'ILIKE';
			if (DB_TYPE === 'mysql') {
				$like = 'LIKE';
			}
			$rsql .= sprintf("AND tvrage.releasetitle %s %s ", $like, $this->db->escapeString("%" . $ragename . "%"));
		}

		$res = $this->db->queryOneRow(sprintf("SELECT COUNT(ID) AS num FROM tvrage WHERE 1=1 %s", $rsql));

		return $res["num"];
	}

	public function getCalendar($date = "")
	{
		if (!preg_match('/\d{4}-\d{2}-\d{2}/', $date)) {
			$date = date("Y-m-d");
		}
		$sql = sprintf("SELECT * FROM episodeinfo WHERE DATE(airdate) = %s ORDER BY airdate ASC", $this->db->escapeString($date));

		return $this->db->query($sql);
	}

	public function getSeriesList($uid, $letter = "", $ragename = "")
	{
		$rsql = '';
		if ($letter != "") {
			if ($letter == '0-9') {
				$letter = '[0-9]';
			}

			if (DB_TYPE === "mysql") {
				$rsql .= sprintf("AND tvrage.releasetitle REGEXP %s", $this->db->escapeString('^' . $letter));
			} else {
				$rsql .= sprintf("AND tvrage.releasetitle ~ %s", $this->db->escapeString('^' . $letter));
			}
		}
		$tsql = '';
		if ($ragename != '') {
			$tsql .= sprintf("AND tvrage.releasetitle LIKE %s", $this->db->escapeString("%" . $ragename . "%"));
		}

		if (DB_TYPE === 'mysql') {
			return $this->db->query(sprintf("SELECT tvrage.ID, tvrage.rageID, tvrage.releasetitle, tvrage.genre, tvrage.country, tvrage.createddate, tvrage.prevdate, tvrage.nextdate, userseries.ID as userseriesID from tvrage LEFT OUTER JOIN userseries ON userseries.userID = %d AND userseries.rageID = tvrage.rageID WHERE tvrage.rageID IN (SELECT rageID FROM releases) AND tvrage.rageID > 0 %s %s GROUP BY tvrage.rageID ORDER BY tvrage.releasetitle ASC", $uid, $rsql, $tsql));
		} else {
			return $this->db->query(sprintf("SELECT tvrage.ID, tvrage.rageID, tvrage.releasetitle, tvrage.genre, tvrage.country, tvrage.createddate, tvrage.prevdate, tvrage.nextdate, userseries.ID as userseriesID from tvrage LEFT OUTER JOIN userseries ON userseries.userID = %d AND userseries.rageID = tvrage.rageID WHERE tvrage.rageID IN (SELECT rageID FROM releases) AND tvrage.rageID > 0 %s %s GROUP BY tvrage.rageID, tvrage.ID, userseries.ID ORDER BY tvrage.releasetitle ASC", $uid, $rsql, $tsql));
		}
	}

	public function updateSchedule()
	{
		$countries = $this->db->query("SELECT DISTINCT(country) AS country FROM tvrage WHERE country != ''");
		$showsindb = $this->db->query("SELECT DISTINCT(rageID) AS rageID FROM tvrage");
		$showarray = array();
		foreach ($showsindb as $show) {
			$showarray[] = $show['rageID'];
		}
		foreach ($countries as $country) {
			if ($this->echooutput) {
				echo $this->c->headerOver('Updating schedule for: ') . $this->c->primary($country['country']);
			}

			$sched = $this->util->getURL($this->xmlFullScheduleUrl . $country['country']);
			if ($sched !== false && ($xml = @simplexml_load_string($sched))) {
				$tzOffset = 60 * 60 * 6;
				$yesterday = strtotime("-1 day") - $tzOffset;
				$xmlSchedule = array();

				foreach ($xml->DAY as $sDay) {
					$currDay = strtotime($sDay['attr']);
					foreach ($sDay as $sTime) {
						$currTime = (string)$sTime['attr'];
						foreach ($sTime as $sShow) {
							$currShowName = (string)$sShow['name'];
							$currShowId = (string)$sShow->sid;
							$day_time = strtotime($sDay['attr'] . ' ' . $currTime);
							$tag = ($currDay < $yesterday) ? 'prev' : 'next';
							if ($tag == 'prev' || ($tag == 'next' && !isset($xmlSchedule[$currShowId]['next']))) {
								$xmlSchedule[$currShowId][$tag] = array('name' => $currShowName, 'day' => $currDay, 'time' => $currTime, 'day_time' => $day_time, 'day_date' => date("Y-m-d H:i:s", $day_time), 'title' => html_entity_decode((string)$sShow->title, ENT_QUOTES, 'UTF-8'), 'episode' => html_entity_decode((string)$sShow->ep, ENT_QUOTES, 'UTF-8'));
								$xmlSchedule[$currShowId]['showname'] = $currShowName;
							}

							// Only add it here, no point adding it to tvrage aswell that will automatically happen when an ep gets posted.
							if ($sShow->ep == "01x01") {
								$showarray[] = $sShow->sid;
							}

							// Only stick current shows and new shows in there.
							if (in_array($currShowId, $showarray)) {
								if (DB_TYPE === 'mysql') {
									$this->db->queryExec(sprintf("INSERT INTO episodeinfo (rageID, showtitle, fullep, airdate, link, eptitle) VALUES (%d, %s, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE airdate = %s, link = %s ,eptitle = %s, showtitle = %s", $sShow->sid, $this->db->escapeString($currShowName), $this->db->escapeString($sShow->ep), $this->db->escapeString(date("Y-m-d H:i:s", $day_time)), $this->db->escapeString($sShow->link), $this->db->escapeString($sShow->title), $this->db->escapeString(date("Y-m-d H:i:s", $day_time)), $this->db->escapeString($sShow->link), $this->db->escapeString($sShow->title), $this->db->escapeString($currShowName)));
								} else if (DB_TYPE === 'pgsql') {
									$check = $this->db->queryOneRow(sprintf('SELECT ID FROM episodeinfo WHERE rageID = %d', $sShow->sid));
									if ($check === false) {
										$this->db->queryExec(sprintf("INSERT INTO episodeinfo (rageID, showtitle, fullep, airdate, link, eptitle) VALUES (%d, %s, %s, %s, %s, %s)", $sShow->sid, $this->db->escapeString($currShowName), $this->db->escapeString($sShow->ep), $this->db->escapeString(date("Y-m-d H:i:s", $day_time)), $this->db->escapeString($sShow->link), $this->db->escapeString($sShow->title)));
									} else {
										$this->db->queryExec(sprintf('UPDATE episodeinfo SET showtitle = %s, fullep = %s, airdate = %s, link = %s, eptitle = %s WHERE ID = %d', $this->db->escapeString($currShowName), $this->db->escapeString($sShow->ep), $this->db->escapeString(date("Y-m-d H:i:s", $day_time)), $this->db->escapeString($sShow->link), $this->db->escapeString($sShow->title), $check['ID']));
									}
								}
							}
						}
					}
				}
				// Update series info.
				foreach ($xmlSchedule as $showId => $epInfo) {
					$res = $this->db->query(sprintf("SELECT * FROM tvrage WHERE rageID = %d", $showId));
					if (sizeof($res) > 0) {
						foreach ($res as $arr) {
							$prev_ep = $next_ep = "";
							$query = array();

							// Previous episode.
							if (isset($epInfo['prev']) && $epInfo['prev']['episode'] != '') {
								$prev_ep = $epInfo['prev']['episode'] . ', "' . $epInfo['prev']['title'] . '"';
								$query[] = sprintf("prevdate = %s, previnfo = %s", $this->db->from_unixtime($epInfo['prev']['day_time']), $this->db->escapeString($prev_ep));
							}

							// Next episode.
							if (isset($epInfo['next']) && $epInfo['next']['episode'] != '') {
								if ($prev_ep == "" && $arr['nextinfo'] != '' && $epInfo['next']['day_time'] > strtotime($arr["nextdate"]) && strtotime(date('Y-m-d', strtotime($arr["nextdate"]))) < $yesterday) {
									$this->db->queryExec(sprintf("UPDATE tvrage SET prevdate = nextdate, previnfo = nextinfo WHERE ID = %d", $arr['ID']));
									$prev_ep = "SWAPPED with: " . $arr['nextinfo'] . " - " . date("r", strtotime($arr["nextdate"]));
								}
								$next_ep = $epInfo['next']['episode'] . ', "' . $epInfo['next']['title'] . '"';
								$query[] = sprintf("nextdate = %s, nextinfo = %s", $this->db->from_unixtime($epInfo['next']['day_time']), $this->db->escapeString($next_ep));
							} else {
								$query[] = "nextdate = NULL, nextinfo = NULL";
							}

							// Output.
							if ($this->echooutput) {
								echo $this->c->primary($epInfo['showname'] . " (" . $showId . "):");
								if (isset($epInfo['prev']['day_time'])) {
									echo $this->c->headerOver("Prev EP: ") . $this->c->primary("{$prev_ep} - " . date("m/d/Y H:i T", $epInfo['prev']['day_time']));
								}
								if (isset($epInfo['next']['day_time'])) {
									echo $this->c->headerOver("Next EP: ") . $this->c->primary("{$next_ep} - " . date("m/d/Y H:i T", $epInfo['next']['day_time']));
								}
								echo "\n";
							}

							// Update info.
							if (count($query) > 0) {
								$sql = join(", ", $query);
								$sql = sprintf("UPDATE tvrage SET {$sql} WHERE ID = %d", $arr['ID']);
								$this->db->queryExec($sql);
							}
						}
					}
				}
			} else {
				// No response from tvrage.
				if ($this->echooutput) {
					echo $this->c->info("Schedule not found.");
				}
			}
		}
		if ($this->echooutput) {
			echo $this->c->primary("Updated the TVRage schedule succesfully.");
		}
	}

	public function getEpisodeInfo($rageid, $series, $episode)
	{
		$result = array('title' => '', 'airdate' => '');

		$series = str_ireplace("s", "", $series);
		$episode = str_ireplace("e", "", $episode);
		$xml = $this->util->getUrl($this->xmlEpisodeInfoUrl . "&sid=" . $rageid . "&ep=" . $series . "x" . $episode);
		if ($xml !== false) {
			if (preg_match('/no show found/i', $xml)) {
				return false;
			}

			$xmlObj = @simplexml_load_string($xml);
			$arrXml = $this->util->objectsIntoArray($xmlObj);
			if (is_array($arrXml)) {
				if (isset($arrXml['episode']['airdate']) && $arrXml['episode']['airdate'] != '0000-00-00') {
					$result['airdate'] = $arrXml['episode']['airdate'];
				}
				if (isset($arrXml['episode']['title'])) {
					$result['title'] = $arrXml['episode']['title'];
				}

				return $result;
			}

			return false;
		}

		return false;
	}

	public function getRageInfoFromPage($rageid)
	{
		$result = array('desc' => '', 'imgurl' => '');
		$page = $this->util->getUrl($this->showInfoUrl . $rageid);
		$matches = '';
		if ($page !== false) {
			// Description.
			preg_match('@<div class="show_synopsis">(.*?)</div>@is', $page, $matches);
			if (isset($matches[1])) {
				$desc = $matches[1];
				$desc = preg_replace('/<hr>.*/s', '', $desc);
				$desc = preg_replace('/&nbsp;?/', '', $desc);
				$desc = preg_replace('/<br>(\n)?<br>/', ' / ', $desc);
				$desc = preg_replace('/\n/', ' ', $desc);
				$desc = preg_replace('/<a href.*?<\/a>/', '', $desc);
				$desc = preg_replace('/<script.*?<\/script>/', '', $desc);
				$desc = preg_replace('/<.*?>/', '', $desc);
				$desc = str_replace('()', '', $desc);
				$desc = trim(preg_replace('/\s{2,}/', ' ', $desc));
				$result['desc'] = $desc;
			}
			// Image.
			preg_match("@src=[\"'](http://images.tvrage.com/shows.*?)[\"']@i", $page, $matches);
			if (isset($matches[1])) {
				$result['imgurl'] = $matches[1];
			}
		}

		return $result;
	}

	public function getRageInfoFromService($rageid)
	{
		$result = array('genres' => '', 'country' => '', 'showid' => $rageid);
		// Full search gives us the akas.
		$xml = $this->util->getUrl($this->xmlShowInfoUrl . $rageid);
		if ($xml !== false) {
			$arrXml = $this->util->objectsIntoArray(simplexml_load_string($xml));
			if (is_array($arrXml)) {
				$result['genres'] = (isset($arrXml['genres'])) ? $arrXml['genres'] : '';
				$result['country'] = (isset($arrXml['origin_country'])) ? $arrXml['origin_country'] : '';
				$result = $this->countryCode($result);

				return $result;
			}

			return false;
		}

		return false;
	}

	// Convert 2012-24-07 to 2012-07-24, there is probably a better way
	public function checkDate($date)
	{
		if (!empty($date) && $date != null) {
			$chk = explode(" ", $date);
			$chkd = explode("-", $chk[0]);
			if ($chkd[1] > 12) {
				$date = date('Y-m-d H:i:s', strtotime($chkd[1] . " " . $chkd[2] . " " . $chkd[0]));
			}

			return $date;
		}

		return null;
	}

	public function updateEpInfo($show, $relid)
	{
		if ($this->echooutput) {
			echo $this->c->headerOver("Updating Episode: ") . $this->c->primary($show['cleanname'] . " " . $show['seriesfull'] . (($show['year'] != '') ? ' ' . $show['year'] : '') . (($show['country'] != '') ? ' [' . $show['country'] . ']' : ''));
		}

		$tvairdate = (isset($show['airdate']) && !empty($show['airdate'])) ? $this->db->escapeString($this->checkDate($show['airdate'])) : "NULL";
		$this->db->queryExec(sprintf("UPDATE releases SET seriesfull = %s, season = %s, episode = %s, tvairdate = %s WHERE ID = %d", $this->db->escapeString($show['seriesfull']), $this->db->escapeString($show['season']), $this->db->escapeString($show['episode']), $tvairdate, $relid));
	}

	public function updateRageInfo($rageid, $show, $tvrShow, $relid)
	{
		// Try and get the episode specific info from tvrage.
		$epinfo = $this->getEpisodeInfo($rageid, $show['season'], $show['episode']);
		if ($epinfo !== false) {
			$tvairdate = (!empty($epinfo['airdate'])) ? $this->db->escapeString($epinfo['airdate']) : "NULL";
			$tvtitle = (!empty($epinfo['title'])) ? $this->db->escapeString($epinfo['title']) : "NULL";

			$this->db->queryExec(sprintf("UPDATE releases set tvtitle = %s, tvairdate = %s, rageID = %d where ID = %d", $this->db->escapeString(trim($tvtitle)), $tvairdate, $tvrShow['showid'], $relid));
		} else {
			$this->db->queryExec(sprintf("UPDATE releases SET rageID = %d WHERE ID = %d", $tvrShow['showid'], $relid));
		}

		$genre = '';
		if (isset($tvrShow['genres']) && is_array($tvrShow['genres']) && !empty($tvrShow['genres'])) {
			if (is_array($tvrShow['genres']['genre'])) {
				$genre = implode('|', $tvrShow['genres']['genre']);
			} else {
				$genre = $tvrShow['genres']['genre'];
			}
		}

		$country = '';
		if (isset($tvrShow['country']) && !empty($tvrShow['country'])) {
			$country = $this->countryCode($tvrShow['country']);
		}

		$rInfo = $this->getRageInfoFromPage($rageid);
		$desc = '';
		if (isset($rInfo['desc']) && !empty($rInfo['desc'])) {
			$desc = $rInfo['desc'];
		}

		$imgbytes = '';
		if (isset($rInfo['imgurl']) && !empty($rInfo['imgurl'])) {
			$img = $this->util->getUrl($rInfo['imgurl']);
			if ($img !== false) {
				$im = @imagecreatefromstring($img);
				if ($im !== false) {
					$imgbytes = $img;
				}
			}
		}
		$this->add($rageid, $show['cleanname'], $desc, $genre, $country, $imgbytes);
	}

	public function updateRageInfoTrakt($rageid, $show, $traktArray, $relid)
	{
		// Try and get the episode specific info from tvrage.
		$epinfo = $this->getEpisodeInfo($rageid, $show['season'], $show['episode']);
		if ($epinfo !== false) {
			$tvairdate = (!empty($epinfo['airdate'])) ? $this->db->escapeString($epinfo['airdate']) : "NULL";
			$tvtitle = (!empty($epinfo['title'])) ? $this->db->escapeString($epinfo['title']) : "NULL";
			$this->db->queryExec(sprintf("UPDATE releases SET tvtitle = %s, tvairdate = %s, rageID = %d WHERE ID = %d", $this->db->escapeString(trim($tvtitle)), $tvairdate, $traktArray['show']['tvrage_id'], $relid));
		} else {
			$this->db->queryExec(sprintf("UPDATE releases SET rageID = %d WHERE ID = %d", $traktArray['show']['tvrage_id'], $relid));
		}

		$genre = '';
		if (isset($traktArray['show']['genres']) && is_array($traktArray['show']['genres']) && !empty($traktArray['show']['genres'])) {
			$genre = $traktArray['show']['genres']['0'];
		}

		$country = '';
		if (isset($traktArray['show']['country']) && !empty($traktArray['show']['country'])) {
			$country = $this->countryCode($traktArray['show']['country']);
		}

		$rInfo = $this->getRageInfoFromPage($rageid);
		$desc = '';
		if (isset($rInfo['desc']) && !empty($rInfo['desc'])) {
			$desc = $rInfo['desc'];
		}

		$imgbytes = '';
		if (isset($rInfo['imgurl']) && !empty($rInfo['imgurl'])) {
			$img = $this->util->getUrl($rInfo['imgurl']);
			if ($img !== false) {
				$im = @imagecreatefromstring($img);
				if ($im !== false) {
					$imgbytes = $img;
				}
			}
		}

		$this->add($rageid, $show['cleanname'], $desc, $genre, $country, $imgbytes);
	}

	public function processTvReleases($releaseToWork = '', $lookupTvRage = true, $local = false)
	{
		$ret = 0;
		$trakt = new TraktTv();

		// Get all releases without a rageID which are in a tv category.
		if ($releaseToWork == '') {
			$res = $this->db->query(sprintf("SELECT r.searchname, r.ID FROM releases r WHERE r.rageID = -1 AND r.size > 1048576 AND r.categoryID BETWEEN 5000 AND 5999 ORDER BY r.postdate DESC LIMIT %d", $this->rageqty));
			$tvcount = count($res);
		} else {
			$pieces = explode("           =+=            ", $releaseToWork);
			$res = array(array('searchname' => $pieces[0], 'ID' => $pieces[1]));
			$tvcount = 1;
		}

		if ($this->echooutput && $tvcount > 1) {
			echo $this->c->header("Processing TV for " . $tvcount . " release(s).");
		}

		foreach ($res as $arr) {
			$show = $this->parseNameEpSeason($arr['searchname']);
			if (is_array($show) && $show['name'] != '') {
				// Update release with season, ep, and airdate info (if available) from releasetitle.
				$this->updateEpInfo($show, $arr['ID']);

				// Find the rageID.
				$id = $this->getByTitle($show['cleanname']);

				// Force local lookup only
				if ($local == true) {
					$lookupTvRage = false;
				}

				if ($id === false && $lookupTvRage) {
					// If it doesnt exist locally and lookups are allowed lets try to get it.
					if ($this->echooutput) {
						echo $this->c->primaryOver("TVRage ID for ") . $this->c->headerOver($show['cleanname']) . $this->c->primary(" not found in local db, checking web.");
					}

					$tvrShow = $this->getRageMatch($show);
					if ($tvrShow !== false && is_array($tvrShow)) {
						// Get all tv info and add show.
						$this->updateRageInfo($tvrShow['showid'], $show, $tvrShow, $arr['ID']);
					} else if ($tvrShow === false) {
						// If tvrage fails, try trakt.
						$traktArray = $trakt->traktTVSEsummary($show['name'], $show['season'], $show['episode']);
						if ($traktArray !== false) {
							if (isset($traktArray['show']['tvrage_id']) && $traktArray['show']['tvrage_id'] !== 0) {
								if ($this->echooutput) {
									echo $this->c->primary('Found TVRage ID on trakt:' . $traktArray['show']['tvrage_id']);
								}
								$this->updateRageInfoTrakt($traktArray['show']['tvrage_id'], $show, $traktArray, $arr['ID']);
							} // No match, add to tvrage with rageID = -2 and $show['cleanname'] title only.
							else {
								$this->add(-2, $show['cleanname'], '', '', '', '');
							}
						} // No match, add to tvrage with rageID = -2 and $show['cleanname'] title only.
						else {
							$this->add(-2, $show['cleanname'], '', '', '', '');
						}
					} else {
						// $tvrShow probably equals -1 but we'll do this as a catchall instead of a specific else if.
						// Skip because we couldnt connect to tvrage.com.
					}
				} else if ($id > 0) {
					//if ($this->echooutput) {
					//    echo $this->c->AlternateOver("TV series: ") . $this->c->header($show['cleanname'] . " " . $show['seriesfull'] . (($show['year'] != '') ? ' ' . $show['year'] : '') . (($show['country'] != '') ? ' [' . $show['country'] . ']' : ''));
					// }
					$tvairdate = (isset($show['airdate']) && !empty($show['airdate'])) ? $this->db->escapeString($this->checkDate($show['airdate'])) : "NULL";
					$tvtitle = "NULL";

					if ($lookupTvRage) {
						$epinfo = $this->getEpisodeInfo($id, $show['season'], $show['episode']);
						if ($epinfo !== false) {
							if (isset($epinfo['airdate'])) {
								$tvairdate = $this->db->escapeString($this->checkDate($epinfo['airdate']));
							}

							if (!empty($epinfo['title'])) {
								$tvtitle = $this->db->escapeString(trim($epinfo['title']));
							}
						}
					}
					if ($tvairdate == "NULL") {
						$this->db->queryExec(sprintf('UPDATE releases SET tvtitle = %s, rageID = %d WHERE ID = %d', $tvtitle, $id, $arr['ID']));
					} else {
						$this->db->queryExec(sprintf('UPDATE releases SET tvtitle = %s, tvairdate = %s, rageID = %d WHERE ID = %d', $tvtitle, $tvairdate, $id, $arr['ID']));
					}
					// Cant find rageID, so set rageID to n/a.
				} else {
					$this->db->queryExec(sprintf('UPDATE releases SET rageID = -2 WHERE ID = %d', $arr['ID']));
				}
				// Not a tv episode, so set rageID to n/a.
			} else {
				$this->db->queryExec(sprintf('UPDATE releases SET rageID = -2 WHERE ID = %d', $arr['ID']));
			}
			$ret++;
		}

		return $ret;
	}

	public function getRageMatch($showInfo)
	{
		$title = $showInfo['cleanname'];
		// Full search gives us the akas.
		$xml = $this->util->getUrl($this->xmlFullSearchUrl . urlencode(strtolower($title)));
		if ($xml !== false) {
			$arrXml = @$this->util->objectsIntoArray(simplexml_load_string($xml));
			if (isset($arrXml['show']) && is_array($arrXml)) {
				// We got a valid xml response
				$titleMatches = $urlMatches = $akaMatches = array();

				if (isset($arrXml['show']['showid'])) {
					// We got exactly 1 match so lets convert it to an array so we can use it in the logic below.
					$newArr = array();
					$newArr[] = $arrXml['show'];
					unset($arrXml);
					$arrXml['show'] = $newArr;
				}

				foreach ($arrXml['show'] as $arr) {
					$titlepct = $urlpct = $akapct = 0;
					$tvrlink = '';

					// Get a match percentage based on our name and the name returned from tvr.
					$titlepct = $this->checkMatch($title, $arr['name']);
					if ($titlepct !== false) {
						$titleMatches[$titlepct][] = array('title' => $arr['name'], 'showid' => $arr['showid'], 'country' => $this->countryCode($arr['country']), 'genres' => $arr['genres'], 'tvr' => $arr);
					}

					// Get a match percentage based on our name and the url returned from tvr.
					if (isset($arr['link']) && preg_match('/tvrage\.com\/((?!shows)[^\/]*)$/i', $arr['link'], $tvrlink)) {
						$urltitle = str_replace('_', ' ', $tvrlink[1]);
						$urlpct = $this->checkMatch($title, $urltitle);
						if ($urlpct !== false) {
							$urlMatches[$urlpct][] = array('title' => $urltitle, 'showid' => $arr['showid'], 'country' => $this->countryCode($arr['country']), 'genres' => $arr['genres'], 'tvr' => $arr);
						}
					}

					// Check if there are any akas for this result and get a match percentage for them too.
					if (isset($arr['akas']['aka'])) {
						if (is_array($arr['akas']['aka'])) {
							// Multuple akas.
							foreach ($arr['akas']['aka'] as $aka) {
								$akapct = $this->checkMatch($title, $aka);
								if ($akapct !== false) {
									$akaMatches[$akapct][] = array('title' => $aka, 'showid' => $arr['showid'], 'country' => $this->countryCode($arr['country']), 'genres' => $arr['genres'], 'tvr' => $arr);
								}
							}
						} else {
							// One aka.
							$akapct = $this->checkMatch($title, $arr['akas']['aka']);
							if ($akapct !== false) {
								$akaMatches[$akapct][] = array('title' => $arr['akas']['aka'], 'showid' => $arr['showid'], 'country' => $this->countryCode($arr['country']), 'genres' => $arr['genres'], 'tvr' => $arr);
							}
						}
					}
				}

				// Reverse sort our matches so highest matches are first.
				krsort($titleMatches);
				krsort($urlMatches);
				krsort($akaMatches);

				// Look for 100% title matches first.
				if (isset($titleMatches[100])) {
					if ($this->echooutput) {
						echo $this->c->primary('Found 100% match: "' . $titleMatches[100][0]['title'] . '"');
					}

					return $titleMatches[100][0];
				}

				// Look for 100% url matches next.
				if (isset($urlMatches[100])) {
					if ($this->echooutput) {
						echo $this->c->primary('Found 100% url match: "' . $urlMatches[100][0]['title'] . '"');
					}

					return $urlMatches[100][0];
				}

				// Look for 100% aka matches next.
				if (isset($akaMatches[100])) {
					if ($this->echooutput) {
						echo $this->c->primary('Found 100% aka match: "' . $akaMatches[100][0]['title'] . '"');
					}

					return $akaMatches[100][0];
				}

				// No 100% matches, loop through what we got and if our next closest match is more than TvAnger::MATCH_PROBABILITY % of the title lets take it.
				foreach ($titleMatches as $mk => $mv) {
					// Since its not 100 match if we have country info lets use that to make sure we get the right show.
					if (isset($showInfo['country']) && !empty($showInfo['country']) && !empty($mv[0]['country'])) {
						if (strtolower($showInfo['country']) != strtolower($mv[0]['country'])) {
							continue;
						}
					}

					if ($this->echooutput) {
						echo $this->c->primary('Found ' . $mk . '% match: "' . $titleMatches[$mk][0]['title'] . '"');
					}

					return $titleMatches[$mk][0];
				}

				// Same as above but for akas.
				foreach ($akaMatches as $ak => $av) {
					if (isset($showInfo['country']) && !empty($showInfo['country']) && !empty($av[0]['country'])) {
						if (strtolower($showInfo['country']) != strtolower($av[0]['country'])) {
							continue;
						}
					}

					if ($this->echooutput) {
						echo $this->c->primary('Found ' . $ak . '% aka match: "' . $akaMatches[$ak][0]['title'] . '"');
					}

					return $akaMatches[$ak][0];
				}

				if ($this->echooutput) {
					echo $this->c->primary('No match found on TVRage trying Trakt.');
				}

				return false;
			} else {
				if ($this->echooutput) {
					echo $this->c->primary('Nothing returned from tvrage.');
				}

				return false;
			}
		} else {
			return -1;
		}

		if ($this->echooutput) {
			echo $this->c->primary('No match found online.');
		}

		return false;
	}

	public function checkMatch($ourName, $tvrName)
	{
		// Clean up name ($ourName is already clean).
		$tvrName = $this->cleanName($tvrName);
		$tvrName = preg_replace('/ of /i', '', $tvrName);
		$ourName = preg_replace('/ of /i', '', $ourName);

		// Create our arrays.
		$ourArr = explode(' ', $ourName);
		$tvrArr = explode(' ', $tvrName);

		// Set our match counts.
		$numMatches = 0;
		$totalMatches = sizeof($ourArr) + sizeof($tvrArr);

		// Loop through each array matching again the opposite value, if they match increment!
		foreach ($ourArr as $oname) {
			if (preg_match('/ ' . preg_quote($oname, '/') . ' /i', ' ' . $tvrName . ' ')) {
				$numMatches++;
			}
		}
		foreach ($tvrArr as $tname) {
			if (preg_match('/ ' . preg_quote($tname, '/') . ' /i', ' ' . $ourName . ' ')) {
				$numMatches++;
			}
		}

		// Check what we're left with.
		if ($numMatches <= 0) {
			return false;
		} else {
			$matchpct = ($numMatches / $totalMatches) * 100;
		}

		if ($matchpct >= TvAnger::MATCH_PROBABILITY) {
			return $matchpct;
		} else {
			return false;
		}
	}

	public function cleanName($str)
	{
		$str = str_replace(array('.', '_'), ' ', $str);

		$str = str_replace(array('à', 'á', 'â', 'ã', 'ä', 'æ', 'À', 'Á', 'Â', 'Ã', 'Ä'), 'a', $str);
		$str = str_replace(array('ç', 'Ç'), 'c', $str);
		$str = str_replace(array('Σ', 'è', 'é', 'ê', 'ë', 'È', 'É', 'Ê', 'Ë'), 'e', $str);
		$str = str_replace(array('ì', 'í', 'î', 'ï', 'Ì', 'Í', 'Î', 'Ï'), 'i', $str);
		$str = str_replace(array('ò', 'ó', 'ô', 'õ', 'ö', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö'), 'o', $str);
		$str = str_replace(array('ù', 'ú', 'û', 'ü', 'ū', 'Ú', 'Û', 'Ü', 'Ū'), 'u', $str);
		$str = str_replace('ß', 'ss', $str);

		$str = str_replace('&', 'and', $str);
		$str = preg_replace('/^(history|discovery) channel/i', '', $str);
		$str = str_replace(array('\'', ':', '!', '"', '#', '*', '’', ',', '(', ')', '?'), '', $str);
		$str = str_replace('$', 's', $str);
		$str = preg_replace('/\s{2,}/', ' ', $str);

		$str = trim($str, '\"');

		return trim($str);
	}

	public function parseNameEpSeason($relname)
	{
		$showInfo = array('name' => '', 'season' => '', 'episode' => '', 'seriesfull' => '', 'airdate' => '', 'country' => '', 'year' => '', 'cleanname' => '');
		$matches = '';

		$following = '[^a-z0-9](\d\d-\d\d|\d{1,2}x\d{2,3}|(19|20)\d\d|(480|720|1080)[ip]|AAC2?|BDRip|BluRay|D0?\d|DD5|DiVX|DLMux|DTS|DVD(Rip)?|E\d{2,3}|[HX][-_. ]?264|ITA(-ENG)?|[HPS]DTV|PROPER|REPACK|S\d+[^a-z0-9]?(E\d+)?|WEB[-_. ]?(DL|Rip)|XViD)[^a-z0-9]';

		// For names that don't start with the title.
		if (preg_match('/[^a-z0-9]{2,}(?P<name>[\w .-]*?)' . $following . '/i', $relname, $matches)) {
			$showInfo['name'] = $matches[1];
		} else if (preg_match('/^(?P<name>[a-z0-9][\w .-]*?)' . $following . '/i', $relname, $matches)) {
			// For names that start with the title.
			$showInfo['name'] = $matches[1];
		}

		if (!empty($showInfo['name'])) {
			// S01E01-E02 and S01E01-02
			if (preg_match('/^(.*?)[^a-z0-9]s(\d{1,2})[^a-z0-9]?e(\d{1,3})(?:[e-])(\d{1,3})[^a-z0-9]/i', $relname, $matches)) {
				$showInfo['season'] = intval($matches[2]);
				$showInfo['episode'] = array(intval($matches[3]), intval($matches[4]));
			} //S01E0102 - lame no delimit numbering, regex would collide if there was ever 1000 ep season.
			else if (preg_match('/^(.*?)[^a-z0-9]s(\d{2})[^a-z0-9]?e(\d{2})(\d{2})[^a-z0-9]/i', $relname, $matches)) {
				$showInfo['season'] = intval($matches[2]);
				$showInfo['episode'] = array(intval($matches[3]), intval($matches[4]));
			} // S01E01 and S01.E01
			else if (preg_match('/^(.*?)[^a-z0-9]s(\d{1,2})[^a-z0-9]?e(\d{1,3})[^a-z0-9]/i', $relname, $matches)) {
				$showInfo['season'] = intval($matches[2]);
				$showInfo['episode'] = intval($matches[3]);
			} // S01
			else if (preg_match('/^(.*?)[^a-z0-9]s(\d{1,2})[^a-z0-9]/i', $relname, $matches)) {
				$showInfo['season'] = intval($matches[2]);
				$showInfo['episode'] = 'all';
			} // S01D1 and S1D1
			else if (preg_match('/^(.*?)[^a-z0-9]s(\d{1,2})[^a-z0-9]?d\d{1}[^a-z0-9]/i', $relname, $matches)) {
				$showInfo['season'] = intval($matches[2]);
				$showInfo['episode'] = 'all';
			} // 1x01
			else if (preg_match('/^(.*?)[^a-z0-9](\d{1,2})x(\d{1,3})[^a-z0-9]/i', $relname, $matches)) {
				$showInfo['season'] = intval($matches[2]);
				$showInfo['episode'] = intval($matches[3]);
			} // 2009.01.01 and 2009-01-01
			else if (preg_match('/^(.*?)[^a-z0-9](19|20)(\d{2})[^a-z0-9](\d{2})[^a-z0-9](\d{2})[^a-z0-9]/i', $relname, $matches)) {
				$showInfo['season'] = $matches[2] . $matches[3];
				$showInfo['episode'] = $matches[4] . '/' . $matches[5];
				$showInfo['airdate'] = $matches[2] . $matches[3] . '-' . $matches[4] . '-' . $matches[5]; //yy-m-d
			} // 01.01.2009
			else if (preg_match('/^(.*?)[^a-z0-9](\d{2})[^a-z0-9](\d{2})[^a-z0-9](19|20)(\d{2})[^a-z0-9]/i', $relname, $matches)) {
				$showInfo['season'] = $matches[4] . $matches[5];
				$showInfo['episode'] = $matches[2] . '/' . $matches[3];
				$showInfo['airdate'] = $matches[4] . $matches[5] . '-' . $matches[2] . '-' . $matches[3]; //yy-m-d
			} // 01.01.09
			else if (preg_match('/^(.*?)[^a-z0-9](\d{2})[^a-z0-9](\d{2})[^a-z0-9](\d{2})[^a-z0-9]/i', $relname, $matches)) {
				$showInfo['season'] = ($matches[4] <= 99 && $matches[4] > 15) ? '19' . $matches[4] : '20' . $matches[4];
				$showInfo['episode'] = $matches[2] . '/' . $matches[3];
				$showInfo['airdate'] = $showInfo['season'] . '-' . $matches[2] . '-' . $matches[3]; //yy-m-d
			} // 2009.E01
			else if (preg_match('/^(.*?)[^a-z0-9]20(\d{2})[^a-z0-9](\d{1,3})[^a-z0-9]/i', $relname, $matches)) {
				$showInfo['season'] = '20' . $matches[2];
				$showInfo['episode'] = intval($matches[3]);
			} // 2009.Part1
			else if (preg_match('/^(.*?)[^a-z0-9](19|20)(\d{2})[^a-z0-9]Part(\d{1,2})[^a-z0-9]/i', $relname, $matches)) {
				$showInfo['season'] = $matches[2] . $matches[3];
				$showInfo['episode'] = intval($matches[4]);
			} // Part1/Pt1
			else if (preg_match('/^(.*?)[^a-z0-9](?:Part|Pt)[^a-z0-9](\d{1,2})[^a-z0-9]/i', $relname, $matches)) {
				$showInfo['season'] = 1;
				$showInfo['episode'] = intval($matches[2]);
			} //The.Pacific.Pt.VI.HDTV.XviD-XII / Part.IV
			else if (preg_match('/^(.*?)[^a-z0-9](?:Part|Pt)[^a-z0-9]([ivx]+)/i', $relname, $matches)) {
				$showInfo['season'] = 1;
				$epLow = strtolower($matches[2]);
				switch ($epLow) {
					case 'i':
						$e = 1;
						break;
					case 'ii':
						$e = 2;
						break;
					case 'iii':
						$e = 3;
						break;
					case 'iv':
						$e = 4;
						break;
					case 'v':
						$e = 5;
						break;
					case 'vi':
						$e = 6;
						break;
					case 'vii':
						$e = 7;
						break;
					case 'viii':
						$e = 8;
						break;
					case 'ix':
						$e = 9;
						break;
					case 'x':
						$e = 10;
						break;
					case 'xi':
						$e = 11;
						break;
					case 'xii':
						$e = 12;
						break;
					case 'xiii':
						$e = 13;
						break;
					case 'xiv':
						$e = 14;
						break;
					case 'xv':
						$e = 15;
						break;
					case 'xvi':
						$e = 16;
						break;
					case 'xvii':
						$e = 17;
						break;
					case 'xviii':
						$e = 18;
						break;
					case 'xix':
						$e = 19;
						break;
					case 'xx':
						$e = 20;
						break;
					default:
						$e = 0;
				}
				$showInfo['episode'] = $e;
			} // Band.Of.Brothers.EP06.Bastogne.DVDRiP.XviD-DEiTY
			else if (preg_match('/^(.*?)[^a-z0-9]EP?[^a-z0-9]?(\d{1,3})/i', $relname, $matches)) {
				$showInfo['season'] = 1;
				$showInfo['episode'] = intval($matches[2]);
			} // Season.1
			else if (preg_match('/^(.*?)[^a-z0-9]Seasons?[^a-z0-9]?(\d{1,2})/i', $relname, $matches)) {
				$showInfo['season'] = intval($matches[2]);
				$showInfo['episode'] = 'all';
			}

			$countryMatch = $yearMatch = '';
			// Country or origin matching.
			if (preg_match('/\W(US|UK|AU|NZ|CA|NL|Canada|Australia|America|United[^a-z0-9]States|United[^a-z0-9]Kingdom)\W/', $showInfo['name'], $countryMatch)) {
				$currentCountry = strtolower($countryMatch[1]);
				if ($currentCountry == 'canada') {
					$showInfo['country'] = 'CA';
				} else if ($currentCountry == 'australia') {
					$showInfo['country'] = 'AU';
				} else if ($currentCountry == 'america' || $currentCountry == 'united states') {
					$showInfo['country'] = 'US';
				} else if ($currentCountry == 'united kingdom') {
					$showInfo['country'] = 'UK';
				} else {
					$showInfo['country'] = strtoupper($countryMatch[1]);
				}
			}

			// Clean show name.
			$showInfo['cleanname'] = $this->cleanName($showInfo['name']);

			// Check for dates instead of seasons.
			if (strlen($showInfo['season']) == 4) {
				$showInfo['seriesfull'] = $showInfo['season'] . "/" . $showInfo['episode'];
			} else {
				// Get year if present (not for releases with dates as seasons).
				if (preg_match('/[^a-z0-9](19|20)(\d{2})/i', $relname, $yearMatch)) {
					$showInfo['year'] = $yearMatch[1] . $yearMatch[2];
				}

				$showInfo['season'] = sprintf('S%02d', $showInfo['season']);
				// Check for multi episode release.
				if (is_array($showInfo['episode'])) {
					$tmpArr = array();
					foreach ($showInfo['episode'] as $ep) {
						$tmpArr[] = sprintf('E%02d', $ep);
					}
					$showInfo['episode'] = implode('', $tmpArr);
				} else {
					$showInfo['episode'] = sprintf('E%02d', $showInfo['episode']);
				}

				$showInfo['seriesfull'] = $showInfo['season'] . $showInfo['episode'];
			}
			$showInfo['airdate'] = (!empty($showInfo['airdate'])) ? $showInfo['airdate'] . ' 00:00:00' : '';

			return $showInfo;
		}

		return false;
	}

	public function getGenres()
	{
		return array('Action', 'Adult/Porn', 'Adventure', 'Anthology', 'Arts & Crafts', 'Automobiles', 'Buy, Sell & Trade', 'Celebrities', 'Children', 'Cinema/Theatre', 'Comedy', 'Cooking/Food', 'Crime', 'Current Events',
					 'Dance', 'Debate', 'Design/Decorating', 'Discovery/Science', 'Drama', 'Educational', 'Family', 'Fantasy', 'Fashion/Make-up', 'Financial/Business', 'Fitness', 'Garden/Landscape', 'History',
					 'Horror/Supernatural', 'Housing/Building', 'How To/Do It Yourself', 'Interview', 'Lifestyle', 'Literature', 'Medical', 'Military/War', 'Music', 'Mystery', 'Pets/Animals', 'Politics', 'Puppets',
					 'Religion', 'Romance/Dating', 'Sci-Fi', 'Sketch/Improv', 'Soaps', 'Sports', 'Super Heroes', 'Talent', 'Tech/Gaming', 'Teens', 'Thriller', 'Travel', 'Western', 'Wildlife'
		);
	}

}

?>