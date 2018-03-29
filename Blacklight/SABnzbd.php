<?php

namespace Blacklight;

use GuzzleHttp\Client;
use App\Models\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Class SABnzbd.
 */
class SABnzbd
{
    /**
     * Type of site integration.
     */
    public const INTEGRATION_TYPE_NONE = 0;
    public const INTEGRATION_TYPE_USER = 2;

    /**
     * Type of SAB API key.
     */
    public const API_TYPE_NZB = 1;
    public const API_TYPE_FULL = 2;

    /**
     * Priority to send the NZB to SAB.
     */
    public const PRIORITY_PAUSED = -2;
    public const PRIORITY_LOW = -1;
    public const PRIORITY_NORMAL = 0;
    public const PRIORITY_HIGH = 1; // Sab is completely disabled - no user can use it.
    public const PRIORITY_FORCE = 2; // Sab is enabled, 1 remote SAB server for the whole site.

    /**
     * URL to the SAB server.
     * @var string|array|bool
     */
    public $url = '';

    /**
     * The SAB API key.
     * @var string|array|bool
     */
    public $apikey = '';

    /**
     * Download priority of the sent NZB file.
     * @var string|array|bool
     */
    public $priority = '';

    /**
     * Type of SAB API key (full/nzb).
     * @var string|array|bool
     */
    public $apikeytype = '';

    /**
     * @var int
     */
    public $integrated = self::INTEGRATION_TYPE_NONE;

    /**
     * Is sab integrated into the site or not.
     * @var bool
     */
    public $integratedBool = false;

    /**
     * ID of the current user, to send to SAB when downloading a NZB.
     *
     *
     * @var int|string
     */
    protected $uid = '';

    /**
     * User's nntmux API key to send to SAB when downloading a NZB.
     * @var string
     */
    protected $rsstoken = '';

    /**
     * nZEDb Site URL to send to SAB to download the NZB.
     * @var string
     */
    protected $serverurl = '';

    private $client;

    /**
     * SABnzbd constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $user = Auth::user();
        $this->uid = Auth::id();
        $this->rsstoken = $user['rsstoken'];
        $this->serverurl = url('/');
        $this->client = new Client(['verify' => false]);

        // Set up properties.
        switch (Settings::settingValue('apps.sabnzbplus.integrationtype')) {
            case self::INTEGRATION_TYPE_USER:
                if (! empty($_COOKIE['sabnzbd_'.$this->uid.'__apikey']) && ! empty($_COOKIE['sabnzbd_'.$this->uid.'__host'])) {
                    $this->url = $_COOKIE['sabnzbd_'.$this->uid.'__host'];
                    $this->apikey = $_COOKIE['sabnzbd_'.$this->uid.'__apikey'];
                    $this->priority = $_COOKIE['sabnzbd_'.$this->uid.'__priority'] ?? 0;
                    $this->apikeytype = $_COOKIE['sabnzbd_'.$this->uid.'__apitype'] ?? 1;
                } elseif (! empty($user['sabapikey']) && ! empty($user['saburl'])) {
                    $this->url = $user['saburl'];
                    $this->apikey = $user['sabapikey'];
                    $this->priority = $user['sabpriority'];
                    $this->apikeytype = $user['sabapikeytype'];
                }
                $this->integrated = self::INTEGRATION_TYPE_USER;
                switch ((int) $user['queuetype']) {
                    case 1:
                    case 2:
                        $this->integratedBool = true;
                        break;
                    default:
                        $this->integratedBool = false;
                        break;
                }
                break;

            case self::INTEGRATION_TYPE_NONE:
                $this->integrated = self::INTEGRATION_TYPE_NONE;
                // This is for nzbget.
                if ($user['queuetype'] === 2) {
                    $this->integratedBool = true;
                }
                break;
        }
        // Verify the URL is good, fix it if not.
        if ($this->url !== '' && preg_match('/(?P<first>\/)?(?P<sab>[a-z]+)?(?P<last>\/)?$/i', $this->url, $matches)) {
            if (! isset($matches['first'])) {
                $this->url .= '/';
            }
            if (! isset($matches['sab'])) {
                $this->url .= 'sabnzbd';
            } elseif ($matches['sab'] !== 'sabnzbd') {
                $this->url .= 'sabnzbd';
            }
            if (! isset($matches['last'])) {
                $this->url .= '/';
            }
        }
    }

    /**
     * @param $guid
     * @return string
     * @throws \RuntimeException
     */
    public function sendToSab($guid): string
    {
        return $this->client->post(
                $this->url.
                    'api?mode=addurl&priority='.
                    $this->priority.
                    '&apikey='.
                    $this->apikey.
                    '&name='.
                    urlencode(
                        $this->serverurl.
                        'getnzb/'.
                        $guid.
                        '&i='.
                        $this->uid.
                        '&r='.
                        $this->rsstoken
                    )
        )->getBody()->getContents();
    }

