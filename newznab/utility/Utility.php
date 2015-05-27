<?php
namespace newznab\utility;

use newznab\db\Settings;
use ColorCLI;


/**
 * Class Utility
 *
 * @package newznab\utility
 */
class Utility
{
	/**
	 *  Regex for detecting multi-platform path. Use it where needed so it can be updated in one location as required characters get added.
	 */
	const PATH_REGEX = '(?P<drive>[A-Za-z]:|)(?P<path>[/\w.-]+|)';

	/**
	 * Checks all levels of the supplied path are readable and executable by current user.
	 *
	 * @todo Make this recursive with a switch to only check end point.
	 * @param $path	*nix path to directory or file
	 *
	 * @return bool|string True is successful, otherwise the part of the path that failed testing.
	 */
	public static function canExecuteRead($path)
	{
		$paths = preg_split('#/#', $path);
		$fullPath = DS;
		foreach ($paths as $path) {
			if ($path !== '') {
				$fullPath .= $path . DS;
				if (!is_readable($fullPath) || !is_executable($fullPath)) {
					return "The '$fullPath' directory must be readable and executable by all ." .PHP_EOL;
				}
			}
		}
		return true;
	}

	static public function clearScreen()
	{
		if (self::isCLI()) {
			if (self::isWin()) {
				passthru('cls');
			} else {
				passthru('clear');
			}
		}
	}

	/**
	 * Replace all white space chars for a single space.
	 *
	 * @param string $text
	 *
	 * @return string
	 *
	 * @static
	 * @access public
	 */
	static public function collapseWhiteSpace($text)
	{
		// Strip leading/trailing white space.
		return trim(
		// Replace 2 or more white space for a single space.
			preg_replace('/\s{2,}/',
				' ',
				// Replace new lines and carriage returns. DO NOT try removing '\r' or '\n' as they are valid in queries which uses this method.
				str_replace(["\n", "\r"], ' ', $text)
			)
		);
	}

	/**
	 * Removes the preceeding or proceeding portion of a string
	 * relative to the last occurrence of the specified character.
	 * The character selected may be retained or discarded.
	 *
	 * @param string $character      the character to search for.
	 * @param string $string         the string to search through.
	 * @param string $side           determines whether text to the left or the right of the character is returned.
	 *                               Options are: left, or right.
	 * @param bool   $keep_character determines whether or not to keep the character.
	 *                               Options are: true, or false.
	 *
	 * @return string
	 */
	static public function cutStringUsingLast($character, $string, $side, $keep_character = true)
	{
		$offset = ($keep_character ? 1 : 0);
		$whole_length = strlen($string);
		$right_length = (strlen(strrchr($string, $character)) - 1);
		$left_length = ($whole_length - $right_length - 1);
		switch ($side) {
			case 'left':
				$piece = substr($string, 0, ($left_length + $offset));
				break;
			case 'right':
				$start = (0 - ($right_length + $offset));
				$piece = substr($string, $start);
				break;
			default:
				$piece = false;
				break;
		}

		return ($piece);
	}

	static public function getDirFiles(array $options = null)
	{
		$defaults = [
			'dir'   => false,
			'ext'   => '', // no full stop (period) separator should be used.
			'path'  => '',
			'regex' => '',
		];
		$options += $defaults;

		$files = [];
		$dir = new \DirectoryIterator($options['path']);
		foreach ($dir as $fileinfo) {
			$file = $fileinfo->getFilename();
			switch (true) {
				case $fileinfo->isDot():
					break;
				case !$options['dir'] && $fileinfo->isDir():
					break;
				case !empty($options['ext']) && $fileinfo->getExtension() != $options['ext'];
					break;
				case !preg_match($options['regex'], str_replace('\\', '/', $file)):
					break;
				default:
					$files[] = $fileinfo->getPathname();
			}
		}

		return $files;
	}

