<?php
/*
    This script requries 3 arguments:

    1) Pane Name that you are communicating
    2) The panes state (stopped/started)
    3) The shared secret between Jonnyboy's tmux Scripts and AlienX's newzdash.
    4) NewzDash URL

    Example:
    tmux_to_newzdash.php update_binaries started 3nyex2moe23cn732 http://mydashboard.mynninstall.net

*/

DEFINE ( 'ARG_PNAME', 1 );
DEFINE ( 'ARG_PSTATE', 2 );
DEFINE ( 'ARG_NDSHAREDSECRET', 3 );
DEFINE ( 'ARG_NDURL', 4 );

//printf( "Arguments Given: %s\n", count($argv));

if ( count($argv) == 5 )
{
    //0 - Pane Name | 1 - Pane State | 2 - Shared Key | 3 - NewzDash URL
    try
    {
        //printf ( "Telling NewzDash that this pane has started..." );
        $URL = "http://" . $argv[ARG_NDURL] . "/tmuxapi.php?pname=" . urlencode($argv[ARG_PNAME]) . "&pstate=" . urlencode($argv[ARG_PSTATE]) . "&ndsharedsecret=" . urlencode($argv[ARG_NDSHAREDSECRET]);
        $handle=fopen($URL, "r");
        $ret = fread($handle, 8192);
        fclose($handle);
        switch ( $ret )
        {
            case "ok":
                printf ( "\n{$argv[ARG_PNAME]} has been {$argv[ARG_PSTATE]}!" );
                break;

            case "ss":
                //printf( "NewzDash could not accept our communcation:\nShared Secret Incorrect!");
                break;

            default:
                //printf("NewzDash could not accept our communication:\nSomething went wrong!");
        }
    }Catch ( Exception $ex )
    {
        //printf ( "Error while trying to contact NewzDash:\n%s", $ex->message());
    }
}else{
    //printf("Not enough or too many arguments given to script, check your\narguments again.\n");
    //print_r($argv);
}
?>
