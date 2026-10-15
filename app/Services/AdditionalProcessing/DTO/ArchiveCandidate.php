<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

final readonly class ArchiveCandidate
{
    /**
     * @param  list<string>  $messageIds
     * @param  list<string>  $tailMessageIds
     */
    public function __construct(
        public string $title,
        public array $messageIds,
        public bool $likelyFirstVolume,
        public int $sourceIndex,
        public array $tailMessageIds = [],
        public bool $hasFirstSegment = true,
        public string $setKey = '',
    ) {}

    /**
     * Key shared by every volume or piece of the file this candidate belongs to.
     */
    public function volumeSet(): string
    {
        return self::volumeSetKey($this->title);
    }

    /**
     * Key of the volume set a subject belongs to: the archive family and the name without
     * its volume suffix, e.g. "rar:movie" for movie.part01.rar, movie.rar and movie.r00, or
     * "split:movie.mkv" for movie.mkv.001. Only genuine sibling volumes share a key; a
     * movie.zip or movie.7z next to movie.part01.rar is a different set.
     */
    public static function volumeSetKey(string $subject): string
    {
        $name = self::fileName($subject);
        $families = [
            'rar' => '/^(.+?)\.(?:part\d+\.rar|rar|r\d{2,3})$/',
            '7z' => '/^(.+?)\.7z(?:\.\d{3})?$/',
            'zip' => '/^(.+?)\.(?:zipx?|zipr\d{2,3}|z\d{2,3})$/',
            'split' => '/^([a-f0-9]{32})\.[1-9]\d{1,2}$|^(.+?)\.\d{3}$/',
        ];
        foreach ($families as $family => $pattern) {
            if (preg_match($pattern, $name, $match) === 1) {
                return $family.':'.($match[1] !== '' ? $match[1] : $match[2]);
            }
        }

        return 'file:'.$name;
    }

    /**
     * File name in an NZB subject, quoted ("name.part01.rar" yEnc (1/50)) or not
     * (name.part01.rar yEnc (1/50), [01/10] - name.mkv.001 yEnc), lower-cased.
     *
     * Unquoted, only "yEnc" and what follows, part counters and "-" separators around the
     * name, and "[01/10] - " post counters inside it are dropped. Everything else stays,
     * spaces and any poster prefix included, as the name can't be told apart from it:
     * "First Movie.rar" and "Second Movie.rar" must never share a name.
     */
    public static function fileName(string $subject): string
    {
        if (preg_match('/"([^"\r\n]+)"/', $subject, $quoted) === 1) {
            return strtolower(trim($quoted[1]));
        }

        $counter = '[\[(]\s*\d+\s*(?:\/|of)\s*\d+\s*[\])]';
        $name = (string) preg_replace('/(?:^|\s)yEnc\b.*$/is', '', $subject);
        $name = (string) preg_replace('/(?:\s*(?:-|'.$counter.'))+\s*$/i', '', $name);
        $name = (string) preg_replace('/^(?:\s*(?:-(?=\s)|'.$counter.'))+\s*/i', '', $name);
        $name = (string) preg_replace('/\s+'.$counter.'\s+-\s+/i', ' ', $name);

        return strtolower(trim($name));
    }
}
