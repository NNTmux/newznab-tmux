<?php
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/util.php");

/**
 * This class handles storage and retrieval of releaseextrafull/releasevideo/audio/subs data.
 */
class ReleaseExtra
{

	const VIDEO_RESOLUTION_NA = 0;
	const VIDEO_RESOLUTION_SD = 1;
	const VIDEO_RESOLUTION_720 = 2;
	const VIDEO_RESOLUTION_1080 = 3;

	/**
	 * Convert a codec string to a user friendly format.
	 */
	public function makeCodecPretty($codec)
	{
		if (preg_match("/DX50|DIVX|DIV3/i", $codec))
			return "DivX";
		if (preg_match("/XVID/i", $codec))
			return "XviD";
		if (preg_match("/^27$/i", $codec))
			return "Blu-Ray";
		if (preg_match("/V_MPEG4\/ISO\/AVC/i", $codec))
			return "x264";
		if (preg_match("/wmv|WVC1/i", $codec))
			return "wmv";
		if (preg_match("/^2$/i", $codec))
			return "HD.ts";
		if (preg_match("/avc1/i", $codec))
			return "h.264";
		if (preg_match("/DX50|DIVX|DIV3/i", $codec))
			return "DivX";

		return $codec;
	}

	public function getAudio($id)
	{
		$db = new DB();

		return $db->query(sprintf("select * from releaseaudio where releaseid = %d order by audioid ASC", $id));
	}

