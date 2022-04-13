<?php

namespace Blacklight;

/**
 * Cleans names for collections/imports/namefixer.
 *
 *
 * Class CollectionsCleaning
 */
class CollectionsCleaning
{
    /**
     * Used for matching endings in article subjects.
     *
     * @const
     * @string
     */
    public const REGEX_END = '[\-_\s]{0,3}yEnc$/ui';

    /**
     * Used for matching file extension endings in article subjects.
     *
     * @const
     * @string
     */
    public const REGEX_FILE_EXTENSIONS = '([\-_](proof|sample|thumbs?))*(\.part\d*(\.rar)?|\.rar|\.7z|\.par2)?(\d{1,3}\.rev"|\.vol\d+\+\d+.+?"|\.[A-Za-z0-9]{2,4}"|")';

    /**
     * Used for matching size strings in article subjects.
     *
     * @example ' - 365.15 KB - '
     * @const
     * @string
     */
    public const REGEX_SUBJECT_SIZE = '[\-_\s]{0,3}\d+([.,]\d+)? [kKmMgG][bB][\-_\s]{0,3}';

    /**
     * Collection subject failed to match any regular expression.
     */
    public const REGEX_NO_MATCH = 0;

    /**
     * Collection subject matched the Generic regular expression.
     */
    public const REGEX_GENERIC_MATCH = -10;

    /**
     * Collection subject matched the Music generic regular expression.
     */
    public const REGEX_MUSIC_MATCH = -20;

    /**
     * @var string
     */
    public string $e0;

    /**
     * @var string
     */
    public string $e1;

    /**
     * @var string
     */
    public string $e2;

    /**
     * @var string
     */
    public string $groupName = '';

    /**
     * @var string
     */
    public string $subject = '';

    /**
     * @var \Blacklight\Regexes
     */
    protected Regexes $_regexes;

    /**
     * CollectionsCleaning constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        // Extensions.
        $this->e0 = self::REGEX_FILE_EXTENSIONS;
        $this->e1 = self::REGEX_FILE_EXTENSIONS.self::REGEX_END;
        $this->e2 = self::REGEX_FILE_EXTENSIONS.self::REGEX_SUBJECT_SIZE.self::REGEX_END;

        $this->_regexes = new Regexes(['Table_Name' => 'collection_regexes']);
    }

    /**
     * @param  string  $subject
     * @param  string  $groupName
     * @return array
     *
     * @throws \Exception
     */
    public function collectionsCleaner(string $subject, string $groupName = ''): array
    {
        $this->subject = $subject;
        $this->groupName = $groupName;

        // Try DB regex first.
        $potentialString = $this->_regexes->tryRegex($subject, $groupName);
        if ($potentialString) {
            return [
                'id'   => $this->_regexes->matchedRegex,
                'name' => $potentialString,
            ];
        }

        return $this->generic();
    }

