<?php

/**
 * Attempt to include PEAR's nntp class if it has not already been included.
 */
require_once(WWW_DIR."/lib/Net_NNTP/NNTP/Client.php");
require_once(WWW_DIR."/lib/Tmux.php");
require_once(WWW_DIR."/lib/site.php");
require_once(WWW_DIR."/lib/binaries.php");
require_once(WWW_DIR."../misc/update_scripts/nix_scripts/tmux/lib/ColorCLI.php");
require_once(WWW_DIR."../misc/update_scripts/nix_scripts/tmux/lib/functions.php");

/**
 * Class for connecting to the usenet, retrieving articles and article headers,
 * decoding yEnc articles, decompressing article headers.
 * Extends PEAR's Net_NNTP_Client class, overrides some functions.
 */
class NNTP extends Net_NNTP_Client
{
     /**
	 * @var ColorCLI
	 */
	protected $c;

	/**
	 * @var Debugging
	 */
	protected $debugging;

	/**
	 * Object containing site settings.
	 *
	 * @var bool|stdClass
	 */
	protected $site;

	/**
	 * Log/echo debug?
	 * @var bool
	 */
	protected $debug;

	/**
	 * Echo to cli?
	 * @var bool
	 */
	protected $echo;

	/**
	 * Does the server support XFeature GZip header compression?
	 * @var boolean
	 */
	protected $compression = false;

	/**
	 * Currently selected group.
	 * @var string
	 */
	protected $currentGroup = '';

	/**
	 * Port of the current NNTP server.
	 * @var int
	 */
	protected $currentPort = NNTP_PORT;

	/**
	 * Address of the current NNTP server.
	 * @var string
	 */
	protected $currentServer = NNTP_SERVER;

	/**
	 * Are we allowed to post to usenet?
	 * @var bool
	 */
	protected $postingAllowed = false;

	/**
	 * How many times should we try to reconnect to the NNTP server?
	 * @var int
	 */
	protected $nntpRetries;

	/**
	 * Path to yyDecoder binary.
	 * @var bool|string
	 */
	protected $yyDecoderPath;

	/**
	 * If on unix, hide yydecode CLI output.
	 * @var string
	 */
	protected $yEncSilence;

	/**
	 * Path to temp yEnc input storage file.
	 * @var string
	 */
	protected $yEncTempInput;

	/**
	 * Path to temp yEnc output storage file.
	 * @var string
	 */
	protected $yEncTempOutput;

	/**
	 * Instance of class Site.
	 * @var object
	 * @access private
	 */
	private $s;

	/**
	 * Start an NNTP connection.
	 */
	public $XFCompression = false;

	/**
	 * Default constructor.
	 *
	 * @param bool $echo Echo to cli?
	 *
	 * @access public
	 */
	public function __construct($echo = true)
	{
		$this->echo = $echo;
		$this->c = new ColorCLI();
		$this->t = new Tmux();
        $this->s = new Sites();
        $this->site = $this->s->get();
		$this->tmux = $this->t->get();
		$this->functions = new Functions();
		$this->tmpPath = $this->site->tmpunrarpath;
		$this->nntpRetries = ((!empty($this->tmux->nntpretries)) ? (int)$this->tmux->nntpretries : 0) + 1;
		// Check if the user wants to use yydecode or the simple_php_yenc_decode extension.
		$this->yyDecoderPath = ((!empty($this->tmux->yydecoderpath)) ? $this->tmux->yydecoderpath : false);
		if ($this->yyDecoderPath === 'simple_php_yenc_decode') {
			if (extension_loaded('simple_php_yenc_decode')) {
				$this->yEncExtension = true;
			} else {
				$this->yyDecoderPath = false;
			}
		} else if ($this->yyDecoderPath !== false) {
			$this->yEncTempInput = WWW_DIR . "nzbfiles/yenc/input";
			$this->yEncTempOutput = WWW_DIR . "nzbfiles/yenc/output";
			$this->yEncSilence = ($this->functions->isWindows() ? '' : ' > /dev/null 2>&1');

			// Test if the user can read/write to the yEnc path.
			if (!is_file($this->yEncTempInput)) {
				@file_put_contents($this->yEncTempInput, 'x');
			}
			if (!is_file($this->yEncTempInput) || !is_readable($this->yEncTempInput) || !is_writable($this->yEncTempInput)) {
				$this->yyDecoderPath = false;
			}
			if (is_file($this->yEncTempInput)) {
				@unlink($this->yEncTempInput);
			}
		}
	}

	/**
	 * Default destructor, close the NNTP connection if still connected.
	 *
	 * @access public
	 */
	public function __destruct()
	{
		$this->doQuit();
	}

