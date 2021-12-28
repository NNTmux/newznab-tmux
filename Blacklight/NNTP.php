<?php

namespace Blacklight;

use App\Extensions\util\PhpYenc;
use App\Models\Settings;
use Blacklight\utility\Utility;

/*
 * Class for connecting to the usenet, retrieving articles and article headers,
 * decoding yEnc articles, decompressing article headers.
 * Extends PEAR's Net_NNTP_Client class, overrides some functions.
 *
 *
 * Class NNTP
 */

/*
 * 'Service discontinued' (RFC977)
 *
 * @access     public
 */
define('NET_NNTP_PROTOCOL_RESPONSECODE_DISCONNECTING_FORCED', 400);

/*
 * 'Groups and descriptions unavailable'
 *
 * @access     public
 */
define('NET_NNTP_PROTOCOL_RESPONSECODE_XGTITLE_GROUPS_UNAVAILABLE', 481);

/*
 * 'Can not initiate TLS negotiation' (RFC4642)
 *
 * @access     public
 */
define('NET_NNTP_PROTOCOL_RESPONSECODE_TLS_FAILED_NEGOTIATION', 580);

class NNTP extends \Net_NNTP_Client
{
    /**
     * @var \Blacklight\ColorCLI
     */
    protected $colorCli;

    /**
     * @var bool
     */
    protected $_debugBool;

    /**
     * @var bool
     */
    protected $_echo;

    /**
     * Does the server support XFeature GZip header compression?
     *
     * @var bool
     */
    protected $_compressionSupported = true;

    /**
     * Is header compression enabled for the session?
     *
     * @var bool
     */
    protected $_compressionEnabled = false;

    /**
     * Currently selected group.
     *
     * @var string
     */
    protected $_currentGroup = '';

    /**
     * Port of the current NNTP server.
     *
     * @var int
     */
    protected $_currentPort = 'NNTP_PORT';

    /**
     * Address of the current NNTP server.
     *
     * @var string
     */
    protected $_currentServer = 'NNTP_SERVER';

    /**
     * Are we allowed to post to usenet?
     *
     * @var bool
     */
    protected $_postingAllowed = false;

    /**
     * How many times should we try to reconnect to the NNTP server?
     *
     * @var int
     */
    protected $_nntpRetries;

    /**
     * Seconds to wait for the blocking socket to timeout.
     *
     * @var int
     */
    protected $_socketTimeout = 120;

    /**
     * Default constructor.
     *
     * @param  array  $options  Class instances and echo to CLI bool.
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'      => true,
            'Logger' => null,
        ];
        $options += $defaults;

        parent::__construct();

        $this->_echo = ($options['Echo'] && config('nntmux.echocli'));

        $this->_nntpRetries = Settings::settingValue('..nntpretries') !== '' ? (int) Settings::settingValue('..nntpretries') : 0 + 1;
        $this->colorCli = new ColorCLI();
    }

    /**
     * Destruct.
     * Close the NNTP connection if still connected.
     */
    public function __destruct()
    {
        $this->doQuit();
    }

