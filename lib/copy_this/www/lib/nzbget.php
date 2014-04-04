<?php
require_once(WWW_DIR."/lib/util.php");  
require_once(WWW_DIR."/lib/page.php");
require_once(WWW_DIR."/lib/releases.php");
require_once(WWW_DIR."/lib/mzb.php");

//This script is adapted from nZEDb for newznab

/**
 * Class NZBGet
 *
 * Transfers data between an NZBGet server and an n website.
 *
 * @package newznab
 */
class NZBGet
{
	/**
	 * NZBGet username.
	 * @var string
	 */
	public $userName = '';

	/**
	 * NZBGet password.
	 * @var string
	 */
	public $password = '';

	/**
	 * NZBGet URL.
	 * @var string
	 */
	public $url = '';

	/**
	 * Full URL (containing password/username/etc).
	 * @var string
	 */
	protected $fullURL = '';

	/**
	 * User ID.
	 * @var int
	 */
	protected $uid = 0;

	/**
	 * The users RSS token.
	 * @var string
	 */
	protected $rsstoken = '';

	/**
	 * URL to your newznab site.
	 * @var string
	 */
	protected $serverurl = '';

	/**
	 * @var Releases
	 */
	protected $Releases;

	/**
	 * @var NZB
	 */
	protected $NZB;

    /**
 * Use cURL To download a web page into a string.
 *
 * @param string $url       The URL to download.
 * @param string $method    get/post
 * @param string $postdata  If using POST, post your POST data here.
 * @param string $language  Use alternate langauge in header.
 * @param bool   $debug     Show debug info.
 * @param string $userAgent User agent.
 * @param string $cookie    Cookie.
 *
 * @return bool|mixed
 */
function getUrl($url, $method = 'get', $postdata = '', $language = "", $debug = false, $userAgent = '', $cookie = '')
{
	switch($language) {
		case 'fr':
		case 'fr-fr':
			$language = "fr-fr";
			break;
		case 'de':
		case 'de-de':
			$language = "de-de";
			break;
		case 'en':
			$language = 'en';
			break;
		case '':
		case 'en-us':
		default:
			$language = "en-us";
	}
	$header[] = "Accept-Language: " . $language;

	$ch = curl_init();
	$options = array(
		CURLOPT_URL            => $url,
		CURLOPT_HTTPHEADER     => $header,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_FOLLOWLOCATION => 1,
		CURLOPT_TIMEOUT        => 15,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
	);
	curl_setopt_array($ch, $options);

	if ($userAgent !== '') {
		curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
	}

	if ($cookie !== '') {
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
	}

	if ($method === 'post') {
		$options = array(
			CURLOPT_POST       => 1,
			CURLOPT_POSTFIELDS => $postdata
		);
		curl_setopt_array($ch, $options);
	}

	if ($debug) {
		$options =
			array(
			CURLOPT_HEADER      => true,
			CURLINFO_HEADER_OUT => true,
			CURLOPT_NOPROGRESS  => false,
			CURLOPT_VERBOSE     => true
		);
		curl_setopt_array($ch, $options);
	}

	$buffer = curl_exec($ch);
	$err = curl_errno($ch);
	curl_close($ch);

	if ($err !== 0) {
		return false;
	} else {
		return $buffer;
	}
}

	/**
	 * Construct.
	 * Set up full URL.
	 *
	 * @var BasePage $page
	 */
	public function __construct(&$page)
	{
		$this->serverurl = $page->serverurl;
		$this->uid = $page->userdata['ID'];
		$this->rsstoken = $page->userdata['rsstoken'];

		if (!empty($page->userdata['nzbgeturl']) && !empty($page->userdata['nzbgetusername']) && !empty($page->userdata['nzbgetpassword'])) {
			$this->url  = $page->userdata['nzbgeturl'];
			$this->userName = $page->userdata['nzbgetusername'];
			$this->password = $page->userdata['nzbgetpassword'];
		}

		$this->fullURL = $this->verifyURL($this->url);
		$this->Releases = new Releases();
		$this->NZB = new NZB();
	}