	public static function getValidVersionsFile()
	{
		$versions = new Versions();

		return $versions->getValidVersionsFile();
	}

	/**
	 * Detect if the command is accessible on the system.
	 *
	 * @param $cmd
	 *
	 * @return bool|null Returns true if found, false if not found, and null if which is not detected.
	 */
	static public function hasCommand($cmd)
	{
		if ('HAS_WHICH') {
			$returnVal = shell_exec("which $cmd");

			return (empty($returnVal) ? false : true);
		} else {
			return null;
		}
	}

	/**
	 * Check for availability of which command
	 */
	static public function hasWhich()
	{
		exec('which which', $output, $error);

		return !$error;
	}

	/**
	 * Check if user is running from CLI.
	 *
	 * @return bool
	 */
	static public function isCLI()
	{
		return ((strtolower(PHP_SAPI) === 'cli') ? true : false);
	}

	static public function isGZipped($filename)
	{
		$gzipped = null;
		if (($fp = fopen($filename, 'r')) !== false) {
			if (@fread($fp, 2) == "\x1F\x8B") { // this is a gzip'd file
				fseek($fp, -4, SEEK_END);
				if (strlen($datum = @fread($fp, 4)) == 4) {
					$gzipped = $datum;
				}
			}
			fclose($fp);
		}

		return ($gzipped);
	}

	public static function isPatched(Settings $pdo = null)
	{
		$versions = self::getValidVersionsFile();

		if (!($pdo instanceof Settings)) {
			$pdo = new Settings();
		}
		$patch = $pdo->getSetting(['section' => '', 'subsection' => '', 'name' => 'sqlpatch']);
		$ver = $versions->versions->sql->file;

		// Check database patch version
		if ($patch < $ver) {
			$message = "\nYour database is not up to date. Reported patch levels\n   Db: $patch\nfile: $ver\nPlease update.\n php " .
				NN_ROOT . "cli/update_db.php true\n";
			if (self::isCLI()) {
				echo (new ColorCLI())->error($message);
			}
			throw new \RuntimeException($message);
		}

		return true;
	}

	static public function isWin()
	{
		return (strtolower(substr(PHP_OS, 0, 3)) === 'win');
	}

	static public function stripBOM(&$text)
	{
		$bom = pack("CCC", 0xef, 0xbb, 0xbf);
		if (0 == strncmp($text, $bom, 3)) {
			$text = substr($text, 3);
		}
	}

	/**
	 * Strips non-printing characters from a string.
	 *
	 * Operates directly on the text string, but also returns the result for situations requiring a
	 * return value (use in ternary, etc.)/
	 *
	 * @param $text        String variable to strip.
	 *
	 * @return string    The stripped variable.
	 */
	static public function stripNonPrintingChars(&$text)
	{
		$lowChars = [
			"\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",
			"\x08", "\x09", "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F",
			"\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17",
			"\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D", "\x1E", "\x1F",
		];
		$text = str_replace($lowChars, '', $text);

		return $text;
	}

	static public function trailingSlash($path)
	{
		if (substr($path, strlen($path) - 1) != '/') {
			$path .= '/';
		}

		return $path;
	}

	/**
	 * Unzip a gzip file, return the output. Return false on error / empty.
	 *
	 * @param string $filePath
	 *
	 * @return bool|string
	 */
	static public function unzipGzipFile($filePath)
	{
		/* Potential issues with this, so commenting out.
		$length = Utility::isGZipped($filePath);
		if ($length === false || $length === null) {
			return false;
		}*/

		$string = '';
		$gzFile = @gzopen($filePath, 'rb', 0);
		if ($gzFile) {
			while (!gzeof($gzFile)) {
				$temp = gzread($gzFile, 1024);
				// Check for empty string.
				// Without this the loop would be endless and consume 100% CPU.
				// Do not set $string empty here, as the data might still be good.
				if (!$temp) {
					break;
				}
				$string .= $temp;
			}
			gzclose($gzFile);
		}

		return ($string === '' ? false : $string);
	}

