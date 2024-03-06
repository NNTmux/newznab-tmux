<?php
/**
 * Fanart.TV
 * PHP class - wrapper for Fanart.TV's API
 * API Documentation - http://docs.fanarttv.apiary.io/#.
 *
 * @author    confact <hakan@dun.se>
 * @author    DariusIII <dkrisan@gmail.com>
 * @copyright 2013 confact
 * @copyright 2017 NNTmux
 *
 * @date 2017-04-12
 *
 * @release <0.0.2>
 */

namespace Blacklight\libraries;

class FanartTV
{
    private string $apiKey;

    private string $server;

    /**
     * The constructor setting the config variables.
     */
    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
        $this->server = 'https://webservice.fanart.tv/';
    }

    /**
    * @param string $id
    * @return bool|array
     *
     */
    public function getMovieFanArt(string $id): bool|array
    {
        if ($this->apiKey !== '') {
            return $this->_getUrl('movie/'.$id);
        }

        return false;
    }

    /**
    * @param string $id
    * @return bool|array
     */
    public function getTVFanArt(string $id): bool|array
    {
        if ($this->apiKey !== '') {
            return $this->_getUrl('tv/'.$id);
        }

        return false;
    }

    /**
    * @param string $path
    * @return bool|array
     */
    private function _getUrl(string $path): bool|array
    {
        $url = $this->server.'/'.$path.'?api_key='.$this->apiKey;

        return getRawHtml($url);
    }
}
