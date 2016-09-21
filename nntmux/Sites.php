<?php
namespace nntmux;

use app\extensions\util\Versions;
use nntmux\libraries\Cache;
use nntmux\db\DB;


class Sites
{
	const REGISTER_STATUS_OPEN = 0;
	const REGISTER_STATUS_INVITE = 1;
	const REGISTER_STATUS_CLOSED = 2;
	const REGISTER_STATUS_API_ONLY = 3;

	const ERR_BADUNRARPATH = -1;
	const ERR_BADFFMPEGPATH = -2;
	const ERR_BADMEDIAINFOPATH = -3;
	const ERR_BADNZBPATH = -4;
	const ERR_DEEPNOUNRAR = -5;
	const ERR_BADTMPUNRARPATH = -6;
	const ERR_BADLAMEPATH = -7;
	const ERR_SABCOMPLETEPATH = -8;

	/**
	 * @var \nntmux\db\Settings
	 */
	protected $_db;

	/**
	 * @var \app\extensions\util\Versions|bool
	 */
	protected $_versions = false;

	/**
	 * @param array $options Class instances.
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Settings' => null,
		];
		$options += $defaults;

		$this->_db = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());

		if (defined('NN_VERSIONS')) {
			try {
				$this->_versions = new Versions();
			} catch (\Exception $e) {
				$this->_versions = false;
			}
		}
	}


	public function version()
	{
		return ($this->_versions === false ? '0.0.0' : $this->_versions->getGitTagInRepo());
	}

	public function update($form)
	{
		$site = $this->row2Object($form);

		if (substr($site->nzbpath, strlen($site->nzbpath) - 1) != '/')
			$site->nzbpath = $site->nzbpath . "/";

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

		$sql = $sqlKeys = [];
		foreach ($form as $settingK => $settingV) {
			$sql[] = sprintf("WHEN %s THEN %s", $this->_db->escapeString($settingK), $this->_db->escapeString(trim($settingV)));
			$sqlKeys[] = $this->_db->escapeString($settingK);
		}

		$this->_db->exec(sprintf("update site SET value = CASE setting %s END WHERE setting IN (%s)", implode(' ', $sql), implode(', ', $sqlKeys)));

		return $this->get(true);
	}

	public function get($refresh = false)
	{
		$sql = "select * from site";

		if ($refresh) {
			$cache = new Cache();
			$cache->delete($sql);
		}

		$rows = $this->_db->query($sql, true, NN_CACHE_EXPIRY_MEDIUM);

		if ($rows === false)
			return false;

		return $this->rows2Object($rows);
	}

	public function rows2Object($rows)
	{
		$obj = new \stdClass;
		foreach ($rows as $row)
			$obj->{$row['setting']} = $row['value'];

		$obj->{'version'} = $this->version();

		return $obj;
	}

	public function row2Object($row)
	{
		$obj = new \stdClass;
		$rowKeys = array_keys($row);
		foreach ($rowKeys as $key)
			$obj->{$key} = $row[$key];

		return $obj;
	}

	public function getUnappliedPatches($site)
	{
		preg_match("/\d+/", $site->dbversion, $matches);
		$currentrev = $matches[0];

		$patchpath = NN_WWW . "../db/patch/0.2.3/";
		$patchfiles = glob($patchpath . "*.sql");
		$missingpatch = [];
		foreach ($patchfiles as $file) {
			$filecontents = file_get_contents($file);
			if (preg_match("/Rev\: (\d+)/", $filecontents, $matches)) {
				$patchrev = $matches[1];
				if ($patchrev > $currentrev)
					$missingpatch[] = $file;
			}
		}

		return $missingpatch;
	}

	public function updateItem($setting, $value)
	{
		$sql = sprintf("update settings set value = %s where setting = %s", $this->_db->escapeString($value), $this->_db->escapeString($setting));

		return $this->_db->exec($sql);
	}

	public function updateLatestRegexRevision($rev)
	{
		return $this->updateItem("latestregexrevision", $rev);
	}

	public function getLicense($html = false)
	{
		$n = "\r\n";
		if ($html)
			$n = "<br/>";

		return $n . "newznab " . $this->version() . " Copyright (C) " . date("Y") . " newznab.com" . $n . "

This program is distributed with a commercial licence. See LICENCE.txt for
further details." . $n;
	}
}