	/**
	 * Connect to a usenet server.
	 *
	 * @param boolean $compression Should we attempt to enable XFeature Gzip
	 *     compression on this connection?
	 *
	 *
	 * @return boolean On success = Did we successfully connect to the usenet?
	 * @return object  On failure = Pear error.
	 *
	 * @access public
	 */
	public function doConnect($compression = true)
	{
		if (// Don't reconnect to usenet if:
			// We are already connected to usenet. AND
			$this->_isConnected() &&
			// (If compression is wanted and on,                    OR    Compression is not wanted and off.) AND
			(($compression && $this->compression) || (!$compression && !$this->compression)) &&
			($this->currentServer === NNTP_SERVER)
		) {
			return true;
		} else {
			$this->doQuit();
		}

		$ret = $ret2 = $connected = $sslEnabled = $cError = $aError = false;

		// If we switched servers, reset objects.
			$sslEnabled = NNTP_SSLENABLED ? true : false;
			$this->currentServer = NNTP_SERVER;
			$this->currentPort = NNTP_PORT;
			$userName = NNTP_USERNAME;
			$password = NNTP_PASSWORD;

		$enc = ($sslEnabled ? ' (ssl)' : ' (non-ssl)');
		$sslEnabled = ($sslEnabled ? 'tls' : false);

		// Try to connect until we run of out tries.
		$retries = $this->nntpRetries;

		while (true) {
			$retries--;
			$authenticated = false;

			// If we are not connected, try to connect.
			if (!$connected) {
				 $ret = $this->connect($this->currentServer, $sslEnabled, $this->currentPort, 5);
			}

			// Check if we got an error while connecting.
			$cErr = $this->isError($ret);

			// If no error, we are connected.
			if (!$cErr) {
				// Say that we are connected so we don't retry.
				$connected = true;
				// When there is no error it returns bool if we are allowed to post or not.
				$this->postingAllowed = $ret;
			} else {
				// Only fetch the message once.
				if (!$cError) {
					$cError = $ret->getMessage();
				}
			}

			// If error, try to connect again.
			if ($cErr && $retries > 0) {
				continue;
			}

			// If we have no more retries and could not connect, return an error.
			if ($retries === 0 && !$connected) {
				$message =
					"Cannot connect to server " .
					$this->currentServer .
					$enc .
					': ' .
					$cError;
				return $this->throwError($this->c->error($message));
			}

			// If we are connected, try to authenticate.
			if ($connected === true && $authenticated === false) {

				// If the username is empty it probably means the server does not require a username.
				if ($userName === '') {
					$authenticated = true;

					// Try to authenticate to usenet.
				} else {
					$ret2 = $this->authenticate($userName, $password);

					// Check if there was an error authenticating.
					$aErr = $this->isError($ret2);

					// If there was no error, then we are authenticated.
					if (!$aErr) {
						$authenticated = true;
					} else {
						if (!$aError) {
							$aError = $ret2->getMessage();
						}
					}

					// If error, try to authenticate again.
					if ($aErr && $retries > 0) {
						continue;
					}

					// If we ran out of retries, return an error.
					if ($retries === 0 && $authenticated === false) {
						$message =
							"Cannot authenticate to server " .
							$this->currentServer .
							$enc .
							' - ' .
							$userName .
							' (' .
							$aError .
							')';
						return $this->throwError($this->c->error($message));
					}
				}
			}

			// If we are connected and authenticated, try enabling compression if we have it enabled.
			if ($connected === true && $authenticated === true) {
				// Try to enable compression.
				if ($compression === true && $this->site->compressedheaders === '1') {
					$this->_enableCompression();
				}
				return true;
			}
			// If we reached this point and have not connected after all retries, break out of the loop.
			if ($retries === 0) {
				break;
			}

			// Sleep .4 seconds between retries.
			usleep(400000);
		}
		// If we somehow got out of the loop, return an error.
		$message = 'Unable to connect to ' . $this->currentServer . $enc;
		return $this->throwError($this->c->error($message));
	}

	/**
	 * Disconnect from the current NNTP server.
	 *
	 * @param  bool $force Force quit even if not connected?
	 *
	 * @return bool   On success : Did we successfully disconnect from usenet?
	 * @return object On Failure : Pear error.
	 *
	 * @access public
	 */
	public function doQuit($force = false)
	{
		$this->resetProperties();

		// Check if we are connected to usenet.
		if ($force === true || parent::_isConnected()) {
			// Disconnect from usenet.
			return parent::disconnect();
		}
		return true;
	}

	/**
	 * Reset some properties when disconnecting from usenet.
	 */
	protected function resetProperties()
	{
		$this->compression = false;
		$this->currentGroup = '';
		$this->postingAllowed = false;
		parent::resetProperties();
	}

	/**
	 * @param string $group    Name of the group to select.
	 * @param bool   $articles (optional) experimental! When true the article numbers is returned in 'articles'.
	 * @param bool   $force    Force a refresh.
	 *
	 * @return array|object
	 */
	public function selectGroup($group, $articles = false, $force = false)
	{
		$connected = $this->checkConnection(false);
		if ($connected !== true) {
			return $connected;
		}

		if ($force || $this->currentGroup !== $group || is_null($this->_selectedGroupSummary)) {
			$this->currentGroup = $group;
			return parent::selectGroup($group, $articles);
		} else {
			return $this->_selectedGroupSummary;
		}
	}

	/**
	 * Fetch an overview of article(s) in the currently selected group.
	 *
	 * @param null $range
	 * @param bool $names
	 * @param bool $forceNames
	 *
	 * @return mixed
	 *
	 * @access public
	 */
	public function getOverview($range = null, $names = true, $forceNames = true)
	{
		$connected = $this->checkConnection();
		if ($connected !== true) {
			return $connected;
		}

		return parent::getOverview($range, $names, $forceNames);
	}

	/**
	 * Download an article body (an article without the header).
	 *
	 * @param string $groupName The name of the group the article is in.
	 * @param string/int $identifier (String)The message-ID of the article to download. or (Int) The article number.
	 *
	 * @return string On success : The article's body.
	 * @return object On failure : Pear error.
	 *
	 * @access public
	 */
	public function getMessage($groupName, $identifier)
	{
		// Make sure the requested group is already selected, if not select it.
		if (parent::group() !== $groupName) {
			// Select the group.
			$summary = $this->selectGroup($groupName);
			// If there was an error selecting the group, return PEAR error object.
			if ($this->isError($summary)) {
				return $summary;
			}
		}

		// Check if this is an article number or message-id.
		if (!is_numeric($identifier)) {
			// It's a message-id so check if it has the triangular brackets.
			$identifier = $this->formatMessageID($identifier);
		}

		// Download the article body from usenet.
		$body = parent::getBody($identifier, true);
		// If there was an error, return the PEAR error object.
		if ($this->isError($body)) {
			return $body;
		}

		// Attempt to yEnc decode and return the body.
		return $this->_decodeYEnc($body);
	}

	/**
	 * Download multiple article bodies and string them together.
	 *
	 * @param string $groupName The name of the group the articles are in.
	 * @param array|string|int $identifiers Message-ID(string) or article number(int), or array containing M-ID's or A-Numbers.
	 *
	 *
	 * @return string On success : The article bodies.
	 * @return object On failure : Pear error.
	 *
	 * @access public
	 */
	public function getMessages($groupName, $identifiers)
	{
		$connected = $this->checkConnection();
		if ($connected !== true) {
			return $connected;
		}

		// String to hold all the bodies.
		$body = '';

		// Check if the msgIds are in an array.
		if (is_array($identifiers)) {

			$iCount = count($identifiers);
			$iDents = 0;

			// Loop over the message-ID's or article numbers.
			foreach ($identifiers as $wanted) {
				// Download the body.
				$message = $this->getMessage($groupName, $wanted);

				// Append the body to $body.
				if (!$this->isError($message)) {
					$body .= $message;
					unset($message);
					// If there is an error return the PEAR error object.
				} else {
						// If we got some data, return it.
						if ($body !== '') {
							return $body;
						// Try until we possibly find data.
						} elseif ($iCount > $iDents) {
							continue;
						}
						return $message;
				}
			}

			// If it's a string check if it's a valid message-ID.
		} else if (is_string($identifiers) || is_numeric($identifiers)) {
			$body = $this->getMessage($groupName, $identifiers);
			// Else return an error.
		} else {
			$message = 'Wrong Identifier type, array, int or string accepted. This type of var was passed: ' . gettype($identifiers);
			return $this->throwError($this->c->error($message));
        }

		return $body;
	}

