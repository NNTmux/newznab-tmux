<?php
require_once(WWW_DIR."/lib/binaries.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/Net_NNTP/NNTP/Client.php");

/**
 * This class extends the standard PEAR NNTP class with some extra features.
 */
class Nntp extends Net_NNTP_Client
{    
	public $XFCompression = false;
	/**
	 * Start an NNTP connection.
	 */
	function doConnect($comp=null, $attempt=1) 
	{
		if ($this->_isConnected()) {
			return true;
		}

		// Attempt to connect up to 5 times before giving up.
		$maxAttempts = 5;
		
		$connected = true;
		
		$s = new Sites();
		$site = $s->get();
		$this->compressedHeaders = ($site->compressedheaders == "1") ? true : false;
		$enc = false;
		if (defined("NNTP_SSLENABLED") && NNTP_SSLENABLED == true)
			$enc = 'ssl';

		$ret = $this->connect(NNTP_SERVER, $enc, NNTP_PORT);
		if(PEAR::isError($ret))
		{
			$err = "Cannot connect to server ".NNTP_SERVER.(!$enc?" (nonssl) ":"(ssl) ").": ". $ret->getMessage();
			echo $err;
			$connected = false;
		}
		if(!defined(NNTP_USERNAME) && NNTP_USERNAME!="" )
		{
			$ret2 = $this->authenticate(NNTP_USERNAME, NNTP_PASSWORD);
			if(PEAR::isError($ret2)) 
			{
				$err = "Cannot authenticate to server ".NNTP_SERVER.(!$enc?" (nonssl) ":" (ssl) ")." - ". NNTP_USERNAME." (".$ret2->getMessage().")";
				echo $err;
				$connected = false;
			}
		}
		if ($this->compressedHeaders)
		{
			if ($comp==true)
			{
				$response = $this->_sendCommand('XFEATURE COMPRESS GZIP');
				if (PEAR::isError($response) || $response != 290) 
					{
						//echo "NNTP: XFeature not supported.\n";
					}
				else
					{
			    	$this->enableXFCompression(); 
					}
			}
		}
		if ($attempt < $maxAttempts && !$connected) {
			sleep(5);
			$connected = $this->doConnect($attempt+1);
		}
		
		if (!$connected && $attempt == 1) {
			echo "\nTried to connect ".$maxAttempts." times, but couldn't. Check your settings and connection.\n";
		}
		
		return $connected;
	}	
	/**
	 * End an NNTP connection.
	 */
	function doQuit() 
	{
		$this->quit();
	}
	
	/**
	 * Retrieve an NNTP message and decode it.
	 */
	function getMessage($groupname, $partMsgId)
	{
		$summary = $this->selectGroup($groupname);
		$message = $dec = '';

		if (PEAR::isError($summary)) 
		{
			echo "NntpPrc : ".substr($summary->getMessage(), 0, 30)."\n";
			return false;
		}
		$body = $this->getBody('<'.$partMsgId.'>', true);
		if (PEAR::isError($body)) 
		{
            printf("NntpPrc : Error fetching part number %s in %s (Server response: %s)\n", $partMsgId, $groupname, $body->getMessage());
            return false;
		}
		$message = $this->decodeYenc($body);
		if (!$message) 
		{
			//
			// Yenc decode failed
			//
			return false;
		}
		return $message;
	}
	