	public function getBriefByGuid($guid)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("select containerformat,videocodec,videoduration,videoaspect, concat(releasevideo.videowidth,'x',releasevideo.videoheight,' @',format(videoframerate,0),'fps') as size, group_concat(distinct releaseaudio.audiolanguage SEPARATOR ', ') as audio, group_concat(distinct releaseaudio.audiobitrate SEPARATOR ', ') as audiobitrate, group_concat(distinct releaseaudio.audioformat SEPARATOR ', ') as audioformat, group_concat(distinct releaseaudio.audiomode SEPARATOR ', ') as audiomode,  group_concat(distinct releaseaudio.audiobitratemode SEPARATOR ', ') as audiobitratemode, group_concat(distinct releasesubs.subslanguage SEPARATOR ', ') as subs from releaseaudio left outer join releasesubs on releaseaudio.releaseid = releasesubs.releaseid left outer join releasevideo on releasevideo.releaseid = releaseaudio.releaseid inner join releases r on r.id = releaseaudio.releaseid where r.guid = %s group by r.id", $db->escapeString($guid)));
	}

	public function delete($id)
	{
		$db = new DB();
		$db->queryExec(sprintf("DELETE from releaseaudio where releaseid = %d", $id));
		$db->queryExec(sprintf("DELETE from releasesubs where releaseid = %d", $id));
		$db->queryExec(sprintf("DELETE from releaseextrafull where releaseid = %d", $id));
		$db->queryExec(sprintf("DELETE from releasevideo where releaseid = %d", $id));
	}

	/**
	 * Add releasevideo/audio/subs for a release based on the mediainfo xml.
	 */
	public function addFromXml($releaseID, $xml)
	{
		$xmlObj = @simplexml_load_string($xml);
		$arrXml = objectsIntoArray($xmlObj);
		$containerformat = "";
		$overallbitrate = "";

		if (isset($arrXml["File"]) && isset($arrXml["File"]["track"])) {
			foreach ($arrXml["File"]["track"] as $track) {
				if (isset($track["@attributes"]) && isset($track["@attributes"]["type"])) {
					if ($track["@attributes"]["type"] == "General") {
						if (isset($track["Format"]))
							$containerformat = $track["Format"];
						if (isset($track["Overall_bit_rate"]))
							$overallbitrate = $track["Overall_bit_rate"];
						$gendata = $track;
					} elseif ($track["@attributes"]["type"] == "Video") {
						$videoduration = "";
						$videoformat = "";
						$videocodec = "";
						$videowidth = "";
						$videoheight = "";
						$videoaspect = "";
						$videoframerate = "";
						$videolibrary = "";
						$gendata = "";
						$viddata = "";
						$audiodata = "";
						if (isset($track["Duration"]))
							$videoduration = $track["Duration"];
						if (isset($track["Format"]))
							$videoformat = $track["Format"];
						if (isset($track["Codec_ID"]))
							$videocodec = $track["Codec_ID"];
						if (isset($track["Width"]))
							$videowidth = preg_replace("/[^0-9]/", '', $track["Width"]);
						if (isset($track["Height"]))
							$videoheight = preg_replace("/[^0-9]/", '', $track["Height"]);
						if (isset($track["Display_aspect_ratio"]))
							$videoaspect = $track["Display_aspect_ratio"];
						if (isset($track["Frame_rate"]))
							$videoframerate = str_replace(" fps", "", $track["Frame_rate"]);
						if (isset($track["Writing_library"]))
							$videolibrary = $track["Writing_library"];
						$viddata = $track;
						$this->addVideo($releaseID, $containerformat, $overallbitrate, $videoduration,
							$videoformat, $videocodec, $videowidth, $videoheight,
							$videoaspect, $videoframerate, $videolibrary
						);
					} elseif ($track["@attributes"]["type"] == "Audio") {
						$audioID = 1;
						$audioformat = "";
						$audiomode = "";
						$audiobitratemode = "";
						$audiobitrate = "";
						$audiochannels = "";
						$audiosamplerate = "";
						$audiolibrary = "";
						$audiolanguage = "";
						$audiotitle = "";
						if (isset($track["@attributes"]["streamid"]))
							$audioID = $track["@attributes"]["streamid"];
						if (isset($track["Format"]))
							$audioformat = $track["Format"];
						if (isset($track["Mode"]))
							$audiomode = $track["Mode"];
						if (isset($track["Bit_rate_mode"]))
							$audiobitratemode = $track["Bit_rate_mode"];
						if (isset($track["Bit_rate"]))
							$audiobitrate = $track["Bit_rate"];
						if (isset($track["Channel_s_"]))
							$audiochannels = $track["Channel_s_"];
						if (isset($track["Sampling_rate"]))
							$audiosamplerate = $track["Sampling_rate"];
						if (isset($track["Writing_library"]))
							$audiolibrary = $track["Writing_library"];
						if (isset($track["Language"]))
							$audiolanguage = $track["Language"];
						if (isset($track["Title"]))
							$audiotitle = $track["Title"];
						$audiodata = $track;
						$this->addAudio($releaseID, $audioID, $audioformat, $audiomode, $audiobitratemode, $audiobitrate, $audiochannels, $audiosamplerate, $audiolibrary, $audiolanguage, $audiotitle);
					} elseif ($track["@attributes"]["type"] == "Text") {
						$subsID = 1;
						$subslanguage = "Unknown";
						if (isset($track["@attributes"]["streamid"]))
							$subsID = $track["@attributes"]["streamid"];
						if (isset($track["Language"]))
							$subslanguage = $track["Language"];
						$this->addSubs($releaseID, $subsID, $subslanguage);
					}
				}
			}
		}
	}

	/**
	 * Add a releasevideo row.
	 */
	public function addVideo($releaseID, $containerformat, $overallbitrate, $videoduration, $videoformat, $videocodec, $videowidth, $videoheight, $videoaspect, $videoframerate, $videolibrary)
	{
		$db = new DB();

		$row = $this->getVideo($releaseID);
		if ($row)
			return -1;

		if (is_numeric($videoframerate))
			$videoframerate = number_format($videoframerate, 3, '.', '');
		else
			$videoframerate = 0.0;

		$sql = sprintf("insert into releasevideo
						(releaseid, containerformat, overallbitrate, videoduration,
						videoformat, videocodec, videowidth, videoheight,
						videoaspect, videoframerate, 	videolibrary, definition)
						values ( %d, %s, %s, %s, %s, %s, %d, %d, %s, %s, %s, %d )",
			$releaseID, $db->escapeString($containerformat), $db->escapeString($overallbitrate), $db->escapeString($videoduration),
			$db->escapeString($videoformat), $db->escapeString($videocodec), $videowidth, $videoheight,
			$db->escapeString($videoaspect), $db->escapeString($videoframerate), $db->escapeString($videolibrary), self::determineVideoResolution($videowidth, $videoheight)
		);

		return $db->queryInsert($sql);
	}

	public function getVideo($id)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("select * from releasevideo where releaseid = %d", $id));
	}

	/**
	 * Work out the res based on height and width
	 */
	public function determineVideoResolution($width, $height)
	{
		if ($width == 0 || $height == 0)
		{
			return self::VIDEO_RESOLUTION_NA;
		}
		elseif ($width <= 720 && $height <= 480)
		{
			return self::VIDEO_RESOLUTION_SD; //SD 480
		}
		elseif ($width <= 768 && $height <= 576) // 720x576 (PAL) (768 when rescaled for square pixels)
		{
			return self::VIDEO_RESOLUTION_SD; //SD 576
		}
		elseif ($width <= 1048 && $height <= 576) // 1024x576 (PAL) (1048 when rescaled for square pixels) (16x9)
		{
			return self::VIDEO_RESOLUTION_SD; //SD 576 16x9
		}
		elseif ($width <= 960 && $height <= 544) // 960x540 (sometimes 544 which is multiple of 16)
		{
			return self::VIDEO_RESOLUTION_SD; //SD 540
		}
		elseif ($width <= 1280 && $height <= 720) // 1280x720
		{
			return self::VIDEO_RESOLUTION_720; //HD 720
		}
		else // 1920x1080
		{
			return self::VIDEO_RESOLUTION_1080; //HD 1080
		}

		return self::VIDEO_RESOLUTION_NA; //catch all

	}

	/**
	 * Add a releaseaudio row.
	 */
	public function addAudio($releaseID, $audioID, $audioformat, $audiomode, $audiobitratemode, $audiobitrate, $audiochannels, $audiosamplerate, $audiolibrary, $audiolanguage, $audiotitle)
	{
		$db = new DB();

		$row = $this->getAudioAndChannel($releaseID, $audioID);
		if ($row)
			return -1;

		$sql = sprintf("insert into releaseaudio
						(releaseid,	audioid,audioformat,audiomode, audiobitratemode, audiobitrate,
						audiochannels,audiosamplerate,audiolibrary,audiolanguage,audiotitle)
						values ( %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s )",
			$releaseID, $audioID, $db->escapeString($audioformat), $db->escapeString($audiomode), $db->escapeString($audiobitratemode),
			$db->escapeString($audiobitrate), $db->escapeString($audiochannels), $db->escapeString($audiosamplerate), $db->escapeString(substr($audiolibrary, 0, 255)),
			$db->escapeString(substr($audiolanguage, 0, 255)), $db->escapeString(substr($audiotitle, 0, 255))
		);

		return $db->queryInsert($sql);
	}

	public function getAudioAndChannel($rid, $aid)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("select * from releaseaudio where releaseid = %d and audioid = %d", $rid, $aid));
	}

	public function addSubs($releaseID, $subsID, $subslanguage)
	{
		$db = new DB();
		$row = $this->getSubs($releaseID);
		if ($row)
			return -1;

		$sql = sprintf("insert into releasesubs (releaseid,	subsID, subslanguage) values ( %d, %d, %s)", $releaseID, $subsID, $db->escapeString($subslanguage));

		return $db->queryInsert($sql);
	}

	public function getSubs($id)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("SELECT group_concat(subslanguage SEPARATOR ', ') as subs FROM `releasesubs` WHERE `releaseid` = %d ORDER BY `subsID` ASC", $id));
	}

	public function deleteFull($id)
	{
		$db = new DB();

		return $db->queryExec(sprintf("DELETE from releaseextrafull where releaseid = %d", $id));
	}

	public function addFull($id, $xml)
	{
		$db = new DB();
		$row = $this->getFull($id);
		if ($row)
			return -1;

		return $db->queryInsert(sprintf("insert into releaseextrafull (releaseid, mediainfo) values (%d, %s)", $id, $db->escapeString($xml)));
	}

	public function getFull($id)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("select * from releaseextrafull where releaseid = %d", $id));
	}
}