	public static function setCoversConstant($path)
	{
		if (!defined('NN_COVERS')) {
			switch (true) {
				case (substr($path, 0, 1) == '/' ||
					substr($path, 1, 1) == ':' ||
					substr($path, 0, 1) == '\\'):
					define('NN_COVERS', self::trailingSlash($path));
					break;
				case (strlen($path) > 0 && substr($path, 0, 1) != '/' && substr($path, 1, 1) != ':' &&
					substr($path, 0, 1) != '\\'):
					define('NN_COVERS', realpath(NN_ROOT . self::trailingSlash($path)));
					break;
				case empty($path): // Default to resources location.
				default:
					define('NN_COVERS', NN_RES . 'covers' . DS);
			}
		}
	}

	/**
	 * Creates an array to be used with stream_context_create() to verify openssl certificates
	 * when connecting to a tls or ssl connection when using stream functions (fopen/file_get_contents/etc).
	 *
	 * @param bool $forceIgnore Force ignoring of verification.
	 *
	 * @return array
	 * @static
	 * @access public
	 */
	static public function streamSslContextOptions($forceIgnore = false)
	{
		$options = [
			'verify_peer'       => ($forceIgnore ? false : (bool)NN_SSL_VERIFY_PEER),
			'verify_peer_name'  => ($forceIgnore ? false : (bool)NN_SSL_VERIFY_HOST),
			'allow_self_signed' => ($forceIgnore ? true : (bool)NN_SSL_ALLOW_SELF_SIGNED),
		];
		if (NN_SSL_CAFILE) {
			$options['cafile'] = NN_SSL_CAFILE;
		}
		if (NN_SSL_CAPATH) {
			$options['capath'] = NN_SSL_CAPATH;
		}
		// If we set the transport to tls and the server falls back to ssl,
		// the context options would be for tls and would not apply to ssl,
		// so set both tls and ssl context in case the server does not support tls.
		return ['tls' => $options, 'ssl' => $options];
	}

	/**
	 * Set curl context options for verifying SSL certificates.
	 *
	 * @param bool $verify false = Ignore config.php and do not verify the openssl cert.
	 *                     true  = Check config.php and verify based on those settings.
	 *                     If you know the certificate will be self-signed, pass false.
	 *
	 * @return array
	 * @static
	 * @access public
	 */
	static public function curlSslContextOptions($verify = true)
	{
		$options = [];
		if ($verify && NN_SSL_VERIFY_HOST) {
			$options += [
				CURLOPT_CAINFO         => NN_SSL_CAFILE,
				CURLOPT_CAPATH         => NN_SSL_CAPATH,
				CURLOPT_SSL_VERIFYPEER => (bool)NN_SSL_VERIFY_PEER,
				CURLOPT_SSL_VERIFYHOST => (NN_SSL_VERIFY_HOST ? 2 : 0),
			];
		} else {
			$options += [
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => 0,
			];
		}

		return $options;
	}