	/**
	 * Download a full article, the body and the header, return an array with named keys and their
	 * associated values, optionally decode the body using yEnc.
	 *
	 * @param string $groupName The name of the group the article is in.
	 * @param string/int $identifier (String)The message-ID of the article to download. or (Int) The article number.
	 * @param bool   $yEnc      Attempt to yEnc decode the body.
	 *
	 * @return array  On success : The article.
	 * @return object On failure : Pear error.
	 *
	 * @access public
	 */
	public function get_Article($groupName, $identifier, $yEnc = false)
	{
		$connected = $this->checkConnection();
		if ($connected !== true) {
			return $connected;
		}

		// Make sure the requested group is already selected, if not select it.
		if (parent::group() !== $groupName) {
			// Select the group.
			$summary = $this->selectGroup($groupName);
			// If there was an error selecting the group, return PEAR error object.
			if ($this->isError($summary)) {
				return $summary;
			}
		}

		// Check if it's an article number or message-ID.
		if (!is_numeric($identifier)) {
			// If it's a message-ID, check if it has the required triangular brackets.
			$identifier = $this->formatMessageID($identifier);
		}

		// Download the article.
		$article = parent::getArticle($identifier);
		// If there was an error downloading the article, return a PEAR error object.
		if ($this->isError($article)) {
			return $article;
		}

		$ret = $article;
		// Make sure the article is an array and has more than 1 element.
		if (sizeof($article) > 0) {
			$ret = array();
			$body = '';
			$emptyLine = false;
			foreach ($article as $line) {
				// If we found the empty line it means we are done reading the header and we will start reading the body.
				if (!$emptyLine) {
					if ($line === "") {
						$emptyLine = True;
						continue;
					}

					// Use the line type of the article as the array key (From, Subject, etc..).
					if (preg_match('/([A-Z-]+?): (.*)/i', $line, $matches)) {
						// If the line type takes more than 1 line, append the rest of the content to the same key.
						if (array_key_exists($matches[1], $ret)) {
							$ret[$matches[1]] = $ret[$matches[1]] . $matches[2];
						} else {
							$ret[$matches[1]] = $matches[2];
						}
					}

					// Now we have the header, so get the body from the rest of the lines.
				} else {
					$body = $body . $line;
				}
			}
			// Finally we decode the message using yEnc.
			$ret['Message'] = $yEnc ? $this->_decodeYEnc($body) : $body;
		}
		return $ret;
	}

	/**
	 * Download a full article header.
	 *
	 * @param string $groupName The name of the group the article is in.
	 * @param string/int $identifier (String)The message-ID of the article to download. or (Int) The article number.
	 *
	 * @return array The header.
	 *
	 * @access public
	 */
	public function get_Header($groupName, $identifier)
	{
		$connected = $this->checkConnection();
		if ($connected !== true) {
			return $connected;
		}

		// Make sure the requested group is already selected, if not select it.
		if (parent::group() !== $groupName) {
			// Select the group.
			$summary = $this->selectGroup($groupName);
			// Return PEAR error object on failure.
			if ($this->isError($summary)) {
				return $summary;
			}
		}

		// Check if it's an article number or message-id.
		if (!is_numeric($identifier)) {
			// Verify we have the required triangular brackets if it is a message-id.
			$identifier = $this->formatMessageID($identifier);
		}

		// Download the header.
		$header = parent::getHeader($identifier);
		// If we failed, return PEAR error object.
		if ($this->isError($header)) {
			return $header;
		}

		$ret = $header;
		if (sizeof($header) > 0) {
			$ret = array();
			// Use the line types of the header as array keys (From, Subject, etc).
			foreach ($header as $line) {
				if (preg_match('/([A-Z-]+?): (.*)/i', $line, $matches)) {
					// If the line type takes more than 1 line, re-use the same array key.
					if (array_key_exists($matches[1], $ret)) {
						$ret[$matches[1]] = $ret[$matches[1]] . $matches[2];
					} else {
						$ret[$matches[1]] = $matches[2];
					}
				}
			}
		}
		return $ret;
	}

	/**
	 * Post an article to usenet.
	 *
	 * @param $groups   array/string (Array) Array of groups. or (String) Single group.
	 *                                 ex.: (Array)  $groups = array('alt.test', 'alt.binaries.testing');
	 *                                 ex.: (String) $groups = 'alt.test';
	 * @param $subject  string       The subject.
	 *                                 ex.: $subject = 'Test article';
	 * @param $body     string       The message.
	 *                                 ex.: $message = 'This is only a test, please disregard.';
	 * @param $from     string       The person who is posting (must be in email format).
	 *                                 ex.: $from = '<anon@anon.com>';
	 * @param $extra    string       Extra stuff, separated by \r\n
	 *                                 ex.: $extra  = 'Organization: <nZEDb>\r\nNNTP-Posting-Host: <127.0.0.1>';
	 * @param $yEnc     bool         Encode the message with yEnc?
	 * @param $compress bool         Compress the message with GZip.
	 *
	 * @return          bool/object  True on success, Pear error on failure.
	 *
	 * @access public
	 */
	public function postArticle($groups, $subject, $body, $from, $yEnc = true, $compress = true, $extra = '')
	{
		if (!$this->postingAllowed) {
			$message = 'You do not have the right to post articles on server ' . $this->currentServer;
			return $this->throwError($this->c->error($message));
		}

		$connected = $this->checkConnection();
		if ($connected !== true) {
			return $connected;
		}

		// Throw errors if subject or from are more than 510 chars.
		if (strlen($subject) > 510) {
			$message = 'Max length of subject is 510 chars.';
			return $this->throwError($this->c->error($message));
		}

		if (strlen($from) > 510) {
			$message = 'Max length of from is 510 chars.';
			return $this->throwError($this->c->error($message));
		}

		// Check if the group is string or array.
		if (is_array(($groups))) {
			$groups = implode(', ', $groups);
		}

			// Check if we should encode to yEnc.
		if ($yEnc) {
			$body = $this->encodeYEnc(($compress ? gzdeflate($body, 4) : $body), $subject);
		// If not yEnc, then check if the body is 510+ chars, split it at 510 chars and separate with \r\n
		} else {
			$body = $this->splitLines($body, $compress);
		}

		// From is required by NNTP servers, but parent function mail does not require it, so format it.
		$from = 'From: ' . $from;
		// If we had extra stuff to post, format it with from.
		if ($extra != '') {
			$from = $from . "\r\n" . $extra;
		}

		return parent::mail($groups, $subject, $body, $from);
	}