    /**
     * @return string
     * @throws \RuntimeException
     */
    public function getAdvQueue(): string
    {
        return $this->client->get(
                    $this->url.
                    'api?mode=queue&start=START&limit=LIMIT&output=json&apikey='.
                    $this->apikey

        )->getBody()->getContents();
    }

    /**
     * @return string
     * @throws \RuntimeException
     */
    public function getHistory(): string
    {
        return $this->client->get(
            $this->url.
            'api?mode=history&start=START&limit=LIMIT&category=CATEGORY&search=SEARCH&failed_only=0&output=json&apikey='.
            $this->apikey

        )->getBody()->getContents();
    }

    /**
     * @param $id
     * @return string
     * @throws \RuntimeException
     */
    public function delFromQueue($id): string
    {
        return $this->client->get(
        $this->url.
            'api?mode=queue&name=delete&value='.
            $id.
            '&apikey='.
            $this->apikey
        )->getBody()->getContents();
    }

    /**
     * @param $id
     * @return string
     * @throws \RuntimeException
     */
    public function pauseFromQueue($id): string
    {
        return $this->client->get(
        $this->url.
            'api?mode=queue&name=pause&value='.
            $id.
            '&apikey='.
            $this->apikey
        )->getBody()->getContents();
    }

    /**
     * @param $id
     * @return string
     * @throws \RuntimeException
     */
    public function resumeFromQueue($id)
    {
        return $this->client->get(
        $this->url.
        'api?mode=queue&name=resume&value='.
            $id.
        '&apikey='.
            $this->apikey
        )->getBody()->getContents();
    }

    /**
     * @return string
     * @throws \RuntimeException
     */
    public function pauseAll(): string
    {
        return $this->client->get(
        $this->url.
        'api?mode=pause'.
        '&apikey='.
            $this->apikey
        )->getBody()->getContents();
    }

    /**
     * Resume all NZB's in the SAB queue.
     *
     * @return string
     * @throws \RuntimeException
     */
    public function resumeAll(): string
    {
        return $this->client->get(
        $this->url.
        'api?mode=resume'.
        '&apikey='.
            $this->apikey
        )->getBody()->getContents();
    }

    /**
     * Check if the SAB cookies are in the User's browser.
     *
     * @return bool
     */
    public function checkCookie()
    {
        $res = false;
        if (isset($_COOKIE['sabnzbd_'.$this->uid.'__apikey'])) {
            $res = true;
        }
        if (isset($_COOKIE['sabnzbd_'.$this->uid.'__host'])) {
            $res = true;
        }
        if (isset($_COOKIE['sabnzbd_'.$this->uid.'__priority'])) {
            $res = true;
        }
        if (isset($_COOKIE['sabnzbd_'.$this->uid.'__apitype'])) {
            $res = true;
        }

        return $res;
    }

    /**
     * Creates the SAB cookies for the user's browser.
     *
     * @param $host
     * @param $apikey
     * @param $priority
     * @param $apitype
     */
    public function setCookie($host, $apikey, $priority, $apitype)
    {
        setcookie('sabnzbd_'.$this->uid.'__host', $host, Carbon::now()->addDays(30)->timestamp);
        setcookie('sabnzbd_'.$this->uid.'__apikey', $apikey, Carbon::now()->addDays(30)->timestamp);
        setcookie('sabnzbd_'.$this->uid.'__priority', $priority, Carbon::now()->addDays(30)->timestamp);
        setcookie('sabnzbd_'.$this->uid.'__apitype', $apitype, Carbon::now()->addDays(30)->timestamp);
    }

    /**
     * Deletes the SAB cookies from the user's browser.
     */
    public function unsetCookie()
    {
        setcookie('sabnzbd_'.$this->uid.'__host', '', Carbon::now()->subDays(30)->timestamp);
        setcookie('sabnzbd_'.$this->uid.'__apikey', '', Carbon::now()->subDays(30)->timestamp);
        setcookie('sabnzbd_'.$this->uid.'__priority', '', Carbon::now()->subDays(30)->timestamp);
        setcookie('sabnzbd_'.$this->uid.'__apitype', '', Carbon::now()->subDays(30)->timestamp);
    }
}
