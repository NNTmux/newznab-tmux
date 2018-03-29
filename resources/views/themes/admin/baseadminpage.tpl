<!DOCTYPE html>
<html>

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
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<!-- Meta, title, CSS, favicons, etc. -->
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title>{$meta_title}{if $meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>

	<!-- Bootstrap core CSS -->
	<link href="{$smarty.const.WWW_ASSETS}/bootstrap-3.x/dist/css/bootstrap.min.css" rel="stylesheet"
		  type="text/css"/>
	<link href="{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/css/fa-svg-with-js.css" rel="stylesheet"
		  type="text/css"/>
	<link href="{$smarty.const.WWW_ASSETS}/css/jquery.qtip.css" rel="stylesheet"
		  type="text/css"/>
	<link href="{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.css" rel="stylesheet" type="text/css"/>
	<link href="{$smarty.const.WWW_ASSETS}/animate.css/animate.min.css" rel="stylesheet">
	<!-- Normalize.css -->
	<link href="{$smarty.const.WWW_ASSETS}/css/normalize.css" rel="stylesheet" type="text/css">
	<!-- Custom styling plus plugins -->
	<link href="{$smarty.const.WWW_ASSETS}/css/custom.css" rel="stylesheet">
	<link href="{$smarty.const.WWW_ASSETS}/icheck/skins/flat/green.css" rel="stylesheet">
	<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
	<!--[if lt IE 9]>
	<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
	<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->

</head>
<body class="nav-md">
<div class="container body">
	<div class="main_container">
		<div class="col-md-3 left_col">
			<div class="left_col scroll-view">
				<div class="navbar nav_title" style="border: 0;">
					<a href="{$site->home_link}" class="site_title"><i class="fa fa-mixcloud"></i>
						<span>{$site->title}</span></a>
				</div>
				<div class="clearfix"></div>
				<br/>
				<!-- sidebar menu -->
				{$admin_menu}
				<!-- /sidebar menu -->
			</div>
		</div>
		<!-- top navigation -->
		<div class="top_nav">
			<div class="nav_menu">
				<nav class="" role="navigation">
					<div class="nav toggle">
						<a id="menu_toggle"><i class="fa fa-bars"></i></a>
					</div>
					{$page->head}
				</nav>
			</div>
		</div>
		<!-- /top navigation -->

		<!-- page content -->
		<div class="right_col" role="main">
			<div class="clearfix"></div>
			<div class="row">
				<div class="col-md-12 col-sm-12 col-xs-12">
					{$content}
					<div class="clearfix"></div>
				</div>
			</div>
			<!-- footer content -->
			<footer>
				<div class="copyright-info">
					<strong>Copyright &copy; {$smarty.now|date_format:"%Y"} <a
								href="https://github.com/NNTmux/">newznab-tmux</a>.</strong> This software is open
					source,
					released under the GPL license
				</div>
				<div class="clearfix"></div>
			</footer>
			<!-- /footer content -->

		</div>
		<!-- /page content -->
	</div>

</div>
<!-- jQuery 3.1.0 -->
<script src="{$smarty.const.WWW_ASSETS}/jquery-3.2.x/dist/jquery.min.js" type="text/javascript"></script>
<script src="{$smarty.const.WWW_ASSETS}/bootstrap-3.x/dist/js/bootstrap.min.js" type="text/javascript"></script>
<!-- bootstrap progress js -->
<script type="text/javascript"
		src="{$smarty.const.WWW_ASSETS}/bootstrap-progressbar/bootstrap-progressbar.min.js"></script>
<!-- bootstrap datepicker -->
<script type="text/javascript"
		src="{$smarty.const.WWW_ASSETS}/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>
<script type="text/javascript"
		src="{$smarty.const.WWW_ASSETS}/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_ASSETS}/autosize/dist/autosize.min.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_ASSETS}/colorbox/jquery.colorbox-min.js"></script>
<script type="text/javascript"
		src="{$smarty.const.WWW_ASSETS}/jquery.nicescroll/dist/jquery.nicescroll.min.js"></script>
<!-- tinymce editor -->
<script type="text/javascript" src="{$smarty.const.WWW_ASSETS}/tinymce-builded/js/tinymce/tinymce.min.js"></script>
<!-- icheck -->
<script type="text/javascript" src="{$smarty.const.WWW_ASSETS}/icheck/icheck.min.js"></script>
<!-- jQuery migrate script -->
<script type="text/javascript" src="https://code.jquery.com/jquery-migrate-1.4.0.min.js"></script>
<!-- newznab default scripts, needed for stuff to work -->
<script type="text/javascript" src="{$smarty.const.WWW_ASSETS}/js/jquery.qtip.min.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_ASSETS}/js/sorttable.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_ASSETS}/js/utils-admin.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_ASSETS}/js/jquery.multiselect.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_ASSETS}/js/jquery.multifile.js"></script>
<!-- Custom functions -->
<script src="{$smarty.const.WWW_ASSETS}/js/functions.js" type="text/javascript"></script>
<script type="text/javascript" src="{$smarty.const.WWW_ASSETS}/js/custom.js"></script>
<!-- pace -->
<script src="{$smarty.const.WWW_ASSETS}/pace/pace.min.js"></script>
<!--font-awesome-->
<script type="text/javascript" src="{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/js/fa-v4-shims.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/js/fontawesome-all.js"></script>

</body>

</html>