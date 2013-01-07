<?php
require_once("innodb/binaries.php");
require_once("framework/db.php");
require_once(WWW_DIR."/lib/Net_NNTP/NNTP/Client.php");

/**
 * This class extends the standard PEAR NNTP class with some extra features.
 */
class Nntp extends Net_NNTP_Client
{    
	/**
	 * Start an NNTP connection.
	 */
	function doConnect() 
	{
		$enc = false;
		if (defined("NNTP_SSLENABLED") && NNTP_SSLENABLED == true)
			$enc = 'ssl';

		$ret = $this->connect(NNTP_SERVER, $enc, NNTP_PORT);
		if(PEAR::isError($ret))
		{
			echo "Cannot connect to server ".NNTP_SERVER.(!$enc?" (nonssl) ":"(ssl) ").": ".$ret->getMessage();
			die();
		}
		if(!defined(NNTP_USERNAME) && NNTP_USERNAME!="" )
		{
			$ret2 = $this->authenticate(NNTP_USERNAME, NNTP_PASSWORD);
			if(PEAR::isError($ret2)) 
			{
				echo "Cannot authenticate to server ".NNTP_SERVER.(!$enc?" (nonssl) ":" (ssl) ")." - ".NNTP_USERNAME." (".$ret2->getMessage().")";
				die();
			}
		}
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
		   //echo 'NntpPrc : Error fetching part number '.$partMsgId.' in '.$groupname.' (Server response: '. $body->getMessage().')\n';
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

		echo $message . "\n";
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
			   //echo 'NntpPrc : Error fetching '.$messageID.' in '.$groupname.' (Server response: '. $body->getMessage().')';
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
			return false;
		
		$summary = $this->selectGroup($binary['groupname']);
		$message = $dec = '';

		if (PEAR::isError($summary)) 
		{
			echo "NntpPrc : ".substr($summary->getMessage(), 0, 30)."\n";
			return false;
		}

		$resparts = $db->query(sprintf("SELECT size, partnumber, messageID FROM parts WHERE binaryID = %d ORDER BY partnumber", $binaryId));
		
		//
		// Dont attempt to download nfos which are larger than one part.
		//
		if (sizeof($resparts) > 1 && $isNfo === true)
		{
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
	 * Get XZVER for a range of NNTP messages.
	 */
	function getXOverview($range, $_names = true, $_forceNames = true)
    {
    	// Fetch overview from server
    	$overview = $this->cmdXZver($range);
    	if (PEAR::isError($overview)) {
    	    return $overview;
    	}

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
	
	/**
	 * Send XZVER command over NNTP connection.
	 */
	function cmdXZver($range = NULL)
	{
	    if (is_null($range)) 
	        $command = 'XZVER';
	    else 
	        $command = 'XZVER ' . $range;
	
	    $response = $this->_sendCommand($command);

	    switch ($response) {
	    case 224: // 224, RFC2980: 'Overview information follows'
	        $data = $this->_getTextResponse();

			//de-yenc
			$dec = $this->decodeYenc(implode("\r\n", $data));
			if (!$dec) 
			{
				$this->throwError("yenc decode failure");
			}
			
			//inflate deflated string
			$data = explode("\r\n", gzinflate($dec));

	        foreach ($data as $key => $value) 
	            $data[$key] = explode("\t", ltrim($value));
	
	        return $data;
	        break;
	    case 412: // 412, RFC2980: 'No news group current selected'
	        $this->throwError("No news group current selected ({$this->_currentStatusResponse()})", $response);
	        break;
	    case 420: // 420, RFC2980: 'No article(s) selected'
	        $this->throwError("No article(s) selected ({$this->_currentStatusResponse()})", $response);
	        break;
	    case 502: // 502 RFC2980: 'no permission'
	        $this->throwError("No permission ({$this->_currentStatusResponse()})", $response);
	        break;
	    case 500: // 500  RFC2980: 'unknown command'
	        $this->throwError("XZver not supported ({$this->_currentStatusResponse()})", $response);
	        break;
	    default:
	        return $this->_handleUnexpectedResponse($response);
	    }
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
}
