<?php

use App\Models\Release;
use Blacklight\NZB;
use Blacklight\utility\Utility;
use Blacklight\XXX;
use Colors\Color;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use sspat\ESQuerySanitizer\Sanitizer;
use Symfony\Component\Process\Process;
use Zip as ZipStream;

if (! function_exists('getRawHtml')) {
    /**
     * @param  bool  $cookie
     * @return bool|mixed|string
     */
    function getRawHtml($url, $cookie = false)
    {
        $cookieJar = new CookieJar;
        $client = new Client(['headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246']]);
        if ($cookie !== false) {
            $cookie = $cookieJar->setCookie(SetCookie::fromString($cookie));
            $client = new Client(['cookies' => $cookie, 'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246']]);
        }
        try {
            $response = $client->get($url)->getBody()->getContents();
            $jsonResponse = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response = $jsonResponse;
            }
        } catch (RequestException $e) {
            if (config('app.debug') === true) {
                Log::error($e->getMessage());
            }
            $response = false;
        } catch (RuntimeException $e) {
            if (config('app.debug') === true) {
                Log::error($e->getMessage());
            }
            $response = false;
        }

        return $response;
    }
}

if (! function_exists('makeFieldLinks')) {
    /**
     * @return string
     *
     * @throws Exception
     */
    function makeFieldLinks($data, $field, $type)
    {
        $tmpArr = explode(', ', $data[$field]);
        $newArr = [];
        $i = 0;
        foreach ($tmpArr as $ta) {
            if (trim($ta) === '') {
                continue;
            }
            if ($type === 'xxx' && $field === 'genre') {
                $ta = (new XXX)->getGenres(true, $ta);
                $ta = $ta['title'] ?? '';
            }
            if ($i > 7) {
                break;
            }
            $newArr[] = '<a href="'.url('/'.ucfirst($type).'?'.$field.'='.urlencode($ta)).'" title="'.$ta.'">'.$ta.'</a>';
            $i++;
        }

        return implode(', ', $newArr);
    }
}

if (! function_exists('getUserBrowseOrder')) {
    /**
     * @param  string  $orderBy
     */
    function getUserBrowseOrder($orderBy): array
    {
        $order = ($orderBy === '' ? 'username_desc' : $orderBy);
        $orderArr = explode('_', $order);
        $orderField = match ($orderArr[0]) {
            'email' => 'email',
            'host' => 'host',
            'createdat' => 'created_at',
            'lastlogin' => 'lastlogin',
            'apiaccess' => 'apiaccess',
            'apirequests' => 'apirequests',
            'grabs' => 'grabs',
            'roles_id' => 'users_role_id',
            'rolechangedate' => 'rolechangedate',
            default => 'username',
        };
        $orderSort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

        return [$orderField, $orderSort];
    }
}

if (! function_exists('getUserBrowseOrdering')) {
    function getUserBrowseOrdering(): array
    {
        return [
            'username_asc',
            'username_desc',
            'email_asc',
            'email_desc',
            'host_asc',
            'host_desc',
            'createdat_asc',
            'createdat_desc',
            'lastlogin_asc',
            'lastlogin_desc',
            'apiaccess_asc',
            'apiaccess_desc',
            'apirequests_asc',
            'apirequests_desc',
            'grabs_asc',
            'grabs_desc',
            'role_asc',
            'role_desc',
            'rolechangedate_asc',
            'rolechangedate_desc',
            'verification_asc',
            'verification_desc',
        ];
    }
}

if (! function_exists('getSimilarName')) {
    /**
     * @param  string  $name
     */
    function getSimilarName($name): string
    {
        return implode(' ', \array_slice(str_word_count(str_replace(['.', '_', '-'], ' ', $name), 2), 0, 2));
    }
}

if (! function_exists('color')) {
    function color(string $string = ''): Color
    {
        return new Color($string);
    }
}

