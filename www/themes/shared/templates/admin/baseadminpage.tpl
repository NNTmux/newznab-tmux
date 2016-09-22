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

	<title>{$page->meta_title}{if $page->meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>

	<!-- Bootstrap core CSS -->
	<link href="{$smarty.const.WWW_THEMES}/shared/assets/bootstrap-3.x/dist/css/bootstrap.min.css" rel="stylesheet"
		  type="text/css"/>
	<!-- bootstrap datepicker CSS
	<link href="{$smarty.const.WWW_THEMES}/shared/assets/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css" rel="stylesheet" type="text/css" /> -->
	<link href="{$smarty.const.WWW_THEMES}/shared/assets/font-awesome/css/font-awesome.min.css" rel="stylesheet"
		  type="text/css"/>
	<link href="{$smarty.const.WWW_THEMES}/shared/css/jquery.qtip.css" rel="stylesheet"
		  type="text/css"/>
	<link href="{$smarty.const.WWW_THEMES}/shared/assets/pnotify/dist/pnotify.css" rel="stylesheet" type="text/css"/>
	<link href="{$smarty.const.WWW_THEMES}/shared/assets/animate.css/animate.min.css" rel="stylesheet">
	<!-- Normalize.css -->
	<link href="{$smarty.const.WWW_THEMES}/shared/css/normalize.css" rel="stylesheet" type="text/css">
	<!-- Custom styling plus plugins -->
	<!-- Newposterwall -->
	<link href="{$smarty.const.WWW_THEMES}/shared/css/posterwall.css" rel="stylesheet" type="text/css" media="screen"/>
	<link href="{$smarty.const.WWW_THEMES}/shared/css/custom.css" rel="stylesheet">
	<link href="{$smarty.const.WWW_THEMES}/shared/assets/icheck/skins/flat/green.css" rel="stylesheet">
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
					{$page->content}
					<div class="clearfix"></div>
				</div>
			</div>
			<!-- footer content -->
			<footer>
				<div class="copyright-info">
					<strong>Copyright &copy; {$smarty.now|date_format:"%Y"} <a
								href="https://github.com/DariusIII/">newznab-tmux</a>.</strong> This software is open
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
<script src="{$smarty.const.WWW_THEMES}/shared/assets/jquery-2.2.x/dist/jquery.min.js" type="text/javascript"></script>
<script src="{$smarty.const.WWW_THEMES}/shared/assets/bootstrap-3.x/dist/js/bootstrap.min.js" type="text/javascript"></script>
<!-- bootstrap progress js -->
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/bootstrap-progressbar/bootstrap-progressbar.min.js"></script>
<!-- bootstrap datepicker -->
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/autosize/dist/autosize.min.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/colorbox/jquery.colorbox-min.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/jquery.nicescroll/dist/jquery.nicescroll.min.js"></script>
<!-- tinymce editor -->
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/tinymce-builded/js/tinymce/tinymce.min.js"></script>
<!-- icheck -->
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/assets/icheck/icheck.min.js"></script>
<!-- jQuery migrate script -->
<script type="text/javascript" src="https://code.jquery.com/jquery-migrate-1.4.0.min.js"></script>
<!-- newznab default scripts, needed for stuff to work -->
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/js/jquery.qtip.min.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/js/sorttable.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/js/utils-admin.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/js/jquery.multiselect.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/js/jquery.multifile.js"></script>
<!-- Custom functions -->
<script src="{$smarty.const.WWW_THEMES}/shared/js/functions.js" type="text/javascript"></script>
<script type="text/javascript" src="{$smarty.const.WWW_THEMES}/shared/js/custom.js"></script>
<!-- pace -->
<script src="{$smarty.const.WWW_THEMES}/shared/assets/pace/pace.min.js"></script>

</body>

</html>
