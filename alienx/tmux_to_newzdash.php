<?php
/*
    This script requries 2 arguments:

    1) Pane Name that you are communicating
    2) The panes state (stopped/started)

    Example:
    tmux_to_newzdash.php update_binaries started

*/

	require_once ( "ndComms.php" );

	DEFINE ( 'ARG_PNAME', 1 );
	DEFINE ( 'ARG_PSTATE', 2 );
	DEFINE ( 'ARG_NDSHAREDSECRET', 3 );
	DEFINE ( 'ARG_NDURL', 4 );

	$ndComms = new newzdashComms();
	//echo "Pane State Changed [" . $argv[2] . "]\n" . date("D jS M Y - H:i:s") . "";
	echo "[" . $argv[2] . "] " . date("D jS M Y - H:i:s") . "\n";
        if ( !$ndComms->init() )
		die();

	$ndComms->broadcast($argv);

?>
