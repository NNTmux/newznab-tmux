<?php

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

/**
 * Categorizer for Music content (MP3, Lossless, Video, Audiobook, Podcast).
 */
class MusicCategorizer extends AbstractCategorizer
{
    protected int $priority = 40;

    // Language patterns for foreign music
    protected const FOREIGN_LANGUAGES = 'arabic|brazilian|bulgarian|cantonese|chinese|croatian|czech|danish|deutsch|dutch|estonian|finnish|flemish|french|german|greek|hebrew|hungarian|icelandic|indian|iranian|italian|japanese|korean|latin|latvian|lithuanian|macedonian|mandarin|nordic|norwegian|persian|polish|portuguese|romanian|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish|ukrainian|vietnamese';

    protected const LANGUAGE_CODES = 'ar|bg|bl|cs|cz|da|de|dk|el|es|et|fi|fr|ger|gr|heb|hr|hu|hun|is|it|ita|jp|jap|ko|kor|lt|lv|mk|nl|no|pl|pt|ro|rs|ru|se|sk|sl|sr|sv|th|tr|ua|vi|zh';

    public function getName(): string
    {
        return 'Music';
    }

    public function shouldSkip(ReleaseContext $context): bool
    {
        if ($context->hasAdultMarkers()) {
            return true;
        }
        // Skip TV shows (season patterns)
        if (preg_match('/[._ -]S\d{1,3}[._ -]?(E\d|Complete|Full|1080|720|480|2160|WEB|HDTV|BluRay)/i', $context->releaseName)) {
            return true;
        }

        return false;
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;

        // Try each music category
        if ($result = $this->checkAudiobook($name)) {
            return $result;
        }

        if ($result = $this->checkPodcast($name)) {
            return $result;
        }

        if ($result = $this->checkMusicVideo($name, $context->categorizeForeign)) {
            return $result;
        }

        if ($result = $this->checkLossless($name, $context->categorizeForeign)) {
            return $result;
        }

        if ($result = $this->checkMP3($name, $context->categorizeForeign)) {
            return $result;
        }

        if ($result = $this->checkOther($name, $context->categorizeForeign)) {
            return $result;
        }

        return $this->noMatch();
    }

    protected function checkForeign(string $name): bool
    {
        return (bool) preg_match('/(?:^|[\s\.\-_])(?:'.self::FOREIGN_LANGUAGES.'|'.self::LANGUAGE_CODES.')(?:$|[\s\.\-_])/i', $name);
    }