    /**
     * Cleans usenet subject before inserting, used for collection hash. If no regexes matched on collectionsCleaner.
     *
     *
     * @return array
     */
    protected function generic(): array
    {
        // For non music groups.
        if (! preg_match('/\.(flac|lossless|mp3|music|sounds)/', $this->groupName)) {
            // File/part count.
            // File extensions.
            // File extensions - If it was not in quotes.
            // File Sizes - Non unique ones.
            // Random stuff.
            $cleanSubject = preg_replace([
                '/\d{1,3}([,\.\/])\d{1,3}\s([kmg])b|(\])?\s\d+KB\s(yENC)?|"?\s\d+\sbytes?|[- ]?\d+([\.,])?\d+\s([gkm])?B\s-?(\s?yenc)?|\s\(d{1,3},\d{1,3}\s{K,M,G}B\)\s|yEnc \d+k$|{\d+ yEnc bytes}|yEnc \d+ |\(\d+ ?([kmg])?b(ytes)?\) yEnc$/i',
                '/AutoRarPar\d{1,5}|\(\d+\)( |  )yEnc|\d+(Amateur|Classic)| \d{4,}[a-z]{4,} |.vol\d+\+\d+|.part\d+/i',
                '/((( \(\d\d\) -|(\d\d)? - \d\d\.|\d{4} \d\d -) | - \d\d-| \d\d\. [a-z]).+| \d\d of \d\d| \dof\d)\.mp3"?|([\)\(\[\s])\d{1,5}(\/|([\s_])of([\s_])|-)\d{1,5}([\)\]\s$:])|\(\d{1,3}\|\d{1,3}\)|[^\d]{4}-\d{1,3}-\d{1,3}\.|\s\d{1,3}\sof\s\d{1,3}\.|\s\d{1,3}\/\d{1,3}|\d{1,3}of\d{1,3}\.|^\d{1,3}\/\d{1,3}\s|\d{1,3} - of \d{1,3}/i',
                '/(-? [a-z0-9]+-?|\(?\d{4}\)?([_-])[a-z0-9]+)\.jpg"?| [a-z0-9]+\.mu3"?|((\d{1,3})?\.part(\d{1,5})?|\d{1,5} ?|sample|- Partie \d+)?\.(7z|\d{3}(?=([\s"]))|avi|diz|docx?|epub|idx|iso|jpg|m3u|m4a|mds|mkv|mobi|mp4|nfo|nzb|par(\s?2|")|pdf|rar|rev|rtf|r\d\d|sfv|srs|srr|sub|txt|vol.+(par2)|xls|zip|z{2,3})"?|(\s|(\d{2,3})?-)\d{2,3}\.mp3|\d{2,3}\.pdf|\.part\d{1,4}\./i',
                '/'.$this->e0.'/i',
            ], ' ', $this->subject);

            // Multi spaces.
            return [
                'id'   => self::REGEX_GENERIC_MATCH,
                'name' => utf8_encode(trim(preg_replace('/\s\s+/', ' ', $cleanSubject))),
            ];
        } // Music groups.

        // Try some music group regexes.
        $musicSubject = $this->musicSubject();
        if ($musicSubject !== false) {
            return [
                'id'   => self::REGEX_MUSIC_MATCH,
                'name' => $musicSubject,
            ];
            // Parts/files
        }

        // Anything between the quotes. Too much variance within the quotes, so remove it completely.
        // File extensions - If it was not in quotes.
        // File Sizes - Non unique ones.
        // Random stuff.
        $cleanSubject = preg_replace([
            '/((( \(\d\d\) -|(\d\d)? - \d\d\.|\d{4} \d\d -) | - \d\d-| \d\d\. [a-z]).+| \d\d of \d\d| \dof\d)\.mp3"?|([\(\[\s])\d{1,4}(\/|([\s_])of([\s_])|-)\d{1,4}([\)\]\s$:])|\(\d{1,3}\|\d{1,3}\)|-\d{1,3}-\d{1,3}\.|\s\d{1,3}\sof\s\d{1,3}\.|\s\d{1,3}\/\d{1,3}|\d{1,3}of\d{1,3}\.|^\d{1,3}\/\d{1,3}\s|\d{1,3} - of \d{1,3}/i',
            '/".+"/i',
            '/(-? [a-z0-9]+-?|\(?\d{4}\)?([_-])[a-z0-9]+)\.jpg"?| [a-z0-9]+\.mu3"?|((\d{1,3})?\.part(\d{1,5})?|\d{1,5} ?|sample|- Partie \d+)?\.(7z|\d{3}(?=([\s"]))|avi|diz|docx?|epub|idx|iso|jpg|m3u|m4a|mds|mkv|mobi|mp4|nfo|nzb|par(\s?2|")|pdf|rar|rev|rtf|r\d\d|sfv|srs|srr|sub|txt|vol.+(par2)|xls|zip|z{2,3})"?|(\s|(\d{2,3})?-)\d{2,3}\.mp3|\d{2,3}\.pdf|\.part\d{1,4}\./i',
            '/\d{1,3}([,\.\/])\d{1,3}\s([kmg])b|(\])?\s\d+KB\s(yENC)?|"?\s\d+\sbytes?|[- ]?\d+[.,]?\d+\s([gkm])?B\s-?(\s?yenc)?|\s\(d{1,3},\d{1,3}\s{K,M,G}B\)\s|yEnc \d+k$|{\d+ yEnc bytes}|yEnc \d+ |\(\d+ ?([kmg])?b(ytes)?\) yEnc$/i',
            '/AutoRarPar\d{1,5}|\(\d+\)( |  )yEnc|\d+(Amateur|Classic)| \d{4,}[a-z]{4,} |.vol\d+\+\d+|.part\d+/i',
        ], ' ', $this->subject);
        // Multi spaces.
        $cleanSubject = utf8_encode(trim(preg_replace('/\s\s+/i', ' ', $cleanSubject)));
        // If the subject is too similar to another because it is so short, try to extract info from the subject.
        if (\strlen($cleanSubject) <= 10 || preg_match('/^[\-a-z0-9$ ]{1,7}yEnc$/i', $cleanSubject)) {
            $x = '';
            if (preg_match('/.*("[A-Z0-9]+).*?"/i', $this->subject, $hit)) {
                $x = $hit[1];
            }
            if (preg_match_all('/[^A-Z0-9]/i', $this->subject, $match1)) {
                $start = 0;
                foreach ($match1[0] as $add) {
                    if ($start > 2) {
                        break;
                    }
                    $x .= $add;
                    $start++;
                }
            }
            $newName = preg_replace(['/".+?"/', '/[a-z0-9]|'.$this->e0.'/i'], '', $this->subject);

            return [
                'id'   => self::REGEX_MUSIC_MATCH,
                'name' => $cleanSubject.$newName.$x,
            ];
        }

        return [
            'id'   => self::REGEX_MUSIC_MATCH,
            'name' => $cleanSubject,
        ];
    }

    /**
     * @return string|false
     */
    protected function musicSubject(): bool|string
    {
        //Broderick_Smith-Unknown_Country-2009-404 "00-broderick_smith-unknown_country-2009.sfv" yEnc
        if (preg_match('/^(\w{10,}-[a-zA-Z0-9]+ ")\d\d-.+?" yEnc$/', $this->subject, $hit)) {
            return $hit[1];
        }

        return false;
    }
}
