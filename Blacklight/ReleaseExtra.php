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
     * ReleaseExtra constructor.
     */
    public function __construct()
    {
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
     *
     * @return array|false
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
     *
     * @return array|false
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
        return ReleaseSubtitle::query()->where('releases_id', $id)->select([DB::raw("GROUP_CONCAT(subslanguage SEPARATOR ', ') AS subs")])->orderBy('subsid')->first();
    }

    /**
     * @param string $guid
     *
     * @return array
     */
    public function getBriefByGuid($guid): array
    {
        return DB::select(sprintf("SELECT containerformat, videocodec, videoduration, videoaspect,
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
                        GROUP BY r.id LIMIT 1", escapeString($guid)));
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
    public function delete($id): void
    {
        AudioData::query()->where('releases_id', $id)->delete();
        ReleaseSubtitle::query()->where('releases_id', $id)->delete();
        VideoData::query()->where('releases_id', $id)->delete();
    }

    /**
     * @param $releaseID
     * @param $xml
     */
    public function addFromXml($releaseID, $xml): void
    {
        $xmlObj = @simplexml_load_string($xml);
        $arrXml = Utility::objectsIntoArray($xmlObj);
        $containerFormat = '';
        $overallBitRate = '';

        $mediaInfoVersion = 0;
        if (\in_array('File', $arrXml, false) && \in_array('track', $arrXml['File'], false)) {
            $mediaInfoVersion = 1;
        }
        if (\in_array('@attributes', $arrXml, false) && \in_array('@version', $arrXml['@attributes'], false)) {
            $mediaInfoVersion = (int) $arrXml['@attributes']['version'];
        }
        if ($mediaInfoVersion < 1) {
            if (isset($arrXml['File']['track'])) {
                $mediaInfoVersion = 1;
            }
            if (isset($arrXml['@attributes']['version'])) {
                $mediaInfoVersion = (int) $arrXml['@attributes']['version'];
            }
        }

        switch ($mediaInfoVersion) {

            /*
             * MediaInfo Schema v1 (1.x - 7.99)
             */
            case 1:
                foreach ($arrXml['File']['track'] as $track) {
                    if (isset($track['@attributes']['type'])) {
                        if ($track['@attributes']['type'] === 'General') {
                            if (isset($track['Format'])) {
                                $containerFormat = $track['Format'];
                            }
                            if (isset($track['Overall_bit_rate'])) {
                                $overallBitRate = $track['Overall_bit_rate'];
                            }
                            if (isset($track['Unique_ID']) && preg_match('/(?P<uid>^\d+)/i', $track['Unique_ID'], $matches)) {
                                $uniqueId = $matches['uid'];
                                $this->addUID($releaseID, $uniqueId);
                            }
                        } elseif ($track['@attributes']['type'] === 'Video') {
                            $videoDuration = $videoFormat = $videoCodec = $videoWidth = $videoHeight = $videoAspect = $videoFrameRate = $videoLibrary = '';
                            if (isset($track['Duration'])) {
                                $videoDuration = $track['Duration'];
                            }
                            if (isset($track['Format'])) {
                                $videoFormat = $track['Format'];
                            }
                            if (isset($track['Codec_ID'])) {
                                $videoCodec = $track['Codec_ID'];
                            }
                            if (isset($track['Width'])) {
                                $videoWidth = preg_replace('/\D/', '', $track['Width']);
                            }
                            if (isset($track['Height'])) {
                                $videoHeight = preg_replace('/\D/', '', $track['Height']);
                            }
                            if (isset($track['Display_aspect_ratio'])) {
                                $videoAspect = $track['Display_aspect_ratio'];
                            }
                            if (isset($track['Frame_rate'])) {
                                $videoFrameRate = str_replace(' fps', '', $track['Frame_rate']);
                            }
                            if (isset($track['Writing_library'])) {
                                $videoLibrary = $track['Writing_library'];
                            }
                            $this->addVideo($releaseID, $containerFormat, $overallBitRate, $videoDuration, $videoFormat, $videoCodec, $videoWidth, $videoHeight, $videoAspect, $videoFrameRate, $videoLibrary);
                        } elseif ($track['@attributes']['type'] === 'Audio') {
                            $audioID = 1;
                            $audioFormat = $audioMode = $audioBitRateMode = $audioBitRate = $audioChannels = $audioSampleRate = $audioLibrary = $audioLanguage = $audioTitle = '';
                            if (isset($track['@attributes']['streamid'])) {
                                $audioID = $track['@attributes']['streamid'];
                            }
                            if (isset($track['Format'])) {
                                $audioFormat = $track['Format'];
                            }
                            if (! empty($track['Mode'])) {
                                $audioMode = $track['Mode'];
                            }
                            if (isset($track['Bit_rate_mode'])) {
                                $audioBitRateMode = $track['Bit_rate_mode'];
                            }
                            if (isset($track['Bit_rate'])) {
                                $audioBitRate = $track['Bit_rate'];
                            }
                            if (isset($track['Channel_s_'])) {
                                $audioChannels = $track['Channel_s_'];
                            }
                            if (isset($track['Sampling_rate'])) {
                                $audioSampleRate = $track['Sampling_rate'];
                            }
                            if (! empty($track['Writing_library'])) {
                                $audioLibrary = $track['Writing_library'];
                            }
                            if (! empty($track['Language'])) {
                                $audioLanguage = $track['Language'];
                            }
                            if (! empty($track['Title'])) {
                                $audioTitle = $track['Title'];
                            }
                            $this->addAudio($releaseID, $audioID, $audioFormat, $audioMode, $audioBitRateMode, $audioBitRate, $audioChannels, $audioSampleRate, $audioLibrary, $audioLanguage, $audioTitle);
                        } elseif ($track['@attributes']['type'] === 'Text') {
                            $subsID = 1;
                            $subsLanguage = 'Unknown';
                            if (isset($track['@attributes']['streamid'])) {
                                $subsID = $track['@attributes']['streamid'];
                            }
                            if (isset($track['Language'])) {
                                $subsLanguage = $track['Language'];
                            }
                            $this->addSubs($releaseID, $subsID, $subsLanguage);
                        }
                    }
                }
                break;
            case 2:
                /*
                 * MediaInfo Schema v2 (mediaInfo version > 7.99)
                 */
                foreach ($arrXml['media']['track'] as $track) {
                    $type = '';

                    if (isset($track['@attributes']['type'])) {
                        $type = $track['@attributes']['type'];
                    }

                    if (isset($track['type'])) {
                        $type = $track['type'];
                    }

                    if ($type === 'General') {
                        if (! empty($track['UniqueID']) && (int) $track['UniqueID'] !== 1) {
                            $uniqueId = $track['UniqueID'];
                            $this->addUID($releaseID, $uniqueId);
                        }

                        if (isset($track['Format'])) {
                            $containerFormat = $track['Format'];
                        }

                        if (isset($track['OverallBitRate_Nominal'])) {
                            $overallBitRate = $track['OverallBitRate_Nominal'];
                        }

                        if (isset($track['OverallBitRate'])) {
                            $overallBitRate = $track['OverallBitRate'];
                        }
                    } elseif ($type === 'Video') {
                        $videoDuration = $videoFormat = $videoCodec = $videoWidth = $videoHeight = $videoAspect = $videoFrameRate = $videoLibrary = $videoBitRate = '';

                        if (isset($track['Duration'])) {
                            $videoDuration = $track['Duration'];
                        }

                        if (isset($track['Format'])) {
                            $videoFormat = $track['Format'];
                        }

                        if (isset($track['CodecID'])) {
                            $videoCodec = $track['CodecID'];
                        }

                        if (isset($track['Width'])) {
                            $videoWidth = $track['Width'];
                        }

                        if (isset($track['Height'])) {
                            $videoHeight = $track['Height'];
                        }

                        if (isset($track['DisplayAspectRatio'])) {
                            $videoAspect = $track['DisplayAspectRatio'];
                        }

                        if (isset($track['FrameRate'])) {
                            $videoFrameRate = $track['FrameRate'];
                        }

                        if (isset($track['Encoded_Library_Version'])) {
                            $videoLibrary = $track['Encoded_Library_Version'];
                        }

                        if (isset($track['Encoded_Library'])) {
                            $videoLibrary = $track['Encoded_Library'];
                        }

                        if (isset($track['BitRate'])) {
                            $videoBitRate = $track['BitRate'];
                        }

                        if (isset($track['BitRate_Nominal'])) {
                            $videoBitRate = $track['BitRate_Nominal'];
                        }

                        if (! empty($videoBitRate)) {
                            $overallBitRate = $videoBitRate;
                        }

                        $this->addVideo($releaseID, $containerFormat, $overallBitRate, $videoDuration, $videoFormat, $videoCodec, $videoWidth, $videoHeight, $videoAspect, $videoFrameRate, $videoLibrary);
                    } elseif ($type === 'Audio') {
                        $audioID = 1;
                        $audioFormat = $audioMode = $audioBitRateMode = $audioBitRate = $audioChannels = $audioSampleRate = $audioLibrary = $audioLanguage = $audioTitle = '';

                        if (isset($track['ID'])) {
                            $audioID = $track['ID'];
                        }

                        if (isset($track['Format'])) {
                            $audioFormat = $track['Format'];
                        }

                        if (! empty($track['Mode'])) {
                            $audioMode = $track['Mode'];
                        }

                        if (! empty($track['Format_Settings_Mode'])) {
                            $audioMode = $track['Format_Settings_Mode'];
                        }

                        if (isset($track['BitRate_Mode'])) {
                            $audioBitRateMode = $track['BitRate_Mode'];
                        }

                        if (isset($track['BitRate'])) {
                            $audioBitRate = $track['BitRate'];
                        }

                        if (isset($track['Channels'])) {
                            $audioChannels = $track['Channels'];
                        }

                        if (isset($track['SamplingRate'])) {
                            $audioSampleRate = $track['SamplingRate'];
                        }

                        if (! empty($track['Encoded_Library'])) {
                            $audioLibrary = $track['Encoded_Library'];
                        }

                        if (! empty($track['Language'])) {
                            $audioLanguage = $track['Language'];
                        }

                        if (! empty($track['Title'])) {
                            $audioTitle = $track['Title'];
                        }

                        $this->addAudio($releaseID, $audioID, $audioFormat, $audioMode, $audioBitRateMode, $audioBitRate, $audioChannels, $audioSampleRate, $audioLibrary, $audioLanguage, $audioTitle);
                    } elseif ($type === 'Text') {
                        $subsID = 1;
                        $subsLanguage = 'Unknown';

                        if (isset($track['ID'])) {
                            $subsID = $track['ID'];
                        }

                        if (isset($track['Language'])) {
                            $subsLanguage = $track['Language'];
                        }

                        $this->addSubs($releaseID, $subsID, $subsLanguage);
                    }
                }
                break;
            default:
                break;
        }
    }

    /**
     * @param $releaseID
     * @param $containerFormat
     * @param $overallBitRate
     * @param $videoDuration
     * @param $videoFormat
     * @param $videoCodec
     * @param $videoWidth
     * @param $videoHeight
     * @param $videoAspect
     * @param $videoFrameRate
     * @param $videoLibrary
     */
    public function addVideo(
        $releaseID,
        $containerFormat,
        $overallBitRate,
        $videoDuration,
        $videoFormat,
        $videoCodec,
        $videoWidth,
        $videoHeight,
        $videoAspect,
        $videoFrameRate,
        $videoLibrary
    ): void {
        $ckid = VideoData::query()->where('releases_id', $releaseID)->first(['releases_id']);
        if ($ckid === null) {
            VideoData::query()->insert([
                    'releases_id' => $releaseID,
                    'containerformat' => $containerFormat,
                    'overallbitrate' => $overallBitRate,
                    'videoduration' => $videoDuration,
                    'videoformat' => $videoFormat,
                    'videocodec' => $videoCodec,
                    'videowidth' => $videoWidth,
                    'videoheight' => $videoHeight,
                    'videoaspect' => $videoAspect,
                    'videoframerate' => $videoFrameRate,
                    'videolibrary' => $videoLibrary,
                ]);
        }
    }

    /**
     * @param $releaseID
     * @param $audioID
     * @param $audioFormat
     * @param $audioMode
     * @param $audioBitRateMode
     * @param $audioBitRate
     * @param $audioChannels
     * @param $audioSampleRate
     * @param $audioLibrary
     * @param $audioLanguage
     * @param $audioTitle
     */
    private function addAudio($releaseID, $audioID, $audioFormat, $audioMode, $audioBitRateMode, $audioBitRate, $audioChannels, $audioSampleRate, $audioLibrary, $audioLanguage, $audioTitle): void
    {
        $ckid = AudioData::query()->where('releases_id', $releaseID)->first(['releases_id']);
        if ($ckid === null) {
            AudioData::query()->insert([
                    'releases_id' => $releaseID,
                    'audioid' => $audioID,
                    'audioformat' => \is_array($audioFormat) ? implode($audioFormat) : $audioFormat,
                    'audiomode' => \is_array($audioMode) ? implode($audioMode) : $audioMode,
                    'audiobitratemode' => \is_array($audioBitRateMode) ? implode($audioBitRateMode) : $audioBitRateMode,
                    'audiobitrate' => \is_array($audioBitRate) ? implode($audioBitRate) : $audioBitRate,
                    'audiochannels' => \is_array($audioChannels) ? implode($audioChannels) : $audioChannels,
                    'audiosamplerate' => \is_array($audioSampleRate) ? implode($audioSampleRate) : $audioSampleRate,
                    'audiolibrary' => \is_array($audioLibrary) ? implode($audioLibrary) : $audioLibrary,
                    'audiolanguage' => \is_array($audioLanguage) ? implode($audioLanguage) : $audioLanguage,
                    'audiotitle' => \is_array($audioTitle) ? implode($audioTitle) : $audioTitle,
                ]);
        }
    }

    /**
     * @param $releaseID
     * @param $subsID
     * @param $subsLanguage
     */
    private function addSubs($releaseID, $subsID, $subsLanguage): void
    {
        $ckid = ReleaseSubtitle::query()->where('releases_id', $releaseID)->first(['releases_id']);
        if ($ckid === null) {
            ReleaseSubtitle::query()->insert([
                'releases_id' => $releaseID,
                'subsid' => $subsID,
                'subslanguage' => $subsLanguage,
            ]);
        }
    }

    /**
     * @param int $releaseID
     * @param string $uniqueId
     */
    public function addUID($releaseID, $uniqueId): void
    {
        $dupecheck = ReleaseUnique::query()->where('releases_id', $releaseID)->orWhere([
                    'releases_id' => $releaseID,
                    'uniqueid' => $uniqueId,
                ])->first(['releases_id']);
        if ($dupecheck === null) {
            ReleaseUnique::query()->insert([
                        'releases_id' => $releaseID,
                        'uniqueid' => $uniqueId,
                    ]);
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
    public function addFull($id, $xml): void
    {
        $ckid = ReleaseExtraFull::query()->where('releases_id', $id)->first();
        if ($ckid === null) {
            ReleaseExtraFull::query()->insert(['releases_id' => $id, 'mediainfo' => $xml]);
        }
    }
}
