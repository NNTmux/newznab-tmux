<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Cookie\CookieJar;
use nntmux\ColorCLI;

if (!function_exists('getUrl')) {

	function getUrl($url, $cookie = false)
	{
		$response = false;
		$cookiejar = new CookieJar();
		$client = new Client();
		if ($cookie !== false) {
			$cookieJar = $cookiejar->setCookie(SetCookie::fromString($this->cookie));
			$client = new Client(['cookies' => $cookieJar]);
		}
		try {
			$response = $client->get($url)->getBody()->getContents();
		} catch (RequestException $e) {
			if ($e->hasResponse()) {
				if($e->getCode() === 404) {
					ColorCLI::doEcho(ColorCLI::notice('Data not available on server'));
				} else if ($e->getCode() === 503) {
					ColorCLI::doEcho(ColorCLI::notice('Service unavailable'));
				} else {
					ColorCLI::doEcho(ColorCLI::notice('Unable to fetch data from server, http error reported: ' . $e->getCode()));
				}
			}
		} catch (\RuntimeException $e) {
			ColorCLI::doEcho(ColorCLI::notice('Runtime error: ' . $e->getCode()));
		}

		return $response;
	}
}