    /**
     * Connect to a usenet server.
     *
     * @param  bool  $compression  Should we attempt to enable XFeature Gzip compression on this connection?
     * @param  bool  $alternate  Use the alternate NNTP connection.
     * @return mixed On success = (bool)   Did we successfully connect to the usenet?
     *
     * @throws \Exception
     *                    On failure = (object) PEAR_Error.
     */
    public function doConnect($compression = true, $alternate = false)
    {
        if ($this->_isConnected() && (($alternate && $this->_currentServer === config('nntmux_nntp.alternate_server')) || (! $alternate && $this->_currentServer === config('nntmux_nntp.server')))) {
            return true;
        }

        $this->doQuit();

        $ret = $connected = $cError = $aError = false;

        // Set variables to connect based on if we are using the alternate provider or not.
        if (! $alternate) {
            $sslEnabled = config('nntmux_nntp.ssl') ? true : false;
            $this->_currentServer = config('nntmux_nntp.server');
            $this->_currentPort = config('nntmux_nntp.port');
            $userName = config('nntmux_nntp.username');
            $password = config('nntmux_nntp.password');
            $socketTimeout = ! empty(config('nntmux_nntp.socket_timeout')) ? config('nntmux_nntp.socket_timeout') : $this->_socketTimeout;
        } else {
            $sslEnabled = config('nntmux_nntp.alternate_server_ssl') ? true : false;
            $this->_currentServer = config('nntmux_nntp.alternate_server');
            $this->_currentPort = config('nntmux_nntp.alternate_server_port');
            $userName = config('nntmux_nntp.alternate_server_username');
            $password = config('nntmux_nntp.alternate_server_password');
            $socketTimeout = ! empty(config('nntmux_nntp.alternate_server_socket_timeout')) ? config('nntmux_nntp.alternate_server_socket_timeout') : $this->_socketTimeout;
        }

        $enc = ($sslEnabled ? ' (ssl)' : ' (non-ssl)');
        $sslEnabled = ($sslEnabled ? 'tls' : false);

        // Try to connect until we run of out tries.
        $retries = $this->_nntpRetries;
        while (true) {
            $retries--;
            $authenticated = false;

            // If we are not connected, try to connect.
            if (! $connected) {
                $ret = $this->connect($this->_currentServer, $sslEnabled, $this->_currentPort, 5, $socketTimeout);
            }

            // Check if we got an error while connecting.
            $cErr = self::isError($ret);

            // If no error, we are connected.
            if (! $cErr) {
                // Say that we are connected so we don't retry.
                $connected = true;
                // When there is no error it returns bool if we are allowed to post or not.
                $this->_postingAllowed = $ret;
            } else {
                // Only fetch the message once.
                if (! $cError) {
                    $cError = $ret->getMessage();
                }
            }

            // If error, try to connect again.
            if ($cErr && $retries > 0) {
                continue;
            }

            // If we have no more retries and could not connect, return an error.
            if ($retries === 0 && ! $connected) {
                $message =
                    'Cannot connect to server '.
                    $this->_currentServer.
                    $enc.
                    ': '.
                    $cError;

                return $this->throwError($this->colorCli->error($message));
            }

            // If we are connected, try to authenticate.
            if ($connected && ! $authenticated) {

                // If the username is empty it probably means the server does not require a username.
                if ($userName === '') {
                    $authenticated = true;

                // Try to authenticate to usenet.
                } else {
                    $ret2 = $this->authenticate($userName, $password);

                    // Check if there was an error authenticating.
                    $aErr = self::isError($ret2);

                    // If there was no error, then we are authenticated.
                    if (! $aErr) {
                        $authenticated = true;
                    } elseif (! $aError) {
                        $aError = $ret2->getMessage();
                    }

                    // If error, try to authenticate again.
                    if ($aErr && $retries > 0) {
                        continue;
                    }

                    // If we ran out of retries, return an error.
                    if ($retries === 0 && ! $authenticated) {
                        $message =
                            'Cannot authenticate to server '.
                            $this->_currentServer.
                            $enc.
                            ' - '.
                            $userName.
                            ' ('.$aError.')';

                        return $this->throwError($this->colorCli->error($message));
                    }
                }
            }

            // If we are connected and authenticated, try enabling compression if we have it enabled.
            if ($connected && $authenticated) {
                // Check if we should use compression on the connection.
                if (! $compression || (int) Settings::settingValue('..compressedheaders') === 0) {
                    $this->_compressionSupported = false;
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
        $message = 'Unable to connect to '.$this->_currentServer.$enc;

        return $this->throwError($this->colorCli->error($message));
    }

    /**
     * Disconnect from the current NNTP server.
     *
     * @param  bool  $force  Force quit even if not connected?
     * @return mixed On success : (bool)   Did we successfully disconnect from usenet?
     *               On Failure : (object) PEAR_Error.
     */
    public function doQuit($force = false)
    {
        $this->_resetProperties();

        // Check if we are connected to usenet.
        if ($force || $this->_isConnected(false)) {
            // Disconnect from usenet.
            return $this->disconnect();
        }

        return true;
    }

    /**
     * Reset some properties when disconnecting from usenet.
     *
     * @void
     */
    protected function _resetProperties(): void
    {
        $this->_compressionEnabled = false;
        $this->_compressionSupported = true;
        $this->_currentGroup = '';
        $this->_postingAllowed = false;
        $this->_selectedGroupSummary = null;
        $this->_overviewFormatCache = null;
    }

    /**
     * Attempt to enable compression if the admin enabled the site setting.
     *
     * @note   This can be used to enable compression if the server was connected without compression.
     *
     * @throws \Exception
     */
    public function enableCompression(): void
    {
        if ((int) Settings::settingValue('..compressedheaders') !== 1) {
            return;
        }
        $this->_enableCompression();
    }

    /**
     * @param  string  $group  Name of the group to select.
     * @param  bool  $articles  (optional) experimental! When true the article numbers is returned in 'articles'.
     * @param  bool  $force  Force a refresh to get updated data from the usenet server.
     * @return mixed On success : (array)  Group information.
     *
     * @throws \Exception
     *                    On failure : (object) PEAR_Error.
     */
    public function selectGroup($group, $articles = false, $force = false)
    {
        $connected = $this->_checkConnection(false);
        if ($connected !== true) {
            return $connected;
        }

        // Check if the current selected group is the same, or if we have not selected a group or if a fresh summary is wanted.
        if ($force || $this->_currentGroup !== $group || $this->_selectedGroupSummary === null) {
            $this->_currentGroup = $group;

            return parent::selectGroup($group, $articles);
        }

        return $this->_selectedGroupSummary;
    }

    /**
     * Fetch an overview of article(s) in the currently selected group.
     *
     * @param  string  $range
     * @param  bool  $names
     * @param  bool  $forceNames
     * @return mixed On success : (array)  Multidimensional array with article headers.
     *
     * @throws \Exception
     *                    On failure : (object) PEAR_Error.
     */
    public function getOverview($range = null, $names = true, $forceNames = true)
    {
        $connected = $this->_checkConnection();
        if ($connected !== true) {
            return $connected;
        }

        // Enabled header compression if not enabled.
        $this->_enableCompression();

        return parent::getOverview($range, $names, $forceNames);
    }

    /**
     * Pass a XOVER command to the NNTP provider, return array of articles using the overview format as array keys.
     *
     * @note This is a faster implementation of getOverview.
     *
     * Example successful return:
     *    array(9) {
     *        'Number'     => string(9)  "679871775"
     *        'Subject'    => string(18) "This is an example"
     *        'From'       => string(19) "Example@example.com"
     *        'Date'       => string(24) "26 Jun 2014 13:08:22 GMT"
     *        'Message-ID' => string(57) "<part1of1.uS*yYxQvtAYt$5t&wmE%UejhjkCKXBJ!@example.local>"
     *        'References' => string(0)  ""
     *        'Bytes'      => string(3)  "123"
     *        'Lines'      => string(1)  "9"
     *        'Xref'       => string(66) "e alt.test:679871775"
     *    }
     *
     * @param  string  $range  Range of articles to get the overview for. Examples follow:
     *                         Single article number:         "679871775"
     *                         Range of article numbers:      "679871775-679999999"
     *                         All newer than article number: "679871775-"
     *                         All older than article number: "-679871775"
     *                         Message-ID:                    "<part1of1.uS*yYxQvtAYt$5t&wmE%UejhjkCKXBJ!@example.local>"
     * @return array|string|\Blacklight\NNTP Multi-dimensional Array of headers on success, PEAR object on failure.
     *
     * @throws \Exception
     */
    public function getXOVER($range)
    {
        // Check if we are still connected.
        $connected = $this->_checkConnection();
        if ($connected !== true) {
            return $connected;
        }

        // Enabled header compression if not enabled.
        $this->_enableCompression();

        // Send XOVER command to NNTP with wanted articles.
        $response = $this->_sendCommand('XOVER '.$range);
        if (self::isError($response)) {
            return $response;
        }

        // Verify the NNTP server got the right command, get the headers data.
        if ($response === NET_NNTP_PROTOCOL_RESPONSECODE_OVERVIEW_FOLLOWS) {
            $data = $this->_getTextResponse();
            if (self::isError($data)) {
                return $data;
            }
        } else {
            return $this->_handleErrorResponse($response);
        }

        // Fetch the header overview format (for setting the array keys on the return array).
        if ($this->_overviewFormatCache !== null && isset($this->_overviewFormatCache['Xref'])) {
            $overview = $this->_overviewFormatCache;
        } else {
            $overview = $this->getOverviewFormat(false, true);
            if (self::isError($overview)) {
                return $overview;
            }
            $this->_overviewFormatCache = $overview;
        }
        // Add the "Number" key.
        $overview = array_merge(['Number' => false], $overview);

        // Iterator used for selecting the header elements to insert into the overview format array.
        $iterator = 0;

        // Loop over strings of headers.
        foreach ($data as $key => $header) {

            // Split the individual headers by tab.
            $header = explode("\t", $header);

            // Make sure it's not empty.
            if ($header === false) {
                continue;
            }

            // Temp array to store the header.
            $headerArray = $overview;

            // Loop over the overview format and insert the individual header elements.
            foreach ($overview as $name => $element) {
                // Strip Xref:
                if ($element === true) {
                    $header[$iterator] = substr($header[$iterator], 6);
                }
                $headerArray[$name] = $header[$iterator++];
            }
            // Add the individual header array back to the return array.
            $data[$key] = $headerArray;
            $iterator = 0;
        }
        // Return the array of headers.
        return $data;
    }

    /**
     * Fetch valid groups.
     *
     * Returns a list of valid groups (that the client is permitted to select) and associated information.
     *
     * @param  string  $wildMat  (optional) http://tools.ietf.org/html/rfc3977#section-4
     * @return array|string Pear error on failure, array with groups on success.
     *
     * @throws \Exception
     */
    public function getGroups($wildMat = null)
    {
        // Enabled header compression if not enabled.
        $this->_enableCompression();

        return parent::getGroups($wildMat);
    }

    /**
     * Download multiple article bodies and string them together.
     *
     * @param  string  $groupName  The name of the group the articles are in.
     * @param  mixed  $identifiers  (string) Message-ID.
     *                              (int)    Article number.
     *                              (array)  Article numbers or Message-ID's (can contain both in the same array)
     * @param  bool  $alternate  Use the alternate NNTP provider?
     * @return mixed On success : (string) The article bodies.
     *
     * @throws \Exception
     *                    On failure : (object) PEAR_Error.
     */
    public function getMessages($groupName, $identifiers, $alternate = false)
    {
        $connected = $this->_checkConnection();
        if ($connected !== true) {
            return $connected;
        }

        // String to hold all the bodies.
        $body = '';

        $aConnected = false;
        $nntp = ($alternate ? new self(['Echo' => $this->_echo]) : null);

        // Check if the msgIds are in an array.
        if (\is_array($identifiers)) {
            $loops = $messageSize = 0;

            // Loop over the message-ID's or article numbers.
            foreach ($identifiers as $wanted) {

                /* This is to attempt to prevent string size overflow.
                 * We get the size of 1 body in bytes, we increment the loop on every loop,
                 * then we multiply the # of loops by the first size we got and check if it
                 * exceeds 1.7 billion bytes (less than 2GB to give us headroom).
                 * If we exceed, return the data.
                 * If we don't do this, these errors are fatal.
                 */
                if ((++$loops * $messageSize) >= 1700000000) {
                    return $body;
                }

                // Download the body.
                $message = $this->_getMessage($groupName, $wanted);

                // Append the body to $body.
                if (! self::isError($message)) {
                    $body .= $message;

                    if ($messageSize === 0) {
                        $messageSize = \strlen($message);
                    }

                    // If there is an error try the alternate provider or return the PEAR error.
                } elseif ($alternate) {
                    if (! $aConnected) {
                        // Check if the current connected server is the alternate or not.
                        $aConnected = $this->_currentServer === config('nntmux_nntp.server') ? $nntp->doConnect(true, true) : $nntp->doConnect();
                    }
                    // If we connected successfully to usenet try to download the article body.
                    if ($aConnected === true) {
                        $newBody = $nntp->_getMessage($groupName, $wanted);
                        // Check if we got an error.
                        if ($nntp->isError($newBody)) {
                            if ($aConnected) {
                                $nntp->doQuit();
                            }
                            // If we got some data, return it.
                            if ($body !== '') {
                                return $body;
                            }
                            // Return the error.
                            return $newBody;
                        }
                        // Append the alternate body to the main body.
                        $body .= $newBody;
                    }
                } else {
                    // If we got some data, return it.
                    if ($body !== '') {
                        return $body;
                    }

                    return $message;
                }
            }

            // If it's a string check if it's a valid message-ID.
        } elseif (\is_string($identifiers) || is_numeric($identifiers)) {
            $body = $this->_getMessage($groupName, $identifiers);
            if ($alternate && self::isError($body)) {
                $nntp->doConnect(true, true);
                $body = $nntp->_getMessage($groupName, $identifiers);
                $aConnected = true;
            }

            // Else return an error.
        } else {
            $message = 'Wrong Identifier type, array, int or string accepted. This type of var was passed: '.gettype($identifiers);

            return $this->throwError($this->colorCli->error($message));
        }

        if ($aConnected === true) {
            $nntp->doQuit();
        }

        return $body;
    }

    /**
     * Download a full article, the body and the header, return an array with named keys and their
     * associated values, optionally decode the body using yEnc.
     *
     * @param  string  $groupName  The name of the group the article is in.
     * @param  mixed  $identifier  (string)The message-ID of the article to download.
     *                             (int) The article number.
     * @param  bool  $yEnc  Attempt to yEnc decode the body.
     * @return mixed On success : (array)  The article.
     *               On failure : (object) PEAR_Error.
     *
     * @throws \Exception
     */
    public function get_Article($groupName, $identifier, $yEnc = false)
    {
        $connected = $this->_checkConnection();
        if ($connected !== true) {
            return $connected;
        }

        // Make sure the requested group is already selected, if not select it.
        if ($this->group() !== $groupName) {
            // Select the group.
            $summary = $this->selectGroup($groupName);
            // If there was an error selecting the group, return PEAR error object.
            if (self::isError($summary)) {
                return $summary;
            }
        }

        // Check if it's an article number or message-ID.
        if (! is_numeric($identifier)) {
            // If it's a message-ID, check if it has the required triangular brackets.
            $identifier = $this->_formatMessageID($identifier);
        }

        // Download the article.
        $article = $this->getArticle($identifier);
        // If there was an error downloading the article, return a PEAR error object.
        if (self::isError($article)) {
            return $article;
        }

        $ret = $article;
        // Make sure the article is an array and has more than 1 element.
        if (\count($article) > 0) {
            $ret = [];
            $body = '';
            $emptyLine = false;
            foreach ($article as $line) {
                // If we found the empty line it means we are done reading the header and we will start reading the body.
                if (! $emptyLine) {
                    if ($line === '') {
                        $emptyLine = true;
                        continue;
                    }

                    // Use the line type of the article as the array key (From, Subject, etc..).
                    if (preg_match('/([A-Z-]+?): (.*)/i', $line, $hits)) {
                        // If the line type takes more than 1 line, append the rest of the content to the same key.
                        if (array_key_exists($hits[1], $ret)) {
                            $ret[$hits[1]] .= $hits[2];
                        } else {
                            $ret[$hits[1]] = $hits[2];
                        }
                    }

                    // Now we have the header, so get the body from the rest of the lines.
                } else {
                    $body .= $line;
                }
            }
            // Finally we decode the message using yEnc.
            $ret['Message'] = ($yEnc ? PhpYenc::decodeIgnore($body) : $body);
        }

        return $ret;
    }

    /**
     * Download a full article header.
     *
     * @param  string  $groupName  The name of the group the article is in.
     * @param  mixed  $identifier  (string) The message-ID of the article to download.
     *                             (int)    The article number.
     * @return mixed On success : (array)  The header.
     *
     * @throws \Exception
     *                    On failure : (object) PEAR_Error.
     */
    public function get_Header($groupName, $identifier)
    {
        $connected = $this->_checkConnection();
        if ($connected !== true) {
            return $connected;
        }

        // Make sure the requested group is already selected, if not select it.
        if ($this->group() !== $groupName) {
            // Select the group.
            $summary = $this->selectGroup($groupName);
            // Return PEAR error object on failure.
            if (self::isError($summary)) {
                return $summary;
            }
        }

        // Check if it's an article number or message-id.
        if (! is_numeric($identifier)) {
            // Verify we have the required triangular brackets if it is a message-id.
            $identifier = $this->_formatMessageID($identifier);
        }

        // Download the header.
        $header = $this->getHeader($identifier);
        // If we failed, return PEAR error object.
        if (self::isError($header)) {
            return $header;
        }

        $ret = $header;
        if (\count($header) > 0) {
            $ret = [];
            // Use the line types of the header as array keys (From, Subject, etc).
            foreach ($header as $line) {
                if (preg_match('/([A-Z-]+?): (.*)/i', $line, $hits)) {
                    // If the line type takes more than 1 line, re-use the same array key.
                    if (array_key_exists($hits[1], $ret)) {
                        $ret[$hits[1]] .= $hits[2];
                    } else {
                        $ret[$hits[1]] = $hits[2];
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * Post an article to usenet.
     *
     * @param  string|array  $groups  mixed   (array)  Groups. ie.: $groups = array('alt.test', 'alt.testing', 'free.pt');
     *                                (string) Group.  ie.: $groups = 'alt.test';
     * @param  string  $subject  string  The subject.     ie.: $subject = 'Test article';
     * @param  string|\Exception  $body  string  The message.     ie.: $message = 'This is only a test, please disregard.';
     * @param  string  $from  string  The poster.      ie.: $from = '<anon@anon.com>';
     * @param $extra    string  Extra, separated by \r\n
     *                                           ie.: $extra  = 'Organization: <NNTmux>\r\nNNTP-Posting-Host: <127.0.0.1>';
     * @param $yEnc     bool    Encode the message with yEnc?
     * @param $compress bool    Compress the message with GZip?
     * @return mixed On success : (bool)   True.
     *               On failure : (object) PEAR_Error.
     *
     * @throws \Exception
     */
    public function postArticle($groups, $subject, $body, $from, $yEnc = true, $compress = true, $extra = '')
    {
        if (! $this->_postingAllowed) {
            $message = 'You do not have the right to post articles on server '.$this->_currentServer;

            return $this->throwError($this->colorCli->error($message));
        }

        $connected = $this->_checkConnection();
        if ($connected !== true) {
            return $connected;
        }

        // Throw errors if subject or from are more than 510 chars.
        if (\strlen($subject) > 510) {
            $message = 'Max length of subject is 510 chars.';

            return $this->throwError($this->colorCli->error($message));
        }

        if (\strlen($from) > 510) {
            $message = 'Max length of from is 510 chars.';

            return $this->throwError($this->colorCli->error($message));
        }

        // Check if the group is string or array.
        if (\is_array($groups)) {
            $groups = implode(', ', $groups);
        }

        // Check if we should encode to yEnc.
        if ($yEnc) {
            $bin = $compress ? gzdeflate($body, 4) : $body;
            $body = PhpYenc::encode($bin, $subject);
        // If not yEnc, then check if the body is 510+ chars, split it at 510 chars and separate with \r\n
        } else {
            $body = $this->_splitLines($body, $compress);
        }

        // From is required by NNTP servers, but parent function mail does not require it, so format it.
        $from = 'From: '.$from;
        // If we had extra stuff to post, format it with from.
        if ($extra !== '') {
            $from = $from."\r\n".$extra;
        }

        return $this->mail($groups, $subject, $body, $from);
    }

    /**
     * Restart the NNTP connection if an error occurs in the selectGroup
     * function, if it does not restart display the error.
     *
     * @param  NNTP  $nntp  Instance of class NNTP.
     * @param  string  $group  Name of the group.
     * @param  bool  $comp  Use compression or not?
     * @return mixed On success : (array)  The group summary.
     *
     * @throws \Exception
     *                    On Failure : (object) PEAR_Error.
     */
    public function dataError($nntp, $group, $comp = true)
    {
        // Disconnect.
        $nntp->doQuit();
        // Try reconnecting. This uses another round of max retries.
        if ($nntp->doConnect($comp) !== true) {
            return $this->throwError('Unable to reconnect to usenet!');
        }

        // Try re-selecting the group.
        $data = $nntp->selectGroup($group);
        if (self::isError($data)) {
            $message = "Code {$data->code}: {$data->message}\nSkipping group: {$group}";

            if ($this->_echo) {
                $this->colorCli->error($message);
            }
            $nntp->doQuit();
        }

        return $data;
    }

    /**
     * If on unix, hide yydecode CLI output.
     *
     * @var string
     */
    protected $_yEncSilence;

    /**
     * Path to temp yEnc input storage file.
     *
     * @var string
     */
    protected $_yEncTempInput;

    /**
     * Path to temp yEnc output storage file.
     *
     * @var string
     */
    protected $_yEncTempOutput;

    /**
     * Split a string into lines of 510 chars ending with \r\n.
     * Usenet limits lines to 512 chars, with \r\n that leaves us 510.
     *
     * @param  string  $string  The string to split.
     * @param  bool  $compress  Compress the string with gzip?
     * @return string The split string.
     */
    protected function _splitLines($string, $compress = false): string
    {
        // Check if the length is longer than 510 chars.
        if (\strlen($string) > 510) {
            // If it is, split it @ 510 and terminate with \r\n.
            $string = chunk_split($string, 510, "\r\n");
        }

        // Compress the string if requested.
        return $compress ? gzdeflate($string, 4) : $string;
    }

    /**
     * Try to see if the NNTP server implements XFeature GZip Compression,
     * change the compression bool object if so.
     *
     * @param  bool  $secondTry  This is only used if enabling compression fails, the function will call itself to retry.
     * @return mixed On success : (bool)   True:  The server understood and compression is enabled.
     *               (bool)   False: The server did not understand, compression is not enabled.
     *               On failure : (object) PEAR_Error.
     *
     * @throws \Exception
     */
    protected function _enableCompression($secondTry = false)
    {
        if ($this->_compressionEnabled) {
            return true;
        }
        if (! $this->_compressionSupported) {
            return false;
        }

        // Send this command to the usenet server.
        $response = $this->_sendCommand('XFEATURE COMPRESS GZIP');

        // Check if it's good.
        if (self::isError($response)) {
            $this->_compressionSupported = false;

            return $response;
        }
        if ($response !== 290) {
            if (! $secondTry) {
                // Retry.
                $this->cmdQuit();
                if ($this->_checkConnection()) {
                    return $this->_enableCompression(true);
                }
            }
            $msg = "Sent 'XFEATURE COMPRESS GZIP' to server, got '$response: ".$this->_currentStatusResponse()."'";

            $this->_compressionSupported = false;

            return false;
        }

        $this->_compressionEnabled = true;
        $this->_compressionSupported = true;

        return true;
    }

    /**
     * Override PEAR NNTP's function to use our _getXFeatureTextResponse instead
     * of their _getTextResponse function since it is incompatible at decoding
     * headers when XFeature GZip compression is enabled server side.
     *
     * @return self|string Our overridden function when compression is enabled.
     *                     parent  Parent function when no compression.
     */
    public function _getTextResponse()
    {
        if ($this->_compressionEnabled &&
            isset($this->_currentStatusResponse[1]) &&
            stripos($this->_currentStatusResponse[1], 'COMPRESS=GZIP') !== false) {
            return $this->_getXFeatureTextResponse();
        }

        return parent::_getTextResponse();
    }

    /**
     * Loop over the compressed data when XFeature GZip Compress is turned on,
     * string the data until we find a indicator
     * (period, carriage feed, line return ;; .\r\n), decompress the data,
     * split the data (bunch of headers in a string) into an array, finally
     * return the array.
     *
     * Have we failed to decompress the data, was there a
     * problem downloading the data, etc..
     *
     * @return array|string On success : (array)  The headers.
     *                      On failure : (object) PEAR_Error.
     *                      On decompress failure: (string) error message
     */
    protected function &_getXFeatureTextResponse()
    {
        $possibleTerm = false;
        $data = null;

        while (! feof($this->_socket)) {

            // Did we find a possible ending ? (.\r\n)
            if ($possibleTerm) {

                // Loop, sleeping shortly, to allow the server time to upload data, if it has any.
                for ($i = 0; $i < 3; $i++) {
                    // If the socket is really empty, fGets will get stuck here, so set the socket to non blocking in case.
                    stream_set_blocking($this->_socket, 0);

                    // Now try to download from the socket.
                    $buffer = fgets($this->_socket);

                    // And set back the socket to blocking.
                    stream_set_blocking($this->_socket, 1);

                    // Don't sleep on last iteration.
                    if (! empty($buffer)) {
                        break;
                    }
                    if ($i < 2) {
                        usleep(10000);
                    }
                }

                // If the buffer was really empty, then we know $possibleTerm was the real ending.
                if (empty($buffer)) {
                    // Remove .\r\n from end, decompress data.
                    $deComp = @gzuncompress(mb_substr($data, 0, -3, '8bit'));

                    if (! empty($deComp)) {
                        $bytesReceived = \strlen($data);
                        if ($this->_echo && $bytesReceived > 10240) {
                            $this->colorCli->primaryOver(
                                    'Received '.round($bytesReceived / 1024).
                                    'KB from group ('.$this->group().').'
                                );
                        }

                        // Split the string of headers into an array of individual headers, then return it.
                        $deComp = explode("\r\n", trim($deComp));

                        return $deComp;
                    }
                    $message = 'Decompression of OVER headers failed.';

                    $message = $this->throwError($this->colorCli->error($message), 1000);

                    return $message;
                }
                // The buffer was not empty, so we know this was not the real ending, so reset $possibleTerm.
                $possibleTerm = false;
            } else {
                // Get data from the stream.
                $buffer = fgets($this->_socket);
            }

            // If we got no data at all try one more time to pull data.
            if (empty($buffer)) {
                usleep(10000);
                $buffer = fgets($this->_socket);

                // If wet got nothing again, return error.
                if (empty($buffer)) {
                    $message = 'Error fetching data from usenet server while downloading OVER headers.';

                    $message = $this->throwError($this->colorCli->error($message), 1000);

                    return $message;
                }
            }

            // Append current buffer to rest of buffer.
            $data .= $buffer;

            // Check if we have the ending (.\r\n)
            if (substr($buffer, -3) === ".\r\n") {
                // We have a possible ending, next loop check if it is.
                $possibleTerm = true;
            }
        }

        $message = 'Unspecified error while downloading OVER headers.';
        $message = $this->throwError($this->colorCli->error($message), 1000);

        return $message;
    }

    /**
     * Check if the Message-ID has the required opening and closing brackets.
     *
     * @param  string  $messageID  The Message-ID with or without brackets.
     * @return string Message-ID with brackets.
     */
    protected function _formatMessageID($messageID): string
    {
        $messageID = (string) $messageID;
        if ($messageID === '') {
            return false;
        }

        // Check if the first char is <, if not add it.
        if ($messageID[0] !== '<') {
            $messageID = ('<'.$messageID);
        }

        // Check if the last char is >, if not add it.
        if (substr($messageID, -1) !== '>') {
            $messageID .= '>';
        }

        return $messageID;
    }

    /**
     * Download an article body (an article without the header).
     *
     * @param  string  $groupName  The name of the group the article is in.
     * @param  mixed  $identifier  (string) The message-ID of the article to download.
     *                             (int)    The article number.
     * @return string On success : (string) The article's body.
     *
     * @throws \Exception
     *                    On failure : (object) PEAR_Error.
     */
    protected function _getMessage($groupName, $identifier): ?string
    {
        // Make sure the requested group is already selected, if not select it.
        if ($this->group() !== $groupName) {
            // Select the group.
            $summary = $this->selectGroup($groupName);
            // If there was an error selecting the group, return PEAR error object.
            if (self::isError($summary)) {
                return $summary;
            }
        }

        // Check if this is an article number or message-id.
        if (! is_numeric($identifier)) {
            // It's a message-id so check if it has the triangular brackets.
            $identifier = $this->_formatMessageID($identifier);
        }

        // Tell the news server we want the body of an article.
        $response = $this->_sendCommand('BODY '.$identifier);
        if (self::isError($response)) {
            return $response;
        }

        $body = '';
        if ($response === NET_NNTP_PROTOCOL_RESPONSECODE_BODY_FOLLOWS) {

            // Continue until connection is lost
            while (! feof($this->_socket)) {

                // Retrieve and append up to 1024 characters from the server.
                $line = fgets($this->_socket, 1024);

                // If the socket is empty/ an error occurs, false is returned.
                // Since the socket is blocking, the socket should not be empty, so it's definitely an error.
                if ($line === false) {
                    return $this->throwError('Failed to read line from socket.', null);
                }

                // Check if the line terminates the text response.
                if ($line === ".\r\n") {

                    // Attempt to yEnc decode and return the body.
                    return PhpYenc::decodeIgnore($body);
                }

                // Check for line that starts with double period, remove one.
                if (strpos($line, '.') === 0 && $line[1] === '.') {
                    $line = substr($line, 1);
                }

                // Add the line to the rest of the lines.
                $body .= $line;
            }

            return $this->throwError('End of stream! Connection lost?', null);
        }

        return $this->_handleErrorResponse($response);
    }

    /**
     * Check if we are still connected. Reconnect if not.
     *
     * @param  bool  $reSelectGroup  Select back the group after connecting?
     * @return mixed On success: (bool)   True;
     *
     * @throws \Exception
     *                    On failure: (object) PEAR_Error
     */
    protected function _checkConnection($reSelectGroup = true)
    {
        $currentGroup = $this->_currentGroup;
        // Check if we are connected.
        if (parent::_isConnected()) {
            $retVal = true;
        } else {
            switch ($this->_currentServer) {
                case config('nntmux_nntp.server'):
                    if (\is_resource($this->_socket)) {
                        $this->doQuit(true);
                    }
                    $retVal = $this->doConnect();
                    break;
                case config('nntmux_nntp.alternate_server'):
                    if (\is_resource($this->_socket)) {
                        $this->doQuit(true);
                    }
                    $retVal = $this->doConnect(true, true);
                    break;
                default:
                    $retVal = $this->throwError('Wrong server constant used in NNTP checkConnection()!');
            }

            if ($retVal === true && $reSelectGroup) {
                $group = $this->selectGroup($currentGroup);
                if (self::isError($group)) {
                    $retVal = $group;
                }
            }
        }

        return $retVal;
    }

    /**
     * Verify NNTP error code and return PEAR error.
     *
     * @param  int  $response  NET_NNTP Response code
     * @return object PEAR error
     */
    protected function _handleErrorResponse($response)
    {
        switch ($response) {
            // 381, RFC2980: 'More authentication information required'
            case NET_NNTP_PROTOCOL_RESPONSECODE_AUTHENTICATION_CONTINUE:
                return $this->throwError('More authentication information required', $response, $this->_currentStatusResponse());
            // 400, RFC977: 'Service discontinued'
            case NET_NNTP_PROTOCOL_RESPONSECODE_DISCONNECTING_FORCED:
                return $this->throwError('Server refused connection', $response, $this->_currentStatusResponse());
            // 411, RFC977: 'no such news group'
            case NET_NNTP_PROTOCOL_RESPONSECODE_NO_SUCH_GROUP:
                return $this->throwError('No such news group on server', $response, $this->_currentStatusResponse());
            // 412, RFC2980: 'No news group current selected'
            case NET_NNTP_PROTOCOL_RESPONSECODE_NO_GROUP_SELECTED:
                return $this->throwError('No news group current selected', $response, $this->_currentStatusResponse());
            // 420, RFC2980: 'Current article number is invalid'
            case NET_NNTP_PROTOCOL_RESPONSECODE_NO_ARTICLE_SELECTED:
                return $this->throwError('Current article number is invalid', $response, $this->_currentStatusResponse());
            // 421, RFC977: 'no next article in this group'
            case NET_NNTP_PROTOCOL_RESPONSECODE_NO_NEXT_ARTICLE:
                return $this->throwError('No next article in this group', $response, $this->_currentStatusResponse());
            // 422, RFC977: 'no previous article in this group'
            case NET_NNTP_PROTOCOL_RESPONSECODE_NO_PREVIOUS_ARTICLE:
                return $this->throwError('No previous article in this group', $response, $this->_currentStatusResponse());
            // 423, RFC977: 'No such article number in this group'
            case NET_NNTP_PROTOCOL_RESPONSECODE_NO_SUCH_ARTICLE_NUMBER:
                return $this->throwError('No such article number in this group', $response, $this->_currentStatusResponse());
            // 430, RFC977: 'No such article found'
            case NET_NNTP_PROTOCOL_RESPONSECODE_NO_SUCH_ARTICLE_ID:
                return $this->throwError('No such article found', $response, $this->_currentStatusResponse());
            // 435, RFC977: 'Article not wanted'
            case NET_NNTP_PROTOCOL_RESPONSECODE_TRANSFER_UNWANTED:
                return $this->throwError('Article not wanted', $response, $this->_currentStatusResponse());
            // 436, RFC977: 'Transfer failed - try again later'
            case NET_NNTP_PROTOCOL_RESPONSECODE_TRANSFER_FAILURE:
                return $this->throwError('Transfer failed - try again later', $response, $this->_currentStatusResponse());
            // 437, RFC977: 'Article rejected - do not try again'
            case NET_NNTP_PROTOCOL_RESPONSECODE_TRANSFER_REJECTED:
                return $this->throwError('Article rejected - do not try again', $response, $this->_currentStatusResponse());
            // 440, RFC977: 'posting not allowed'
            case NET_NNTP_PROTOCOL_RESPONSECODE_POSTING_PROHIBITED:
                return $this->throwError('Posting not allowed', $response, $this->_currentStatusResponse());
            // 441, RFC977: 'posting failed'
            case NET_NNTP_PROTOCOL_RESPONSECODE_POSTING_FAILURE:
                return $this->throwError('Posting failed', $response, $this->_currentStatusResponse());
            // 481, RFC2980: 'Groups and descriptions unavailable'
            case NET_NNTP_PROTOCOL_RESPONSECODE_XGTITLE_GROUPS_UNAVAILABLE:
                return $this->throwError('Groups and descriptions unavailable', $response, $this->_currentStatusResponse());
            // 482, RFC2980: 'Authentication rejected'
            case NET_NNTP_PROTOCOL_RESPONSECODE_AUTHENTICATION_REJECTED:
                return $this->throwError('Authentication rejected', $response, $this->_currentStatusResponse());
            // 500, RFC977: 'Command not recognized'
            case NET_NNTP_PROTOCOL_RESPONSECODE_UNKNOWN_COMMAND:
                return $this->throwError('Command not recognized', $response, $this->_currentStatusResponse());
            // 501, RFC977: 'Command syntax error'
            case NET_NNTP_PROTOCOL_RESPONSECODE_SYNTAX_ERROR:
                return $this->throwError('Command syntax error', $response, $this->_currentStatusResponse());
            // 502, RFC2980: 'No permission'
            case NET_NNTP_PROTOCOL_RESPONSECODE_NOT_PERMITTED:
                return $this->throwError('No permission', $response, $this->_currentStatusResponse());
            // 503, RFC2980: 'Program fault - command not performed'
            case NET_NNTP_PROTOCOL_RESPONSECODE_NOT_SUPPORTED:
                return $this->throwError('Internal server error, function not performed', $response, $this->_currentStatusResponse());
            // RFC4642: 'Can not initiate TLS negotiation'
            case NET_NNTP_PROTOCOL_RESPONSECODE_TLS_FAILED_NEGOTIATION:
                return $this->throwError('Can not initiate TLS negotiation', $response, $this->_currentStatusResponse());
            default:
                $text = $this->_currentStatusResponse();

                return $this->throwError("Unexpected response: '$text'", $response, $text);
        }
    }

    /**
     * Connect to a NNTP server.
     *
     * @param  string  $host  (optional) The address of the NNTP-server to connect to, defaults to 'localhost'.
     * @param  mixed  $encryption  (optional) Use TLS/SSL on the connection?
     *                             (string) 'tcp'                 => Use no encryption.
     *                             'ssl', 'sslv3', 'tls' => Use encryption.
     *                             (null)|(false) Use no encryption.
     * @param  int  $port  (optional) The port number to connect to, defaults to 119.
     * @param  int  $timeout  (optional) How many seconds to wait before giving up when connecting.
     * @param  int  $socketTimeout  (optional) How many seconds to wait before timing out the (blocked) socket.
     * @return mixed (bool)   On success: True when posting allowed, otherwise false.
     *               (object) On failure: pear_error
     */
    public function connect($host = null, $encryption = null, $port = null, $timeout = 15, $socketTimeout = 120)
    {
        if ($this->_isConnected()) {
            return $this->throwError('Already connected, disconnect first!', null);
        }
        // v1.0.x API
        if (is_int($encryption)) {
            trigger_error('You are using deprecated API v1.0 in Net_NNTP_Protocol_Client: connect() !', E_USER_NOTICE);
            $port = $encryption;
            $encryption = false;
        }
        if ($host === null) {
            $host = 'localhost';
        }
        // Choose transport based on encryption, and if no port is given, use default for that encryption.
        switch ($encryption) {
            case null:
            case 'tcp':
            case false:
                $transport = 'tcp';
                $port = $port ?? 119;
                break;
            case 'ssl':
            case 'tls':
                $transport = $encryption;
                $port = $port ?? 563;
                break;
            default:
                $message = '$encryption parameter must be either tcp, tls, ssl.';
                trigger_error($message, E_USER_ERROR);

                return $this->throwError($message);
        }
        // Attempt to connect to usenet.
        $socket = stream_socket_client(
            $transport.'://'.$host.':'.$port,
            $errorNumber,
            $errorString,
            $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create(Utility::streamSslContextOptions())
        );
        if ($socket === false) {
            $message = "Connection to $transport://$host:$port failed.";
            if (preg_match('/tls|ssl/', $transport)) {
                $message .= ' Try disabling SSL/TLS, and/or try a different port.';
            }
            $message .= ' [ERROR '.$errorNumber.': '.$errorString.']';
            if ($this->_logger) {
                $this->_logger->notice($message);
            }

            return $this->throwError($message);
        }
        // Store the socket resource as property.
        $this->_socket = $socket;
        $this->_socketTimeout = (is_numeric($socketTimeout) ? $socketTimeout : $this->_socketTimeout);
        // Set the socket timeout.
        stream_set_timeout($this->_socket, $this->_socketTimeout);
        if ($this->_logger) {
            $this->_logger->info("Connection to $transport://$host:$port has been established.");
        }
        // Retrieve the server's initial response.
        $response = $this->_getStatusResponse();
        if (self::isError($response)) {
            return $response;
        }
        switch ($response) {
            // 200, Posting allowed
            case NET_NNTP_PROTOCOL_RESPONSECODE_READY_POSTING_ALLOWED:
                // TODO: Set some variable before return
                return true;
            // 201, Posting NOT allowed
            case NET_NNTP_PROTOCOL_RESPONSECODE_READY_POSTING_PROHIBITED:
                if ($this->_logger) {
                    $this->_logger->info('Posting not allowed!');
                }
                // TODO: Set some variable before return
                return false;
            default:
                return $this->_handleErrorResponse($response);
        }
    }

    /**
     * Test whether we are connected or not.
     *
     * @param  bool  $feof  Check for the end of file pointer.
     * @return bool true or false
     */
    public function _isConnected($feof = true): bool
    {
        return is_resource($this->_socket) && ($feof ? ! feof($this->_socket) : true);
    }
}
