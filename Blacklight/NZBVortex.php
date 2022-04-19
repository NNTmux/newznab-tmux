<?php

namespace Blacklight;

use App\Models\User;
use Page;

/**
 * Class NZBVortex.
 */
final class NZBVortex
{
    private $nonce = null;
    private $session = null;

    public function __construct()
    {
        if (null === $this->session) {
            $this->getNonce();
            $this->login();
        }
    }

    /**
     * get text for state.
     *
     * @param  int  $code
     * @return string
     */
    public function getState($code = 0)
    {
        $states = [
            0  => 'Waiting',
            1  => 'Downloading',
            2  => 'Waiting for save',
            3  => 'Saving',
            4  => 'Saved',
            5  => 'Password request',
            6  => 'Queued for processing',
            7  => 'User wait for processing',
            8  => 'Checking',
            9  => 'Repairing',
            10 => 'Joining',
            11 => 'Wait for further processing',
            12 => 'Joining',
            13 => 'Wait for uncompress',
            14 => 'Uncompressing',
            15 => 'Wait for cleanup',
            16 => 'Cleaning up',
            17 => 'Cleaned up',
            18 => 'Moving to completed',
            19 => 'Move completed',
            20 => 'Done',
            21 => 'Uncompress failed',
            22 => 'Check failed, data corrupt',
            23 => 'Move failed',
            24 => 'Badly encoded download (uuencoded)',
        ];

        return (isset($states[$code])) ?
            $states[$code] : -1;
    }

    /**
     * get overview of NZB's in queue.
     *
     * @return array
     */
    public function getOverview()
    {
        $params = ['sessionid' => $this->session];
        $response = $this->sendRequest(sprintf('app/webUpdate'), $params);
        foreach ($response['nzbs'] as &$nzb) {
            $nzb['original_state'] = $nzb['state'];
            $nzb['state'] = ($nzb['isPaused'] === true) ? 'Paused' : $this->getState($nzb['state']);
        }

        return $response;
    }

    /**
     * add NZB to queue.
     *
     * @param  string  $nzb
     * @return void
     *
     * @throws \Exception
     */
    public function addQueue($nzb = '')
    {
        if (! empty($nzb)) {
            $page = new Page;

            $host = $page->serverurl;
            $data = User::find(User::currentUserId());
            $url = sprintf('%sgetnzb?id=%s.nzb&i=%s&r=%s', $host, $nzb, $data['id'], $data['api_token']);

            $params = [
                'sessionid' => $this->session,
                'url'       => $url,
            ];

            $response = $this->sendRequest('nzb/add', $params);
        }
    }

    /**
     * resume NZB.
     *
     * @param  int  $id
     * @return void
     *
     * @throws \Exception
     */
    public function resume($id = 0): void
    {
        if ($id > 0) {
            // /nzb/(id)/resume
            $params = ['sessionid' => $this->session];
            $response = $this->sendRequest(sprintf('nzb/%s/resume', $id), $params);
        }
    }

    /**
     * pause NZB.
     *
     * @param  int  $id
     * @return void
     *
     * @throws \Exception
     */
    public function pause($id = 0)
    {
        if ($id > 0) {
            // /nzb/(id)/pause
            $params = ['sessionid' => $this->session];
            $response = $this->sendRequest(sprintf('nzb/%s/pause', $id), $params);
        }
    }

    /**
     * move NZB up in queue.
     *
     * @param  int  $id
     * @return void
     *
     * @throws \Exception
     */
    public function moveUp($id = 0)
    {
        if ($id > 0) {
            // nzb/(nzbid)/moveup
            $params = ['sessionid' => $this->session];
            $response = $this->sendRequest(sprintf('nzb/%s/moveup', $id), $params);
        }
    }

    /**
     * move NZB down in queue.
     *
     * @param  int  $id
     * @return void
     *
     * @throws \Exception
     */
    public function moveDown($id = 0)
    {
        if ($id > 0) {
            // nzb/(nzbid)/movedown
            $params = ['sessionid' => $this->session];
            $response = $this->sendRequest(sprintf('nzb/%s/movedown', $id), $params);
        }
    }

    /**
     * move NZB to bottom of queue.
     *
     * @param  int  $id
     * @return void
     *
     * @throws \Exception
     */
    public function moveBottom($id = 0)
    {
        if ($id > 0) {
            // nzb/(nzbid)/movebottom
            $params = ['sessionid' => $this->session];
            $response = $this->sendRequest(sprintf('nzb/%s/movebottom', $id), $params);
        }
    }

    /**
     * Remove a (ﬁnished/unﬁnished) NZB from queue and delete files.
     *
     * @param  int  $id
     * @return void
     *
     * @throws \Exception
     */
    public function delete($id = 0)
    {
        if ($id > 0) {
            // nzb/(nzbid)/movebottom
            $params = ['sessionid' => $this->session];
            $response = $this->sendRequest(sprintf('nzb/%s/cancelDelete', $id), $params);
        }
    }

    /**
     * move NZB to top of queue.
     *
     * @param  int  $id
     * @return void
     *
     * @throws \Exception
     */
    public function moveTop($id = 0)
    {
        if ($id > 0) {
            // nzb/(nzbid)/movebottom
            $params = ['sessionid' => $this->session];
            $response = $this->sendRequest(sprintf('nzb/%s/movetop', $id), $params);
        }
    }

    /**
     * get filelist for nzb.
     *
     * @param  int  $id
     * @return array|false
     *
     * @throws \Exception
     */
    public function getFilelist($id = 0)
    {
        if ($id > 0) {
            // ﬁle/(nzbid)
            $params = ['sessionid' => $this->session];

            return $this->sendRequest(sprintf('file/%s', $id), $params);
        }

        return false;
    }

    /**
     * get /auth/nonce.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function getNonce()
    {
        $response = $this->sendRequest('auth/nonce');
        $this->nonce = $response['authNonce'];
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function login()
    {
        $data = User::find(User::currentUserId());
        $cnonce = generateUuid();
        $hash = hash('sha256', sprintf('%s:%s:%s', $this->nonce, $cnonce, $data['nzbvortex_api_key']), true);
        $hash = base64_encode($hash);

        $params = [
            'nonce'  => $this->nonce,
            'cnonce' => $cnonce,
            'hash'   => $hash,
        ];

        $response = $this->sendRequest('auth/login', $params);

        if ('successful' === $response['loginResult']) {
            $this->session = $response['sessionID'];
        }

        if ('failed' === $response['loginResult']) {
        }
    }

    /**
     * sendRequest().
     *
     * @param  $path
     * @param  array  $params
     * @return array
     *
     * @throws \Exception
     */
    protected function sendRequest($path, $params = [])
    {
        $data = User::find(User::currentUserId());

        $url = sprintf('%s/api', $data['nzbvortex_server_url']);
        $params = http_build_query($params);
        $ch = curl_init(sprintf('%s/%s?%s', $url, $path, $params));

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        //curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
        //curl_setopt($ch, CURLOPT_PROXY, 'localhost:8888');

        $response = curl_exec($ch);
        $response = json_decode($response, true);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        switch ($status) {
            case 0:
                throw new \RuntimeException(sprintf('Unable to connect. Is NZBVortex running? Is your API key correct? Is something blocking ports? (Err: %s)', $error));
                break;

            case 200:
                return $response;
                break;

            case 403:
                throw new \RuntimeException('Unable to login. Is your API key correct?');
                break;

            default:
                throw new \RuntimeException(sprintf('%s (%s): %s', $path, $status, $response['result']));
                break;
        }
    }
}
