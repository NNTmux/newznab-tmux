<?php

namespace Blacklight;

use App\Models\Release;
use Blacklight\utility\Utility;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

/**
 * Transfers data between an NZBGet server and a nntmux website.
 *
 *
 * Class NZBGet
 */
class NZBGet
{
    /**
     * NZBGet username.
     *
     * @var string
     */
    public $userName = '';

    /**
     * NZBGet password.
     *
     * @var string
     */
    public $password = '';

    /**
     * NZBGet URL.
     *
     * @var string
     */
    public $url = '';

    /**
     * Full URL (containing password/username/etc).
     *
     * @var string|bool
     */
    protected $fullUrl = '';

    /**
     * User id.
     *
     * @var int
     */
    protected $uid = 0;

    /**
     * The users RSS token.
     *
     * @var string
     */
    protected $rsstoken = '';

    /**
     * URL to your NNTmux site.
     *
     * @var string
     */
    protected $serverurl = '';

    /**
     * @var \Blacklight\Releases
     */
    protected $releases;

    /**
     * @var \Blacklight\NZB
     */
    protected $nzb;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Construct.
     * Set up full URL.
     *
     * @var \App\Http\Controllers\BasePageController
     *
     * @throws \Exception
     */
    public function __construct(&$page)
    {
        $this->serverurl = url('/');
        $this->uid = $page->userdata['id'];
        $this->api_token = $page->userdata['api_token'];

        if (! empty($page->userdata['nzbgeturl'])) {
            $this->url = $page->userdata['nzbgeturl'];
            $this->userName = (empty($page->userdata['nzbgetusername']) ? '' : $page->userdata['nzbgetusername']);
            $this->password = (empty($page->userdata['nzbgetpassword']) ? '' : $page->userdata['nzbgetpassword']);
        }

        $this->fullUrl = $this->verifyURL($this->url);
        $this->releases = new Releases();
        $this->nzb = new NZB();
        $this->client = new Client();
    }

    /**
     * @param $guid
     * @return \GuzzleHttp\Psr7\Request
     */
    public function sendNZBToNZBGet($guid)
    {
        $relData = Release::getByGuid($guid);

        $gzipFile = Utility::unzipGzipFile($this->nzb->NZBPath($guid));
        $string = $gzipFile === false ? '' : $gzipFile;

        $header =
            '<?xml version="1.0"?>
			<methodCall>
				<methodName>append</methodName>
				<params>
					<param>
						<value><string>'.$relData['searchname'].'</string></value>
					</param>
					<param>
						<value><string>'.$relData['category_name'].'</string></value>
					</param>
					<param>
						<value><i4>0</i4></value>
					</param>
					<param>
						<value><boolean>>False</boolean></value>
					</param>
					<param>
						<value>
							<string>'.
            base64_encode($string).
            '</string>
						</value>
					</param>
				</params>
			</methodCall>';

        return new Request('POST', $this->fullUrl.'append', ['Content-Type' => 'text/xml; charset=UTF8'], $header);
    }

    /**
     * Send a NZB URL to NZBGet.
     *
     * @param  string  $guid  Release identifier.
     * @return bool|mixed
     */
    public function sendURLToNZBGet($guid)
    {
        $reldata = Release::getByGuid($guid);

        $header =
            '<?xml version="1.0"?>
			<methodCall>
				<methodName>appendurl</methodName>
				<params>
					<param>
						<value><string>'.$reldata['searchname'].'.nzb'.'</string></value>
					</param>
					<param>
						<value><string>'.$reldata['category_name'].'</string></value>
					</param>
					<param>
						<value><i4>0</i4></value>
					</param>
					<param>
						<value><boolean>>False</boolean></value>
					</param>
					<param>
						<value>
							<string>'.
            $this->serverurl.
            'getnzb?id='.
            $guid.
            '%26r%3D'.
            $this->api_token
            .
            '</string>
						</value>
					</param>
				</params>
			</methodCall>';

        return new Request('POST', $this->fullUrl.'append', ['Content-Type' => 'text/xml; charset=UTF8'], $header);
    }

    /**
     * Pause download queue on server. This method is equivalent for command "nzbget -P".
     *
     * @return void
     */
    public function pauseAll()
    {
        $header =
            '<?xml version="1.0"?>
			<methodCall>
				<methodName>pausedownload2</methodName>
				<params>
					<param>
						<value><boolean>1</boolean></value>
					</param>
				</params>
			</methodCall>';
        new Request('POST', $this->fullUrl.'pausedownload2', ['Content-Type' => 'text/xml; charset=UTF8'], $header);
    }

    /**
     * Resume (previously paused) download queue on server. This method is equivalent for command "nzbget -U".
     *
     * @return void
     */
    public function resumeAll()
    {
        $header =
            '<?xml version="1.0"?>
			<methodCall>
				<methodName>resumedownload2</methodName>
				<params>
					<param>
						<value><boolean>1</boolean></value>
					</param>
				</params>
			</methodCall>';
        new Request('POST', $this->fullUrl.'resumedownload2', ['Content-Type' => 'text/xml; charset=UTF8'], $header);
    }