if (! function_exists('human_filesize')) {
    /**
     * @param  int  $decimals
     */
    function human_filesize($bytes, $decimals = 0): string
    {
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = floor((\strlen($bytes) - 1) / 3);

        return round(sprintf("%.{$decimals}f", $bytes / (1024 ** $factor)), $decimals).@$size[$factor];
    }
}

if (! function_exists('bcdechex')) {
    /**
     * @return string
     */
    function bcdechex($dec)
    {
        $hex = '';
        do {
            $last = bcmod($dec, 16);
            $hex = dechex($last).$hex;
            $dec = bcdiv(bcsub($dec, $last), 16);
        } while ($dec > 0);

        return $hex;
    }
}

if (! function_exists('runCmd')) {
    /**
     * Run CLI command.
     *
     *
     * @param  string  $command
     * @param  bool  $debug
     * @return string
     */
    function runCmd($command, $debug = false)
    {
        if ($debug) {
            echo '-Running Command: '.PHP_EOL.'   '.$command.PHP_EOL;
        }

        $process = Process::fromShellCommandline('exec '.$command);
        $process->setTimeout(1800);
        $process->run();
        $output = $process->getOutput();

        if ($debug) {
            echo '-Command Output: '.PHP_EOL.'   '.$output.PHP_EOL;
        }

        return $output;
    }
}

if (! function_exists('escapeString')) {
    /**
     * @return string
     */
    function escapeString($string)
    {
        return DB::connection()->getPdo()->quote($string);
    }
}

if (! function_exists('realDuration')) {
    /**
     * @return string
     */
    function realDuration($milliseconds)
    {
        $time = round($milliseconds / 1000);

        return sprintf('%02dh:%02dm:%02ds', floor($time / 3600), floor($time / 60 % 60), $time % 60);
    }
}

