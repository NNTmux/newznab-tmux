<?php

use Colors\Color;
use Blacklight\XXX;
use GuzzleHttp\Client;
use Blacklight\ColorCLI;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\RequestException;

if (! function_exists('getRawHtml')) {

    /**
     * @param      $url
     * @param bool|string $cookie
     *
     * @return bool|string
     */
    function getRawHtml($url, $cookie = false)
    {
        $response = false;
        $cookiejar = new CookieJar();
        $client = new Client();
        if ($cookie !== false) {
            $cookieJar = $cookiejar->setCookie(SetCookie::fromString($cookie));
            $client = new Client(['cookies' => $cookieJar]);
        }
        try {
            $response = $client->get($url)->getBody()->getContents();
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                if ($e->getCode() === 404) {
                    ColorCLI::doEcho(ColorCLI::notice('Data not available on server'), true);
                } elseif ($e->getCode() === 503) {
                    ColorCLI::doEcho(ColorCLI::notice('Service unavailable'), true);
                } else {
                    ColorCLI::doEcho(ColorCLI::notice('Unable to fetch data from server, http error reported: '.$e->getCode()), true);
                }
            }
        } catch (\RuntimeException $e) {
            ColorCLI::doEcho(ColorCLI::notice('Runtime error: '.$e->getCode()), true);
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
            $newArr[] = '<a href="'.WWW_TOP.'/'.$type.'?'.$field.'='.urlencode($ta).'" title="'.$ta.'">'.$ta.'</a>';
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
                case 'user_roles_id':
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

            return round(sprintf("%.{$decimals}f", $bytes / (1024 ** $factor))).@$size[$factor];
        }
    }
}