    /**
     * Pause a single NZB from the queue.
     *
     * @param  string  $id
     */
    public function pauseFromQueue($id)
    {
        $header =
            '<?xml version="1.0"?>
			<methodCall>
				<methodName>editqueue</methodName>
				<params>
					<param>
						<value><string>GroupPause</string></value>
					</param>
					<param>
						<value><i4>0</i4></value>
					</param>
					<param>
						<value><string>""</string></value>
					</param>
					<param>
						<value>
							<array>
								<value><i4>'.$id.'</i4></value>
							</array>
						</value>
					</param>
				</params>
			</methodCall>';
        new Request('POST', $this->fullUrl.'editqueue', ['Content-Type' => 'text/xml; charset=UTF8'], $header);
    }

    /**
     * Resume a single NZB from the queue.
     *
     * @param  string  $id
     */
    public function resumeFromQueue($id)
    {
        $header =
            '<?xml version="1.0"?>
			<methodCall>
				<methodName>editqueue</methodName>
				<params>
					<param>
						<value><string>GroupResume</string></value>
					</param>
					<param>
						<value><i4>0</i4></value>
					</param>
					<param>
						<value><string>""</string></value>
					</param>
					<param>
						<value>
							<array>
								<value><i4>'.$id.'</i4></value>
							</array>
						</value>
					</param>
				</params>
			</methodCall>';
        new Request('POST', $this->fullUrl.'editqueue', ['Content-Type' => 'text/xml; charset=UTF8'], $header);
    }

    /**
     * Delete a single NZB from the queue.
     *
     * @param  string  $id
     */
    public function delFromQueue($id)
    {
        $header =
            '<?xml version="1.0"?>
			<methodCall>
				<methodName>editqueue</methodName>
				<params>
					<param>
						<value><string>GroupDelete</string></value>
					</param>
					<param>
						<value><i4>0</i4></value>
					</param>
					<param>
						<value><string>""</string></value>
					</param>
					<param>
						<value>
							<array>
								<value><i4>'.$id.'</i4></value>
							</array>
						</value>
					</param>
				</params>
			</methodCall>';
        new Request('POST', $this->fullUrl.'editqueue', ['Content-Type' => 'text/xml; charset=UTF8'], $header);
    }

    /**
     * Set download speed limit. This method is equivalent for command "nzbget -R <Limit>".
     *
     * @param  int  $limit  The speed to limit it to.
     * @return void
     */
    public function rate($limit)
    {
        $header =
            '<?xml version="1.0"?>
			<methodCall>
				<methodName>rate</methodName>
				<params>
					<param>
						<value><i4>'.$limit.'</i4></value>
					</param>
				</params>
			</methodCall>';
        new Request('POST', $this->fullUrl.'rate', ['Content-Type' => 'text/xml; charset=UTF8'], $header);
    }

    /**
     * Get all items in download queue.
     *
     *
     * @return array|false
     */
    public function getQueue()
    {
        $data = $this->client->get($this->fullUrl.'listgroups')->getBody()->getContents();
        $retVal = false;
        if ($data) {
            $xml = simplexml_load_string($data);
            if ($xml) {
                $retVal = [];
                $i = 0;
                foreach ($xml->params->param->value->array->data->value as $value) {
                    foreach ($value->struct->member as $member) {
                        $value = (array) $member->value;
                        $value = array_shift($value);
                        if (! \is_object($value)) {
                            $retVal[$i][(string) $member->name] = $value;
                        }
                    }
                    $i++;
                }
            }
        }

        return $retVal;
    }

    /**
     * Request for current status (summary) information. Parts of informations returned by this method can be printed by command "nzbget -L".
     *
     * @return array|false The status.
     *
     * @throws \RuntimeException
     */
    public function status()
    {
        $data = $this->client->get($this->fullUrl.'status')->getBody()->getContents();
        $retVal = false;
        if ($data) {
            $xml = simplexml_load_string($data);
            if ($xml) {
                foreach ($xml->params->param->value->struct->member as $member) {
                    $value = (array) $member->value;
                    $value = array_shift($value);
                    if (! \is_object($value)) {
                        $retVal[(string) $member->name] = $value;
                    }
                }
            }
        }

        return $retVal;
    }

    /**
     * Verify if the NZBGet URL is correct.
     *
     * @param  string  $url  NZBGet URL to verify.
     * @return bool|string
     */
    public function verifyURL($url)
    {
        if (preg_match('/(?P<protocol>https?):\/\/(?P<url>.+?)(:(?P<port>\d+\/)|\/)$/i', $url, $hits)) {
            return $hits['protocol'].'://'.$this->userName.':'.$this->password.'@'.$hits['url'].(isset($hits['port']) ? ':'.$hits['port'] : (substr($hits['url'], -1) === '/' ? '' : '/')).'xmlrpc/';
        }

        return false;
    }
}