if (! function_exists('is_it_json')) {
    /**
     * @param  array|string  $isIt
     * @return bool
     */
    function is_it_json($isIt)
    {
        if (is_array($isIt)) {
            return false;
        }
        json_decode($isIt, true);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

/**
 * @throws Exception
 */
function getStreamingZip(array $guids = [])
{
    $nzb = new NZB;
    $zipped = ZipStream::create(now()->format('Ymdhis').'.zip');
    foreach ($guids as $guid) {
        $nzbPath = $nzb->NZBPath($guid);

        if ($nzbPath) {
            $nzbContents = Utility::unzipGzipFile($nzbPath);

            if ($nzbContents) {
                $filename = $guid;
                $r = Release::query()->where('guid', $guid)->first();
                if ($r !== null) {
                    $filename = $r->searchname;
                }
                $zipped->addRaw($nzbContents, $filename.'.nzb');
            }
        }
    }

    return $zipped;
}

if (! function_exists('release_flag')) {
    // Function inspired by c0r3@newznabforums adds country flags on the browse page.
    /**
     * @param  string  $text  Text to match against.
     * @param  string  $page  Type of page. browse or search.
     * @return bool|string
     */
    function release_flag($text, $page)
    {
        $code = $language = '';

        switch (true) {
            case stripos($text, 'Arabic') !== false:
                $code = 'PK';
                $language = 'Arabic';
                break;
            case stripos($text, 'Cantonese') !== false:
                $code = 'TW';
                $language = 'Cantonese';
                break;
            case preg_match('/Chinese|Mandarin|\bc[hn]\b/i', $text):
                $code = 'CN';
                $language = 'Chinese';
                break;
            case preg_match('/\bCzech\b/i', $text):
                $code = 'CZ';
                $language = 'Czech';
                break;
            case stripos($text, 'Danish') !== false:
                $code = 'DK';
                $language = 'Danish';
                break;
            case stripos($text, 'Finnish') !== false:
                $code = 'FI';
                $language = 'Finnish';
                break;
            case preg_match('/Flemish|\b(Dutch|nl)\b|NlSub/i', $text):
                $code = 'NL';
                $language = 'Dutch';
                break;
            case preg_match('/French|Vostfr|Multi/i', $text):
                $code = 'FR';
                $language = 'French';
                break;
            case preg_match('/German(bed)?|\bger\b/i', $text):
                $code = 'DE';
                $language = 'German';
                break;
            case preg_match('/\bGreek\b/i', $text):
                $code = 'GR';
                $language = 'Greek';
                break;
            case preg_match('/Hebrew|Yiddish/i', $text):
                $code = 'IL';
                $language = 'Hebrew';
                break;
            case preg_match('/\bHindi\b/i', $text):
                $code = 'IN';
                $language = 'Hindi';
                break;
            case preg_match('/Hungarian|\bhun\b/i', $text):
                $code = 'HU';
                $language = 'Hungarian';
                break;
            case preg_match('/Italian|\bita\b/i', $text):
                $code = 'IT';
                $language = 'Italian';
                break;
            case preg_match('/Japanese|\bjp\b/i', $text):
                $code = 'JP';
                $language = 'Japanese';
                break;
            case preg_match('/Korean|\bkr\b/i', $text):
                $code = 'KR';
                $language = 'Korean';
                break;
            case stripos($text, 'Norwegian') !== false:
                $code = 'NO';
                $language = 'Norwegian';
                break;
            case stripos($text, 'Polish') !== false:
                $code = 'PL';
                $language = 'Polish';
                break;
            case stripos($text, 'Portuguese') !== false:
                $code = 'PT';
                $language = 'Portugese';
                break;
            case stripos($text, 'Romanian') !== false:
                $code = 'RO';
                $language = 'Romanian';
                break;
            case stripos($text, 'Spanish') !== false:
                $code = 'ES';
                $language = 'Spanish';
                break;
            case preg_match('/Swe(dish|sub)/i', $text):
                $code = 'SE';
                $language = 'Swedish';
                break;
            case preg_match('/Tagalog|Filipino/i', $text):
                $code = 'PH';
                $language = 'Tagalog|Filipino';
                break;
            case preg_match('/\bThai\b/i', $text):
                $code = 'TH';
                $language = 'Thai';
                break;
            case stripos($text, 'Turkish') !== false:
                $code = 'TR';
                $language = 'Turkish';
                break;
            case stripos($text, 'Russian') !== false:
                $code = 'RU';
                $language = 'Russian';
                break;
            case stripos($text, 'Vietnamese') !== false:
                $code = 'VN';
                $language = 'Vietnamese';
                break;
        }

        if ($code !== '' && $page === 'browse') {
            return '<img title="'.$language.'" alt="'.$language.'" src="'.asset('/assets/images/flags/'.$code.'.png').'"/>';
        }

        if ($page === 'search') {
            if ($code === '') {
                return false;
            }

            return $code;
        }

        return '';
    }
    if (! function_exists('sanitize')) {
        function sanitize(array|string $phrases, array $doNotSanitize = []): string
        {
            if (! is_array($phrases)) {
                $wordArray = explode(' ', str_replace('.', ' ', $phrases));
            } else {
                $wordArray = $phrases;
            }

            $keywords = [];
            $tempWords = [];
            foreach ($wordArray as $words) {
                $words = preg_split('/\s+/', $words);
                foreach ($words as $st) {
                    if (Str::startsWith($st, ['!', '+', '-', '?', '*']) && Str::length($st) > 1 && ! preg_match('/([!+?\-*]){2,}/', $st)) {
                        $str = $st;
                    } elseif (Str::endsWith($st, ['+', '-', '?', '*']) && Str::length($st) > 1 && ! preg_match('/([!+?\-*]){2,}/', $st)) {
                        $str = $st;
                    } else {
                        $str = Sanitizer::escape($st, $doNotSanitize);
                    }
                    $tempWords[] = $str;
                }

                $keywords = $tempWords;
            }

            return implode(' ', $keywords);
        }
    }
}
