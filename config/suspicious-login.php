<?php

use App\Models\LoginAttempt;
use Spatie\SuspiciousLogin\Fingerprinters\CookieFingerprinter;
use Spatie\SuspiciousLogin\Mail\SuspiciousLoginMail;
use Spatie\SuspiciousLogin\Presets\DefaultPreset;

return [
    /*
     * When disabled, Laravel's auth events will not record or assess logins.
     * Explicit calls to the SuspiciousLogin facade still work.
     */
    'enabled' => (bool) env('SUSPICIOUS_LOGIN_ENABLED', true),

    /*
     * The preset decides which checks run, how their scores map to a risk
     * level and which actions run for each risk level. The package ships
     * with DefaultPreset, MonitorOnlyPreset and NonePreset.
     * Extend a preset to build your own. Use
     * SuspiciousLogin::determinePresetUsing() to choose per login.
     */
    'preset' => DefaultPreset::class,

    /*
     * The scores at which medium and high risk start. Presets can override
     * either threshold with their own $mediumRiskThreshold or $highRiskThreshold.
     */
    'risk_thresholds' => [
        'medium' => 40,
        'high' => 70,
    ],

    'geolocation' => [
        /*
         * The driver used to find the location of an IP address. Several checks,
         * such as NewCountryCheck and ImpossibleTravelCheck, need a location.
         *
         * By default, public IP addresses are sent to ipinfo.io without an API token.
         * Use "null" to disable lookups.
         *
         * Supported: "ipinfo", "cloudflare", "maxmind", "null"
         */
        'driver' => 'ipinfo',

        /*
         * How long the location of an IP address is cached, in seconds.
         */
        'cache_ttl' => 60 * 60 * 24 * 7,

        'ipinfo' => [
            'token' => env('IPINFO_TOKEN'),
        ],

        'maxmind' => [
            /*
             * The path to a GeoLite2 City or GeoIP2 City database.
             * Requires `composer require geoip2/geoip2`.
             */
            'database_path' => storage_path('app/geoip/GeoLite2-City.mmdb'),
        ],
    ],

    'ip_reputation' => [
        /*
         * The driver used to determine if an IP address is known to be abusive
         * or a Tor exit node. Only used by IpReputationCheck.
         *
         * Supported: "null", "tor", "abuseipdb"
         */
        'driver' => 'null',

        /*
         * How long the reputation of an IP address is cached, in seconds.
         */
        'cache_ttl' => 60 * 60 * 24,

        'tor' => [
            'exit_node_list_url' => 'https://check.torproject.org/torbulkexitlist',
        ],

        'abuseipdb' => [
            'key' => env('ABUSEIPDB_KEY'),
        ],
    ],

    /*
     * The model used to store login attempts. App\Models\LoginAttempt extends
     * Spatie\SuspiciousLogin\Models\LoginAttempt with the admin list filters.
     */
    'model' => LoginAttempt::class,

    /*
     * The class that turns a request into a device fingerprint. It must implement
     * Spatie\SuspiciousLogin\Fingerprinters\DeviceFingerprinter.
     * The default requires Laravel's web cookie middleware. For stateless apps,
     * use Spatie\SuspiciousLogin\Fingerprinters\UserAgentFingerprinter instead.
     */
    'device_fingerprinter' => CookieFingerprinter::class,

    /*
     * The mailable that NotifyUserAction sends to the user's email address.
     * It receives the LoginAttempt in its constructor.
     */
    'mailable' => SuspiciousLoginMail::class,

    /*
     * Login attempts older than this many days are deleted by the daily
     * `model:prune --model="App\Models\LoginAttempt"` scheduled in routes/console.php.
     */
    'prune_after_days' => 365,
];