	/**
	 * Restart the NNTP connection if an error occurs in the selectGroup
	 * function, if it does not restart display the error.
	 *
	 * @param object $nntp  Instance of class NNTP.
	 * @param string $group Name of the group.
	 * @param boolean $comp Use compression or not?
	 *
	 * @return array   On success : The group summary.
	 * @return object  On Failure : Pear error.
	 *
	 * @access public
	 */
	public function dataError($nntp, $group, $comp = true)
	{
		// Disconnect.
		$nntp->doQuit();
		// Try reconnecting. This uses another round of max retries.
		if ($nntp->doConnect($comp) !== true) {
			return false;
		}

		// Try re-selecting the group.
		$data = $nntp->selectGroup($group);
		if ($this->isError($data)) {
			$message = "Code {$data->code}: {$data->message}\nSkipping group: {$group}";

			if ($this->echo) {
				$this->c->doEcho($this->c->error($message), true);
			}
			$nntp->doQuit();
		}
		return $data;
	}

	/**
	 * Override PEAR NNTP's function to use our _getXFeatureTextResponse instead
	 * of their _getTextResponse function since it is incompatible at decoding
	 * headers when XFeature GZip compression is enabled server side.
	 *
	 * @note Overrides parent function.
	 * @note Function can not be protected because parent function is public.
	 *
	 * @return self    Our overridden function when compression is enabled.
	 * @return parent  Parent function when no compression.
	 *
	 * @access public
	 */
	public function _getTextResponse()
	{
		if ($this->compression === true &&
			isset($this->_currentStatusResponse[1]) &&
			stripos($this->_currentStatusResponse[1], 'COMPRESS=GZIP') !== false) {

			return $this->_getXFeatureTextResponse();
		} else {
			return parent::_getTextResponse();
		}
	}

	/**
	 * Decode a string of text encoded with yEnc. Ignores all errors.
	 *
	 * @param  string $data The encoded text to decode.
	 *
	 * @return string The decoded yEnc string, or the input string, if it's not yEnc.
	 */
	protected function _decodeIgnoreYEnc($data)
	{
		if (preg_match('/^(=yBegin.*=yEnd[^$]*)$/ims', $data, $input)) {
			// If there user has no yyDecode path set, use PHP to decode yEnc.
			if ($this->yyDecoderPath === false) {
				$data = '';
				$input =
					trim(
						preg_replace(
							'/\r\n/im', '',
							preg_replace(
								'/(^=yEnd.*)/im', '',
								preg_replace(
									'/(^=yPart.*\\r\\n)/im', '',
									preg_replace(
										'/(^=yBegin.*\\r\\n)/im', '',
										$input[1],
										1),
									1),
								1)
						)
					);

				$length = strlen($input);
				for ($chr = 0; $chr < $length; $chr++) {
					$data .= ($input[$chr] !== '=' ? chr(ord($input[$chr]) - 42) : chr((ord($input[++$chr]) - 64) - 42));
				}
			} elseif ($this->yEncExtension) {
				$data = simple_yenc_decode($input[1]);
			} else {
				$inFile = $this->yEncTempInput . mt_rand(0, 999999);
				$ouFile = $this->yEncTempOutput . mt_rand(0, 999999);
				file_put_contents($inFile, $input[1]);
				file_put_contents($ouFile, '');
				$this->functions->runCmd(
					"'" .
					$this->yyDecoderPath .
					"' '" .
					$inFile .
					"' -o '" .
					$ouFile .
					"' -f -b" .
					$this->yEncSilence
				);
				$data = file_get_contents($ouFile);
				if ($data === false) {
					return $this->throwError('Error getting data from yydecode.');
				}
				unlink($inFile);
				unlink($ouFile);
			}
		}
		return $data;
	}

