<!DOCTYPE html>
<html lang="en">

<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=9" />
	<meta name="keywords" content="{$page->meta_keywords}{if $page->meta_keywords != "" && $site->metakeywords != ""},{/if}{$site->metakeywords}" />
	<meta name="description" content="{$page->meta_description}{if $page->meta_description != "" && $site->metadescription != ""} - {/if}{$site->metadescription}" />
	<meta name="robots" content="noindex,nofollow"/>
	<meta name="application-name" content="newznab-{$site->version}" />
	<title>{$page->meta_title}{if $page->meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
	<meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

{if $loggedin == "true"}
	<link rel="alternate" type="application/rss+xml" title="{$site->title} Full Rss Feed" href="{$smarty.const.WWW_TOP}/rss?t=0&amp;dl=1&amp;i={$userdata.id}&amp;r={$userdata.rsstoken}" />
{/if}

{if $site->google_adsense_acc != ''}
	<link href="http://www.google.com/cse/api/branding.css" rel="stylesheet" type="text/css" media="screen" />
{/if}
	<!-- Newposterwall -->
	<link href="{$smarty.const.WWW_THEMES}/shared/css/posterwall.css" rel="stylesheet" type="text/css" media="screen" />
	<link href="{$smarty.const.WWW_THEMES}/shared/assets/bootstrap-3.x/dist/css/bootstrap.min.css" rel="stylesheet" type="text/css" media="screen" />
	<link href="{$smarty.const.WWW_THEMES}/shared/assets/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" media="screen" />
	<link href="{$smarty.const.WWW_THEMES}/{$theme}/styles/extra.css" rel="stylesheet" type="text/css" media="screen" />
	<link href="{$smarty.const.WWW_THEMES}/{$theme}/styles/jquery.pnotify.default.css" rel="stylesheet" type="text/css" media="screen" />
	<link href="{$smarty.const.WWW_THEMES}/{$theme}/styles/style.css" rel="stylesheet" type="text/css" media="screen" />
	<link href="{$smarty.const.WWW_THEMES}/{$theme}/styles/bootstrap.cyborg.css" rel="stylesheet" type="text/css" media="screen" />
	<link href="{$smarty.const.WWW_THEMES}/{$theme}/styles/bootstrap-fixes.css" rel="stylesheet" type="text/css" media="screen" />

	<!-- FAVICON -->
	<link rel="search" type="application/opensearchdescription+xml" href="/opensearch" title="{$site->title|escape}" />
	<link rel="shortcut icon" type="image/ico" href="{$smarty.const.WWW_THEMES}/shared/images/favicon.ico"/>

	<!-- Javascripts -->
	<!-- jQuery 3.1.0 -->
	<script src="{$smarty.const.WWW_THEMES}/shared/assets/jquery-2.2.x/dist/jquery.min.js" type="text/javascript"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/jquery-migrate-1.4.x/jquery-migrate.min.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/colorbox/jquery.colorbox-min.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/js/jquery.qtip2.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/autosize/dist/autosize.min.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/js/sorttable.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js"></script>
	<!-- Added the Bootstrap JS -->
	<script type="text/javascript"
			src="{$smarty.const.WWW_THEMES}/shared/assets/bootstrap-3.x/dist/js/bootstrap.min.js"></script>
	<!-- tinymce editor -->
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/tinymce-builded/js/tinymce/tinymce.min.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/{$theme}/scripts/utils.js"></script>
	<!-- Pines Notify -->
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/js/jquery.pnotify.js"></script>
	<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
	<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
	<!--[if lt IE 9]>
	<script src="{$smarty.const.WWW_THEMES}/shared/assets/html5shiv/dist/html5shiv.min.js"></script>
	<script src="{$smarty.const.WWW_THEMES}/shared/assets/respond/dest/respond.min.js"></script>
	<![endif]-->
	{literal}
	<script>
		/* <![CDATA[ */
		var WWW_TOP = "{/literal}{$smarty.const.WWW_TOP}{literal}";
		var SERVERROOT = "{/literal}{$serverroot}{literal}";
		var UID = "{/literal}{if $loggedin == "true"}{$userdata.id}{else}{/if}{literal}";
		var RSSTOKEN = "{/literal}{if $loggedin == "true"}{$userdata.rsstoken}{else}{/if}{literal}";
		/* ]]> */
	</script>
	{/literal}
	{$page->head}
</head>
<body {$page->body}>
<!-- NAV
	================================================== -->
	<!-- If you want the navbar "white" remove Navbar-inverse -->
	<div class="navbar navbar-inverse navbar-fixed-top">
		<div class="container" style="padding-left:30px; padding-right:30px;">
			<div class="container">
						{if $loggedin == "true"}
							{$header_menu}
						{/if}
					{if $loggedin == "true"}
						    <div class="btn-group">
								<a class="btn" href="{$smarty.const.WWW_TOP}/profile"><i class="icon-user icon-white"></i> {$userdata.username} </a>
								<a class="btn dropdown-toggle" data-toggle="dropdown" href="#"><span class="caret"></span></a>
								<ul class="dropdown-menu">
										<li><a href="{$smarty.const.WWW_TOP}/profile"><i class="icon-user icon-white"></i> Profile</a></li>
										<li class="divider"></li>
										<li><a href="{$smarty.const.WWW_TOP}/mymovies"><i class="icon-hdd icon-white"></i> My Movies</a></li>
										<li><a href="{$smarty.const.WWW_TOP}/myshows"><i class="icon-hdd icon-white"></i> My Shows</a></li>
										<li class="divider"></li>
										<li><a href="{$smarty.const.WWW_TOP}/queue"><i class="icon-tasks icon-white"></i> Queue</a></li>
										<li><a href="{$smarty.const.WWW_TOP}/cart"><i class="icon-shopping-cart icon-white"></i> Download Basket</a></li>
									{if isset($isadmin)}
											<li class="divider"></li>
											<li>
													<li><a href="{$smarty.const.WWW_TOP}/admin"><i class="icon-cog icon-white"></i> Admin</a></li>
											</li>
									{/if}
										<li class="divider"></li>
										<li><a href="{$smarty.const.WWW_TOP}/logout"><i class="icon-off icon-white"></i> Logout</a></li>
								</ul>
							</div>
					{else}
							<ul class="nav pull-right">
							<li class="">
								<a href="{$smarty.const.WWW_TOP}/login">Login</a>
							</li>
							</ul>
					{/if}
			</div>
		</div>
	</div>
	</br>
	</br>
	</br>
	<!-- Container
		================================================== -->
		<div class="container-fluid">
			<div class="row-fluid">
				<div class="span2">
					<ul class="nav nav-list">
					{$main_menu}
					{$useful_menu}
					</ul>
				</div>
				<div class="span10">
					{$page->content}
				</div>
			</div>
		</div>
			{if $loggedin == "true"}
				<input type="hidden" name="UID" value="{$userdata.id}" />
				<input type="hidden" name="RSSTOKEN" value="{$userdata.rsstoken}" />
			{/if}
</body>
</html>
