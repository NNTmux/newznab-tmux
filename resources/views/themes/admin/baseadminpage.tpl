<!DOCTYPE html>
<html lang="{{App::getLocale()}}">

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<!-- Meta, title, CSS, favicons, etc. -->
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title>{$page->meta_title}{if $page->meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>

	<!-- Bootstrap core CSS -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/bootstrap-3.x/dist/css/bootstrap.min.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/css/fa-svg-with-js.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/jquery.qtip.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/animate.css/animate.min.css")}}
	<!-- Normalize.css -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/normalize.css")}}
	<!-- Custom styling plus plugins -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/custom.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/icheck/skins/flat/green.css")}}
	<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
	<!--[if lt IE 9]>
	{{Html::script("https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js")}}
	{{Html::script("https://oss.maxcdn.com/respond/1.4.2/respond.min.js")}}
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
{{Html::script("{$smarty.const.WWW_ASSETS}/jquery-3.2.x/dist/jquery.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-3.x/dist/js/bootstrap.min.js")}}
<!-- bootstrap progress js -->
{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-progressbar/bootstrap-progressbar.min.js")}}
<!-- bootstrap datepicker -->
{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/autosize/dist/autosize.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/colorbox/jquery.colorbox-min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/jquery.nicescroll/dist/jquery.nicescroll.min.js")}}
<!-- tinymce editor -->
{{Html::script("{$smarty.const.WWW_ASSETS}/tinymce-builded/js/tinymce/tinymce.min.js")}}
<!-- icheck -->
{{Html::script("{$smarty.const.WWW_ASSETS}/icheck/icheck.min.js")}}
<!-- jQuery migrate script -->
{{Html::script("https://code.jquery.com/jquery-migrate-1.4.0.min.js")}}
<!-- newznab default scripts, needed for stuff to work -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.qtip.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/sorttable.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/utils-admin.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.multiselect.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.multifile.js")}}
<!-- Custom functions -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/functions.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/custom.js")}}
<!-- pace -->
{{Html::script("{$smarty.const.WWW_ASSETS}/pace/pace.min.js")}}
<!--font-awesome-->
{{Html::script("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/js/fa-v4-shims.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/js/fontawesome-all.js")}}

</body>

</html>