	/**
	 * Loop over the compressed data when XFeature GZip Compress is turned on,
	 * string the data until we find a indicator
	 * (period, carriage feed, line return ;; .\r\n), decompress the data,
	 * split the data (bunch of headers in a string) into an array, finally
	 * return the array.
	 *
	 * @return string/print Have we failed to decompress the data, was there a
	 *                 problem downloading the data, etc..

	 * @return array  On success : The headers.
	 * @return object On failure : Pear error.
	 *
	 * @access protected
	 */
	protected function _getXFeatureTextResponse()
	{
		$tries = $bytesReceived = $totalBytesReceived = 0;
		$completed = $possibleTerm = false;
		$data = $buffer = null;

		while (!feof($this->_socket)) {
			// Reset only if decompression has not failed.
			if ($tries === 0) {
				$completed = false;
			}

			// Did we find a possible ending ? (.\r\n)
			if ($possibleTerm !== false) {

				// If the socket is really empty, fGets will get stuck here,
				// so set the socket to non blocking in case.
				stream_set_blocking($this->_socket, 0);

				// Now try to download from the socket.
				$buffer = fgets($this->_socket);

				// And set back the socket to blocking.
				stream_set_blocking($this->_socket, 15);

				// If the buffer was really empty, then we know $possibleTerm
				// was the real ending.
				if (empty($buffer)) {
					$completed = true;

					// The buffer was not empty, so we know this was not
					// the real ending, so reset $possibleTerm.
				} else {
					$possibleTerm = false;
				}
			} else {
				// Don't try to re-download from the socket if decompression failed.
				if ($tries === 0) {
					// Get data from the stream.
					$buffer = fgets($this->_socket);
				}
			}

			// We found a ending, try to decompress the full buffer.
			if ($completed === true) {
				$deComp = @gzuncompress(mb_substr($data, 0, -3, '8bit'));
				// Split the string of headers into an array of individual headers, then return it.
				if (!empty($deComp)) {

					if ($this->echo && $totalBytesReceived > 10240) {
						$this->c->doEcho(
							$this->c->primaryOver(
								'Received ' .
								round($totalBytesReceived / 1024) .
								'KB from group (' .
								$this->group() .
								")."
							), true
						);
					}

					// Return array of headers.
					return explode("\r\n", trim($deComp));
				} else {
					// Try 5 times to decompress.
					if ($tries++ > 5) {
						$message = 'Decompression Failed after 5 tries.';
						return $this->throwError($this->c->error($message), 1000);
					}
					// Skip the loop to try decompressing again.
					continue;
				}
			}

			// Get byte count.
			$bytesReceived = strlen($buffer);

			// If we got no bytes at all try one more time to pull data.
			if ($bytesReceived === 0) {
				$buffer = fgets($this->_socket);
				$bytesReceived = strlen($buffer);
			}

			// If the buffer is zero it's zero, return error.
			if ($bytesReceived === 0) {
				$message = 'The NNTP server has returned no data.';
				return $this->throwError($this->c->error($message), 1000);
			}

			// Append buffer to final data object.
			$data .= $buffer;

			// Update total bytes received.
			$totalBytesReceived += $bytesReceived;

			// Check if we have the ending (.\r\n)
			if ($bytesReceived > 2 &&
				ord($buffer[$bytesReceived - 3]) == 0x2e &&
				ord($buffer[$bytesReceived - 2]) == 0x0d &&
				ord($buffer[$bytesReceived - 1]) == 0x0a) {

				// We have a possible ending, next loop check if it is.
				$possibleTerm = true;
				continue;
			}
		}
		// Throw an error if we get out of the loop.
		if (!feof($this->_socket)) {
			$message = "Error: Could not find the end-of-file pointer on the gzip stream.";
			return $this->throwError($this->c->error($message), 1000);
		}

		$message = 'Decompression Failed, connection closed.';
		return $this->throwError($this->c->error($message), 1000);
	}

   /**
	 * Check if we are still connected. Reconnect if not.
	 *
	 * @param  bool $reSelectGroup Select back the group after connecting?
	 *
	 * @return mixed On success: (bool)   True;
	 *               On failure: (object) PEAR_Error>
	 */
	protected function checkConnection($reSelectGroup = true)
	{
		$currentGroup = $this->currentGroup;
		// Check if we are connected.
		if (parent::_isConnected()) {
			$retVal = true;
		} else {
			switch($this->currentServer) {
				case NNTP_SERVER:
					if (is_resource($this->_socket)) {
						$this->doQuit(true);
					}
					$retVal = $this->doConnect();
					break;
				default:
					$retVal = $this->throwError('Wrong server constant used in NNTP checkConnection()!');
			}
			if ($retVal === true && $reSelectGroup){
				$group = $this->selectGroup($currentGroup);
				if ($this->isError($group)) {
					$retVal = $group;
				}
			}
		}
		return $retVal;
	}

	/**
	 * Decode a string of text encoded with yEnc.
	 *
	 * @note     For usage outside of this class, please use the YEnc library.
	 *
	 * @param $data
	 *
	 * @internal param string $string The encoded text to decode.
	 *
	 * @return string  The decoded yEnc string, or the input, if it's not yEnc.
	 *
	 * @access   protected
	 *
	 * @TODO     : ? Maybe this function should be merged into the YEnc class?
	 */
   protected function _decodeYEnc($data)
	{
		if (preg_match('/^(=yBegin.*=yEnd[^$]*)$/ims', $data, $input)) {
			// If there user has no yyDecode path set, use PHP to decode yEnc.
			if ($this->yyDecoderPath === false) {
				$data = '';
				$input =
					trim(
						preg_replace(
							'/\r\n/im', '',
							preg_replace(
								'/(^=yEnd.*)/im', '',
								preg_replace(
									'/(^=yPart.*\\r\\n)/im', '',
									preg_replace(
										'/(^=yBegin.*\\r\\n)/im', '',
										$input[1],
									1),
								1),
							1)
						)
					);

				$length = strlen($input);
				for ($chr = 0; $chr < $length; $chr++) {
					$data .= ($input[$chr] !== '=' ? chr(ord($input[$chr]) - 42) : chr((ord($input[++$chr]) - 64) - 42));
				}
			} else {
				$inFile = $this->yEncTempInput . mt_rand(0, 999999);
				$ouFile = $this->yEncTempOutput . mt_rand(0, 999999);
				file_put_contents($inFile, $input[1]);
				file_put_contents($ouFile, '');
				$this->functions->runCmd(
					"'" .
					$this->yyDecoderPath .
					"' '" .
					$inFile .
					"' -o '" .
					$ouFile .
					"' -f -b" .
					$this->yEncSilence
				);
				$data = file_get_contents($ouFile);
				unlink($inFile);
				unlink($ouFile);
			}
		}
		return $data;
	}

	/**
	 * Check if the Message-ID has the required opening and closing brackets.
	 *
	 * @param  string $messageID The Message-ID with or without brackets.
	 *
	 * @return string            Message-ID with brackets.
	 *
	 * @access protected
	 */
	protected function formatMessageID($messageID)
	{
		// Check if the first char is <, if not add it.
		if ($messageID[0] !== '<') {
			$messageID = '<' . $messageID;
		}

		// Check if the last char is >, if not add it.
		if (substr($messageID, -1) !== '>') {
			$messageID = $messageID . '>';
		}
		return $messageID;
	}

	/**
	 * Split a string into lines of 510 chars ending with \r\n.
	 * Usenet limits lines to 512 chars, with \r\n that leaves us 510.
	 *
	 * @param string $string   The string to split.
	 * @param bool   $compress Compress the string with gzip?
	 *
	 * @return string The split string.
	 *
	 * @access protected
	 */
	protected function splitLines($string, $compress = false)
	{
		// Check if the length is longer than 510 chars.
		if (strlen($string) > 510) {
			// If it is, split it @ 510 and terminate with \r\n.
			$string = chunk_split($string, 510, "\r\n");
		}

		// Compress the string if requested.
		return ($compress ? gzdeflate($string, 4) : $string);
	}