	/**
	 * Use cURL To download a web page into a string.
	 *
	 * @param array $options See details below.
	 *
	 * @return bool|mixed
	 * @access public
	 * @static
	 */
	public static function getUrl(array $options = [])
	{
		$defaults = [
			'url'        => '',    // The URL to download.
			'method'     => 'get', // Http method, get/post/etc..
			'postdata'   => '',    // Data to send on post method.
			'enctype'    => '',    // Encoding type
			'language'   => '',    // Language in header string.
			'debug'      => false, // Show curl debug information.
			'useragent'  => '',    // User agent string.
			'cookie'     => '',    // Cookie string.
			'verifycert' => true,  /* Verify certificate authenticity?
									  Since curl does not have a verify self signed certs option,
									  you should use this instead if your cert is self signed. */
		];

		$options += $defaults;

		if (!$options['url']) {
			return false;
		}

		switch ($options['language']) {
			case 'fr':
			case 'fr-fr':
				$language = "fr-fr";
				break;
			case 'de':
			case 'de-de':
				$language = "de-de";
				break;
			case 'en-us':
				$language = "en-us";
				break;
			case 'en-gb':
				$language = "en-gb";
				break;
			case '':
			case 'en':
			default:
				$language = 'en';
		}
		$header = array();
		$header[] = "Accept-Language: " . $language;

		$ch = curl_init();

		$context = [
			CURLOPT_URL            => $options['url'],
			CURLOPT_HTTPHEADER     => $header,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_TIMEOUT        => 15
		];
		$context += self::curlSslContextOptions($options['verifycert']);
		if ($options['useragent'] !== '') {
			$context += [CURLOPT_USERAGENT => $options['useragent']];
		}
		if ($options['cookie'] !== '') {
			$context += [CURLOPT_COOKIE => $options['cookie']];
		}
		if ($options['method'] === 'post') {
			$context += [
				CURLOPT_POST       => 1,
				CURLOPT_POSTFIELDS => $options['postdata']
			];
		}
		if ($options['enctype'] !== '') {
			$context += [CURLOPT_ENCODING => $options['enctype']];
		}
		if ($options['debug']) {
			$context += [
				CURLOPT_HEADER      => true,
				CURLINFO_HEADER_OUT => true,
				CURLOPT_NOPROGRESS  => false,
				CURLOPT_VERBOSE     => true
			];
		}
		curl_setopt_array($ch, $context);

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
	 * Get human readable size string from bytes.
	 *
	 * @param int $bytes     Bytes number to convert.
	 * @param int $precision How many floating point units to add.
	 *
	 * @return string
	 */
	static public function bytesToSizeString($bytes, $precision = 0)
	{
		if ($bytes == 0) {
			return '0B';
		}
		$unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];

		return round($bytes / pow(1024, ($i = floor(log($bytes, 1024)))), $precision) . $unit[(int)$i];
	}

	public static function getCoverURL(array $options = [])
	{
		$defaults = [
			'id'     => null,
			'suffix' => '-cover.jpg',
			'type'   => '',
		];
		$options += $defaults;
		$fileSpecTemplate = '%s/%s%s';
		$fileSpec = '';

		if (!empty($options['id']) && in_array($options['type'],
				['anime', 'audio', 'audiosample', 'book', 'console', 'games', 'movies', 'music', 'preview', 'sample', 'tvrage', 'video', 'xxx']
			)
		) {
			$fileSpec = sprintf($fileSpecTemplate, $options['type'], $options['id'], $options['suffix']);
			$fileSpec = file_exists(NN_COVERS . $fileSpec) ? $fileSpec :
				sprintf($fileSpecTemplate, $options['type'], 'no', $options['suffix']);
		}

		return $fileSpec;
	}

