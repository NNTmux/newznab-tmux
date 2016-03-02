<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/html">
<head>
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
	<!--
		===
		This comment should NOT be removed.
		Charisma v2.0.0
		Copyright 2012-2014 Muhammad Usman
		Licensed under the Apache License v2.0
		http://www.apache.org/licenses/LICENSE-2.0
		http://usman.it
		http://twitter.com/halalit_usman
		===
	-->
	<meta charset="utf-8">
	<title>{$page->meta_title}{if $page->meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
	<!-- Newposterwall -->
	<link href="{$smarty.const.WWW_THEMES}/shared/css/posterwall.css" rel="stylesheet" type="text/css" media="screen" />
	<!-- The styles -->
	<link id="bs-css" href="{$smarty.const.WWW_THEMES}/{$theme}/css/bootstrap-spacelab.min.css" rel="stylesheet">
	<link href="{$smarty.const.WWW_THEMES}/{$theme}/css/charisma-app.css" rel="stylesheet">
	<link href='{$smarty.const.WWW_THEMES}/{$theme}/bower_components/chosen/chosen.min.css' rel='stylesheet'>
	<link href='{$smarty.const.WWW_THEMES}/{$theme}/bower_components/colorbox/example3/colorbox.css'
		  rel='stylesheet'>
	<link href='{$smarty.const.WWW_THEMES}/{$theme}/bower_components/responsive-tables/responsive-tables.css'
		  rel='stylesheet'>
	<link href='{$smarty.const.WWW_THEMES}/{$theme}/bower_components/bootstrap-tour/build/css/bootstrap-tour.min.css'
		  rel='stylesheet'>
	<link href='{$smarty.const.WWW_THEMES}/{$theme}/css/elfinder.min.css' rel='stylesheet'>
	<link href='{$smarty.const.WWW_THEMES}/{$theme}/css/elfinder.theme.css' rel='stylesheet'>
	<link href='{$smarty.const.WWW_THEMES}/{$theme}/css/jquery.iphone.toggle.css' rel='stylesheet'>
	<link href='{$smarty.const.WWW_THEMES}/{$theme}/css/animate.min.css' rel='stylesheet'>
	<!-- Font Awesome Icons -->
	<link href="{$smarty.const.WWW_THEMES}/shared/css/font-awesome.min.css" rel="stylesheet"
		  type="text/css"/>
	<!-- Normalize.css -->
	<link href="{$smarty.const.WWW_THEMES}/shared/css/normalize.css" rel="stylesheet" type="text/css">
	<!-- The fav icon -->
	<link rel="shortcut icon" href="{$smarty.const.WWW_THEMES}/shared/images/favicon.ico">
</head>
	<body>
	<!-- topbar starts -->
	<div class="navbar navbar-default" role="navigation">
		<div class="navbar-inner">
			<button type="button" class="navbar-toggle pull-left animated flip">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="{$site->home_link}"> <img alt="newznab-tmux logo"
																	src="{$smarty.const.WWW_THEMES}/{$theme}/img/logo-tmux.png"
						/></a>
			{$header_menu}
			<!-- user dropdown starts -->
			{if $loggedin == "true"}
			<div class="btn-group pull-right">
				<button class="btn btn-default dropdown-toggle" data-toggle="dropdown">
					<i class="fa fa-user"></i><span class="hidden-sm hidden-xs"><span
								class="username"> Hi, {$userdata.username}</span></span>
					<span class="caret"></span>
				</button>
				<ul class="dropdown-menu">
					<li><a href="{$smarty.const.WWW_TOP}/profile"><i class="fa fa-user"></i><span> My Profile</span></a></li>
					<li><a href="{$smarty.const.WWW_TOP}/cart"><i class="fa fa-shopping-basket"></i><span> My Download Basket</span></a></li>
					<li><a href="{$smarty.const.WWW_TOP}/queue"><i class="fa fa-cloud-download"></i><span> My Queue</span></a></li>
					<li><a href="{$smarty.const.WWW_TOP}/mymovies"><i class="fa fa-film"></i><span> My movies</span></a></li>
					<li><a href="{$smarty.const.WWW_TOP}/myshows"><i class="fa fa-television"></i> My Shows</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/profileedit"><i class="fa fa-cog fa-spin"></i><span> Account Settings</span></a>
					</li>
					{if isset($isadmin)}
						<li><a href="{$smarty.const.WWW_TOP}/admin"><i class="fa fa-cogs fa-spin"></i><span> Admin</span></a></li>
					{/if}
					<li><a href="{$smarty.const.WWW_TOP}/logout"><i class="fa fa-unlock-alt"></i><span> Logout</span></a></li>
				</ul>
				{else}
				<li><a href="{$smarty.const.WWW_TOP}/login"><i class="fa fa-lock"></i><span> Login</span></a></li>
				<li><a href="{$smarty.const.WWW_TOP}/register"><i class="fa fa-bookmark-o"></i><span> Register</span></a></li>
			</div>
			{/if}
			<!-- user dropdown ends -->
		</div>
	</div>
	<!-- topbar ends -->
	<div class="ch-container">
		<div class="row">
			<!-- left menu starts -->
			<div class="col-sm-2 col-lg-2">
				<div class="sidebar-nav">
					<div class="nav-canvas">
						<div class="nav-sm nav nav-stacked">
						</div>
						<ul class="nav nav-pills nav-stacked main-menu">
							<!-- search form -->
							{if $loggedin == "true"}
							<form id="headsearch_form" action="{$smarty.const.WWW_TOP}/search/" method="get">
								<input id="headsearch" name="search" value="{if $header_menu_search == ""}Search...{else}{$header_menu_search|escape:"htmlall"}{/if}" class="form-control" type="text" tabindex="1$" />
								<div class="row" style="padding-top:3px;">
									<div class="col-md-8">
										<select id="headcat" name="t" class="form-control" data-search="true">
											<option class="grouping" value="-1">All</option>
											{foreach $parentcatlist as $parentcat}
												<option {if $header_menu_cat == $parentcat.id}selected="selected"{/if} value="{$parentcat.id}"> [{$parentcat.title}]</option>
												{foreach $parentcat.subcatlist as $subcat}
													<option {if $header_menu_cat == $subcat.id}selected="selected"{/if} value="{$subcat.id}">&nbsp;&nbsp;&nbsp; > {$subcat.title}</option>
												{/foreach}
											{/foreach}
										</select>
									</div>
									<div class="col-md-3">
										<input id="headsearch_go" type="submit" class="btn btn-dark" style="margin-top:0px; margin-left:4px;" value="Go"/>
									</div>
								</div>
							</form>
							<!-- /.search form -->
							<li class="nav-header">Main</li>
							<li><a href="{$site->home_link}"><i class="fa fa-home"></i><span> Home</span> <span
											class="fa arrow"></span></a></li>
							<li class="accordion">
								<a href="#"><i class="fa fa-list-ol"></i><span> Browse</span></a>
								<ul class="nav nav-pills nav-stacked">
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
									<li><a href="{$smarty.const.WWW_TOP}/browse"><i class="fa fa-list-ul"></i><span> Browse All Releases</span></a>
									</li>
									<li><a href="{$smarty.const.WWW_TOP}/browsegroup"><i class="fa fa-object-group"></i><span> Browse Groups</span></a>
									</li>
									<li><a href="{$smarty.const.WWW_TOP}/predb"><i
													class="fa fa-list-ol"></i><span> PreDb</span></a></li>
								</ul>
							</li>
							{/if}
							<li class="accordion">
								<a href="#"><i class="fa fa-list-ol"></i><span> Articles & Links</span></a>
								<ul class="nav nav-pills nav-stacked">
									<li><a href="{$smarty.const.WWW_TOP}/contact-us"><i
													class="fa fa-envelope-o"></i><span> Contact</span> <span
													class="fa arrow"></span></a></li>
									{if $loggedin == "true"}
									<li><a href="{$smarty.const.WWW_TOP}/search"><i class="fa fa-search"></i> Search</a></li>
									<li><a href="{$smarty.const.WWW_TOP}/rss"><i class="fa fa-rss"></i> RSS Feeds</a></li>
									<li><a href="{$smarty.const.WWW_TOP}/apihelp"><i class="fa fa-cloud"></i> API</a></li>
								</ul>
							</li>
							<li><a href="{$smarty.const.WWW_TOP}/logout"><i class="fa fa-unlock"></i><span> Logout</span></a>
								{/if}
							</li>
						</ul>
					</div>
				</div>
			</div>
			<!--/span-->
			<!-- left menu ends -->
			<noscript>
				<div class="alert alert-block col-md-12">
					<h4 class="alert-heading">Warning!</h4>
					<p>You need to have <a href="http://en.wikipedia.org/wiki/JavaScript" target="_blank">JavaScript</a>
						enabled to use this site.</p>
				</div>
			</noscript>
			<div id="content" class="col-lg-10 col-sm-10">
				<!-- content starts -->
				<div class="row">
					<div class="box col-md-12">
						<div class="box-inner">
							<div class="box-content">
								<!-- put your content here -->
								{$page->content}
							</div>
						</div>
					</div>
				</div>
				<!--/row-->
				<!-- content ends -->
			</div>
			<!--/#content.col-md-0-->
		</div>
		<!--/fluid-row-->
		<hr>
		<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
			 aria-hidden="true">
		</div>
		<footer class="row">
			<div class="box col-md-12">
				<p class="col-md-9 col-sm-9 col-xs-12 copyright">&copy; <i class="fa fa-github-alt"></i><a
							href="https://github.com/DariusIII/" target="_blank"> DariusIII</a> newznab-tmux {$smarty.now|date_format:"%Y"}</p>
		</footer>
	</div>
	<!--/.fluid-container-->
	<!-- Scripts-->
	<!-- jQuery -->
	<!-- jQuery 2.2.1 -->
	<script src="{$smarty.const.WWW_THEMES}/shared/scripts/jQuery-2.2.1.min.js"></script>
	<!-- jQuery migrate script -->
	<script type="text/javascript"
			src="https://code.jquery.com/jquery-migrate-1.4.0.min.js"></script>
	<script type="text/javascript"
			src="{$smarty.const.WWW_THEMES}/shared/libs/bootstrap/v3/js/bootstrap.min.js"></script>
	<!-- Bootstrap hover on mouseover script -->
	<script type="text/javascript"
			src="{$smarty.const.WWW_THEMES}/shared/scripts/bootstrap-hover-dropdown.min.js"></script>
	<!-- library for cookie management -->
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/{$theme}/js/jquery.cookie.js"></script>
	<!-- data table plugin -->
	<script type="text/javascript"
			src='{$smarty.const.WWW_THEMES}/{$theme}/js/jquery.dataTables.min.js'></script>
	<!-- select or dropdown enhancer -->
	<script type="text/javascript"
			src="{$smarty.const.WWW_THEMES}/{$theme}/bower_components/chosen/chosen.jquery.min.js"></script>
	<!-- plugin for gallery image view -->
	<script type="text/javascript"
			src="{$smarty.const.WWW_THEMES}/{$theme}/bower_components/colorbox/jquery.colorbox-min.js"></script>
	<!-- notification plugin -->
	<script type="text/javascript"
			src="{$smarty.const.WWW_THEMES}/shared/libs/noty/packaged/jquery.noty.packaged.js"></script>
	<!-- library for making tables responsive -->
	<script type="text/javascript"
			src="{$smarty.const.WWW_THEMES}/{$theme}/bower_components/responsive-tables/responsive-tables.js"></script>
	<!-- tour plugin -->
	<script type="text/javascript"
			src="{$smarty.const.WWW_THEMES}/{$theme}/bower_components/bootstrap-tour/build/js/bootstrap-tour.min.js"></script>
	<!-- star rating plugin -->
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/{$theme}/js/jquery.raty.min.js"></script>
	<!-- for iOS style toggle switch -->
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/{$theme}/js/jquery.iphone.toggle.js"></script>
	<!-- autogrowing textarea plugin -->
	<script type="text/javascript"
			src="{$smarty.const.WWW_THEMES}/{$theme}/js/jquery.autogrow-textarea.js"></script>
	<!-- history.js for cross-browser state change on ajax -->
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/{$theme}/js/jquery.history.js"></script>
	<!-- Charisma functions -->
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/{$theme}/js/charisma.js"></script>
	<!-- Functions with noty -->
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/{$theme}/js/functions.js"></script>
	<!-- newznab default scripts, needed for stuff to work -->
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/{$theme}/scripts/jquery.colorbox-min.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/{$theme}/scripts/jquery.autosize-min.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/{$theme}/scripts/jquery.qtip2.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/{$theme}/scripts/sorttable.js"></script>
	<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
	<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
	<!--[if lt IE 9]>
	<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
	<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->
	</body>
</html>