	/**
	 * Try to see if the NNTP server implements XFeature GZip Compression,
	 * change the compression bool object if so.
	 *
	 * @note Based on this script : http://pastebin.com/A3YypDAJ
	 *
	 * @return boolean On success : The server understood and compression is enabled.
	 * @return object  On failure : Pear error.
	 * @return int     On failure : Response code. (should be 500)
	 *
	 * @access protected
	 */
	protected function _enableCompression()
	{
		// Send this command to the usenet server.
		$response = $this->_sendCommand('XFEATURE COMPRESS GZIP');

		// Check if it's good.
		if ($this->isError($response)) {
			return $response;
		} else if ($response !== 290) {
			$msg = "XFeature GZip Compression not supported. Consider disabling compression in site settings.";

			if ($this->echo) {
				$this->c->doEcho($this->c->error($msg), true);
			}
			return $response;
		}

		$this->compression = true;
		return true;
	}

	/**
    *
	* Added from original nntp for compatibility reasons
    *
	*/

	/**
     * Retrieve blob
     * Get data and assume we do not hit any blindspots
     * @return mixed (array) text response on success or (object) pear_error on failure
     * @access private
     */
    function _getCompressedResponse()
    {
        $data = array();

		// We can have two kinds of compressed support:
		// - yEnc encoding
		// - Just a gzip drop
		// We try to autodetect which one this uses

		$line = @fread($this->_socket, 1024);
		if (substr($line, 0, 7) == '=ybegin') {
			$data = $this->_getTextResponse();
			$data = $line . "\r\n" . implode("", $data);
   	    	$data = $this->yencDecode($data);
			$data = explode("\r\n", gzinflate($data));
			return $data;
		}
		// We cannot use blocked I/O on this one
		$streamMetadata = stream_get_meta_data($this->_socket);
		stream_set_blocking($this->_socket, false);

        // Continue until connection is lost or we don't receive any data anymore
		$tries = 0;
		$uncompressed = '';
        while (!feof($this->_socket)) {

            # Retrieve and append up to 32k characters from the server
            $received = @fread($this->_socket, 32768);
			if (strlen($received) == 0) {
				$tries++;
				# Try decompression
				$uncompressed = @gzuncompress($line);
				if (($uncompressed !== false) || ($tries > 500)) {
					break;
				}
				if ($tries % 50 == 0) {
				}
			}
			# an error occured
			if ($received === false) {
				@fclose($this->_socket);
				$this->_socket = false;
			}
            $line .= $received;
        }
		# and set the stream to its original blocked(?) value
		stream_set_blocking($this->_socket, $streamMetadata['blocked']);
		$data = explode("\r\n", $uncompressed);
		$dataCount = count($data);

		# Gzipped compress includes the "." and linefeed in the compressed stream, skip those.
		if ($dataCount >= 2) {
			if (($data[($dataCount - 2)] == ".") && (empty($data[($dataCount - 1)]))) {
				array_pop($data);
				array_pop($data);
			}
			$data = array_filter($data);
		}
		return $data;
    }


	/**
	 * Enable XFeature compression support for the current connection.
	 */
	function enableXFCompression()
	{
		$response = $this->_sendCommand('XFEATURE COMPRESS GZIP');

		if (PEAR::isError($response) || $response != 290) {
			//echo "Xfeature compression not supported!\n";
			return false;
		}

		$this->XFCompression = true;
		//echo "XFeature compression enabled\n";
		return true;
	}

	function _getXFCompressedTextResponse()
	{
		$tries 				= 0;
		$bytesreceived 		= 0;
		$totalbytesreceived = 0;
		$completed			= false;
		$data 				= null;
		//build binary array that represents zero results basically a compressed empty string terminated with .(period) char(13) char(10)
		 $emptyreturnend 	= chr(0x03).chr(0x00).chr(0x00).chr(0x00).chr(0x00).chr(0x01).chr(0x2e).chr(0x0d).chr(0x0a);
		 $emptyreturn  		= chr(0x78).chr(0x9C).$emptyreturnend;
		 $emptyreturn2 		= chr(0x78).chr(0x01).$emptyreturnend;
		 $emptyreturn3 		= chr(0x78).chr(0x5e).$emptyreturnend;
		 $emptyreturn4 		= chr(0x78).chr(0xda).$emptyreturnend;

		while (!feof($this->_socket))
		{
			$completed = false;
			//get data from the stream
			 $buffer = fgets($this->_socket);
			 //get byte count and update total bytes
			 $bytesreceived = strlen($buffer);
			 //if we got no bytes at all try one more time to pull data.
			 if ($bytesreceived == 0)
			 {
				$buffer = fgets($this->_socket);
			 }
			//get any socket error codes
			$errorcode = 0;
			if (function_exists("socket_last_error"))
				$errorcode = socket_last_error();

			//if the buffer is zero its zero...
			if ($bytesreceived === 0)
				return $this->throwError('No data returned.', 1000);
			//did we have any socket errors?
			 if ($errorcode === 0)
			 {
				//append buffer to final data object
				 $data .= $buffer;
				 $totalbytesreceived = $totalbytesreceived+$bytesreceived;

				 //output byte count in real time once we have 1MB of data
				if ($totalbytesreceived > 10240)
				if ($totalbytesreceived%128 == 0)
				{
					//echo "bytes received: ";
					//echo $totalbytesreceived;
					//echo "\r";
				}

				//check to see if we have the magic terminator on the byte stream
				$b1 = null;
				if ($bytesreceived > 2)
				if (ord($buffer[$bytesreceived-3]) == 0x2e && ord($buffer[$bytesreceived-2]) == 0x0d && ord($buffer[$bytesreceived-1]) == 0x0a)//substr($buffer,-3) == ".\r\n"
				{
					//check to see if the returned binary string is 11 bytes long generally and indcator
					//of an compressed empty string probably don't need this check
					if ($totalbytesreceived==11)
					{
						//compare the data to the empty string if the data is a compressed empty string
						//throw an error else return the data
						if (($data === $emptyreturn)||($data === $emptyreturn2)||($data === $emptyreturn3)||($data === $emptyreturn4))
						{
							echo "empty gzip stream\n";
							return $this->throwError('No data returned.', 1000);
						}
					}
					else
					{
						//echo "\n";
						$completed = true;
					}
				}
			 }
			 else
			 {
				 echo "failed to read from socket\n";
				 return $this->throwError('Failed to read line from socket.', 1000);
			 }

			if ($completed)
			{
				//check to see if the header is valid for a gzip stream
				if(ord($data[0]) == 0x78 && in_array(ord($data[1]),array(0x01,0x5e,0x9c,0xda)))
				{
					$decomp = @gzuncompress(mb_substr ( $data , 0 ,-3, '8bit' ));
				}
				else
				{
					echo "Invalid header on gzip stream.\n";
					return $this->throwError('Invalid gzip stream.', 1000);
				}

				if ($decomp != false)
				{
					$decomp = explode("\r\n", trim($decomp));
					return $decomp;
				}
				else
				{
					$tries++;
					echo "Decompression Failed Retry Number: $tries \n";
				}
			}
		}
		//throw an error if we get out of the loop
		if (!feof($this->_socket))
		{
			return "Error: unexpected fgets() fail\n";
		}
		return $this->throwError('Decompression Failed, connection closed.', 1000);
	}

