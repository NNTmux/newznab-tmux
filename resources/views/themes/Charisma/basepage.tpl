<!DOCTYPE html>
<html lang="{{App::getLocale()}}">

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
	<title>{$meta_title}{if $meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
	<meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
	<meta name="csrf-token" content="{$csrf_token}">
	<!-- The styles -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/bootswatch/slate/bootstrap.min.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/{$theme}/css/charisma-app.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/chosen/chosen.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/colorbox/example3/colorbox.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/responsive-tables-js/dist/responsivetables.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/{$theme}/css/elfinder.min.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/{$theme}/css/elfinder.theme.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/{$theme}/css/jquery.iphone.toggle.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/animate.css/animate.min.css")}}
	<!-- flexboxgrid -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/flexboxgrid/dist/flexboxgrid.min.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/css/fa-svg-with-js.css")}}
	<!-- Material design Icons -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/material-design-iconic-font/dist/css/material-design-iconic-font.min.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/jquery.qtip.css")}}
	<!-- Normalize.css -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/normalize.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/icheck/skins/square/blue.css")}}
	<!-- Materializecss css -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/materialize/dist/css/materialize.min.css")}}
	<!-- The fav icon -->
	<link rel="shortcut icon" href="{$smarty.const.WWW_ASSETS}/images/favicon.ico">
</head>
<body>
<!-- topbar starts -->
<div class="navbar navbar-default" role="navigation">
	<div class="container-fluid">
		<button type="button" class="navbar-toggle navbar-left animated flip">
			<span class="sr-only">Toggle navigation</span>
			<span class="icon-bar"></span>
			<span class="icon-bar"></span>
			<span class="icon-bar"></span>
		</button>
		<div class="navbar-header">
			<a href="{$site->home_link}" class="navbar-brand">
				<span>{$site->title}</span></a>
		</div>
		<div class="navbar-header" style="margin: 0 auto; float: right;">
			{$header_menu}
		</div>
	</div>
</div>
<!-- topbar ends -->
<div class="container-fluid">
	<div class="row">
		<!-- left menu starts -->
		<div class="col-sm-2 col-lg-2">
			<div class="sidebar-nav">
				<div class="nav-canvas">
					<div class="nav-sm nav nav-stacked">
					</div>
					<ul class="nav nav-pills nav-stacked main-menu">
						{if $loggedin == "true"}
							<li class="nav-header">Main</li>
							<li><a href="{$site->home_link}"><i class="zmdi zmdi-home"></i><span> Home</span></a></li>
							<li class="accordion">
								<a href="#"><i class="zmdi zmdi-view-list-alt"></i><span> Browse</span></a>
								<ul class="nav nav-pills nav-stacked">
									<li><a href="{$smarty.const.WWW_TOP}/Console"><i
													class="zmdi zmdi-xbox"></i><span> Console</span></a></li>
									<li><a href="{$smarty.const.WWW_TOP}/Movies"><i
													class="zmdi zmdi-movie-alt"></i><span> Movies</span></a></li>
									<li><a href="{$smarty.const.WWW_TOP}/Audio"><i
													class="zmdi zmdi-audio"></i><span> Audio</span></a></li>
									<li><a href="{$smarty.const.WWW_TOP}/Games"><i
													class="zmdi zmdi-keyboard"></i><span> Games</span></a></li>
									<li><a href="{$smarty.const.WWW_TOP}/series"><i
													class="zmdi zmdi-tv-play"></i><span> TV</span></a></li>
									<li><a href="{$smarty.const.WWW_TOP}/XXX"><i
													class="zmdi zmdi-male-female"></i><span> Adult</span></a>
									</li>
									<li><a href="{$smarty.const.WWW_TOP}/Books"><i
													class="zmdi zmdi-book"></i><span> Books</span></a></li>
									<li><a href="{$smarty.const.WWW_TOP}/browse/All"><i class="zmdi zmdi-view-list-alt"></i><span> Browse All Releases</span></a>
									</li>
									<li><a href="{$smarty.const.WWW_TOP}/browsegroup"><i
													class="zmdi zmdi-group"></i><span> Browse Groups</span></a>
									</li>
								</ul>
							</li>
						{/if}
						<li class="accordion">
							<a href="#"><i class="zmdi zmdi-view-list-alt"></i><span> Articles & Links</span></a>
							<ul class="nav nav-pills nav-stacked">
								<li><a href="{$smarty.const.WWW_TOP}/contact-us"><i
												class="zmdi zmdi-email"></i><span> Contact</span></a></li>
								{if $loggedin == "true"}
								<li><a href="{$smarty.const.WWW_TOP}/forum"><i class="zmdi zmdi-disqus"></i> Forum</a>
								</li>
								<li><a href="{$smarty.const.WWW_TOP}/search"><i class="zmdi zmdi-search-for"></i> Search</a>
								</li>
								<li><a href="{$smarty.const.WWW_TOP}/rss"><i class="zmdi zmdi-rss"></i> RSS Feeds</a>
								</li>
								<li><a href="{$smarty.const.WWW_TOP}/apihelp"><i class="zmdi zmdi-cloud"></i> API</a>
								</li>
							</ul>
						</li>
						<li><a href="{$smarty.const.WWW_TOP}/logout"><i
										class="zmdi zmdi-lock-open"></i><span> Logout</span></a>
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
			<div class="container-fluid">
				<div class="row">
					<div class="box col-md-12">
						<div class="box-content">
							<!-- put your content here -->
							{$content}
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
			<p class="col-md-9 col-sm-9 col-xs-12 copyright">&copy; <i class="zmdi zmdi-github-alt"></i><a
						href="https://github.com/NNTmux/newznab-tmux" target="_blank"> NNTmux</a>
				newznab-tmux {$smarty.now|date_format:"%Y"}</p>
	</footer>
</div>
<!--/.fluid-container-->
<!-- Scripts-->
<!-- jQuery -->
<!-- jQuery 3.1.0 -->
{{Html::script("{$smarty.const.WWW_ASSETS}/jquery-2.2.x/dist/jquery.min.js")}}
<!-- jQuery migrate script -->
{{Html::script("{$smarty.const.WWW_ASSETS}/jquery-migrate-1.4.x/jquery-migrate.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-3.x/dist/js/bootstrap.min.js")}}
<!-- Bootstrap hover on mouseover script -->
{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js")}}
<!-- library for cookie management -->
{{Html::script("{$smarty.const.WWW_ASSETS}/{$theme}/js/jquery.cookie.js")}}
<!-- data table plugin -->
{{Html::script("{$smarty.const.WWW_ASSETS}/datatables/media/js/jquery.dataTables.min.js")}}
<!-- select or dropdown enhancer -->
{{Html::script("{$smarty.const.WWW_ASSETS}/chosen/chosen.jquery.js")}}
<!-- plugin for gallery image view -->
{{Html::script("{$smarty.const.WWW_ASSETS}/colorbox/jquery.colorbox-min.js")}}
<!-- library for making tables responsive -->
{{Html::script("{$smarty.const.WWW_ASSETS}/responsive-tables-js/dist/responsivetables.js")}}
<!-- for iOS style toggle switch -->
{{Html::script("{$smarty.const.WWW_ASSETS}/{$theme}/js/jquery.iphone.toggle.js")}}
<!-- icheck -->
{{Html::script("{$smarty.const.WWW_ASSETS}/icheck/icheck.min.js")}}
<!-- autogrowing textarea plugin -->
{{Html::script("{$smarty.const.WWW_ASSETS}/{$theme}/js/jquery.autogrow-textarea.js")}}
<!-- tinymce editor -->
{{Html::script("{$smarty.const.WWW_ASSETS}/tinymce-dist/tinymce.min.js")}}
<!-- history.js for cross-browser state change on ajax -->
{{Html::script("{$smarty.const.WWW_ASSETS}/{$theme}/js/jquery.history.js")}}
<!-- Charisma functions -->
{{Html::script("{$smarty.const.WWW_ASSETS}/{$theme}/js/charisma.js")}}
<!-- newznab default scripts, needed for stuff to work -->
{{Html::script("{$smarty.const.WWW_ASSETS}/autosize/dist/autosize.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.qtip2.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/sorttable.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/functions.js")}}
<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
<!--[if lt IE 9]>
{{Html::script("{$smarty.const.WWW_ASSETS}/html5shiv/dist/html5shiv.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/respond/dest/respond.min.js")}}
<![endif]-->
<!-- PNotify -->
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.animate.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.desktop.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.callbacks.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.buttons.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.confirm.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.nonblock.js")}}
<!-- materializecss js -->
{{Html::script("{$smarty.const.WWW_ASSETS}/materialize/dist/js/materialize.min.js")}}
<!--font-awesome-->
{{Html::script("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/js/fa-v4-shims.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/js/fontawesome-all.js")}}
</body>
</html>
