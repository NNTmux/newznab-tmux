<!DOCTYPE html>
<html>

<head>
	{literal}
	<script>
        /* <![CDATA[ */
        var WWW_TOP = "{/literal}{$smarty.const.WWW_TOP}{literal}";
        var SERVERROOT = "{/literal}{$serverroot}{literal}";
        var UID = "{/literal}{if $loggedin == "true"}{$userdata.id}{else}{/if}{literal}";
        var RSSTOKEN = "{/literal}{if $loggedin == "true"}{$userdata.api_token}{else}{/if}{literal}";
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
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/bootstrap.min.css")}}
	<!-- flexboxgrid -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/flexboxgrid.min.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/svg-with-js.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/pnotify.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/animate.min.css")}}
	<!-- Normalize.css -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/normalize.css")}}
	<!-- Custom styling plus plugins -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/custom.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/green.css")}}
	<!-- fancybox css -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/jquery.fancybox.min.css")}}
    <!--multiselect css-->
    {{Html::style("{$smarty.const.WWW_ASSETS}/css/multi-select.css")}}

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
<!-- jQuery 3.2.0 -->
{{Html::script("{$smarty.const.WWW_ASSETS}/jquery/jquery.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/bootstrap.min.js")}}
<!-- bootstrap progress js -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/bootstrap-progressbar.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/bootstrap-hover-dropdown.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.nicescroll.min.js")}}
<!-- icheck -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/icheck.min.js")}}
<!-- tinymce editor -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/tinymce.min.js")}}
<!-- jQuery migrate script -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery-migrate.min.js")}}
<!-- newznab default scripts, needed for stuff to work -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.colorbox-min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/autosize.min.js")}}
<!-- Custom functions -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/functions.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/custom.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/utils-admin.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.multi-select.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.MultiFile.min.js")}}
<!-- pace -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/pace.min.js")}}
<!-- fancybox js -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.fancybox.min.js")}}
<!--font-awesome-->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/v4-shims.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/all.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/bootstrap-datepicker.min.js")}}

</body>

</html>