	/**
	 * Fetch message header from message number $first until $last
	 * The format of the returned array is:
	 * $messages[message_id][header_name]
	 *
	 * @param null $range
	 *
	 * @internal param string $optional $range articles to fetch
	 * @return mixed (array) nested array of message and there headers on success or (object) pear_error on failure
	 * @access   protected
	 */
	function cmdXZver($range = null)
	{
        if (is_null($range))
			$command = 'XZVER';
    	else
    	    $command = 'XZVER ' . $range;
        $response = $this->_sendCommand($command);

    	switch ($response) {
    	    case 224: // RFC2980: 'Overview information follows'
				$data = $this->_getCompressedResponse();
    	        foreach ($data as $key => $value)
    	            $data[$key] = explode("\t", trim($value));

    	    	return $data;
    	    	break;
    	    case 412: // RFC2980: 'No news group current selected'
    	    	return $this->throwError('No news group current selected', $response, $this->_currentStatusResponse());
    	    	break;
    	    case 420: // RFC2980: 'No article(s) selected'
    	    	return $this->throwError('No article(s) selected', $response, $this->_currentStatusResponse());
    	    	break;
    	    case 502: // RFC2980: 'no permission'
    	    	return $this->throwError('No permission', $response, $this->_currentStatusResponse());
    	    	break;
	    	case 500: // RFC2980: 'unknown command'
	        	$this->throwError("XZver not supported ({$this->_currentStatusResponse()})", $response);
	       		break;
    	    default:
    	    	return $this->_handleUnexpectedResponse($response);
    	}
    }

	/**
	 * yDecodes an encoded string and either writes the result to a file or returns it as a string.
	 *
	 * @param string $string yEncoded string to decode.
	 *
	 * @return mixed On success: (string) The decoded string.
	 *               On failure: (object) PEAR_Error.
	 */
	public function decodeYEnc($string)
	{
		$encoded = $crc = '';
		// Extract the yEnc string itself.
		if (preg_match("/=ybegin.*size=([^ $]+).*\\r\\n(.*)\\r\\n=yend.*size=([^ $\\r\\n]+)(.*)/ims", $string, $encoded)) {
			if (preg_match('/crc32=([^ $\\r\\n]+)/ims', $encoded[4], $trailer)) {
				$crc = trim($trailer[1]);
			}
			$headerSize = $encoded[1];
			$trailerSize = $encoded[3];
			$encoded = $encoded[2];

		} else {
			return false;
		}

		// Remove line breaks from the string.
		$encoded = trim(str_replace("\r\n", '', $encoded));

		// Make sure the header and trailer file sizes match up.
		if ($headerSize != $trailerSize) {
			$message = 'Header and trailer file sizes do not match. This is a violation of the yEnc specification.';

			return $this->throwError($message);
		}

		// Decode.
		$decoded = '';
		$encodedLength = strlen($encoded);
		for ($chr = 0; $chr < $encodedLength; $chr++) {
			$decoded .= ($encoded[$chr] !== '=' ? chr(ord($encoded[$chr]) - 42) : chr((ord($encoded[++$chr]) - 64) - 42));
		}

		// Make sure the decoded file size is the same as the size specified in the header.
		if (strlen($decoded) != $headerSize) {
			$message = 'Header file size and actual file size do not match. The file is probably corrupt.';

			return $this->throwError($message);
		}

		// Check the CRC value
		if ($crc !== '' && (strtolower($crc) !== strtolower(sprintf("%04X", crc32($decoded))))) {
			$message = 'CRC32 checksums do not match. The file is probably corrupt.';

			return $this->throwError($message);
		}

		return $decoded;
	}

   	/**
	 * yEncodes a string and returns it.
	 *
	 * @param string $string     String to encode.
	 * @param string $filename   Name to use as the filename in the yEnc header (this does not have to be an actual file).
	 * @param int    $lineLength Line length to use (can be up to 254 characters).
	 * @param bool   $crc32      Pass True to include a CRC checksum in the trailer to allow decoders to verify data integrity.
	 *
	 * @return mixed On success: (string) yEnc encoded string.
	 *               On failure: (bool)   False.
	 */
	public function encodeYEnc($string, $filename, $lineLength = 128, $crc32 = true)
	{
		// yEnc 1.3 draft doesn't allow line lengths of more than 254 bytes.
		if ($lineLength > 254) {
			$lineLength = 254;
		}

		if ($lineLength < 1) {
			$message = $lineLength . ' is not a valid line length.';
			return $this->throwError($message);
		}

		$encoded = '';
		$stringLength = strlen($string);
		// Encode each character of the string one at a time.
		for ($i = 0; $i < $stringLength; $i++) {
			$value = ((ord($string{$i}) + 42) % 256);


			// Escape NULL, TAB, LF, CR, space, . and = characters.
			if ($value == 0 || $value == 9 || $value == 10 || $value == 13 || $value == 32 || $value == 46 || $value == 61) {
				$encoded .= ('=' . chr(($value + 64) % 256));
			} else {
				$encoded .= chr($value);
			}
		}

		$encoded =
			'=ybegin line=' .
			$lineLength .
			' size=' .
			$stringLength .
			' name=' .
			trim($filename) .
			"\r\n" .
			trim(chunk_split($encoded, $lineLength)) .
			"\r\n=yend size=" .
			$stringLength;

		// Add a CRC32 checksum if desired.
		if ($crc32 === true) {
			$encoded .= ' crc32=' . strtolower(sprintf("%04X", crc32($string)));
		}

		return $encoded . "\r\n";
	}

