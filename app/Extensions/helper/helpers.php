<?php

use Colors\Color;
use Blacklight\XXX;
use GuzzleHttp\Client;
use Tuna\CloudflareMiddleware;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Cookie\FileCookieJar;
use Symfony\Component\Process\Process;
use GuzzleHttp\Exception\RequestException;

if (! function_exists('getRawHtml')) {

    /**
     * @param      $url
     * @param bool $cookie
     *
     * @return bool|mixed|string
     */
    function getRawHtml($url, $cookie = false)
    {
        $cookiejar = new CookieJar();
        $client = new Client(['headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246']]);
        if ($cookie !== false) {
            $cookieJar = $cookiejar->setCookie(SetCookie::fromString($cookie));
            $client = new Client(['cookies' => $cookieJar, 'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246']]);
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
        } catch (\RuntimeException $e) {
            if (config('app.debug') === true) {
                Log::error($e->getMessage());
            }
            $response = false;
        }

        return $response;
    }
}

if (! function_exists('getRawHtmlThroughCF')) {

    /**
     * @param $url
     *
     * @return bool|mixed|string
     */
    function getRawHtmlThroughCF($url)
    {
        $client = new Client(['cookies' => new FileCookieJar('cookies.txt'), 'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246']]);
        $client->getConfig('handler')->push(CloudflareMiddleware::create());

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
        } catch (\RuntimeException $e) {
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
     * @param $data
     * @param $field
     * @param $type
     *
     * @return string
     * @throws \Exception
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
                $ta = (new XXX())->getGenres(true, $ta);
                $ta = $ta['title'];
            }
            if ($i > 7) {
                break;
            }
            $newArr[] = '<a href="'.WWW_TOP.'/'.ucfirst($type).'?'.$field.'='.urlencode($ta).'" title="'.$ta.'">'.$ta.'</a>';
            $i++;
        }

        return implode(', ', $newArr);
    }

    if (! function_exists('getUserBrowseOrder')) {
        /**
         * @param string $orderBy
         *
         * @return array
         */
        function getUserBrowseOrder($orderBy): array
        {
            $order = ($orderBy === '' ? 'username_desc' : $orderBy);
            $orderArr = explode('_', $order);
            switch ($orderArr[0]) {
                case 'username':
                    $orderField = 'username';
                    break;
                case 'email':
                    $orderField = 'email';
                    break;
                case 'host':
                    $orderField = 'host';
                    break;
                case 'createdat':
                    $orderField = 'created_at';
                    break;
                case 'lastlogin':
                    $orderField = 'lastlogin';
                    break;
                case 'apiaccess':
                    $orderField = 'apiaccess';
                    break;
                case 'apirequests':
                    $orderField = 'apirequests';
                    break;
                case 'grabs':
                    $orderField = 'grabs';
                    break;
                case 'roles_id':
                    $orderField = 'users_role_id';
                    break;
                case 'rolechangedate':
                    $orderField = 'rolechangedate';
                    break;
                default:
                    $orderField = 'username';
                    break;
            }
            $orderSort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

            return [$orderField, $orderSort];
        }
    }

    if (! function_exists('getUserBrowseOrdering')) {

        /**
         * @return array
         */
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

    if (! function_exists('createGUID')) {
        /**
         * @return string
         * @throws \Exception
         */
        function createGUID(): string
        {
            $data = random_bytes(16);
            $data[6] = \chr(\ord($data[6]) & 0x0f | 0x40);    // set version to 0100
            $data[8] = \chr(\ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10

            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(sodium_bin2hex($data), 4));
        }
    }

    if (! function_exists('getSimilarName')) {
        /**
         * @param string $name
         *
         * @return string
         */
        function getSimilarName($name): string
        {
            return implode(' ', \array_slice(str_word_count(str_replace(['.', '_'], ' ', $name), 2), 0, 2));
        }
    }

    if (! function_exists('color')) {
        /**
         * @param string $string
         *
         * @return \Colors\Color
         */
        function color($string = ''): Color
        {
            return new Color($string);
        }
    }

    if (! function_exists('human_filesize')) {

        /**
         * @param     $bytes
         * @param int $decimals
         *
         * @return string
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
         * @param $dec
         *
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
         * @param string $command
         * @param bool $debug
         *
         * @return string
         */
        function runCmd($command, $debug = false)
        {
            if ($debug) {
                echo '-Running Command: '.PHP_EOL.'   '.$command.PHP_EOL;
            }

            $process = new Process($command);
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
         * @param $string
         *
         * @return string
         */
        function escapeString($string)
        {
            return DB::connection()->getPdo()->quote($string);
        }
    }

    if (! function_exists('realDuration')) {

        /**
         * @param $milliseconds
         *
         * @return string
         */
        function realDuration($milliseconds)
        {
            $time = round($milliseconds / 1000);

            return sprintf('%02dh:%02dm:%02ds', $time / 3600, $time / 60 % 60, $time % 60);
        }
    }

    if (! function_exists('is_it_json')) {

        /**
         * @param array|string $isIt
         * @return bool
         */
        function is_it_json($isIt)
        {
            if (is_array($isIt)) {
                return false;
            }
            json_decode($isIt);

            return (json_last_error() === JSON_ERROR_NONE);
        }
    }
}