	/**
	 * Retrieve a series of NNTP messages and decode them.
	 */
	function getMessages($groupname, $msgIds)
	{		
		$summary = $this->selectGroup($groupname);
		$message = $dec = '';

		if (PEAR::isError($summary)) 
		{
			echo "NntpPrc : ".substr($summary->getMessage(), 0, 30)."\n";
			return false;
		}
		foreach($msgIds as $msgId) 
		{
			$messageID = '<'.$msgId.'>';
			$body = $this->getBody($messageID, true);
			if (PEAR::isError($body)) 
			{
                printf("NntpPrc : Error fetching part number %s in %s (Server response: %s)\n", $messageID, $groupname, $body->getMessage());
                return false;
			}
			$dec = $this->decodeYenc($body);
			if (!$dec) 
			{
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
			echo "NntpPrc : ".substr($summary->getMessage(), 0, 30)."\n";
			return false;
		}
		$resparts = $db->query(sprintf("SELECT size, partnumber, messageID FROM parts WHERE binaryID = %d ORDER BY partnumber", $binaryId));
		
		// Dont attempt to download nfos which are larger than one part.
		if (sizeof($resparts) > 1 && $isNfo === true)
		{
			echo "NntpPrc : Error Nfo is too large... skipping.\n";
			return false;
		}
		foreach($resparts as $part) 
		{
			$messageID = '<'.$part['messageID'].'>';
			$body = $this->getBody($messageID, true);
			if (PEAR::isError($body)) 
			{
                printf("NntpPrc : Error fetching part number %s in %s (Server response: %s)\n", $part['messageID'], $binary['groupname'], $body->getMessage());
				return false;
			}
			$dec = $this->decodeYenc($body);
			if (!$dec) 
			{
                printf("NntpPrc: Unable to decode body of binary: %s\n", $binaryId);
                
				// Yenc decode failed		
				return false;
			}
			$message .= $dec;
		}
		return $message;
	}

	/**
	 * Decode a yenc encoded string.
	 */	
	function decodeYenc($yencodedvar)
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
	 * Get XZVER for a range of NNTP messages.
	 */
	function getXOverview($range, $_names = true, $_forceNames = true)
    {
		$overview = $this->cmdXOver($range);
    	if (PEAR::isError($overview)) {
    	    return $overview; }

    	// Use field names from overview format as keys?
    	if ($_names) 
    	{
    	    // Already cached?
    	    if (is_null($this->_overviewFormatCache)) {
    	    	// Fetch overview format
    	        $format = $this->getOverviewFormat($_forceNames, true);
    	        if (PEAR::isError($format)){
    	            return $format;
    	        }
    	    	// Prepend 'Number' field
    	    	$format = array_merge(array('Number' => false), $format);
				
    	    	// Cache format
    	        $this->_overviewFormatCache = $format;
    	    } 
    	    else 
    	    {
    	        $format = $this->_overviewFormatCache;
    	    }
    	    // Loop through all articles
            foreach ($overview as $key => $article) 
            {	
            	if (sizeof($format) == sizeof($article))
            	{
            		//Replace overview using $format as keys, $article as values
					$overview[$key] = array_combine(array_keys($format), $article);
					
					// If article prefixed by field name, remove it
					foreach($format as $fkey=>$fval) 
					{
						if ($fval === true) 
						{
							$overview[$key][$fkey] = trim(str_replace($fkey.':', '', $overview[$key][$fkey]));
						}
					}
				}
    	    }
    	}
    	switch (true)
    	{
    	    // Expect one article
    	    case is_null($range);
    	    case is_int($range);
            case is_string($range) && ctype_digit($range):
    	    case is_string($range) && substr($range, 0, 1) == '<' && substr($range, -1, 1) == '>':
    	        if (count($overview) == 0) {
    	    	    return false;
    	    	} else {
    	    	    return reset($overview);
    	    	}
    	    	break;

    	    // Expect multiple articles
    	    default:
    	    	return $overview;
    	}
    }
	
    // }}}
    // {{{ cmdXZver()
	/*
	 * Based on code from http://wonko.com/software/yenc/, but
	 * simplified because XZVER and the likes don't implement
	 * yenc properly 
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
     * Fetch message header from message number $first until $last
     * The format of the returned array is:
     * $messages[message_id][header_name]
     * @param optional string $range articles to fetch
     * @return mixed (array) nested array of message and there headers on success or (object) pear_error on failure
     * @access protected
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
			echo "Xfeature compression not supported!\n";
			return false;
		}

		$this->XFCompression = true;
		echo "XFeature compression enabled\n";
		return true;
	}

	/**
	 * Override to intercept any Xfeature compressed responses.
	 */
	function _getTextResponse()
	{
		if ($this->XFCompression && isset($this->_currentStatusResponse[1])
			&& stripos($this->_currentStatusResponse[1], 'COMPRESS=GZIP') !== false)
		{
			return $this->_getXFCompressedTextResponse();
		}
		return parent::_getTextResponse();
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
					echo "bytes recived: ";
					echo $totalbytesreceived;
					echo "\r";
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
						echo "\n";
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
}
