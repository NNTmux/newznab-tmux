<?php

    # We should make sure that that the NEWZNAB_HOME value provided is valid
    # That is, there should be a config.php in the NEWZNAB_HOME/www directory
    if (file_exists($_POST['newznab_home']."/www/config.php"))
    {
	$newconfig = "";
        $newconfig .= "<?php\n";

        $newconfig .= "define('NEWZNAB_URL','".$_POST['newznab_url']."');\n";
        $newconfig .= "define('NEWZNAB_HOME','".$_POST['newznab_home']."');\n";

	if (isset($_POST['show_movies']))
	{
	    $newconfig .= "define('SHOW_MOVIES','".$_POST['show_movies']."');\n";
	}
	else
	{
	    $newconfig .= "define('SHOW_MOVIES','');\n";
	}
	if (isset($_POST['show_tv']))
	{
	    $newconfig .= "define('SHOW_TV','".$_POST['show_tv']."');\n";
	}
	else
	{
	    $newconfig .= "define('SHOW_TV','');\n";
	}
	if (isset($_POST['show_music']))
	{
	    $newconfig .= "define('SHOW_MUSIC','".$_POST['show_music']."');\n";
	}
	else
	{
	    $newconfig .= "define('SHOW_MUSIC','');\n";
	}
	if (isset($_POST['show_games']))
	{
	    $newconfig .= "define('SHOW_GAMES','".$_POST['show_games']."');\n";
	}
	else
	{
	    $newconfig .= "define('SHOW_GAMES','');\n";
	}
	if (isset($_POST['show_pc']))
	{
	    $newconfig .= "define('SHOW_PC','".$_POST['show_pc']."');\n";
	}
	else
	{
	    $newconfig .= "define('SHOW_PC','');\n";
	}
        if (isset($_POST['show_other']))
	{
	    $newconfig .= "define('SHOW_OTHER','".$_POST['show_other']."');\n";
	}
	else
	{
	    $newconfig .= "define('SHOW_OTHER','');\n";
	}
	if (isset($_POST['show_xxx']))
	{
	    $newconfig .= "define('SHOW_XXX','".$_POST['show_xxx']."');\n";
	}
	else
	{
	    $newconfig .= "define('SHOW_XXX','');\n";
	}

	if (isset($_POST['show_processing']))
	{
	    $newconfig .= "define('SHOW_PROCESSING','".$_POST['show_processing']."');\n";
	}
	else
	{
	    $newconfig .= "define('SHOW_PROCESSING','');\n";
	}
	if (isset($_POST['show_rpc']))
	{
	    $newconfig .= "define('SHOW_RPC','".$_POST['show_rpc']."');\n";
	}
	else
	{
	    $newconfig .= "define('SHOW_RPC','');\n";
	}
	if (isset($_POST['show_rpg']))
	{
	    $newconfig .= "define('SHOW_RPG','".$_POST['show_rpg']."');\n";
	}
	else
	{
	    $newconfig .= "define('SHOW_RPG','');\n";
	}


        $newconfig .= "include(NEWZNAB_HOME.'/www/config.php');\n";

        $newconfig .= "?>\n";

        $newconfigfile=__DIR__ . "/config.php";

        file_put_contents($newconfigfile, $newconfig);


        print '<div class="alert alert-info" id="savemessage"><strong>Success!</strong> The setttings have been saved!</div>';

        print '<script type="text/javascript">
            window.setTimeout(function() {
            $("#savemessage").fadeTo(500, 0).slideUp(500, function(){
            $(this).remove();
            });
            }, 5000);
            </script>';
    }
    else
    {
        printf ('<div class="alert alert-error" id="savemessage"><strong>Error!</strong> The value for the Newznab-tmux Directory does not appear to be valid. I cannot find the Newznab-tmux config.php file at: %s</div>',
            $_POST['newznab_home'].'/www/config.php');

        print '<script type="text/javascript">
            window.setTimeout(function() {
            $("#savemessage").fadeTo(500, 0).slideUp(500, function(){
            $(this).remove();
            });
            }, 5000);
            </script>';
    }


?>

