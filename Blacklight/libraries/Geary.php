<?php

namespace Blacklight\libraries;

/*
 * Geary PHP API
 *
 * Access all API features of https://gear.mycelium.com
 *
 * @package  Geary
 * @author   Mario Dian (http://freedomnode.com)
 * @license  https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0 Universal
 * @version  0.1
 * @link     https://github.com/mariodian/geary
 *
 * Updated for NNTmux by DariusIII
 */

if (! \function_exists('getAllHeaders')) {
    function getAllHeaders()
    {
        if (! \is_array($_SERVER)) {
            return [];
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }
}

class Geary
{
    public const CONNECT_TIMEOUT = 60;

    public const API_URL = 'https://gateway.gear.mycelium.com';

    private $gateway_id;

    private $gateway_secret;

    /**
     * @param  string  $gateway_id  Your API key obtained from https://admin.gear.mycelium.com/gateways
     * @param  string  $gateway_secret  Your API secret obtained from https://admin.gear.mycelium.com/gateways
     */
    public function __construct($gateway_id, $gateway_secret)
    {
        $this->gateway_id = $gateway_id;
        $this->gateway_secret = $gateway_secret;
    }

    /**
     * Create Order.
     *
     * Create a new gateway order
     *
     * @param  float  $amount  Amount determines the amount to be paid for this
     *                         order. The amount should be in the currency you
     *                         have previously set for the gateway. If the
     *                         gateway currency is BTC, then the amount is
     *                         normally in satoshis
     * @return mixed
     */
    public function create_order($amount, $keychain_id, $callback_data)
    {
        $request = $this->endpoint('orders');
        $params = [
            'amount' => $amount,
            'keychain_id' => $keychain_id,
            'callback_data' => $callback_data,
        ];

        $data = [
            'request_uri' => $request,
            'request_method' => 'POST',
            'params' => $params,
        ];

        return $this->send_signed_request($data);
    }

    /**
     * Cancel Order.
     *
     * Cancel existing gateway order
     *
     * @param  int  $id  Id is an existing order ID or payment ID
     * @return mixed
     */
    public function cancel_order($id)
    {
        $request = $this->endpoint('orders');

        $data = [
            'request_uri' => $request,
            'request_method' => 'POST',
            'params' => "$id/cancel",
        ];

        return $this->send_signed_request($data);
    }

    /**
     * Check Order.
     *
     * Check existing gateway order status
     *
     * @param  int  $payment_id  Id is an existing payment ID
     * @return mixed
     */
    public function check_order($payment_id)
    {
        $request_uri = $this->endpoint('orders');

        $data = [
            'request_uri' => $request_uri,
            'request_method' => 'GET',
            'params' => $payment_id,
        ];

        return $this->send_signed_request($data);
    }

    /**
     * Check Order Callback.
     *
     * Check for any order status changes via callback URL
     *
     * @return mixed
     */
    public function check_order_callback()
    {
        $header_signature = $this->get_header('X-Signature');
        $request_path = $_SERVER['REQUEST_URI'];

        $nonce = null;
        $body = null;

        if (isset($_GET['after_payment_redirect_to']) && $after_payment_redirect_to = $_GET['after_payment_redirect_to']) {
            $_GET['after_payment_redirect_to'] = urlencode($after_payment_redirect_to);
        }

        $constant_digest = hash('sha512', $nonce.$body, true);
        $payload = $_SERVER['REQUEST_METHOD'].$request_path.$constant_digest;
        $raw_signature = hash_hmac('sha512', $payload, $this->gateway_secret, true);
        $signature = base64_encode($raw_signature);

        if ($signature === $header_signature) {
            return [
                'order_id' => $_GET['order_id'],
                'amount' => $_GET['amount'],
                'amount_in_btc' => $_GET['amount_in_btc'],
                'amount_paid_in_btc' => $_GET['amount_paid_in_btc'],
                'status' => $_GET['status'],
                'address' => $_GET['address'],
                'transaction_ids' => $_GET['transaction_ids'],
                'callback_data' => $_GET['callback_data'],
            ];
        }

        return false;
    }

    /**
     * Order Websocket Link.
     *
     * Get an order link for status monitoring via websocket
     *
     * @param  int  $id  Id is an existing order ID
     */
    public function order_websocket_link($id): string
    {
        return "wss://gateway.gear.mycelium.com/gateways/{$this->gateway_id}/orders/$id/websocket";
    }

    /**
     * Get Last Keychain Id.
     *
     * Get a last keychain id for a specific gateway
     *
     * @return mixed
     */
    public function get_last_keychain_id()
    {
        $request_uri = $this->endpoint('last_keychain_id');

        $data = [
            'request_uri' => $request_uri,
            'request_method' => 'GET',
            'params' => null,
        ];

        return $this->send_signed_request($data);
    }

    /**
     *Curl Error.
     *
     * Output curl error if possible
     */
    private function curl_error($ch): bool
    {
        if ($errno = curl_errno($ch)) {
            $error_message = curl_strerror($errno);
            echo "cURL error ({$errno}):\n {$error_message}";

            return false;
        }

        return true;
    }

    /**
     * Endpoint.
     *
     * Construct an endpoint URL
     *
     * @param  string  $method
     */
    private function endpoint($method): string
    {
        return "/gateways/{$this->gateway_id}/$method";
    }

    /**
     * Get Params.
     *
     * Construct URL parameters
     *
     * @param  mixed  $params
     */
    private function get_params($params): string
    {
        $parameters = '';

        if ($params !== null) {
            if (\is_array($params)) {
                $parameters = '?';
                $parameters .= http_build_query($params);
            } else {
                $parameters = '/';
                $parameters .= $params;
            }
        }

        return $parameters;
    }

    /**
     * Get Header.
     *
     * Get single data from header
     *
     * @param  string  $name
     */
    private function get_header($name): string
    {
        $headers = getAllHeaders();

        if ($headers) {
            foreach ($headers as $key => $value) {
                if (strtolower($key) === strtolower($name)) {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * Prepare Header.
     *
     * Add data to header for authentication purpose
     *
     * @param  array  $data
     */
    private function prepare_header($data): array
    {
        $params = $data['params'];
        $params_query = ! \is_array($params) ? "/$params" : '?'.http_build_query($params);

        $nonce = (int) round(microtime(true) * 1000);
        $body = '';

        $nonce_hash = hash('sha512', (string) $nonce.$body, true);
        $payload = $data['request_method'].$data['request_uri'].$params_query.$nonce_hash;
        $raw_signature = hash_hmac('sha512', $payload, $this->gateway_secret, true);
        $signature = base64_encode($raw_signature);

        return [
            'X-Nonce: '.$nonce,
            'X-Signature: '.$signature,
        ];
    }

    /**
     * Send Signed Request.
     *
     * Send a signed HTTP request
     *
     * @param  array  $data
     * @return mixed
     */
    private function send_signed_request($data)
    {
        $ch = curl_init();
        $url = self::API_URL.$data['request_uri'];

        $headers = $this->prepare_header($data);
        $params = $this->get_params($data['params']);

        $curl_data = [
            CURLOPT_URL => $url.$params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_POST => $data['request_method'] === 'POST',
        ];

        curl_setopt_array($ch, $curl_data);

        if (! $result = curl_exec($ch)) {
            return $this->curl_error($ch);
        }

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
            echo "Error: $result";

            return false;
        }

        return json_decode($result);
    }
}
