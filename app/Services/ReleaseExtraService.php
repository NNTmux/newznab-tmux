<?php

namespace App\Services;

use App\Models\AudioData;
use App\Models\ReleaseSubtitle;
use App\Models\VideoData;
use Illuminate\Support\Facades\DB;
use Mhor\MediaInfo\Container\MediaInfoContainer;

/**
 * Service for managing release extra data (video, audio, and subtitles).
 */
class ReleaseExtraService
{
    /**
     * Get video data for a release.
     *
     * @param  int  $id  Release ID
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function get(int $id)
    {
        // hopefully nothing will use this soon and it can be deleted
        return VideoData::query()->where('releases_id', $id)->first();
    }

    /**
     * Get video data as array.
     *
     * @param  int  $id  Release ID
     * @return array|false
     */
    public function getVideo(int $id): false|array
    {
        $result = VideoData::query()->where('releases_id', $id)->first();

        if ($result !== null) {
            return $result->toArray();
        }

        return false;
    }

    /**
     * Get audio data for a release.
     *
     * @param  int  $id  Release ID
     * @return array|false
     */
    public function getAudio(int $id): false|array
    {
        $result = AudioData::query()->where('releases_id', $id)->orderBy('audioid')->get();

        if ($result !== null) {
            return $result->toArray();
        }

        return false;
    }

    /**
     * Get subtitles for a release.
     *
     * @param  int  $id  Release ID
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getSubs(int $id)
    {
        return ReleaseSubtitle::query()
            ->where('releases_id', $id)
            ->select([DB::raw("GROUP_CONCAT(subslanguage SEPARATOR ', ') AS subs")])
            ->orderBy('subsid')
            ->first();
    }

    /**
     * Get video data by release GUID.
     *
     * @param  string  $guid  Release GUID
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null
     */
    public function getByGuid(string $guid)
    {
        return VideoData::query()
            ->where('r.guid', $guid)
            ->leftJoin('releases as r', 'r.id', '=', 'video_data.releases_id')
            ->first();
    }

    /**
     * Delete all extra data for a release.
     *
     * @param  int  $id  Release ID
     */
    public function delete(int $id): void
    {
        AudioData::query()->where('releases_id', $id)->delete();
        ReleaseSubtitle::query()->where('releases_id', $id)->delete();
        VideoData::query()->where('releases_id', $id)->delete();
    }

    /**
     * Add video, audio, and subtitle data from MediaInfo XML.
     *
     * @param  int  $releaseID  Release ID
     * @param  MediaInfoContainer  $arrXml  Parsed MediaInfo container
     */
    public function addFromXml(int $releaseID, MediaInfoContainer $arrXml): void
    {
        $containerFormat = '';
        $overallBitRate = '';
        $general = $arrXml->getGeneral();
        $audios = $arrXml->getAudios();
        $videos = $arrXml->getVideos();
        $subtitles = $arrXml->getSubtitles();

        if ($general !== null) {
            if ($general->get('format') !== null) {
                $containerFormat = $general->get('format')->getFullName();
            }

            $videoDuration = $videoFormat = $videoCodec = $videoWidth = $videoHeight = $videoAspect = $videoFrameRate = $videoLibrary = $videoBitRate = '';

            if (! empty($videos)) {
                foreach ($videos as $video) {
                    if ($video->get('duration') !== null) {
                        $videoDuration = $video->get('duration')->getMilliseconds();
                    }

                    if ($video->get('bit_rate') !== null) {
                        $overallBitRate = $video->get('bit_rate');
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
     * Add video data for a release.
     */
    public function addVideo(
        int $releaseID,
        string $containerFormat,
        mixed $overallBitRate,
        mixed $videoDuration,
        string $videoFormat,
        mixed $videoCodec,
        mixed $videoWidth,
        mixed $videoHeight,
        string $videoAspect,
        string $videoFrameRate,
        mixed $videoLibrary
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
     * Add audio data for a release.
     */
    private function addAudio(
        int $releaseID,
        mixed $audioID,
        mixed $audioFormat,
        mixed $audioMode,
        mixed $audioBitRateMode,
        mixed $audioBitRate,
        mixed $audioChannels,
        mixed $audioSampleRate,
        mixed $audioLibrary,
        mixed $audioLanguage,
        mixed $audioTitle
    ): void {
        $ckid = AudioData::query()->where('releases_id', $releaseID)->where('audioid', '=', $audioID)->first(['releases_id']);
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
     * Add subtitle data for a release.
     */
    private function addSubs(int $releaseID, mixed $subsID, mixed $subsLanguage): void
    {
        $ckid = ReleaseSubtitle::query()->where('releases_id', $releaseID)->where('subsid', '=', $subsID)->first(['releases_id']);
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
}