	// Central function for sending site email.
	static public function sendEmail($to, $subject, $contents, $from)
	{
		$mail = new \PHPMailer;

		//Setup the body first since we need it regardless of sending method.
		$eol = PHP_EOL;

		$body = '<html>' . $eol;
		$body .= '<body style=\'font-family:Verdana, Verdana, Geneva, sans-serif; font-size:12px; color:#666666;\'>' . $eol;
		$body .= $contents;
		$body .= '</body>' . $eol;
		$body .= '</html>' . $eol;

		// If the mailer couldn't instantiate there's a good chance the user has an incomplete update & we should fallback to php mail()
		// @todo Log this failure.
		if (!defined('PHPMAILER_ENABLED') || PHPMAILER_ENABLED !== true || !($mail instanceof \PHPMailer)) {
			$headers = 'From: ' . $from . $eol;
			$headers .= 'Reply-To: ' . $from . $eol;
			$headers .= 'Return-Path: ' . $from . $eol;
			$headers .= 'X-Mailer: newznab' . $eol;
			$headers .= 'MIME-Version: 1.0' . $eol;
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . $eol;
			$headers .= $eol;

			return mail($to, $subject, $body, $headers);
		}

		// Check to make sure the user has their settings correct.
		if (PHPMAILER_USE_SMTP == true) {
			if ((!defined('PHPMAILER_SMTP_HOST') || PHPMAILER_SMTP_HOST === '') ||
				(!defined('PHPMAILER_SMTP_PORT') || PHPMAILER_SMTP_PORT === '')
			) {
				throw new \phpmailerException(
					'You opted to use SMTP but the PHPMAILER_SMTP_HOST and/or PHPMAILER_SMTP_PORT is/are not defined correctly! Either fix the missing/incorrect values or change PHPMAILER_USE_SMTP to false in the www/settings.php'
				);
			}

			// If the user enabled SMTP & Auth but did not setup credentials, throw an exception.
			if (defined('PHPMAILER_SMTP_AUTH') && PHPMAILER_SMTP_AUTH == true) {
				if ((!defined('PHPMAILER_SMTP_USER') || PHPMAILER_SMTP_USER === '') ||
					(!defined('PHPMAILER_SMTP_PASSWORD') || PHPMAILER_SMTP_PASSWORD === '')
				) {
					throw new \phpmailerException(
						'You opted to use SMTP and SMTP Auth but the PHPMAILER_SMTP_USER and/or PHPMAILER_SMTP_PASSWORD is/are not defined correctly. Please set them in www/settings.php'
					);
				}
			}
		}

		//Finally we can send the mail.
		$mail->isHTML(true);

		if (PHPMAILER_USE_SMTP) {
			$mail->isSMTP();

			$mail->Host = PHPMAILER_SMTP_HOST;
			$mail->Port = PHPMAILER_SMTP_PORT;

			$mail->SMTPSecure = PHPMAILER_SMTP_SECURE;

			if (PHPMAILER_SMTP_AUTH) {
				$mail->SMTPAuth = true;
				$mail->Username = PHPMAILER_SMTP_USER;
				$mail->Password = PHPMAILER_SMTP_PASSWORD;
			}
		}
		$s = new \Sites();
		$settings = $s->get();

		$site_email = $settings->email;

		$fromEmail = (PHPMAILER_FROM_EMAIL === '') ? $site_email : PHPMAILER_FROM_EMAIL;
		$fromName = (PHPMAILER_FROM_NAME === '') ? $settings->title : PHPMAILER_FROM_NAME;
		$replyTo = (PHPMAILER_REPLYTO === '') ? $site_email : PHPMAILER_REPLYTO;

		(PHPMAILER_BCC !== '') ? $mail->addBCC(PHPMAILER_BCC) : null;

		$mail->setFrom($fromEmail, $fromName);
		$mail->addAddress($to);
		$mail->addReplyTo($replyTo);
		$mail->Subject = $subject;
		$mail->Body = $body;
		$mail->AltBody = $mail->html2text($body, true);

		$sent = $mail->send();

		if (!$sent) {
			//@todo Log failed email send attempt.
			throw new \phpmailerException('Unable to send mail. Error: ' . $mail->ErrorInfo);
		}

		return $sent;
	}

	/**
	 * Return file type/info using magic numbers.
	 * Try using `file` program where available, fallback to using PHP's finfo class.
	 *
	 * @param string $path Path to the file / folder to check.
	 *
	 * @return string File info. Empty string on failure.
	 */
	static public function fileInfo($path)
	{
		$output = '';
		$magicPath = (new \Sites())->get()->magic_file_path;
		if (self::hasCommand('file') && (!self::isWin() || !empty($magicPath))) {
			$magicSwitch = empty($magicPath) ? '' : " -m $magicPath";
			$output = Utility::runCmd('file' . $magicSwitch . ' -b "' . $path . '"');

			if (is_array($output)) {
				switch (count($output)) {
					case 0:
						$output = '';
						break;
					case 1:
						$output = $output[0];
						break;
					default:
						$output = implode(' ', $output);
						break;
				}
			} else {
				$output = '';
			}
		} else {
			$fileInfo = empty($magicPath) ? new \finfo(FILEINFO_RAW) : new \finfo(FILEINFO_RAW, $magicPath);

			$output = $fileInfo->file($path);
			if (empty($output)) {
				$output = '';
			}
			$fileInfo->close();
		}

		return $output;
	}


