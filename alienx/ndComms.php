<?php
	class newzdashComms
	{

		public $URL;
		public $SHARED_SECRET;

		public function init()
		{
			$varnames = null;
			$vardata = null;
			$path = dirname(__FILE__);
			if ( file_exists($path . "/../defaults.sh") ) {
				$varnames = shell_exec("cat " . $path . "/../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
				$vardata =  shell_exec("cat " . $path . "/../defaults.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
			}
			if ( file_exists($path . '/defaults.sh') ) {
				$varnames = shell_exec("cat " . $path . "/defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
				$vardata =  shell_exec("cat " . $path . "/defaults.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
			}
			if ( ($vardata == null) || ($varnames == null) )
				die();

			$varnames = explode("\n", $varnames);
			$vardata = explode("\n", $vardata);
			$array = array_combine($varnames, $vardata);
			unset ( $array[''] );

			$this->URL = $array['NEWZDASH_URL'];
			$this->SHARED_SECRET = $array['NEWZDASH_SHARED_SECRET'];

			if ( $this->URL == "" )
			{
				return false;
			}else{
				return true;
			}
		}

		public function broadcast ( $arguments )
		{
			if ( count($arguments) < 2 )
				die ( "Invalid arguments count given in newzdash comms class\n" );

			$pane = $arguments[1];
			$state = $arguments[2];

			//pname, pstate, ndsharedsecret

			$URL = $this->URL . "/tmuxapi.php?pname=" . urlencode($pane) . "&pstate=" . urlencode($state) . "&ndsharedsecret=" . urlencode($this->SHARED_SECRET);
			$handle=fopen($URL, "r");
			$ret = fread($handle, 8192);
			fclose($handle);
			switch ( $ret )
			{
				case "ok":
					printf ( "\n[NewzDash] " . $pane . " has been " . $state . "!\n" );
					break;

				case "ss":
					printf( "NewzDash could not accept our communcation:\nShared Secret Incorrect!\n");
					break;

				default:
					printf("NewzDash could not accept our communication:\nSomething went wrong!\n");
			}
		}
	}
?>