    protected function checkAudiobook(string $name): ?CategorizationResult
    {
        // Explicit audiobook indicators
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Audiobook|Audio\s*Book|Talking\s*Book|ABEE|Audible)/i', $name)) {
            if (preg_match('/\b(?:Unabridged|Abridged|Narrated|Narrator|MP3|M4A|M4B|AAC|Read\s+By|Tantor|Blackstone|Brilliance|GraphicAudio|Penguin|Audible)\b/i', $name) ||
                preg_match('/\d+\s*CDs|\d+\s*Hours|Spoken\s+Word/i', $name) ||
                preg_match('/\.(mp3|m4a|m4b|aac|flac|ogg|wma)$/i', $name)) {
                return $this->matched(Category::MUSIC_AUDIOBOOK, 0.95, 'audiobook');
            }
        }

        // Audiobook patterns
        if (preg_match('/(?:[\(_\[])(?:Audiobook|AB|Unabridged)(?:[\)_\]])/i', $name) ||
            preg_match('/Read\s+By\s+[A-Z][a-z]+\s+[A-Z][a-z]+/i', $name)) {
            return $this->matched(Category::MUSIC_AUDIOBOOK, 0.9, 'audiobook_pattern');
        }

        // Legacy pattern
        if (preg_match('/(Audiobook|Audio.?Book)/i', $name)) {
            return $this->matched(Category::MUSIC_AUDIOBOOK, 0.85, 'audiobook_legacy');
        }

        return null;
    }

    protected function checkPodcast(string $name): ?CategorizationResult
    {
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Podcast|Pod[._ -]?cast|Pod[._ -]Show)/i', $name)) {
            return $this->matched(Category::MUSIC_PODCAST, 0.9, 'podcast');
        }

        // Known podcast networks with episode indicators
        if (preg_match('/\b(?:NPR|BBC[._ -]Sounds|Gimlet|Wondery|Stitcher|iHeart[._ -]?Radio|Joe[._ -]Rogan|RadioLab|Serial)\b/i', $name) &&
            preg_match('/\b(?:Podcast|Episode|EP?[._ -]?\d+|Show)\b/i', $name)) {
            return $this->matched(Category::MUSIC_PODCAST, 0.85, 'podcast_network');
        }

        // Simple podcast match
        if (preg_match('/podcast/i', $name)) {
            return $this->matched(Category::MUSIC_PODCAST, 0.8, 'podcast_simple');
        }

        return null;
    }

    protected function checkMusicVideo(string $name, bool $categorizeForeign): ?CategorizationResult
    {
        // Music video indicators
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Music\s*Video|Concert|Live\s*Show|Tour|Festival|MV|MTV)|\b(?:MVID|MVid)\b/i', $name)) {
            if (preg_match('/\b(?:720p|1080[pi]|2160p|BDRip|BluRay|DVDRip|HDTV|WebRip|WEB-DL|x264|x265)\b/i', $name) ||
                preg_match('/\b(?:Live|Unplugged|Acoustic|World\s*Tour|in\s*Concert|Official\s*Video|Bootleg|Remastered)\b/i', $name) ||
                preg_match('/\.(mkv|mp4|avi|ts|m2ts|mpg|mpeg|mov|wmv|vob|m4v)$/i', $name)) {

                if ($categorizeForeign && $this->checkForeign($name)) {
                    return $this->matched(Category::MUSIC_FOREIGN, 0.85, 'music_video_foreign');
                }

                return $this->matched(Category::MUSIC_VIDEO, 0.9, 'music_video');
            }
        }

        // Artist-title pattern with video format
        if (preg_match('/^[A-Z0-9][A-Za-z0-9\.\s\&\'\(\)\-]+\s+\-\s+[A-Z0-9][A-Za-z0-9\.\s\&\'\(\)\-]+.*?\b(720p|1080[pi]|2160p|Bluray|x264|x265)\b/i', $name)) {
            if ($categorizeForeign && $this->checkForeign($name)) {
                return $this->matched(Category::MUSIC_FOREIGN, 0.8, 'music_video_foreign');
            }

            return $this->matched(Category::MUSIC_VIDEO, 0.8, 'music_video_artist');
        }

        return null;
    }

    protected function checkLossless(string $name, bool $categorizeForeign): ?CategorizationResult
    {
        // Lossless format indicators
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:FLAC|APE|WAV|ALAC|DSD|DSF|AIFF|PCM|Lossless)|\b(?:FLAC|APE|WAV|ALAC|DSD|DSF|AIFF|PCM)\b/i', $name)) {
            if (preg_match('/\b(?:24[Bb]it|96kHz|192kHz|Hi[- ]?Res|HD[- ]?Tracks|Vinyl[- ]?Rip|CD[- ]?Rip|WEB[- ]?Rip|HDtracks|Qobuz|Tidal|MQA|SACD)\b/i', $name) ||
                preg_match('/\.(flac|ape|wav|aiff|dsf|dff|m4a|tak)$/i', $name)) {

                if ($categorizeForeign && $this->checkForeign($name)) {
                    return $this->matched(Category::MUSIC_FOREIGN, 0.9, 'lossless_foreign');
                }

                return $this->matched(Category::MUSIC_LOSSLESS, 0.9, 'lossless');
            }
        }

        // FLAC patterns
        if (preg_match('/\[(19|20)\d\d\][._ -]\[FLAC\]|([\(\[])flac([\)\]])|FLAC\-(19|20)\d\d\-[a-z0-9]{1,12}|\.flac"|(19|20)\d\d\sFLAC|[._ -]FLAC.+(19|20)\d\d[._ -]| FLAC$/i', $name) ||
            preg_match('/\d{3,4}kbps[._ -]FLAC|\[FLAC\]|\(FLAC\)|FLACME|FLAC[._ -]\d{3,4}(kbps)?|WEB[._ -]FLAC/i', $name)) {

            if ($categorizeForeign && $this->checkForeign($name)) {
                return $this->matched(Category::MUSIC_FOREIGN, 0.85, 'flac_foreign');
            }

            return $this->matched(Category::MUSIC_LOSSLESS, 0.85, 'flac');
        }

        // Other lossless formats
        if (preg_match('/\b(?:APE|Monkey\'s[._ -]Audio|WavPack|WV|TAK|TTA|ALAC|Apple[._ -]Lossless)\b|\.(ape|wv|tak|tta)$/i', $name)) {
            if ($categorizeForeign && $this->checkForeign($name)) {
                return $this->matched(Category::MUSIC_FOREIGN, 0.85, 'lossless_format_foreign');
            }

            return $this->matched(Category::MUSIC_LOSSLESS, 0.85, 'lossless_format');
        }

        return null;
    }

    protected function checkMP3(string $name, bool $categorizeForeign): ?CategorizationResult
    {
        // MP3 indicators
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:MP3|320kbps|256kbps|192kbps|128kbps|CBR|VBR)|\b(?:MP3)\b|[\._-](?:MP3)[\._-]|\.mp3$/i', $name)) {
            if (preg_match('/\b(?:320|256|192|128)[._-]?kbps|\b(?:320|256|192|128)[._-]?K|\((?:320|256|192|128)\)|\[(?:320|256|192|128)\]|V0|V2|VBR/i', $name) ||
                preg_match('/\b(?:CD[._-]?Rip|Web[._-]?Rip|WEB|iTunes|AmazonRip|Spotify[._-]?Rip|MP3\s*\-\s*\d{3}kbps)\b/i', $name) ||
                preg_match('/\.(m3u|mp3)"|rip(?:192|256|320)|[._-]FM[._-].+MP3/i', $name)) {

                if ($categorizeForeign && $this->checkForeign($name)) {
                    return $this->matched(Category::MUSIC_FOREIGN, 0.85, 'mp3_foreign');
                }

                return $this->matched(Category::MUSIC_MP3, 0.85, 'mp3');
            }
        }

        // MP3 scene patterns
        if (preg_match('/^[a-zA-Z0-9]{1,12}[._-](19|20)\d\d[._-][a-zA-Z0-9]{1,12}$|[a-z0-9]{1,12}\-(19|20)\d\d\-[a-z0-9]{1,12}/i', $name)) {
            if ($categorizeForeign && $this->checkForeign($name)) {
                return $this->matched(Category::MUSIC_FOREIGN, 0.75, 'mp3_scene_foreign');
            }

            return $this->matched(Category::MUSIC_MP3, 0.75, 'mp3_scene');
        }

        // Bitrate patterns
        if (preg_match('/[\.\-\(\[_ ]\d{2,3}k[\.\-\)\]_ ]|\((192|256|320)\)|(320|cd|eac|vbr)[._-]+mp3|(cd|eac|mp3|vbr)[._-]+320/i', $name)) {
            if ($categorizeForeign && $this->checkForeign($name)) {
                return $this->matched(Category::MUSIC_FOREIGN, 0.8, 'mp3_bitrate_foreign');
            }

            return $this->matched(Category::MUSIC_MP3, 0.8, 'mp3_bitrate');
        }

        return null;
    }

    protected function checkOther(string $name, bool $categorizeForeign): ?CategorizationResult
    {
        // Compilation and VA indicators
        if (preg_match('/(?:^|[^a-zA-Z0-9])(?:Compilation|Various[._ -]Artists|OST|Soundtrack|B-Sides|Greatest[._ -]Hits|Anthology)|\b(?:VA|V\.A|Bonus[._ -]Track|Discography|Box[._ -]Set)\b/i', $name)) {
            if ($categorizeForeign && $this->checkForeign($name)) {
                return $this->matched(Category::MUSIC_FOREIGN, 0.8, 'music_other_foreign');
            }

            return $this->matched(Category::MUSIC_OTHER, 0.8, 'music_other');
        }

        // Album/CD patterns
        if (preg_match('/(?:\d)[._ -](?:CD|Albums|LP)[._ -](?:Set|Compilation)|CD[._ -](Collection|Box|SET)|(\d)-?CD[._ -]/i', $name) ||
            preg_match('/Vinyl[._ -](?:24[._ -]96|2496|Collection|RIP)|WEB[._ -](?:Single|Album)|EP[._ -]\d{4}|\bEP\b.+(?:19|20)\d\d|Live[._ -](?:at|At|@)/i', $name)) {
            if ($categorizeForeign && $this->checkForeign($name)) {
                return $this->matched(Category::MUSIC_FOREIGN, 0.75, 'music_album_foreign');
            }

            return $this->matched(Category::MUSIC_OTHER, 0.75, 'music_album');
        }

        // DJ mixes and labels
        if (preg_match('/\b(?:Ministry[._ -]of[._ -]Sound|Hed[._ -]Kandi|Cream|Fabric[._ -]Live|Ultra[._ -]Music)\b/i', $name) ||
            preg_match('/\b(?:DJ[._ -]Mix|Mixed[._ -]By|Tiesto[._ -]Club|Radio[._ -]Show|Club[._ -]Hits)\b/i', $name)) {
            if ($categorizeForeign && $this->checkForeign($name)) {
                return $this->matched(Category::MUSIC_FOREIGN, 0.75, 'music_dj_foreign');
            }

            return $this->matched(Category::MUSIC_OTHER, 0.75, 'music_dj');
        }

        return null;
    }
}