	public function checkStatus($code)
	{
		return ($code == 0) ? true : false;
	}

	/**
	 * Convert Code page 437 chars to UTF.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function cp437toUTF($string)
	{
		return iconv('CP437', 'UTF-8//IGNORE//TRANSLIT', $string);
	}

	/**
	 * Fetches an embeddable video to a IMDB trailer from http://www.traileraddict.com
	 *
	 * @param $imdbID
	 *
	 * @return string
	 */
	public static function imdb_trailers($imdbID)
	{
		$xml = Utility::getUrl(['http://api.traileraddict.com/?imdb=' . $imdbID]);
		if ($xml !== false) {
			if (preg_match('/(<iframe.+?<\/iframe>)/i', $xml, $html)) {
				return $html[1];
			}
		}

		return '';
	}

// Check if O/S is windows.
	public static function isWindows()
	{
		return Utility::isWin();
	}

// Convert obj to array.
	public static function objectsIntoArray($arrObjData, $arrSkipIndices = [])
	{
		$arrData = [];

		// If input is object, convert into array.
		if (is_object($arrObjData)) {
			$arrObjData = get_object_vars($arrObjData);
		}

		if (is_array($arrObjData)) {
			foreach ($arrObjData as $index => $value) {
				// Recursive call.
				if (is_object($value) || is_array($value)) {
					$value = Utility::objectsIntoArray($value, $arrSkipIndices);
				}
				if (in_array($index, $arrSkipIndices)) {
					continue;
				}
				$arrData[$index] = $value;
			}
		}

		return $arrData;
	}

	/**
	 * Run CLI command.
	 *
	 * @param string $command
	 * @param bool   $debug
	 *
	 * @return array
	 */
	public static function runCmd($command, $debug = false)
	{
		$nl = PHP_EOL;
		if (Utility::isWindows() && strpos(phpversion(), "5.2") !== false) {
			$command = "\"" . $command . "\"";
		}

		if ($debug) {
			echo '-Running Command: ' . $nl . '   ' . $command . $nl;
		}

		$output = [];
		$status = 1;
		@exec($command, $output, $status);

		if ($debug) {
			echo '-Command Output: ' . $nl . '   ' . implode($nl . '  ', $output) . $nl;
		}

		return $output;
	}

	/**
	 * Remove unsafe chars from a filename.
	 *
	 * @param string $filename
	 *
	 * @return string
	 */
	public static function safeFilename($filename)
	{
		return trim(preg_replace('/[^\w\s.-]*/i', '', $filename));
	}

	public static function generateUuid()
	{
		$key = sprintf
		(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			// 16 bits for "time_mid"
			mt_rand(0, 0xffff),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand(0, 0x0fff) | 0x4000,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand(0, 0x3fff) | 0x8000,
			// 48 bits for "node"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);

		return $key;
	}

	public static function startsWith($haystack, $needle)
	{
		$length = strlen($needle);

		return (substr($haystack, 0, $length) === $needle);
	}

	public static function endsWith($haystack, $needle)
	{
		$length = strlen($needle);
		$start = $length * -1;

		return (substr($haystack, $start) === $needle);
	}

	public static function responseXmlToObject($input)
	{
		$input = str_replace('<newznab:', '<', $input);
		$xml = @simplexml_load_string($input);

		return $xml;
	}

}