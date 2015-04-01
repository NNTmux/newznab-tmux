<?php
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/site.php");
require_once(WWW_DIR . "/lib/category.php");
require_once(WWW_DIR . "/lib/movie.php");
require_once(WWW_DIR . "/lib/releases.php");
require_once(WWW_DIR . "/lib/nzb.php");
require_once(WWW_DIR . "/lib/nzbinfo.php");
require_once(WWW_DIR . "/lib/nntp.php");
require_once(WWW_DIR . "/lib/rarinfo/par2info.php");
require_once(WWW_DIR . "/lib/ColorCLI.php");

/**
 * This class handles parsing misc>other releases into real names.
 */
class Parsing
{
	/**
	 * @var DB
	 */
	public $pdo;

	/**
	 * Default constructor.
	 *
	 * @param bool $echoonly
	 * @param bool $limited
	 * @param bool $verbose
	 */
	public function __construct($echoonly = false, $limited = true, $verbose = false)
	{
		$this->echoonly = $echoonly;
		$this->limited = $limited;
		$this->verbose = $verbose;
		$this->releasestocheck = 0;
		$this->numupdated = 0;
		$this->numcleaned = 0;
		$this->numnuked = 0;
		$this->nummiscd = 0;
		$this->nfosprocessed = 0;
		$this->parsprocessed = 0;
		$this->releasefilesprocessed = 0;
		$this->cleanup = ['nuke' => [], 'misc' => []];
		$s = new Sites();
		$this->site = $s->get();
		$this->pdo = new DB();
	}

