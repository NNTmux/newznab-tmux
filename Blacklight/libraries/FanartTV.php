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
     * The constructor setting the config variables.
     *
     * @param $apiKey
     */
    public function __construct($apiKey)
    {
        $this->apikey = $apiKey;
        $this->server = 'https://webservice.fanart.tv/v3';
    }

    /**
     * Getting movie pictures.
     *
     * @param string $id
     *
     * @return array|bool
     */
    public function getMovieFanart($id)
    {
        if ($this->apikey !== '') {
            $fanart = $this->_call('movies/'.$id);
            if (! empty($fanart)) {
                return $fanart;
            }

            return false;
        }

        return false;
    }

    /**
     * Getting tv show pictures.
     *
     * @param string $id
     * @return array|bool
     */
    public function getTVFanart($id)
    {
        if ($this->apikey !== '') {
            $fanart = $this->_call('tv/'.$id);
            if (! empty($fanart)) {
                return $fanart;
            }

            return false;
        }

        return false;
    }

    /**
     * The function making all the work using curl to call.
     *
     * @param string $path
     * @return array
     */
    private function _call($path)
    {
        $url = $this->server.'/'.$path.'?api_key='.$this->apikey;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