	// }}}
    // {{{ cmdXZver()
	/*
	 * Based on code from http://wonko.com/software/yenc/, but
	 * simplified because XZVER and the likes don't implement
	 * yenc properly
	 */
	/**
	 * @param        $string
	 * @param string $destination
	 *
	 * @return string
	 * @throws Exception
	 */
	private function yencDecode($string, $destination = "") {
		$encoded = array();
		$header = array();
		$decoded = '';

		# Extract the yEnc string itself
		preg_match("/^(=ybegin.*=yend[^$]*)$/ims", $string, $encoded);
		$encoded = $encoded[1];

		# Extract the filesize and filename from the yEnc header
		preg_match("/^=ybegin.*size=([^ $]+).*name=([^\\r\\n]+)/im", $encoded, $header);
		$filesize = $header[1];
		$filename = $header[2];

		# Remove the header and footer from the string before parsing it.
		$encoded = preg_replace("/(^=ybegin.*\\r\\n)/im", "", $encoded, 1);
		$encoded = preg_replace("/(^=yend.*)/im", "", $encoded, 1);

		# Remove linebreaks and whitespace from the string
		$encoded = trim(str_replace("\r\n", "", $encoded));

		// Decode
		$strLength = strlen($encoded);
		for($i = 0; $i < $strLength; $i++) {
			$c = $encoded[$i];

			if ($c == '=') {
				$i++;
				$decoded .= chr((ord($encoded[$i]) - 64) - 42);
			} else {
				$decoded .= chr(ord($c) - 42);
			}
		}
		// Make sure the decoded filesize is the same as the size specified in the header.
		if (strlen($decoded) != $filesize) {
			throw new Exception("Filesize in yEnc header en filesize found do not match up");
		}
		return $decoded;
	}

		/**
	 * Retrieve all NNTP messages associated with a binaries.ID
	 */
	function getBinary($binaryId, $isNfo=false)
	{
		$db = new DB();
		$bin = new Binaries();

		$binary = $bin->getById($binaryId);
		if (!$binary)
		{
            printf("NntpPrc: Unable to locate binary: %s\n", $binaryId);
			return false;
        }

		$summary = $this->selectGroup($binary['groupname']);
		$message = $dec = '';

		if (PEAR::isError($summary))
		{
			echo "NntpPrc : ".substr($summary->getMessage(), 0, 100)."\n";
			return false;
		}

		$resparts = $db->query(sprintf("SELECT size, partnumber, messageID FROM parts WHERE binaryID = %d ORDER BY partnumber", $binaryId));

		//
		// Dont attempt to download nfos which are larger than one part.
		//
		if (sizeof($resparts) > 1 && $isNfo === true)
		{
			//echo 'NntpPrc : Error Nfo is too large... skipping.\n';
			return false;
		}

		foreach($resparts as $part)
		{
			$messageID = '<'.$part['messageID'].'>';
			$body = $this->getBody($messageID, true);
			if (PEAR::isError($body))
			{
				//echo 'NntpPrc : Error fetching part number '.$part['messageID'].' in '.$binary['groupname'].' (Server response: '. $body->getMessage().')';
				return false;
			}

			$dec = $this->decodeYenc($body);
			if (!$dec)
			{
                printf("NntpPrc: Unable to decode body of binary: %s\n", $binaryId);

				//
				// Yenc decode failed
				//
				return false;
			}
			$message .= $dec;
		}
		return $message;
	}



	/**
	 * Extend to not get weak warnings.
	 *
	 * @param mixed $data Data to check for error.
	 * @param int $code Error code.
	 *
	 * @return mixed
	 */
	public function isError($data, $code = null)
	{
		return PEAR::isError($data, $code);
	}

	/**
	 * Decode a yenc encoded string.
	 */
	function decodeYenc2($yencodedvar)
	{
		$input = array();
		preg_match("/^(=ybegin.*=yend[^$]*)$/ims", $yencodedvar, $input);
		if (isset($input[1]))
		{
			$ret = "";
			$input = trim(preg_replace("/\r\n/im", "",  preg_replace("/(^=yend.*)/im", "", preg_replace("/(^=ypart.*\\r\\n)/im", "", preg_replace("/(^=ybegin.*\\r\\n)/im", "", $input[1], 1), 1), 1)));

			for( $chr = 0; $chr < strlen($input) ; $chr++)
				$ret .= ($input[$chr] != "=" ? chr(ord($input[$chr]) - 42) : chr((ord($input[++$chr]) - 64) - 42));

			return $ret;
		}
		return false;
	}

	/**
	 * Encode a yenc encoded string.
	 */
	function encodeYenc2($message, $filename, $linelen = 128, $crc32 = true)
	{
		/*
		* This code was found http://everything2.com/title/yEnc+PHP+Class
		*/

		// yEnc 1.3 draft doesn't allow line lengths of more than 254 bytes.
		if ($linelen > 254)
			$linelen = 254;

		if ($linelen < 1)
			return false;

		$encoded = "";

		// Encode each character of the message one at a time.
		for( $i = 0; $i < strlen($message); $i++)
		{
			$value = (ord($message{$i}) + 42) % 256;

		// Escape NULL, TAB, LF, CR, space, . and = characters.
		if ($value == 0 || $value == 9 || $value == 10 ||
			$value == 13 || $value == 32 || $value == 46 ||
			$value == 61)
			$encoded .= "=".chr(($value + 64) % 256);
		else
			$encoded .= chr($value);
	}

		// Wrap the lines to $linelen characters
		$encoded = trim(chunk_split($encoded, $linelen));

		// Tack a yEnc header onto the encoded message.
		$encoded = "=ybegin line=$linelen size=".strlen($message)
				." name=".trim($filename)."\r\n".$encoded;
		$encoded .= "\r\n=yend size=".strlen($message);

		// Add a CRC32 checksum if desired.
		if ($crc32 === true)
			$encoded .= " crc32=".strtolower(sprintf("%04X", crc32($message)));

		return $encoded."\r\n";
	}

}