<?php

return [
    /*
     * This is the BTCPay API Key generated under your user's profile.
     * This value must be supplied for communication to work.
     */
    'api_key' => env('BTCPAY_API_KEY'),

    /*
     * This is the URL to your BTCPayServer as shown in the address bar of
     * your browser. Without the last / at the end of the URL.
     * i.e.: https://btcpay.your.server.tld
     */
    'server_url' => env('BTCPAY_SERVER_URL'),

    /*
     * This is the Store ID of your BTCPayServer Store that you wish to create
     * the invoices on. You will find it in the "Store Settings".
     * i.e.: someRandomStringWithWildAlphaNumericChars
     */
    'store_id' => env('BTCPAY_STORE_ID'),

    /*
     * Indicates if configured webhook (notificationURL) should automatically be set on:
     * - Invoices
     * - Recipients
     * - Payouts/PayoutBatches
     *
     * This feature is overridden when a value is manually set on a respective resource
     * before submitting it to the BitPay API.
     *
     * Uncomment an entry to enable its auto-population.
     */
    'auto_populate_webhook' => [
        //        \Petzsch\LaravelBtcpay\Constants\WebhookAutoPopulate::For_Invoices,
        //        \Petzsch\LaravelBtcpay\Constants\WebhookAutoPopulate::For_Recipients,
        //        \Petzsch\LaravelBtcpay\Constants\WebhookAutoPopulate::For_Payouts,
    ],
];
