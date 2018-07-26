<?php

namespace Blacklight;

use App\Models\AudioData;
use App\Models\VideoData;
use App\Models\ReleaseUnique;
use App\Models\ReleaseSubtitle;
use Blacklight\utility\Utility;
use App\Models\ReleaseExtraFull;
use Illuminate\Support\Facades\DB;

class ReleaseExtra
{
    /**
     * @var \PDO
     */
    public $pdo;

    /**
     * ReleaseExtra constructor.
     */
    public function __construct()
    {
        $this->pdo = DB::connection()->getPdo();
    }

    /**
     * @param $codec
     *
     * @return string
     */
    public function makeCodecPretty($codec): string
    {
        switch (true) {
            case preg_match('#(?:^36$|HEVC)#i', $codec):
                $codec = 'HEVC';
                break;
            case preg_match('#(?:^(?:7|27|H264)$|AVC)#i', $codec):
                $codec = 'h.264';
                break;
            case preg_match('#(?:^(?:20|FMP4|MP42|MP43|MPG4)$|ASP)#i', $codec):
                $codec = 'MPEG-4';
                break;
            case preg_match('#^2$#i', $codec):
                $codec = 'MPEG-2';
                break;
            case $codec === 'MPEG':
                $codec = 'MPEG-1';
                break;
            case preg_match('#DX50|DIVX|DIV3#i', $codec):
                $codec = 'DivX';
                break;
            case stripos($codec, 'XVID') !== false:
                $codec = 'XviD';
                break;
            case preg_match('#(?:wmv|WVC1)#i', $codec):
                $codec = 'wmv';
                break;
            default:
        }

        return $codec;
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function get($id)
    {
        // hopefully nothing will use this soon and it can be deleted
        return VideoData::query()->where('releases_id', $id)->first();
    }

    /**
     * @param $id
     * @return array|bool
     */
    public function getVideo($id)
    {
        $result = VideoData::query()->where('releases_id', $id)->first();

        if ($result !== null) {
            return $result->toArray();
        }

        return false;
    }

    /**
     * @param $id
     * @return array|bool
     */
    public function getAudio($id)
    {
        $result = AudioData::query()->where('releases_id', $id)->orderBy('audioid')->get();

        if ($result !== null) {
            return $result->toArray();
        }

        return false;
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getSubs($id)
    {
        return ReleaseSubtitle::query()
            ->where('releases_id', $id)
            ->select([DB::raw("GROUP_CONCAT(subslanguage SEPARATOR ', ') AS subs")])
            ->orderBy('subsid')
            ->first();
    }

    /**
     * @param $guid
     *
     * @return array|bool
     */
    public function getBriefByGuid($guid)
    {
        return DB::select(
            sprintf(
                "SELECT containerformat, videocodec, videoduration, videoaspect,
                        CONCAT(video_data.videowidth,'x',video_data.videoheight,' @',format(videoframerate,0),'fps') AS size,
                        GROUP_CONCAT(DISTINCT audio_data.audiolanguage SEPARATOR ', ') AS audio,
                        GROUP_CONCAT(DISTINCT audio_data.audioformat,' (',SUBSTRING(audio_data.audiochannels,1,1),' ch)' SEPARATOR ', ') AS audioformat,
                        GROUP_CONCAT(DISTINCT audio_data.audioformat,' (',SUBSTRING(audio_data.audiochannels,1,1),' ch)' SEPARATOR ', ') AS audioformat,
                        GROUP_CONCAT(DISTINCT release_subtitles.subslanguage SEPARATOR ', ') AS subs
                        FROM video_data
                        LEFT OUTER JOIN release_subtitles ON video_data.releases_id = release_subtitles.releases_id
                        LEFT OUTER JOIN audio_data ON video_data.releases_id = audio_data.releases_id
                        INNER JOIN releases r ON r.id = video_data.releases_id
                        WHERE r.guid = %s
                        GROUP BY r.id LIMIT 1",
                $this->pdo->quote($guid)
            )
        );
    }

    /**
     * @param $guid
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null|object
     */
    public function getByGuid($guid)
    {
        return VideoData::query()->where('r.guid', $guid)->leftJoin('releases as r', 'r.id', '=', 'video_data.releases_id')->first();
    }

    /**
     * @param $id
     */
    public function delete($id)
    {
        AudioData::query()->where('releases_id', $id)->delete();
        ReleaseSubtitle::query()->where('releases_id', $id)->delete();
        VideoData::query()->where('releases_id', $id)->delete();
    }

    /**
     * @param $releaseID
     * @param $xml
     */
    public function addFromXml($releaseID, $xml)
    {
        $xmlObj = @simplexml_load_string($xml);
        $arrXml = Utility::objectsIntoArray($xmlObj);
        $containerformat = '';
        $overallbitrate = '';

        if (isset($arrXml['File']['track'])) {
            foreach ($arrXml['File']['track'] as $track) {
                if (isset($track['@attributes']['type'])) {
                    if ($track['@attributes']['type'] === 'General') {
                        if (isset($track['Format'])) {
                            $containerformat = $track['Format'];
                        }
                        if (isset($track['Overall_bit_rate'])) {
                            $overallbitrate = $track['Overall_bit_rate'];
                        }
                        if (isset($track['Unique_ID']) && preg_match('/\(0x(?P<hash>[0-9a-f]{32})\)/i', $track['Unique_ID'], $matches)) {
                            $uniqueid = $matches['hash'];
                            $this->addUID($releaseID, $uniqueid);
                        }
                    } elseif ($track['@attributes']['type'] === 'Video') {
                        $videoduration = $videoformat = $videocodec = $videowidth = $videoheight = $videoaspect = $videoframerate = $videolibrary = '';
                        if (isset($track['Duration'])) {
                            $videoduration = $track['Duration'];
                        }
                        if (isset($track['Format'])) {
                            $videoformat = $track['Format'];
                        }
                        if (isset($track['Codec_ID'])) {
                            $videocodec = $track['Codec_ID'];
                        }
                        if (isset($track['Width'])) {
                            $videowidth = preg_replace('/[^0-9]/', '', $track['Width']);
                        }
                        if (isset($track['Height'])) {
                            $videoheight = preg_replace('/[^0-9]/', '', $track['Height']);
                        }
                        if (isset($track['Display_aspect_ratio'])) {
                            $videoaspect = $track['Display_aspect_ratio'];
                        }
                        if (isset($track['Frame_rate'])) {
                            $videoframerate = str_replace(' fps', '', $track['Frame_rate']);
                        }
                        if (isset($track['Writing_library'])) {
                            $videolibrary = $track['Writing_library'];
                        }
                        $this->addVideo($releaseID, $containerformat, $overallbitrate, $videoduration, $videoformat, $videocodec, $videowidth, $videoheight, $videoaspect, $videoframerate, $videolibrary);
                    } elseif ($track['@attributes']['type'] === 'Audio') {
                        $audioID = 1;
                        $audioformat = $audiomode = $audiobitratemode = $audiobitrate = $audiochannels = $audiosamplerate = $audiolibrary = $audiolanguage = $audiotitle = '';
                        if (isset($track['@attributes']['streamid'])) {
                            $audioID = $track['@attributes']['streamid'];
                        }
                        if (isset($track['Format'])) {
                            $audioformat = $track['Format'];
                        }
                        if (isset($track['Mode'])) {
                            $audiomode = $track['Mode'];
                        }
                        if (isset($track['Bit_rate_mode'])) {
                            $audiobitratemode = $track['Bit_rate_mode'];
                        }
                        if (isset($track['Bit_rate'])) {
                            $audiobitrate = $track['Bit_rate'];
                        }
                        if (isset($track['Channel_s_'])) {
                            $audiochannels = $track['Channel_s_'];
                        }
                        if (isset($track['Sampling_rate'])) {
                            $audiosamplerate = $track['Sampling_rate'];
                        }
                        if (isset($track['Writing_library'])) {
                            $audiolibrary = $track['Writing_library'];
                        }
                        if (isset($track['Language'])) {
                            $audiolanguage = $track['Language'];
                        }
                        if (! empty($track['Title'])) {
                            $audiotitle = $track['Title'];
                        }
                        $this->addAudio($releaseID, $audioID, $audioformat, $audiomode, $audiobitratemode, $audiobitrate, $audiochannels, $audiosamplerate, $audiolibrary, $audiolanguage, $audiotitle);
                    } elseif ($track['@attributes']['type'] === 'Text') {
                        $subsID = 1;
                        $subslanguage = 'Unknown';
                        if (isset($track['@attributes']['streamid'])) {
                            $subsID = $track['@attributes']['streamid'];
                        }
                        if (isset($track['Language'])) {
                            $subslanguage = $track['Language'];
                        }
                        $this->addSubs($releaseID, $subsID, $subslanguage);
                    }
                }
            }
        }
    }

    /**
     * @param $releaseID
     * @param $containerformat
     * @param $overallbitrate
     * @param $videoduration
     * @param $videoformat
     * @param $videocodec
     * @param $videowidth
     * @param $videoheight
     * @param $videoaspect
     * @param $videoframerate
     * @param $videolibrary
     */
    public function addVideo($releaseID, $containerformat, $overallbitrate, $videoduration, $videoformat, $videocodec, $videowidth, $videoheight, $videoaspect, $videoframerate, $videolibrary)
    {
        $ckid = VideoData::query()->where('releases_id', $releaseID)->first(['releases_id']);
        if ($ckid === null) {
            VideoData::query()->insert(
                [
                    'releases_id' => $releaseID,
                    'containerformat' => $containerformat,
                    'overallbitrate' => $overallbitrate,
                    'videoduration' => $videoduration,
                    'videoformat' => $videoformat,
                    'videocodec' => $videocodec,
                    'videowidth' => $videowidth,
                    'videoheight' => $videoheight,
                    'videoaspect' => $videoaspect,
                    'videoframerate' => $videoframerate,
                    'videolibrary' => substr($videolibrary, 0, 50),
            ]
            );
        }
    }

    /**
     * @param $releaseID
     * @param $audioID
     * @param $audioformat
     * @param $audiomode
     * @param $audiobitratemode
     * @param $audiobitrate
     * @param $audiochannels
     * @param $audiosamplerate
     * @param $audiolibrary
     * @param $audiolanguage
     * @param $audiotitle
     * @return bool
     */
    public function addAudio($releaseID, $audioID, $audioformat, $audiomode, $audiobitratemode, $audiobitrate, $audiochannels, $audiosamplerate, $audiolibrary, $audiolanguage, $audiotitle)
    {
        $ckid = AudioData::query()->where('releases_id', $releaseID)->first(['releases_id']);
        if ($ckid === null) {
            return AudioData::query()->insert(
                [
                    'releases_id' => $releaseID,
                    'audioid' => $audioID,
                    'audioformat' => $audioformat,
                    'audiomode' => $audiomode,
                    'audiobitratemode' => $audiobitratemode,
                    'audiobitrate' => substr($audiobitrate, 0, 10),
                    'audiochannels' => $audiochannels,
                    'audiosamplerate' => substr($audiosamplerate, 0, 25),
                    'audiolibrary' => substr($audiolibrary, 0, 50),
                    'audiolanguage' => $audiolanguage,
                    'audiotitle' => substr($audiotitle, 0, 50),
                ]
            );
        }

        return false;
    }

    /**
     * @param $releaseID
     * @param $subsID
     * @param $subslanguage
     * @return bool
     */
    public function addSubs($releaseID, $subsID, $subslanguage)
    {
        $ckid = ReleaseSubtitle::query()->where('releases_id', $releaseID)->first(['releases_id']);
        if ($ckid === null) {
            return ReleaseSubtitle::query()->insert(['releases_id' => $releaseID, 'subsid' => $subsID, 'subslanguage' => $subslanguage]);
        }

        return false;
    }

    /**
     * @param $releaseID
     * @param $uniqueid
     */
    public function addUID($releaseID, $uniqueid): void
    {
        $dupecheck = ReleaseUnique::query()
            ->where('releases_id', $releaseID)
            ->orWhere(
                [
                    'releases_id' => $releaseID,
                    'uniqueid' => sodium_hex2bin($uniqueid),
                ]
            )->first(['releases_id']);
        if ($dupecheck === null) {
            ReleaseUnique::query()
                ->insert(
                    [
                        'releases_id' => $releaseID,
                        'uniqueid' => sodium_hex2bin($uniqueid),
                    ]
                );
        }
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getFull($id)
    {
        return ReleaseExtraFull::query()->where('releases_id', $id)->first();
    }

    /***
     * @param $id
     *
     * @return mixed
     */
    public function deleteFull($id)
    {
        return ReleaseExtraFull::query()->where('releases_id', $id)->delete();
    }

    /**
     * @param $id
     * @param $xml
     */
    public function addFull($id, $xml)
    {
        $ckid = ReleaseExtraFull::query()->where('releases_id', $id)->first();
        if ($ckid === null) {
            ReleaseExtraFull::query()->insert(['releases_id' => $id, 'mediainfo' => $xml]);
        }
    }
}
