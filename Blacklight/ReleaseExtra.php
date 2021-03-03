<?php

namespace Blacklight;

use App\Models\AudioData;
use App\Models\ReleaseExtraFull;
use App\Models\ReleaseSubtitle;
use App\Models\ReleaseUnique;
use App\Models\VideoData;
use Illuminate\Support\Facades\DB;
use Mhor\MediaInfo\Container\MediaInfoContainer;

/**
 * Class ReleaseExtra.
 */
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
     * @param                                              $releaseID
     * @param \Mhor\MediaInfo\Container\MediaInfoContainer $arrXml
     */
    public function addFromXml($releaseID, MediaInfoContainer $arrXml): void
    {
        $containerFormat = '';
        $overallBitRate = '';
        $general = $arrXml->getGeneral();
        $audios = $arrXml->getAudios();
        $videos = $arrXml->getVideos();
        $subtitles = $arrXml->getSubtitles();
        if (! empty($general)) {
            if (! empty($general->get('unique_id')) && (int) $general->get('unique_id')->getShortName() !== 1) {
                $uniqueId = $general->get('unique_id')->getShortName();
                $this->addUID($releaseID, $uniqueId);
            }

            if ($general->get('format') !== null) {
                $containerFormat = $general->get('format')->getFullName();
            }

            if ($general->get('overall_bit_rate') !== null) {
                $overallBitRate = $general->get('overall_bit_rate')->getFullName();
            }

            $videoDuration = $videoFormat = $videoCodec = $videoWidth = $videoHeight = $videoAspect = $videoFrameRate = $videoLibrary = $videoBitRate = '';

            if (! empty($videos)) {
                foreach ($videos as $video) {
                    if ($video->get('duration') !== null) {
                        $videoDuration = $video->get('duration')->getMilliseconds();
                    }

                    if ($video->get('format') !== null) {
                        $videoFormat = $video->get('format')->getFullName();
                    }

                    if ($video->get('codec_id') !== null) {
                        $videoCodec = $video->get('codec_id');
                    }

                    if ($video->get('width') !== null) {
                        $videoWidth = $video->get('width')->getAbsoluteValue();
                    }

                    if ($video->get('height') !== null) {
                        $videoHeight = $video->get('height')->getAbsoluteValue();
                    }

                    if ($video->get('display_aspect_ratio') !== null) {
                        $videoAspect = $video->get('display_aspect_ratio')->getTextValue();
                    }

                    if ($video->get('frame_rate') !== null) {
                        $videoFrameRate = $video->get('frame_rate')->getTextValue();
                    }

                    if ($video->get('encoded_library') !== null) {
                        $videoLibrary = $video->get('encoded_library');
                    }

                    if ($video->get('encoded_library_version') !== null) {
                        $videoLibrary .= $video->get('encoded_library_version');
                    }

                    if ($video->get('writing_library') !== null) {
                        $videoLibrary = $video->get('writing_library')->getFullName();
                    }

                    if ($video->get('nominal_bit_rate') !== null) {
                        $videoBitRate = $video->get('nominal_bit_rate')->getTextValue();
                    }

                    if (! empty($videoBitRate)) {
                        $overallBitRate = $videoBitRate;
                    }

                    $this->addVideo($releaseID, $containerFormat, $overallBitRate, $videoDuration, $videoFormat, $videoCodec, $videoWidth, $videoHeight, $videoAspect, $videoFrameRate, $videoLibrary);
                }
            }

            $audioID = 1;
            $audioFormat = $audioMode = $audioBitRateMode = $audioBitRate = $audioChannels = $audioSampleRate = $audioLibrary = $audioLanguage = $audioTitle = '';

            if (! empty($audios)) {
                foreach ($audios as $audio) {
                    if ($audio->get('id') !== null) {
                        $audioID = $audio->get('id')->getFullName();
                    }

                    if ($audio->get('format') !== null) {
                        $audioFormat = $audio->get('format')->getFullName();
                    }

                    if ($audio->get('format_settings_sbr') !== null) {
                        $audioMode = $audio->get('format_settings_sbr')->getFullName();
                    }

                    if ($audio->get('bit_rate_mode') !== null) {
                        $audioBitRateMode = $audio->get('bit_rate_mode')->getFullName();
                    }

                    if ($audio->get('bit_rate') !== null) {
                        $audioBitRate = $audio->get('bit_rate')->getTextValue();
                    }

                    if ($audio->get('channel_s') !== null) {
                        $audioChannels = $audio->get('channel_s')->getAbsoluteValue();
                    }

                    if ($audio->get('sampling_rate') !== null) {
                        $audioSampleRate = $audio->get('sampling_rate')->getTextValue();
                    }

                    if ($audio->get('encoded_library') !== null) {
                        $audioLibrary = $audio->get('encoded_library');
                    }

                    if ($audio->get('language') !== null) {
                        $audioLanguage = $audio->get('language');
                    }

                    if ($audio->get('title') !== null) {
                        $audioTitle = $audio->get('title');
                    }

                    $this->addAudio($releaseID, $audioID, $audioFormat, $audioMode, $audioBitRateMode, $audioBitRate, $audioChannels, $audioSampleRate, $audioLibrary, $audioLanguage, $audioTitle);
                }
            }

            if (! empty($subtitles)) {
                foreach ($subtitles as $subtitle) {
                    $subsID = 1;
                    $subsLanguage = 'Unknown';

                    if ($subtitle->get('id') !== null) {
                        $subsID = $subtitle->get('id')->getFullName();
                    }

                    if ($subtitle->get('language') !== null) {
                        $subsLanguage = $subtitle->get('language');
                    }

                    $this->addSubs($releaseID, $subsID, $subsLanguage);
                }
            }
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
            VideoData::insertOrIgnore([
                'releases_id' => $releaseID,
                'containerformat' => $containerFormat,
                'overallbitrate' => $overallBitRate,
                'videoduration' => is_numeric($videoDuration) ? realDuration($videoDuration) : $videoDuration,
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
            AudioData::insertOrIgnore([
                'releases_id' => $releaseID,
                'audioid' => $audioID,
                'audioformat' => \is_array($audioFormat) ? implode($audioFormat) : $audioFormat,
                'audiomode' => \is_array($audioMode) ? implode($audioMode) : $audioMode,
                'audiobitratemode' => \is_array($audioBitRateMode) ? implode($audioBitRateMode) : $audioBitRateMode,
                'audiobitrate' => \is_array($audioBitRate) ? implode($audioBitRate) : $audioBitRate,
                'audiochannels' => \is_array($audioChannels) ? implode($audioChannels) : $audioChannels,
                'audiosamplerate' => \is_array($audioSampleRate) ? implode($audioSampleRate) : $audioSampleRate,
                'audiolibrary' => \is_array($audioLibrary) ? implode($audioLibrary) : $audioLibrary,
                'audiolanguage' => ! empty($audioLanguage) ? $audioLanguage[1] : '',
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
        $subs = '';
        if (! empty($subsLanguage)) {
            if (! empty($subsLanguage[1])) {
                $subs = $subsLanguage[1];
            } elseif (! empty($subsLanguage[0])) {
                $subs = $subsLanguage[0];
            }
        }
        if ($ckid === null) {
            ReleaseSubtitle::insertOrIgnore([
                'releases_id' => $releaseID,
                'subsid' => $subsID,
                'subslanguage' => $subs,
            ]);
        }
    }

    /**
     * @param int $releaseID
     * @param string $uniqueId
     */
    public function addUID($releaseID, $uniqueId): void
    {
        $dupecheck = ReleaseUnique::query()->orWhere([
            'releases_id' => $releaseID,
            'uniqueid' => $uniqueId,
        ])->first(['releases_id']);
        if ($dupecheck === null) {
            ReleaseUnique::insertOrIgnore([
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
     * @param                                              $id
     * @param \Mhor\MediaInfo\Container\MediaInfoContainer $xmlArray
     */
    public function addFull($id, MediaInfoContainer $xmlArray): void
    {
        $ckid = ReleaseExtraFull::query()->where('releases_id', $id)->first();
        if ($ckid === null) {
            $xml = $xmlArray->__toXML()->asXML();
            ReleaseExtraFull::insertOrIgnore(['releases_id' => $id, 'mediainfo' => $xml]);
        }
    }
}
