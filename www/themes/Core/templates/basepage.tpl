<!DOCTYPE html>
<!--[if IE 8]> <html lang="en" class="ie8"> <![endif]-->
<!--[if IE 9]> <html lang="en" class="ie9"> <![endif]-->
<!--[if !IE]><!--> <html lang="en"> <!--<![endif]-->
<!-- BEGIN HEAD -->
<head>
    {literal}
    <script>
        /* <![CDATA[ */
        var WWW_TOP = "{/literal}{$smarty.const.WWW_TOP}{literal}";
        var SERVERROOT = "{/literal}{$serverroot}{literal}";
        var UID = "{/literal}{if $loggedin=="true"}{$userdata.id}{else}{/if}{literal}";
        var RSSTOKEN = "{/literal}{if $loggedin=="true"}{$userdata.rsstoken}{else}{/if}{literal}";
        /* ]]> */
    </script>
    {/literal}
    <meta charset="UTF-8" />
    <title>{$page->meta_title}{if $page->meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
     <meta content="width=device-width, initial-scale=1.0" name="viewport" />
	<meta content="" name="description" />
	<meta content="" name="author" />
     <!--[if IE]>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <![endif]-->
    <!-- GLOBAL STYLES -->
    <!-- Bootstrap 3.3.6 -->
    <link href="{$smarty.const.WWW_THEMES}/shared/libs/bootstrap/v3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="{$smarty.const.WWW_THEMES}/{$theme}/assets/css/main.css" />
    <link rel="stylesheet" href="{$smarty.const.WWW_THEMES}/{$theme}/assets/css/theme.css" />
    <link rel="stylesheet" href="{$smarty.const.WWW_THEMES}/{$theme}/assets/css/MoneAdmin.css" />
    <!-- Font Awesome Icons -->
    <link href="{$smarty.const.WWW_THEMES}/shared/libs/bootstrap/v3/css/font-awesome.min.css" rel="stylesheet" type="text/css"/>
    <!--END GLOBAL STYLES -->

    <!-- PAGE LEVEL STYLES -->
    <link href="{$smarty.const.WWW_THEMES}/{$theme}/assets/css/layout2.css" rel="stylesheet" />
       <link href="{$smarty.const.WWW_THEMES}/{$theme}/assets/plugins/flot/examples/examples.css" rel="stylesheet" />
       <link rel="stylesheet" href="{$smarty.const.WWW_THEMES}/{$theme}/assets/plugins/timeline/timeline.css" />
    <!-- END PAGE LEVEL  STYLES -->
     <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

    <!-- END HEAD -->

    <!-- BEGIN BODY -->
<body class="padTop53 " >

    <!-- MAIN WRAPPER -->
    <div id="wrap" >


        <!-- HEADER SECTION -->
        <div id="top">

            <nav class="navbar navbar-inverse navbar-fixed-top " style="padding-top: 10px;">
                <a data-original-title="Show/Hide Menu" data-placement="bottom" data-tooltip="tooltip" class="accordion-toggle btn btn-primary btn-sm visible-xs" data-toggle="collapse" href="#menu" id="menu-toggle">
                    <i class="icon-align-justify"></i>
                </a>
                <!-- LOGO SECTION -->
                <header class="navbar-header">

                    <a href="{$site->home_link}" class="navbar-brand">
                    <img src="assets/img/logo.png" alt="" />

                        </a>
                </header>
                <!-- END LOGO SECTION -->
                <ul class="nav navbar-top-links navbar-right">
                    {$header_menu}
                    <!--ADMIN SETTINGS SECTIONS -->
                    <ul class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                            <!-- The user image in the navbar-->
                            {if ($loggedin)=="true"}
                            <img src="{$smarty.const.WWW_THEMES}/shared/images/userimage.png"
                                 class="user-image" alt="User Image"/>
                            <!-- hidden-xs hides the username on small devices so only the image appears. -->
                            <span class="hidden-xs">{$userdata.username}</span>
                        </a>
                        <li class="dropdown-menu dropdown-user">
                            <li class="col-xs-12 text-center">
                                <a href="{$smarty.const.WWW_TOP}/cart"><i class="fa fa-shopping-basket"></i> My Download Basket</a></li>
                            <li class="col-xs-12 text-center">
                                <a href="{$smarty.const.WWW_TOP}/queue"><i class="fa fa-list-alt"></i> My Queue</a></li>
                            <li class="col-xs-12 text-center">
                                <a href="{$smarty.const.WWW_TOP}/mymovies"><i class="fa fa-film"></i> My Movies</a></li>
                            <li class="col-xs-12 text-center">
                                <a href="{$smarty.const.WWW_TOP}//myshows"><i class="fa fa-television"></i> My Shows</a></li>
                            <li class="col-xs-12 text-center">
                                <a href="{$smarty.const.WWW_TOP}/profileedit"><i class="fa fa-cog fa-spin"></i> Account Settings</a></li>
                            {if isset($isadmin)}
                                <li class="col-xs-12 text-center">
                                    <a href="{$smarty.const.WWW_TOP}/admin"><i class="fa fa-cogs fa-spin"></i> Admin</a></li>
                            {/if}
                        </li>
                    </ul>
                    {else}
                    <li><a href="{$smarty.const.WWW_TOP}/login"><i class="fa fa-lock"></i><span> Login</span></a></li>
                    <li><a href="{$smarty.const.WWW_TOP}/register"><i class="fa fa-bookmark-o"></i><span> Register</span></a></li>
                    {/if}
                    <!--END ADMIN SETTINGS -->
                </ul>
            </nav>

        </div>
        <!-- END HEADER SECTION -->

        <!-- MENU SECTION -->
       <div id="left" >
            <div class="media user-media well-small">
                {if ($loggedin)=="true"}
                        <div class="pull-left image">
                            <img src="{$smarty.const.WWW_THEMES}/shared/images/user-loggedin.png" class="img-circle"
                                 alt="User Image"/>
                        </div>
                        <div class="pull-left info">
                            <p>{$userdata.username}</p>
                            <a href="#"><i class="fa fa-circle text-success"></i><span>{$userdata.rolename}</span></a>
                        </div>
                    <!-- search form -->
                    <form id="headsearch_form" action="{$smarty.const.WWW_TOP}/search/" method="get">
                        <input id="headsearch" name="search" value="{if $header_menu_search == ""}Search...{else}{$header_menu_search|escape:"htmlall"}{/if}" class="form-control" type="text" tabindex="1$" />
                        <div class="row small-gutter-left" style="padding-top:3px;">
                            <div class="col-md-8">
                                <select id="headcat" name="t" class="form-control" data-search="true">
                                    <option class="grouping" value="-1">All</option>
                                    {foreach from=$parentcatlist item=parentcat}
                                        <option {if $header_menu_cat==$parentcat.id}selected="selected"{/if} value="{$parentcat.id}"> [{$parentcat.title}]</option>
                                        {foreach from=$parentcat.subcatlist item=subcat}
                                            <option {if $header_menu_cat==$subcat.id}selected="selected"{/if} value="{$subcat.id}">&nbsp;&nbsp;&nbsp; > {$subcat.title}</option>
                                        {/foreach}
                                    {/foreach}
                                </select>
                            </div>
                            <div class="col-md-3 small-gutter-left">
                                <input id="headsearch_go" type="submit" class="btn btn-dark" style="margin-top:0px; margin-left:4px;" value="Go"/>
                            </div>
                        </div>
                    </form>
                {/if}
                <!-- /.search form -->
            </div>

            <ul id="menu" class="collapse">
                <li class="panel active">
                <a href="{$site->home_link}"><i class="fa fa-home"></i><span> Home</span> <span class="fa arrow"></span></a>
                </li>
                {if ($loggedin)=="true"}
                <li class="panel ">
                    <a href="#"><i class="fa fa-list-ol"></i><span> Browse</span></a>
                    <ul class="collapse" id="component-nav">
                        <li><a href="{$smarty.const.WWW_TOP}/newposterwall"><i
                                        class="fa fa-fire"></i><span> New Releases</span></a></li>
                        <li><a href="{$smarty.const.WWW_TOP}/console"><i
                                        class="fa fa-gamepad"></i><span> Console</span></a></li>
                        <li><a href="{$smarty.const.WWW_TOP}/movies"><i
                                        class="fa fa-film"></i><span> Movies</span></a></li>
                        <li><a href="{$smarty.const.WWW_TOP}/music"><i
                                        class="fa fa-music"></i><span> Music</span></a></li>
                        <li><a href="{$smarty.const.WWW_TOP}/games"><i
                                        class="fa fa-gamepad"></i><span> Games</span></a></li>
                        <li><a href="{$smarty.const.WWW_TOP}/series"><i
                                        class="fa fa-television"></i><span> TV</span></a></li>
                        <li><a href="{$smarty.const.WWW_TOP}/xxx"><i class="fa fa-venus-mars"></i><span> Adult</span></a></li>
                        <li><a href="{$smarty.const.WWW_TOP}/books"><i class="fa fa-book"></i><span> Books</span></a></li>
                        <li><a href="{$smarty.const.WWW_TOP}/browse"><i
                                        class="fa fa-list-ul"></i><span> Browse All Releases</span></a></li>
                        <li><a href="{$smarty.const.WWW_TOP}/predb"><i class="fa fa-list-ol"></i><span> PreDb</span></a>
                        </li>
                    </ul>
                </li>
                <li class="panel ">
                    <a href="#"><i class="fa fa-list-ol"></i><span> Articles & Links</span></a>
                    <ul class="collapse" id="form-nav">
                        <li><a href="{$smarty.const.WWW_TOP}/contact-us"><i
                                        class="fa fa-envelope-o"></i><span> Contact</span> <span
                                        class="fa arrow"></span></a></li>
                        {if ($loggedin)=="true"}
                        <li><a href="{$smarty.const.WWW_TOP}/search"><i class="fa fa-search"></i> Search</a></li>
                        <li><a href="{$smarty.const.WWW_TOP}/rss"><i class="fa fa-rss"></i> RSS Feeds</a></li>
                        <li><a href="{$smarty.const.WWW_TOP}/apihelp"><i class="fa fa-cloud"></i> API</a></li>
                            <li><a href="{$smarty.const.WWW_TOP}/logout"><i class="fa fa-unlock"></i><span> Sign out</span></a></li>
                        {/if}
                    </ul>
                </li>
            </ul>
        </div>
        <!--END MENU SECTION -->



        <!--PAGE CONTENT -->
        <div id="content">

            <div class="inner" style="min-height: 700px;">
                <div class="row">
                    {$page->content}
                </div>
            </div>
        </div>
        <!--END PAGE CONTENT -->
    </div>

    <!--END MAIN WRAPPER -->

    <!-- FOOTER -->
    <div id="footer">
        <strong>Copyright &copy; {$smarty.now|date_format:"%Y"} <a
                    href="https://github.com/DariusIII/">newznab-tmux</a>.</strong> This software is open source,
        released under the GPLv2 license
    </div>
    <!--END FOOTER -->


    <!-- GLOBAL SCRIPTS -->
    <script src="{$smarty.const.WWW_THEMES}/{$theme}/assets/plugins/jquery-2.0.3.min.js"></script>
    <!-- Bootstrap 3.3.6 JS -->
    <script src="{$smarty.const.WWW_THEMES}/shared/libs/bootstrap/v3/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="{$smarty.const.WWW_THEMES}/{$theme}/assets/plugins/modernizr-2.6.2-respond-1.1.0.min.js"></script>
    <!-- END GLOBAL SCRIPTS -->

    <!-- PAGE LEVEL SCRIPTS -->
    <script src="{$smarty.const.WWW_THEMES}/{$theme}/assets/plugins/flot/jquery.flot.js"></script>
    <script src="{$smarty.const.WWW_THEMES}/{$theme}/assets/plugins/flot/jquery.flot.resize.js"></script>
    <script src="{$smarty.const.WWW_THEMES}/{$theme}/assets/plugins/flot/jquery.flot.time.js"></script>
     <script  src="{$smarty.const.WWW_THEMES}/{$theme}/assets/plugins/flot/jquery.flot.stack.js"></script>
    <script src="{$smarty.const.WWW_THEMES}/{$theme}/assets/js/for_index.js"></script>

    <!-- END PAGE LEVEL SCRIPTS -->


</body>

    <!-- END BODY -->
</html>