	/**
	 * Send a release to NZBGet.
	 *
	 * @param string $guid Release identifier.
	 *
	 * @return bool|mixed
	 */
	public function sendToNZBGet($guid)
	{
		$reldata = $this->Releases->getByGuid($guid);
		$nzbpath = $this->NZB->getNZBPath($guid);

		$string = '';
		$nzb = @gzopen($nzbpath, 'rb', 0);
		if ($nzb) {
			while (!gzeof($nzb)) {
				$string .= gzread($nzb, 1024);
			}
			gzclose($nzb);
		}

		$header =
			'<?xml version="1.0"?>
			<methodCall>
				<methodName>append</methodName>
				<params>
					<param>
						<value><string>' . $reldata['searchname'] . '</string></value>
					</param>
					<param>
						<value><string>' . $reldata["category_name"] . '</string></value>
					</param>
					<param>
						<value><i4>0</i4></value>
					</param>
					<param>
						<boolean>>False</boolean></value>
					</param>
					<param>
						<value>
							<string>' .
								base64_encode($string) .
							'</string>
						</value>
					</param>
				</params>
			</methodCall>';
		$this->getUrl($this->fullURL . 'append', 'post', $header);
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
		$this->getUrl($this->fullURL . 'pausedownload2', 'post', $header);
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
		$this->getUrl($this->fullURL . 'resumedownload2', 'post', $header);
	}

	/**
	 * Pause a single NZB from the queue.
	 *
	 * @param string $id
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
								<value><i4>' . $id . '</i4></value>
							</array>
						</value>
					</param>
				</params>
			</methodCall>';
		$this->getUrl($this->fullURL . 'editqueue', 'post', $header);
	}

	/**
	 * Resume a single NZB from the queue.
	 *
	 * @param string $id
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
								<value><i4>' . $id . '</i4></value>
							</array>
						</value>
					</param>
				</params>
			</methodCall>';
		$this->getUrl($this->fullURL . 'editqueue', 'post', $header);
	}

	/**
	 * Delete a single NZB from the queue.
	 *
	 * @param string $id
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
								<value><i4>' . $id . '</i4></value>
							</array>
						</value>
					</param>
				</params>
			</methodCall>';
		$this->getUrl($this->fullURL . 'editqueue', 'post', $header);
	}

	/**
	 * Set download speed limit. This method is equivalent for command "nzbget -R <Limit>".
	 *
	 * @param int $limit The speed to limit it to.
	 *
	 * @return bool
	 */
	public function rate($limit)
	{
		$header =
			'<?xml version="1.0"?>
			<methodCall>
				<methodName>rate</methodName>
				<params>
					<param>
						<value><i4>' . $limit . '</i4></value>
					</param>
				</params>
			</methodCall>';
		$this->getUrl($this->fullURL . 'rate', 'post', $header);
	}

	/**
	 * Get all items in download queue.
	 *
	 * @return array|bool
	 */
	public function getQueue()
	{
		$data = $this->getUrl($this->fullURL . 'listgroups');
		$retVal = false;
		if ($data) {
			$xml = simplexml_load_string($data);
			if ($xml) {
				$retVal = array();
				$i = 0;
				foreach($xml->params->param->value->array->data->value as $value) {
					foreach ($value->struct->member as $member) {
						$value = (array)$member->value;
						$value = array_shift($value);
						if (!is_object($value)) {
							$retVal[$i][(string)$member->name] = $value;
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
	 * @return array The status.
	 */
	public function status()
	{
		$data = $this->getUrl($this->fullURL . 'status');
		$retVal = false;
		if ($data) {
			$xml = simplexml_load_string($data);
			if ($xml) {
				foreach($xml->params->param->value->struct->member as $member) {
					$value = (array)$member->value;
					$value = array_shift($value);
					if (!is_object($value)) {
						$retVal[(string)$member->name] = $value;
					}

				}
			}
		}
		return $retVal;
	}

	/**
	 * Verify if the NZBGet URL is correct.
	 *
	 * @param string $url NZBGet URL to verify.
	 *
	 * @return bool|string
	 */
	public function verifyURL ($url)
	{
		if (preg_match('/(?P<protocol>https?):\/\/(?P<url>.+?)(:(?P<port>\d+\/)|\/)$/i', $url, $matches)) {
			return
				$matches['protocol'] .
				'://' .
				$this->userName .
				':' .
				$this->password .
				'@' .
				$matches['url'] .
				(isset($matches['port']) ? ':' . $matches['port'] : '') .
				'xmlrpc/';
		} else {
			return false;
		}
	}
}