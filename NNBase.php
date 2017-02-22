<?php
include_once 'nntmux/constants.php';

if ($argc > 1) {
	$constant = $argv[1];
	include_once 'nntmux/constants.php';
	if (defined($constant)) {
		exit(constant($constant));
	}

}

exit(dirname(__FILE__));

?>
