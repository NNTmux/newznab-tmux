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
 * @date 2017-04-12
 * @release <0.0.2>
 */

namespace Blacklight\libraries;

class FanartTV
{
    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $server;

    /**
     * The constructor setting the config variables.
     *
     * @param $apiKey
     */
    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
        $this->server = 'https://webservice.fanart.tv/v3';
    }

    /**
     * Getting movie pictures.
     *
     * @param  string  $id
     * @return array|false
     */
    public function getMovieFanArt($id)
    {
        if ($this->apiKey !== '') {
            $fanArt = $this->_getUrl('movies/'.$id);
            if ($fanArt !== false) {
                return $fanArt;
            }

            return false;
        }

        return false;
    }

    /**
     * Getting tv show pictures.
     *
     * @param  string  $id
     * @return array|false
     */
    public function getTVFanart($id)
    {
        if ($this->apiKey !== '') {
            $fanArt = $this->_getUrl('tv/'.$id);
            if ($fanArt !== false) {
                return $fanArt;
            }

            return false;
        }

        return false;
    }

    /**
     * The function making all the work using curl to call.
     *
     * @param  string  $path
     * @return false|array
     */
    private function _getUrl($path)
    {
        $url = $this->server.'/'.$path.'?api_key='.$this->apiKey;

        return getRawHtml($url);
    }
}
