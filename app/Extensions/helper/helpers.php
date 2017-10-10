<?php

use nntmux\ColorCLI;
use GuzzleHttp\Client;
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
                    ColorCLI::doEcho(ColorCLI::notice('Data not available on server'));
                } elseif ($e->getCode() === 503) {
                    ColorCLI::doEcho(ColorCLI::notice('Service unavailable'));
                } else {
                    ColorCLI::doEcho(ColorCLI::notice('Unable to fetch data from server, http error reported: '.$e->getCode()));
                }
            }
        } catch (\RuntimeException $e) {
            ColorCLI::doEcho(ColorCLI::notice('Runtime error: '.$e->getCode()));
        }

        return $response;
    }
}

if (! function_exists('makeFieldLinks')) {

    /**
     * @param $data
     * @param $field
     * @param $type
     * @return string
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
            if ($i > 7) {
                break;
            }
            $newArr[] = '<a href="'.WWW_TOP.'/'.$type.'?'.$field.'='.urlencode($ta).'" title="'.$ta.'">'.$ta.'</a>';
            $i++;
        }

        return implode(', ', $newArr);
    }
}