	/**
	 * Performing parsing.
	 */
	public function process()
	{

		// Default query for both full db and last 4 hours.
		$sql = "SELECT r.searchname, r.name, r.fromname, r.id as rid, r.categoryid, r.guid, r.postdate,
			   rn.id as nfoid,
			   g.name as groupname,
			   GROUP_CONCAT(rf.name) as filenames
		FROM releases r
		LEFT JOIN releasenfo rn ON (rn.releaseid = r.id)
		LEFT JOIN groups g ON (g.id = r.groupid)
		LEFT JOIN releasefiles rf ON (rf.releaseid = r.id)
		WHERE r.categoryid in (' . Category::CAT_TV_OTHER . ',' . Category::CAT_MOVIE_OTHER . ',' . Category::CAT_MISC_OTHER . ',' . Category::CAT_XXX_OTHER . ')
		%s
		GROUP BY r.id";

		$res = $this->pdo->query(sprintf($sql, $this->limited ? "AND r.adddate BETWEEN NOW() - INTERVAL 4 HOUR AND NOW()" : ""));
		$this->releasestocheck = sizeof($res);
		if ($res) {
			echo "PostPrc : Parsing last " . $this->releasestocheck . " releases in the Other-Misc categories\n";
			foreach ($res as $rel) {
				$tempname = $foundName = $methodused = '';

				//Knoc.One
				if (preg_match("/KNOC.ONE/i", $rel['name'])) {
					$title = '';
					$items = preg_split("/(\.| )/", $rel['name']);
					foreach ($items as $value) {
						if (preg_match("/^[a-z]+$/i", $value)) {
							$len = strlen($value);
							if ($len > 2) {
								$title .= substr($value, -2) . substr($value, 0, -2) . " ";
							} elseif ($len = 2) {
								$title .= substr($value, -1) . substr($value, 0, -1) . " ";
							} else {
								$title .= $value . " ";
							}
						} else {
							$title .= $value . " ";
						}
					}
					$foundName = $title;
					$methodused = "Knoc.One";
					$this->determineCategory($rel, $foundName, $methodused);
				}

				///
				///Use the Nfo to try to get the proper Releasename.
				///
				$nfo = $this->pdo->queryOneRow(sprintf("select uncompress(nfo) as nfo from releasenfo where releaseid = %d", $rel['rid']));
				if ($nfo && $foundName == "") {
					$this->nfosprocessed++;
					$nfo = $nfo['nfo'];

					//LOUNGE releases
					if (preg_match('/([a-z0-9.]+\.MBLURAY)/i', $nfo, $matches)) {
						$foundName = $matches[1];
						$methodused = "LOUNGE";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//AsianDVDClub releases
					if (preg_match('/adc-[a-z0-9]{1,10}/', $rel['name'])) {
						if (preg_match('/.*\(\d{4}\).*/i', $nfo, $matches)) {
							$foundName = $matches[0];
							$methodused = "AsianDVDClub";
							$this->determineCategory($rel, $foundName, $methodused);
						}
					}

					//ACOUSTiC  releases
					if (preg_match('/ACOUSTiC presents \.\.\..*?([a-z0-9].*?\(.*?\))/is', $nfo, $matches)) {
						$foundName = $matches[1] . ".MBLURAY";
						$methodused = "ACOUSTiC ";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//Japhson  releases
					if (preg_match('/Japhson/i', $nfo, $matches)) {
						$movie = new Movie();
						$imdbID = null;
						if (preg_match('/tt(\d{7})/i', $nfo, $matches)) {
							$imdbId = $matches[1];
							$movCheck = $movie->fetchImdbProperties($imdbId);
							$foundName = $movCheck['title'];
							if (!preg_match('/(19|20)\d{2}/i', $foundName)) {
								$foundName = $foundName . "." . $movCheck['year'];
							}
							if (preg_match('/language.*?\b([a-z0-9]+)\b/i', $nfo, $matches)) {
								if (!preg_match('/English/i', $matches[1])) {
									$foundName = $foundName . "." . $matches[1];
								}
							}
							if (preg_match('/audio.*?\b(\w+)\b/i', $nfo, $matches)) {
								if (preg_match('/(Chinese|German|Dutch|Spanish|Hebrew|Finnish|Norwegian)/i', $matches[1])) {
									$foundName = $foundName . "." . $matches[1];
								}
							}
							if (preg_match('/(video|resolution|video res).*?(1080|720|816|820|272|1280 @|528|1920)/i', $nfo, $matches)) {
								if ($matches[2] == '1280 @') {
									$matches[2] = '720';
								}
								if ($matches[2] == '1920') {
									$matches[2] = '1080';
								}
								$foundName = $foundName . "." . $matches[2];
							}
							if (preg_match('/source.*?\b(DVD9|DVD5|BDRIP|DVD\-?RIP|BLURAY)\b/i', $nfo, $matches)) {
								$foundName = $foundName . "." . $matches[1];
							}
							if (preg_match('/(video|resolution|video res).*?(XVID|X264|WMV)/i', $nfo, $matches)) {
								$foundName = $foundName . "." . $matches[2];
							}
							if (preg_match('/audio.*?\b(DTS|AC3)\b/i', $nfo, $matches)) {
								$foundName = $foundName . "." . $matches[1];
							}
							$foundName = $foundName . "-Japhson";
							$methodused = "Japhson";
							$this->determineCategory($rel, $foundName, $methodused);
						}
					}

					//AIHD  releases
					if (preg_match('/ALWAYS iN HiGH/i', $nfo, $matches)) {
						$movie = new Movie();
						$imdbID = null;
						if (preg_match('/tt(\d{7})/i', $nfo, $matches)) {
							$imdbId = $matches[1];
							$movCheck = $movie->fetchImdbProperties($imdbId);
							$foundName = $movCheck['title'];
							if (!preg_match('/(19|20)\d{2}/i', $foundName)) {
								$foundName = $foundName . "." . $movCheck['year'];
							}
							if (preg_match('/L\.([a-z0-9]+)\b/i', $nfo, $matches)) {
								if (!preg_match('/En/i', $matches[1])) {
									$foundName = $foundName . "." . $matches[1];
								}
							}
							if (preg_match('/(V).*?(1080|720|816|820|272|1280 @|528|1920)/i', $nfo, $matches)) {
								if ($matches[2] == '1280 @') {
									$matches[2] = '720';
								}
								if ($matches[2] == '1920') {
									$matches[2] = '1080';
								}
								$foundName = $foundName . "." . $matches[2];
							}
							if (preg_match('/V.*?\b(DVD9|DVD5|BDRIP|DVD\-?RIP|BLURAY)\b/i', $nfo, $matches)) {
								$foundName = $foundName . "." . $matches[1];
							}
							if (preg_match('/(V).*?(XVID|X264|WMV)/i', $nfo, $matches)) {
								$foundName = $foundName . "." . $matches[2];
							}
							if (preg_match('/A.*?\b(DTS|AC3)\b/i', $nfo, $matches)) {
								$foundName = $foundName . "." . $matches[1];
							}
							$foundName = $foundName . "-AIHD";
							$methodused = "AIHD";
							$this->determineCategory($rel, $foundName, $methodused);
						}
					}

					//IMAGiNE releases
					if (preg_match('/\*\s+([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- ]+ \- imagine)\s+\*/i', $nfo, $matches)) {
						$foundName = $matches[1];
						$methodused = "imagine";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//LEGION releases
					if (preg_match('/([a-z0-9 \.\-]+LEGi0N)/is', $nfo, $matches) && $foundName == "") {
						$foundName = $matches[1];
						$methodused = "Legion";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//SWAGGER releases
					if (preg_match('/(S  W  A  G  G  E  R|swg.*?nfo)/i', $nfo) && $foundName == "") {
						if (preg_match('/presents.*?([a-z0-9].*?\((19|20)\d{2}\))/is', $nfo, $matches)) {
							$foundName = $matches[1];
						}
						if (preg_match('/language.*?\b([a-z0-9]+)\b/i', $nfo, $matches)) {
							if ($matches[1] != "english") {
								$foundName = $foundName . "." . $matches[1];
							}
						}
						if (preg_match('/resolution.*?(1080|720)/i', $nfo, $matches)) {
							$foundName = $foundName . ".BluRay." . $matches[1];
						}
						if (preg_match('/video.*?\b([a-z0-9]+)\b/i', $nfo, $matches)) {
							$foundName = $foundName . "." . $matches[1];
						}
						if (preg_match('/audio.*?\b([a-z0-9]+)\b/i', $nfo, $matches)) {
							$foundName = $foundName . "." . $matches[1];
						}
						$foundName = $foundName . "-SWAGGER";
						$methodused = "SWAGGER";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//cm8 releases
					if (preg_match('/([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- \'\)\(]+\-(futv|crimson|qcf|runner|clue|ftp|episode|momentum|PFA|topaz|vision|tdp|haggis|nogrp|shirk|imagine|santi|sys|deimos|ltu|ficodvdr|cm8|dvdr|Nodlabs|aaf|sprinter|exvid|flawl3ss|rx|magicbox|done|unveil))\b/i', $nfo, $matches) && $foundName == "") {
						//echo "this: ".$matches[1]."\n";
						$foundName = $matches[1];
						$methodused = "cm8";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//river
					if (preg_match('/([a-z0-9\.\_\-]+\-(webios|river|w4f|sometv|ngchd|C4|gf|bov|26k|ftw))\b/i', $nfo, $matches) && $foundName == "") {
						$foundName = $matches[1];
						$methodused = "river-1";
						$this->determineCategory($rel, $foundName, $methodused);
					}
					if (preg_match('/([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- \'\)\(]+\-(CiA|Anarchy|RemixHD|FTW|Revott|WAF|CtrlHD|Telly|Nif|Line|NPW|Rude|EbP|CRisC|SHK|AssAss1ns|Leverage|BBW|NPW))\b/i', $nfo, $matches) && $foundName == "") {
						$foundName = $matches[1];
						$methodused = "river-2";
						$this->determineCategory($rel, $foundName, $methodused);
					}
					if (preg_match('/([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- \'\)\(]+\-(XPD|RHyTM))\b/i', $nfo, $matches) && $foundName == "") {
						$foundName = $matches[1];
						$methodused = "river-3";
						$this->determineCategory($rel, $foundName, $methodused);
					}
					if (preg_match('/(-PROD$|-BOV$|-NMR$|$-HAGGiS|-JUST$|CRNTV$|-MCA$|int$|-DEiTY$|-VoMiT$|-iNCiTE$|-BRUTUS$|-DCN$|-saints$|-sfm$|-lol$|-fov$|-logies$|-c4tv$|-fqm$|-jetset$|-ils$|-miragetv$|-gfvid$|-btl$|-terra$)/i', $rel['searchname']) && $foundName == "") {
						$foundName = $rel['searchname'];
						$methodused = "river-4";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//SANTi releases
					if (preg_match('/\b([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- \']+\-santi)\b/i', $nfo, $matches) && $foundName == "") {
						$foundName = $matches[1];
						$methodused = "SANTi";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//INSPiRAL releases
					if (preg_match('/^([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- ]+ \- INSPiRAL)\s+/im', $nfo, $matches) && $foundName == "") {
						$foundName = $matches[1];
						$methodused = "INSPiRAL";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//CIA releases
					if (preg_match('/Release NAME.*?\:.*?([a-z0-9][a-z0-9\.\ ]+)\b.*?([a-z0-9][a-z0-9\.\ ]+\-CIA)\b/is', $nfo, $matches) && $foundName == "") {
						$foundName = $matches[1] . $matches[2];
						$methodused = "CIA";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//HDChina releases
					if (preg_match('/HDChina/', $nfo) && $foundName == "") {
						if (preg_match('/Disc Title\:.*?\b([a-z0-9\ \.\-\_()]+\-HDChina)/i', $nfo, $matches)) {
							$foundName = $matches[1];
							$methodused = "HDChina";
							$this->determineCategory($rel, $foundName, $methodused);
						}
					}

					//Pringles
					if (preg_match('/PRiNGLES/', $nfo) && $foundName == "") {
						if (preg_match('/is giving you.*?\b([a-z0-9 ]+)\s/i', $nfo, $matches)) {
							$foundName = $matches[1];
							$foundName = rtrim($foundName);
							$foundName = ltrim($foundName);
						}
						if (preg_match('/this release.*?((19|20)\d{2})/i', $nfo, $matches)) {
							$foundName = $foundName . "." . $matches[1];
							$foundName = rtrim($foundName);
						}
						if (preg_match('/\[x\] (Danish|Norwegian|Swedish|Finish|Other)/i', $nfo, $matches)) {
							$foundName = $foundName . "." . $matches[1];
						}
						if (preg_match('/\[x\] (DVD9|DVD5)/i', $nfo, $matches)) {
							$foundName = $foundName . "." . $matches[1];
						}
						$foundName = $foundName . "-PRiNGLES";
						$methodused = "Pringles";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//Fairlight releases
					if (preg_match('/\/Team FairLight/', $nfo) && $foundName == "") {
						$title = null;
						$os = null;
						$method = null;
						if (preg_match('/\b([a-z0-9\ \- \_()\.]+) \(c\)/i', $nfo, $matches)) {
							$title = $matches['1'];
							$foundName = $title;
						}
						$foundName = $foundName . "-FLT";
						$methodused = "FairLight";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//CORE releases
					if (preg_match('/Supplied.*?\:.*?(CORE)/', $nfo) || preg_match('/Packaged.*?\:.*?(CORE)/', $nfo) && $foundName == "") {
						$title = null;
						$os = null;
						$method = null;
						if (preg_match('/\b([a-z0-9\.\-\_\+\ ]+) \*[a-z0-9]+\*/i', $nfo, $matches)) {
							$title = $matches['1'];
							$foundName = $title;
						}
						if (preg_match('/Crack\/.*?\:.*?([a-z]+)/i', $nfo, $matches)) {
							$method = $matches['1'];
							$foundName = $foundName . " " . $method;
						}
						if (preg_match('/OS.*?\:.*?([a-z]+)/i', $nfo, $matches)) {
							$os = $matches['1'];
							$foundName = $foundName . " " . $os;
						}
						$foundName = $foundName . "-CORE";
						$methodused = "CORE";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//CompleteRelease
					if (preg_match('/Complete name.*?([a-z0-9].*?\-[a-z0-9]+)\b/i', $nfo, $matches) && $foundName == "") {
						$foundName = $matches[1];
						$methodused = "CompleteRelease";
						$this->determineCategory($rel, $foundName, $methodused);
					}

					//Livesets
					if (preg_match('/\nLivesets.*?\n.*?\n.*?\n.*?\n.*?\n(?P<name>\w.*?)\n(?P<album>\w.*?)\n/im', $nfo, $matches) && $foundName == "") {
						$artist = $matches['name'];
						$title = $matches['album'];
						$source = null;
						$year = null;
						if (preg_match('/Year.*?\:{1,2} ?(?P<year>(19|20)\d{2})/i', $nfo, $matches)) {
							$year = $matches[1];
						} elseif (preg_match('/date.*?\:.*?(?P<year>(19|20)\d{2})/i', $nfo, $matches)) {
							$year = $matches[1];
						}
						if (preg_match('/(web|cable|sat)/i', $title)) {
							$source = "";
						} elseif (preg_match('/Source.*?\:{1,2} ?(?P<source>.*?)(\s{2,}|\s{1,})/i', $nfo, $matches)) {
							$source = $matches[1];
							if ($source == "Satellite") {
								$source = "Sat";
							}
						}
						if ($artist) {
							$tempname = $artist;
							if ($title) {
								$tempname = $tempname . "-" . $title;
							}
							if ($source) {
								$tempname = $tempname . "-" . $source;
							}
							if ($year) {
								$tempname = $tempname . "-" . $year;
							}
							$tempname = preg_replace("/[^a-zA-Z,0-9,\-,\s]/", "", $tempname);
							$foundName = $tempname;
							$methodused = "Live Sets";
							$this->determineCategory($rel, $foundName, $methodused);
						}
					}

					//Typical scene regex
					if (preg_match('/(?P<source>Source[\s\.]*?:|fix for nuke)?(?:\s|\]|\[)?(?P<name>[a-z0-9\'\-]+(?:\.|_)[a-z0-9\.\-_\'&]+\-[a-z0-9&]+)(?:\s|\[|\])/i', $nfo, $matches) && $foundName == "") {
						if (empty($matches['source'])) {
							if (!preg_match('/usenet\-space/i', $matches['name'])) {
								$foundName = $matches['name'];
								$methodused = "Scene";
								$this->determineCategory($rel, $foundName, $methodused);
							}
						}
					}
				}

				//The Big One
				if (preg_match_all('/([a-z0-9\ ]+)\.{1,}(\:|\[)(?P<name>.*)(\s{2}|\s{1})/i', $nfo, $matches) && $foundName == "") {
					$lut = array();
					foreach ($matches[1] as $key => $k) {
						$lut[str_replace(' ', '', strtolower(trim($k)))] = trim($matches[3][$key]);
					}
					$year = null;
					$vidsource = null;
					$series = null;
					$season = null;
					$episode = null;
					$language = null;
					$artist = null;
					$source = null;

					foreach ($lut as $k => $v) {
						$v = rtrim($v);
						if (!$year && preg_match('/((19|20)\d{2})/', $v, $matches)) {
							$year = $matches[1];
						}
						if (!$vidsource && preg_match('/(xvid|x264|h264|wmv|divx)/i', $v, $matches)) {
							$vidsource = $matches[1];
						}

						if (!$season && preg_match('/(season|seizon).*?(\d{1,3})/i', $v, $matches)) {
							$season = $matches[2];
						}
						if (!$episode && preg_match('/(Episode|ep).*?(\d{1,3})/i', $v, $matches)) {
							$episode = $matches[2];
						}
					}

					if (isset ($lut['artist'])) {
						$del = "-";
						if (isset ($lut['artist'])) {
							$lut['artist'] = trim($lut['artist'], " ");
							$tempname = $lut['artist'];
						}
						if (isset ($lut['title'])) {
							$tempname = $tempname . $del . $lut['title'];
						}
						if (isset ($lut['album']) && !isset ($lut['title'])) {
							$tempname = $tempname . $del . $lut['album'];
						}
						if (isset ($lut['track']) && !isset ($lut['title']) && !isset ($lut['album'])) {
							$tempname = $tempname . $del . $lut['track'];
						}
						if (!isset ($lut['source'])) {
							$lut['source'] = 'WEB';
						}
						if (isset ($lut['source']) && !preg_match('/SAT/i', $tempname)) {
							$tempname = $tempname . $del . $lut['source'];
						}
						if (!preg_match('/(19|20)\d{2}/', $tempname) && $year) {
							$tempname = $tempname . $del . $year;
						}
						if (isset ($lut['ripper'])) {
							$tempname = $tempname . $del . $lut['ripper'];
						}
						$tempname = preg_replace("/[^a-zA-Z,0-9,\-,\&,\s]/", "", $tempname);
						$tempname = preg_replace("/[ ]{2,}/", "", $tempname);
						$methodused = "The Big One Music";
						$foundName = $tempname;
						$this->determineCategory($rel, $foundName, $methodused);
					} else if (isset ($lut['title'])) {
						$del = " ";
						if (isset ($lut['series'])) {
							$tempname = $lut['series'];
						}
						$tempname = $tempname . $del . $lut['title'];
						if ($season && $episode) {
							$tempname = $tempname . $del . "S" . str_pad($season, 2, '0', STR_PAD_LEFT) . 'E' . str_pad($episode, 2, '0', STR_PAD_LEFT);
						} else {
							if ($season) {
								$tempname = $tempname . $del . "S" . $season;
							}
							if ($episode) {
								$tempname = $tempname . $del . "Ep" . $episode;
							}
						}
						if (isset ($lut['source']) && !preg_match('/SAT/i', $lut['title'])) {
							$tempname = $tempname . $del . $lut['source'];
						}
						if (!preg_match('/(19|20)\d{2}/', $tempname) && $year) {
							$tempname = $tempname . $del . $year;
						}
						if (isset($lut['language'])) {
							$tempname = $tempname . $del . $lut['language'];
						}
						if ($vidsource) {
							$tempname = $tempname . $del . $vidsource;
						}
						$tempname = preg_replace("/ /", " ", $tempname);
						$tempname = preg_replace("/[^a-zA-Z,0-9,\-,\&,\s]/", " ", $tempname);
						$tempname = preg_replace("/[ ]+/", " ", $tempname);
						$methodused = "The Big One Other";
						$foundName = $tempname;
						$this->determineCategory($rel, $foundName, $methodused);
					}
				}

				///
				///unable to extract releasename from nfo, try the rar file
				///
				if ($rel['filenames'] && $foundName == '') {
					$this->releasefilesprocessed++;
					$files = explode(',', $rel['filenames']);
					if (![$files]) {
						$files = [$files];
					}

					// Scene regex
					$sceneRegex = '/([a-z0-9\'\-\.\_\(\)\+\ ]+\-[a-z0-9\'\-\.\_\(\)\ ]+)(.*?\\\\.*?|)\.(?:\w{3,4})$/i';

					foreach ($files AS $file) {
						// Petje Releases
						if (preg_match('/Petje \<petje\@pietamientje\.com\>/', $rel['fromname'], $matches3) && $foundName == '') {
							if (preg_match('/.*\.(mkv|avi|mp4|wmv|divx)/', $file, $matches4)) {
								$array_new = explode('\\', $matches4[0]);
								foreach ($array_new as $item) {
									if (preg_match('/.*\((19|20\d{2})\)$/', $item, $matched)) {
										//echo $matched[0].".720p.x264-Petje";
										//print_r($matched);
										$foundName = $matched[0] . ".720p.x264-Petje";
										$methodused = "Petje";
										$this->determineCategory($rel, $foundName, $methodused);
										break 2;
									}
								}
							}
						}

						//3D Remux
						if (preg_match('/.*Remux\.mkv/', $file, $matches4)) {
							$foundName = str_replace(".mkv", "", $matches4[0]);
							$methodused = "3D Remux";
							$this->determineCategory($rel, $foundName, $methodused);
						}
						//QoQ Extended
						if (preg_match('/Q\-sbuSLN.*/i', $file, $matches4)) {
							$new1 = preg_match('/( )?(\.wmv|\.divx|\.avi|\.mkv)/i', $matches4[0], $matched);
							$new2 = str_replace($matched[0], "", $matches4[0]);
							$foundName = strrev($new2);
							$methodused = "QoQ Extended";
							$this->determineCategory($rel, $foundName, $methodused);
						}
						// Directory\Title.Year.Format.Group.mkv
						if (preg_match('/(?<=\\\).*?BLURAY.(1080|720)P.*?KNORLOADING(?=\.MKV)/i', $file, $matches3) && $foundName == '') {
							$foundName = $matches3['0'];
							$methodused = "a.b.hdtv.x264";
							$this->determineCategory($rel, $foundName, $methodused);
						}
						// ReleaseGroup.Title.Format.mkv
						if (preg_match('/(?<=swg_|swghd\-|lost\-|veto\-|kaka\-|abd\-|airline\-|daa\-|data\-|japhson\-|ika\-|lng\-|nrdhd\-|saimorny\-|sparks\-|ulshd\-|nscrns\-|ifpd\-|invan\-|an0\-|besthd\-|muxhd\-|s7\-).*?((1080|720)|P)(?=\.MKV)/i', $file, $matches3) && $foundName == '') {
							$foundName = str_replace("_", ".", $matches3['0']);
							$methodused = "a.b.hdtv.x264";
							$this->determineCategory($rel, $foundName, $methodused);
						}
						// Title.Format.ReleaseGroup.mkv
						if (preg_match('/.*?(1080|720)(|P).(SON)/i', $file, $matches3) && $foundName == '') {
							$foundName = str_replace("_", ".", $matches3['0']);
							$methodused = "a.b.hdtv.x264";
							$this->determineCategory($rel, $foundName, $methodused);
						}

						//epubmobi
						if (preg_match('/.*\.(epub|mobi|azw3|pdf|prc|lit|rtf|azw|cbr|doc)/', $file, $matches4)) {
							$foundName = str_replace(".doc", "", str_replace(".cbr", "", str_replace(".prc", "", str_replace(".pdf", "", str_replace(".azw3", "", str_replace(".mobi", "", str_replace(".epub", "", str_replace(".rtf", "", str_replace(".azw", "", str_replace(".lit", "", $matches4[0]))))))))));
							$methodused = "EpubMobi";
							$this->determineCategory($rel, $foundName, $methodused);
						}

						//Check rarfile contents for a scene name
						if (preg_match($sceneRegex, $file, $matches) && $foundName == '') {
							//Simply Releases Toppers
							if (preg_match('/(\\\\)(?P<name>.*?ReleaseS Toppers)/', $file, $matches1) && $foundName == '') {
								$foundName = $matches1['name'];
								$methodused = "Release Files-1";
								$this->determineCategory($rel, $foundName, $methodused);

							}
							//Scene format no folder.
							if (preg_match('/^([a-z0-9\.\_\- ]+\-[a-z0-9\_]+)(\\\\|)$/i', $matches[1]) && $foundName == '') {
								if (strlen($matches['1']) >= 15) {
									$foundName = $matches['1'];
									$methodused = "Scene format no folder.";
									$this->determineCategory($rel, $foundName, $methodused);
								}
							}

							//Check to see if file is inside of a folder. Use folder name if it is
							if (preg_match('/^(.*?\\\\)(.*?\\\\|)(.*?)$/i', $file, $matches1) && $foundName == '') {
								If (preg_match('/^([a-z0-9\.\_\- ]+\-[a-z0-9\_]+)(\\\\|)$/i', $matches1['1'], $res)) {
									$foundName = $res['1'];
									$methodused = "Release Files-1";
									$this->determineCategory($rel, $foundName, $methodused);
								}
								If (preg_match('/(?!UTC)([a-z0-9]+[a-z0-9\.\_\- \'\)\(]+(\d{4}|HDTV).*?\-[a-z0-9]+)/i', $matches1['1'], $res) && $foundName == '') {
									$foundName = $res['1'];
									$methodused = "Release Files-2";
									$this->determineCategory($rel, $foundName, $methodused);
								}
								If (preg_match('/^([a-z0-9\.\_\- ]+\-[a-z0-9\_]+)(\\\\|)$/i', $matches1['2'], $res) && $foundName == '') {
									$foundName = $res['1'];
									$methodused = "Release Files-3";
									$this->determineCategory($rel, $foundName, $methodused);
								}
								If (preg_match('/^([a-z0-9\.\_\- ]+\-(?:.+)\(html\))\\\\/i', $matches1['1'], $res) && $foundName == '') {
									$foundName = $res['1'];
									$methodused = "Release Files-4";
									$this->determineCategory($rel, $foundName, $methodused);
								}

							}
							If (preg_match('/(?!UTC)([a-z0-9]+[a-z0-9\.\_\- \'\)\(]+(\d{4}|HDTV).*?\-[a-z0-9]+)/i', $file, $matches2) && $foundName == '') {
								$foundName = $matches2['1'];
								$methodused = "Release Files-4";
								$this->determineCategory($rel, $foundName, $methodused);
							}
						}
					}

					//RAR file contents release name matching

					/*if (sizeof($files) > 0 && $foundName == '')
					{
						echo "RAR checking\n";
						//Loop through releaseFiles to find a match
						foreach($files as $rarFile)
						{
							//echo "-{$rarFile}\n";
							if ($foundName == '')
							{
								//Lookup name via reqid (filename)
								if (preg_match('/\.(avi|mkv|mp4|mov|wmv|iso|img|gcm|ps3|wad|ac3|nds|bin|cue|mdf)/i', $rarFile))
								{
									$this->site->reqidurl;
									$lookupUrl = 'http://allfilled/query.php?t=alt.binaries.srrdb&reqid='.urlencode(basename($rarFile));
									echo '-lookup: '.$lookupUrl."\n";
									$xml = Utility::getUrl(['url' => $lookupUrl]);
									//$xml = false;

									if ($xml !== false)
									{
										$xmlObj = @simplexml_load_string($xml);
										$arrXml = objectsIntoArray($xmlObj);

										if (isset($arrXml["item"]) && is_array($arrXml["item"]) && isset($arrXml["item"]["@attributes"]) && is_array($arrXml["item"]["@attributes"]))
										{
											$foundName = $arrXml["item"]["@attributes"]["title"];
										}
									}
								}
							}
						}
					}*/

				}

				// do par check if user has elected for downloading extra stuff
				if ($this->site->unrarpath != '' && $foundName == "") {
					$nzb = new NZB();
					$nzbfile = $nzb->NZBPath($rel['guid']);
					$nzbInfo = new nzbInfo;
					$nzbInfo->loadFromFile($nzbfile);
					if (!empty($nzbInfo->parfiles) && empty($nzbInfo->rarfiles) && empty($nzbInfo->audiofiles)) {
						$nntp = new Nntp;
						$nntp->doConnect();

						if ($this->verbose) echo "Checking Par\n";
						foreach ($nzbInfo->parfiles as $parfile) {
							$this->parsprocessed++;
							$parBinary = $nntp->getMessages($parfile['groups'][0], $parfile['segments'], $this->verbose);
							if ($parBinary) {
								$par2 = new Par2info();
								$par2->setData($parBinary);
								if (!$par2->error) {
									$parFiles = $par2->getFileList();
									foreach ($parFiles as $file) {
										if (isset($file['name']) && (preg_match('/.*part0*1\.rar$/iS', $file['name'], $matches) || preg_match('/(?!part0*1)\.rar$/iS', $file['name'], $matches) || preg_match('/\.001$/iS', $file['name'], $matches))) {
											$foundName = preg_replace('/^(.*)(\.part0*1\.rar|\.rar|\.001)$/i', '\1', $file['name']);
											$methodused = "Par file";
											$this->determineCategory($rel, $foundName, $methodused);
											break;
										}
									}
								}
							}
							unset($parBinary);

							if ($foundName != "")
								break;
						}
						$nntp->doQuit();
					}
				}

				///
				///	This is a last ditch effort, build a ReleaseName from the Nfo
				///
				if ($nfo && ($foundName == "" || $methodused == 'Scene format no folder.')) {
					//LastNfoAttempt
					if (preg_match('/tt(\d{7})/i', $nfo, $matches) && $foundName == "") {
						$movie = new Movie();
						$imdbId = $matches[1];
						$movCheck = $movie->fetchImdbProperties($imdbId);
						$buffer = Utility::getUrl(['url' => 'http://akas.imdb.com/title/tt' . $imdbId . '/']);
						if (!preg_match('/content\=\"video\.tv\_show\"/i', $buffer)) {
							if (isset($movCheck['title'])) {
								$foundName = $movCheck['title'];
								if (!preg_match('/(19|20)\d{2}/i', $foundName)) {
									$foundName = $foundName . "." . (isset($movCheck['year']) ? $movCheck['year'] : "");
								}
								if (preg_match('/language.*?\b([a-z0-9]+)\b/i', $nfo, $matches)) {
									if (!preg_match('/English/i', $matches[1])) {
										$foundName = $foundName . "." . $matches[1];
									}
								}
								if (preg_match('/audio.*?\b(\w+)\b/i', $nfo, $matches)) {
									if (preg_match('/(Chinese|German|Dutch|Spanish|Hebrew|Finnish|Norwegian)/i', $matches[1])) {
										$foundName = $foundName . "." . $matches[1];
									}
								}
								if (preg_match('/(video|resolution|video res).*?(1080|720|816|820|272|1280 @|528|1920)/i', $nfo, $matches)) {
									if ($matches[2] == '1280 @') {
										$matches[2] = '720';
									}
									if ($matches[2] == '1920') {
										$matches[2] = '1080';
									}
									$foundName = $foundName . "." . $matches[2];
								}
								if (preg_match('/source.*?\b(DVD9|DVD5|BDRIP|DVD\-?RIP|BLURAY|BD)\b/i', $nfo, $matches)) {
									if ($matches[1] == 'BD') {
										$matches[1] = 'Bluray.x264';
									}
									$foundName = $foundName . "." . $matches[1];
								}
								if (preg_match('/(video|resolution|video res).*?(XVID|X264|WMV)/i', $nfo, $matches)) {
									$foundName = $foundName . "." . $matches[2];
								}
								if (preg_match('/audio.*?\b(DTS|AC3)\b/i', $nfo, $matches)) {
									$foundName = $foundName . "." . $matches[1];
								}

								$foundName = $foundName . "-NoGroup";
								$methodused = "LastNfoAttempt";
								$this->determineCategory($rel, $foundName, $methodused);
							}
						}
					}
				}

				if ($foundName == '' && $this->verbose) {
					echo "ReleaseID: 		" . $rel["rid"] . "\n" .
						" Group: 		" . $rel["groupname"] . "\n" .
						" Old Name: 		" . $rel["name"] . "\n" .
						" Old SearchName: 	" . $rel["searchname"] . "\n" .
						" Status: 		No new name found.\n\n";
				}
			}
		}

		if ($this->verbose) {
			echo $this->releasestocheck . " releases checked\n" .
				$this->nfosprocessed . " of " . $this->releasestocheck . " releases had nfo's processed\n" .
				$this->parsprocessed . " of " . $this->releasestocheck . " releases had par's processed\n" .
				$this->releasefilesprocessed . " of " . $this->releasestocheck . " releases had releasefiles processed\n" .
				$this->numupdated . " of " . $this->releasestocheck . " releases " . ($this->releasestocheck > 0 ? (floor($this->numupdated / $this->releasestocheck * 100)) . "%" : "") . " changed\n";
		}
	}

	/**
	 * Work out the category based on the name, resets to null if no category matched.
	 */
	private function determineCategory($rel, &$foundName, &$methodused)
	{
		$categoryID = null;
		$category = new Categorize();
		$categoryID = $category->determineCategory($rel['groupname'], $foundName);
		if (($methodused == 'a.b.hdtv.x264') && ($rel['groupname'] == 'alt.binaries.hdtv.x264')) {
			$categoryID = Category::CAT_MOVIE_HD;
		}
		if (($categoryID == $rel['categoryid'] || $categoryID == '7900') || ($foundName == $rel['name'] || $foundName == $rel['searchname'])) {
			$foundName = null;
			$methodused = null;
		} else {
			$foundName = str_replace('&#x27;', '', trim(html_entity_decode($foundName)));
			$name = str_replace(' ', '_', $foundName);
			$searchname = str_replace('_', ' ', $foundName);
			echo
				PHP_EOL .
				ColorCLI::headerOver('ReleaseID: 		') . ColorCLI::primaryOver($rel['rid']) . PHP_EOL .
				ColorCLI::headerOver(' Group: 		') . ColorCLI::primaryOver($rel['groupname']) . PHP_EOL .
				ColorCLI::headerOver(' Old Name: 		') . ColorCLI::primaryOver($rel['name']) . PHP_EOL .
				ColorCLI::headerOver(' Old SearchName: 	') . ColorCLI::primaryOver($rel['searchname']) . PHP_EOL .
				ColorCLI::headerOver(' New Name: 		') . ColorCLI::primaryOver($name) . PHP_EOL .
				ColorCLI::headerOver(' New SearchName: 	') . ColorCLI::primaryOver($searchname) . PHP_EOL .
				ColorCLI::headerOver(' Old Cat: 		') . ColorCLI::primaryOver($rel['categoryid']) . PHP_EOL .
				ColorCLI::headerOver(' New Cat: 		') . ColorCLI::primaryOver($categoryID) . PHP_EOL .
				ColorCLI::headerOver(' Method: 		') . ColorCLI::primaryOver($methodused) . PHP_EOL ;
			if (!$this->echoonly) {
				$this->pdo->queryExec(sprintf("update releases SET name = %s, searchname = %s, categoryid = %d, imdbid = NULL, rageid = -1, bookinfoid = NULL, musicinfoid = NULL, consoleinfoid = NULL WHERE releases.id = %d", $this->pdo->escapeString($name), $this->pdo->escapeString($searchname), $categoryID, $rel['rid']));
			}
			$this->numupdated++;
		}
	}

	/**
	 * Perform cleanup of names and categories.
	 */
	public function cleanup()
	{
		echo "PostPrc : Performing cleanup \n";
		$catsql = "select id from groups";
		$res = $this->pdo->query($catsql);
		foreach ($res as $r2) {
			$sql = sprintf("select r.id, name, searchname, categoryid, size, totalpart, musicinfoid, preid, groupid, rn.id as nfoid from releases r left outer join releasenfo rn ON rn.releaseid = r.id where groupid = %d", $r2['id']) . " %s ";
			$unbuf = $this->pdo->queryDirect(sprintf($sql, ($this->limited ? " and r.adddate BETWEEN NOW() - INTERVAL 1 DAY AND NOW() " : "")));

			while ($r = $this->pdo->getAssocArray($unbuf)) {
				///
				///This Section will remove releases based on specific criteria
				///

				//Remove releases if they include Parts info in the release name, as they are not full releases.
				if (preg_match('/(\[|\()\d{1,4}\/\d{1,4}(\]|\))/', $r['name'])) {
					$this->handleClean($r, "Modifying Release due to Parts in Release Name: " . $r['name'] . " - ", true);
					continue;
				}

				//Remove releases if they include file of in  the release name, as they are not full releases.
				if (preg_match('/file \d+ of \d+/i', $r['name'])) {
					$this->handleClean($r, "Modifying Release due to File of in Release Name: " . $r['name'] . " - ", true);
					continue;
				}

				//Remove releases if they include file of in  the release name, as they are not full releases.
				if (preg_match('/\[\d+ of \d+\]/i', $r['name'])) {
					$this->handleClean($r, "Modifying Release due to 23 of 23 in Release Name: " . $r['name'] . " - ", true);
					continue;
				}

				//Remove releases if the name is numbers only.
				if (preg_match('/^[0-9\.\ \-\_\[\]\(\)\@\#]+$/', $r['name'])) {
					$this->handleClean($r, "Modifying Release because it is Number Only in Release Name: " . $r['name'] . " - ");
					continue;
				}

				//Remove releases if it starts with a IMDBID.
				if (preg_match('/^tt\d{6}/i', $r['name'])) {
					$this->handleClean($r, "Modifying Release because it starts with a IMDB id: " . $r['name'] . " - ");
					continue;
				}

				//Remove releases if the name contains http(s): .
				if (preg_match('/http(s|)\:/i', $r['name'])) {
					$this->handleClean($r, "Modifying Release because it contains HTTP in the Release Name: " . $r['name'] . " - ", true);
					continue;
				}

				//Remove releases if the name contains http(s): .
				// try stripos, its faster than preg_match
				if (preg_match('/sample/i', $r['name']) && $r['categoryid'] > 5000 && $r['categoryid'] < 5999) {
					$this->handleClean($r, "Modifying Release because it contains Sample in the Release Name: " . $r['name'] . " - ", true);
					continue;
				}

				///
				///End of Remove by name section
				///

				///
				///This section will cleanup releases based on the category and things such as release size and release name length
				///

				switch ($r['categoryid']) {
					//CONSOLE
					case Category::CAT_GAME_NDS: //NDS
						if ($r['size'] < 2000000) {
							$this->handleClean($r, "Modifying Release Due to NDS Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 11 && !preg_match('/\([a-z]{2,3}\)/i', $r['name']) && !$r['preid']) {
							$this->handleClean($r, "Modifying Release Due to NDS Release Length: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_GAME_PSP: //PSP
						if ($r['size'] < 100000 && $r['name'] != 'DLC') {
							$this->handleClean($r, "Modifying Release Due to PSP Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 13 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release PSP Due to Release Length: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_GAME_WII: //Wii
						if ($r['size'] < 9000000 && $r['totalpart'] < 10) {
							$this->handleClean($r, "Modifying Release Wii Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 14 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release Wii ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_GAME_XBOX: //Xbox
						if ($r['size'] < 1500000) {
							$this->handleClean($r, "Modifying Release Xbox Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 14 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release Xbox ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_GAME_XBOX360: //Xbox 360
						if ($r['size'] < 50000000) {
							$this->handleClean($r, "Modifying Release Xbox360 Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 14 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release Xbox360 ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_GAME_WIIWARE: //WiiWare + VC
						if ($r['size'] < 1000000 && $r['totalpart'] <= 1) {
							$this->handleClean($r, "Modifying Release Wiiware Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 14 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release Wiiware ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_GAME_XBOX360DLC: //Xbox 360DLC
						if ($r['size'] < 50000000) {
							//I Haven't figured out what to do for this group yet...
							//handleClean($r,true);
							continue;
						}
						if (strlen($r['name']) < 16 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release Xbox 360 DLC ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_GAME_PS3: //PS3
						if ($r['size'] < 100000) {
							$this->handleClean($r, "Modifying Release Due to PS3 Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 14 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release PS3 Due to Release Length: " . $r['name'] . " - ");
							continue;
						}
						break;

					//MOVIES
					case Category::CAT_MOVIE_FOREIGN: //Foreign Movies
						if (($r['size'] < 10000000 || $r['totalpart'] <= 6) && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release Foreign Movie Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 16 && !preg_match('/(fix|pack|\b((19|20)\d{2})\b)/i', $r['name']) && !$r['preid']) {
							$this->handleClean($r, "Modifying Release Foreign Movies ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_MOVIE_OTHER: //Other Movies
						if (($r['size'] < 10000000 || $r['totalpart'] <= 6) && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release Other Movies Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 24 && !preg_match('/(fix|pack|\b((19|20)\d{2})\b)/i', $r['name']) && !$r['preid']) {
							$this->handleClean($r, "Modifying Release Other Movies ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_MOVIE_SD: //SD Movies
						if (($r['size'] < 10000000 || $r['totalpart'] <= 6) && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release SD Movie Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 21 && !preg_match('/(fix|pack|\b((19|20)\d{2})\b)/i', $r['name']) && !$r['preid']) {
							$this->handleClean($r, "Modifying Release SD Movies ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_MOVIE_HD: //HD Movies
						if (($r['size'] < 60000000 || $r['totalpart'] <= 6) && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release HD Movie Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 21 && !preg_match('/(fix|pack|\b((19|20)\d{2})\b)/i', $r['name']) && !$r['preid']) {
							$this->handleClean($r, "Modifying Release HD Movies ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_MOVIE_BLURAY: //Bluray Movies
						if (($r['size'] < 60000000 || $r['totalpart'] <= 6) && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release Bluray Movie Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 21 && !preg_match('/(fix|pack|\b((19|20)\d{2})\b)/i', $r['name']) && !$r['preid']) {
							$this->handleClean($r, "Modifying Release Bluray Movies ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_MOVIE_3D: //3D Movies
						if (($r['size'] < 60000000 || $r['totalpart'] <= 6) && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, true);
							continue;
						}
						if (strlen($r['name']) < 15 && !preg_match('/(fix|pack|\b((19|20)\d{2})\b)/i', $r['name']) && !$r['preid']) {
							$this->handleClean($r, "Modifying Release 3D Movies ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					//AUDIO
					case Category::CAT_MUSIC_MP3: // MP3
						if (preg_match('/m3u/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release Audio MP3 Due to M3U in the name: " . $r['name'] . " - ", true);
							continue;
						}
						if ($r['size'] < 5000000) {
							$this->handleClean($r, "Modifying Release Audio MP3 Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 25 && !preg_match('/(discography|\b((19|20)\d{2})\b)/i', $r['name']) && $r['musicinfoid'] == '-2' && !$r['preid']) {
							$this->handleClean($r, "Modifying Release Audio MP3 ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_MUSIC_VIDEO: // Video
						if ($r['size'] < 10000000) {
							$this->handleClean($r, "Modifying Release Audio Video Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 20 && !preg_match('/(discography|\b((19|20)\d{2})\b)/i', $r['name']) && $r['musicinfoid'] == '-2' && !$r['preid']) {
							//echo "Modifying Release Audio Video ReleaseLEN: ".$r['name']." - ";
							//handleClean($r); not sure what to
							continue;
						}
						break;

					case Category::CAT_MUSIC_AUDIOBOOK: // Audiobook
						if ($r['size'] < 10000000) {
							$this->handleClean($r, "Modifying Release Audiobook Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 20 && !preg_match('/(discography|\b((19|20)\d{2})\b)/i', $r['name']) && $r['musicinfoid'] == '-2' && !$r['preid']) {
							$this->handleClean($r, "Modifying Release Audiobook ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_MUSIC_LOSSLESS: // Lossless
						if ($r['size'] < 10000000) {
							//echo "Modifying Release Audio Lossless Size: ".$r['name']." - ";
							//handleClean($r,true);
							continue;
						}
						if (strlen($r['name']) < 20 && !preg_match('/(discography|\b((19|20)\d{2})\b)/i', $r['name']) && $r['musicinfoid'] == '-2' && !$r['preid']) {
							//echo "Modifying Release Audio Lossless ReleaseLEN: ".$r['name']." - ";
							//handleClean($r);
							continue;
						}
						break;

					// PC
					case Category::CAT_PC_0DAY: // 0Day
						if ($r['size'] < 1500000) {
							$this->handleClean($r, "Modifying Release PC 0Day Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 20 && $r['nfoid'] == null && !$r['preid']) {
							$this->handleClean($r, "Modifying Release PC 0Day ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_PC_ISO: // ISO
						if ($r['size'] < 1000000) {
							$this->handleClean($r, "Modifying Release PC ISO Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 20 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release PC ISO ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_PC_MAC: // Mac
						if ($r['size'] < 500000) {
							$this->handleClean($r, "Modifying Release PC Mac Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 15 && !$r['preid']) {
							//echo "Modifying Release PC Mac ReleaseLEN: ".$r['name']." - ";
							//handleClean($r); Not sure about this yet
							continue;
						}
						break;

					case Category::CAT_PC_MOBILEOTHER: // Mobile Other
						if ($r['size'] < 500000) {
							$this->handleClean($r, "Modifying Release PC Mobile Other Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 20 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release PC Mobile Other ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_PC_GAMES: // Games
						if ($r['size'] < 1000000) {
							$this->handleClean($r, "Modifying Release PC Games Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 15 && !$r['preid']) {
							//echo "Modifying Release PC Games ReleaseLEN: ".$r['name']." - ";
							//handleClean($r); Not sure about this yet

							continue;
						}
						break;

					case Category::CAT_PC_MOBILEIOS: // Ios
						if ($r['size'] < 600000) {
							$this->handleClean($r, "Modifying Release PC Ios Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 21 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release PC Ios ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_PC_MOBILEANDROID: // Android
						if ($r['size'] < 600000) {
							$this->handleClean($r, "Modifying Release PC Android Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 15 && !$r['preid']) {
							//echo "Modifying Release PC Android ReleaseLEN: ".$r['name']." - ";
							//handleClean($r); Not sure about this yet
							continue;
						}
						break;

					// TV
					case Category::CAT_TV_FOREIGN: // Foreign
						if ($r['size'] < 20000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release TV Foreign Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 18 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release TV Foreign ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_TV_SD: // SD
						if ($r['size'] < 20000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release TV SD Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 20 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release TV SD ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_TV_HD: // HD
						if ($r['size'] < 20000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release TV HD Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 15 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release TV HD ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_TV_OTHER: // Other
						if ($r['size'] < 20000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release TV Other Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 15 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release TV Other ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_TV_SPORT: // Sport
						if ($r['size'] < 20000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release TV Sport Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 19 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release TV Sport ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_TV_ANIME: // Anime
						if ($r['size'] < 20000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release TV Anime Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 15 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release TV Anime ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_TV_DOCU: // Docu
						if ($r['size'] < 20000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release TV Docu Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 15 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release TV Docu ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					// XXX
					case Category::CAT_XXX_DVD: // DVD
						if ($r['size'] < 20000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release XXX DVD Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 20 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release XXX DVD ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_XXX_WMV: // WMV
						if ($r['size'] < 20000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release XXX WMV Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 20 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release XXX WMV ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_XXX_XVID: // XVID
						if ($r['size'] < 20000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release XXX XVID Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 14 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release XXX XVID ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_XXX_X264: // X264
						if ($r['size'] < 20000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release XXX X264 Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 15 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release XXX X264 ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_XXX_PACK: // PACK
						if ($r['size'] < 20000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release XXX PACK Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 15 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release XXX PACK ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_XXX_IMAGESET: // IMAGESET
						if ($r['size'] < 3000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release XXX IMAGESET Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 15 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release XXX IMAGESET ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_BOOK_EBOOK: // Ebook
						if ($r['size'] < 50000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release Misc Ebook Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 12 && !$r['preid']) {
							$this->handleClean($r, "Modifying Release Misc Ebook ReleaseLEN: " . $r['name'] . " - ");
							continue;
						}
						break;

					case Category::CAT_BOOK_COMICS: // Comic
						if ($r['size'] < 50000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							//echo "Modifying Release MISC Comic Size: ".$r['name']." - ";
							//handleClean($r,true);
							continue;
						}
						if (strlen($r['name']) < 7 && !$r['preid']) {
							//echo "Modifying Release MISC Comic ReleaseLEN: ".$r['name']." - ";
							//handleClean($r);
							continue;
						}
						break;

					//OTHER
					case Category::CAT_MISC_OTHER:
						if ($r['size'] < 1000000 && !preg_match('/(fix|pack)/i', $r['name'])) {
							$this->handleClean($r, "Modifying Release Misc Other Size: " . $r['name'] . " - ", true);
							continue;
						}
						if (strlen($r['name']) < 15 && !$r['preid']) {
							//echo "Modifying Release MISC Other ReleaseLEN: ".$r['name']." - ";
							//handleClean($r); Not sure what to do with this
							continue;
						}
						break;
				}
				///
				///End of Remove by name section
				///

			}

			$this->doClean();
			gc_collect_cycles();
		}
	}

	/**
	 * Stores reason code and id of item to be cleaned.
	 */
	private function handleClean($row, $reason = "", $forceNuke = false)
	{
		if (!$forceNuke) {
			$this->cleanup['misc'][$row['id']] = true;
			if ($this->verbose) echo $reason . "Moving to Misc Other\n";
		} else {
			$this->cleanup['nuke'][$row['id']] = true;
			if ($this->verbose) echo $reason . "Removing Release\n";
		}
	}

	/**
	 * Perform cleanup of all items in arrays.
	 */
	private function doClean()
	{

		$this->numnuked += count($this->cleanup['nuke']);
		$this->nummiscd += count($this->cleanup['misc']);

		if (!$this->echoonly) {
			$releases = new \Releases(['Settings' => $this->pdo]);
			foreach (array_keys($this->cleanup['nuke']) as $id) {
				$releases->delete($id);
			}

			if (count($this->cleanup['misc'])) {
				$sql = 'update releases set categoryid = ' . Category::CAT_MISC_OTHER . ' where categoryid != ' . Category::CAT_MISC_OTHER . ' and id in (' . implode(array_keys($this->cleanup['misc']), ',') . ')';
				$this->pdo->queryExec($sql);
			}
		}

		$this->cleanup = array('nuke' => array(), 'misc' => array());
	}

	/**
	 * Removes funky chars from beginning and end of string
	 */
	public function removeSpecial()
	{
		$sql = "select id, searchname from releases where 1 = 1 ";
		$sql .= ($this->limited ? "AND adddate BETWEEN NOW() - INTERVAL 1 DAY AND NOW()" : "");
		$sql .= " order by postdate desc";

		$res = $this->pdo->queryDirect($sql);
		while ($r = $this->pdo->getAssocArray($res)) {
			$oldname = $r['searchname'];

			if (preg_match('/^(\:|\"|\-| )+/', $r['searchname'])) {
				while (preg_match('/^(\:|\"|\-| |\_)+/', $r['searchname'])) {
					$r['searchname'] = substr($r['searchname'], 1);
				}
				$this->updateName($this->pdo, $r['id'], $oldname, $r['searchname']);
			}
			if (preg_match('/^000\-/', $r['searchname'])) {
				while (preg_match('/^000\-/', $r['searchname'])) {
					$r['searchname'] = substr($r['searchname'], 4);
				}
				$this->updateName($this->pdo, $r['id'], $oldname, $r['searchname']);
			}
			if (preg_match('/(\:|\"|\-| |\/)$/', $r['searchname'])) {
				while (preg_match('/(\:|\"|\-| |\/)$/', $r['searchname'])) {
					$r['searchname'] = substr($r['searchname'], 0, -1);
				}
				$this->updateName($this->pdo, $r['id'], $oldname, $r['searchname']);
			}
			if (preg_match('/\"/', $r['searchname'])) {
				while (preg_match('/\"/', $r['searchname'])) {
					$r['searchname'] = str_replace('"', '', $r['searchname']);
				}
				$this->updateName($this->pdo, $r['id'], $oldname, $r['searchname']);
			}
			if (preg_match('/\-\d{1}$/', $r['searchname'])) {
				while (preg_match('/\-\d{1}$/', $r['searchname'])) {
					$r['searchname'] = preg_replace('/\-\d{1}$/', '', $r['searchname']);
				}
				$this->updateName($this->pdo, $r['id'], $oldname, $r['searchname']);
			}
			if (preg_match('/\!+.*?mom.*?\!+/i', $r['searchname'])) {
				while (preg_match('/\!+.*?mom.*?\!+/i', $r['searchname'])) {
					$r['searchname'] = preg_replace('/\!+.*?mom.*?\!+/i', '', $r['searchname']);
				}
				$this->updateName($this->pdo, $r['id'], $oldname, $r['searchname']);
			}
			if (preg_match('/(\\/)/i', $r['searchname'])) {
				while (preg_match('/(\\/)/i', $r['searchname'])) {
					$r['searchname'] = preg_replace('/(\\/)/i', '', $r['searchname']);
				}
				$this->updateName($this->pdo, $r['id'], $oldname, $r['searchname']);
			}
		}
	}

	/**
	 * update a release name
	 *
	 * @param DB $pdo
	 * @param    $id
	 * @param    $oldname
	 * @param    $newname
	 */
	private function updateName(DB $pdo, $id, $oldname, $newname)
	{
		if ($this->verbose)
			echo sprintf("OLD : %s\nNEW : %s\n\n", $oldname, $newname);

		if (!$this->echoonly)
			$this->pdo->queryExec(sprintf("update releases set name=%s, searchname = %s WHERE id = %d", $this->pdo->escapeString($newname), $this->pdo->escapeString($newname), $id));
	}
}
