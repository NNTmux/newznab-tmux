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
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<!-- Meta, title, CSS, favicons, etc. -->
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{csrf_token()}}">

	<title>{$meta_title}{if $meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
	<meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
	<!-- Bootstrap core CSS -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/bootstrap-3.x/dist/css/bootstrap.min.css")}}
	<!-- flexboxgrid -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/flexboxgrid/dist/flexboxgrid.min.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/css/fa-svg-with-js.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/animate.css/animate.min.css")}}
	<!-- Normalize.css -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/normalize.css")}}
	<!-- Custom styling plus plugins -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/custom.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/icheck/skins/flat/green.css")}}
	<!-- fancybox css -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/fancybox/dist/jquery.fancybox.css")}}
	<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
	<!--[if lt IE 9]>
	{{Html::script("{$smarty.const.WWW_ASSETS}/html5shiv/dist/html5shiv.min.js")}}
	{{Html::script("{$smarty.const.WWW_ASSETS}/respond/dest/respond.min.js")}}
	<![endif]-->

</head>
<body class="nav-md">
<div class="container body">
	<div class="main_container">
		<div class="col-md-3 left_col">
			<div class="left_col scroll-view">
				<div class="navbar nav_title" style="border: 0;">
					<a href="{$site->home_link}" class="site_title"><i class="fab fa-phoenix-framework"></i>
						<span>{$site->title}</span></a>
				</div>
				<div class="clearfix"></div>
				<!-- menu profile quick info -->
				{if $loggedin == "true"}
				<div class="profile">

						<div class="profile_pic">
							<img src="{$smarty.const.WWW_ASSETS}/images/userimage.png" alt="User Image"
								 class="img-circle profile_img">
						</div>
						<div class="profile_info">
							<span>Welcome,</span>
							<h2>{$userdata.username}</h2>
						</div>
				</div>
				{/if}
				<!-- /menu profile quick info -->
				<br/>
				<div class="clearfix"></div>
				<!-- sidebar menu -->
				<div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
					<div class="menu_section">
						<h3>Main</h3>
						<ul class="nav side-menu">
							{if $loggedin == "true"}
								<li><a><i class="fa fa-home"></i><span> Browse</span> <span
												class="fa fa-chevron-down"></span></a>
									<ul class="nav child_menu" style="display: none">
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
										<li><a href="{$smarty.const.WWW_TOP}/xxx"><i class="fa fa-venus-mars"></i><span> Adult</span></a>
										</li>
										<li><a href="{$smarty.const.WWW_TOP}/books"><i class="fa fa-book"></i><span> Books</span></a>
										</li>
										<li><a href="{$smarty.const.WWW_TOP}/browse"><i
														class="fa fa-list-ul"></i><span> Browse All Releases</span></a>
										</li>
										<li><a href="{$smarty.const.WWW_TOP}/browsegroup"><i
														class="fa fa-object-group"></i><span> Browse Groups</span></a>
										</li>
									</ul>
								</li>
							{/if}
							<div class="clearfix"></div>
							{if $loggedin == "true"}
							<li><a><i class="fa fa-edit"></i> Articles & Links <span class="fa fa-chevron-down"></span></a>

								<ul class="nav child_menu" style="display: none">

										<li><a href="{$smarty.const.WWW_TOP}/forum"><i class="fa fa-forumbee"></i> Forum</a>
										</li>
										<li><a href="{$smarty.const.WWW_TOP}/search"><i class="fab fa-searchengin"></i> Search</a>
										</li>
										<li><a href="{$smarty.const.WWW_TOP}/rss"><i class="fa fa-rss"></i> RSS
												Feeds</a></li>
										<li><a href="{$smarty.const.WWW_TOP}/apihelp"><i class="fa fa-cloud"></i>
												API</a></li>
								</ul>
								<div class="clearfix"></div>
							<li><a href="{{route('logout')}}" onclick="event.preventDefault(); document.getElementById('frm-logout').submit();">
									<i class="fa fa-unlock"></i><span> Sign Out</span>
								</a>
							</li>
							{else}
							<li><a href="{$smarty.const.WWW_TOP}/login"><i class="fa fa-lock"></i><span> Sign In</span></a>
							</li>
							{/if}
							<li><a href="{$smarty.const.WWW_TOP}/contact-us"><i
											class="far fa-envelope"></i><span> Contact</span></a></li>
						</ul>
					</div>
				</div>
				<!-- /sidebar menu -->
			</div>
		</div>
		<!-- top navigation -->
		{if $loggedin == "true"}
		<div class="top_nav">
			<div class="nav_menu">
				<nav class="" role="navigation">
					<div class="nav toggle">
						<a id="menu_toggle"><i class="fa fa-bars"></i></a>
					</div>
					{$header_menu}
				</nav>
			</div>
		</div>
		{/if}
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
			<div class="container">
				<!-- footer content -->
				<footer>
					<div class="copyright-info">
						<strong>Copyright &copy; {$smarty.now|date_format:"%Y"}
							<a href="https://github.com/NNTmux/newznab-tmux">NNTmux</a> <i class="fab fa-github-alt"></i>.</strong> This software is
						open source, released under the GPL license, proudly powered by <i class="fab fa-laravel"></i> <a href="https://github.com/laravel/framework/">Laravel</a>
					</div>
					<div class="clearfix"></div>
				</footer>
				<!-- /footer content -->
			</div>

		</div>
		<!-- /page content -->
	</div>

</div>
<!-- jQuery 3.1.0 -->
{{Html::script("{$smarty.const.WWW_ASSETS}/jquery-3.2.x/dist/jquery.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-3.x/dist/js/bootstrap.min.js")}}
<!-- bootstrap progress js -->
{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-progressbar/bootstrap-progressbar.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/jquery.nicescroll/jquery.nicescroll.min.js")}}
<!-- icheck -->
{{Html::script("{$smarty.const.WWW_ASSETS}/icheck/icheck.min.js")}}
<!-- tinymce editor -->
{{Html::script("{$smarty.const.WWW_ASSETS}/tinymce-builded/js/tinymce/tinymce.min.js")}}
<!-- jQuery migrate script -->
{{Html::script("{$smarty.const.WWW_ASSETS}/jquery-migrate-3.0.x/jquery-migrate.min.js")}}
<!-- newznab default scripts, needed for stuff to work -->
{{Html::script("{$smarty.const.WWW_ASSETS}/colorbox/jquery.colorbox-min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/autosize/dist/autosize.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.qtip2.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/sorttable.js")}}
<!-- Custom functions -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/functions.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/custom.js")}}

<!-- PNotify -->
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.animate.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.desktop.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.callbacks.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.buttons.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.confirm.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.nonblock.js")}}

<!-- pace -->
{{Html::script("{$smarty.const.WWW_ASSETS}/pace/pace.min.js")}}
<!-- scrollup -->
{{Html::script("{$smarty.const.WWW_ASSETS}/jquery-goup/dist/jquery.goup.min.js")}}

<!-- fancybox js -->
{{Html::script("{$smarty.const.WWW_ASSETS}/fancybox/dist/jquery.fancybox.js")}}
<!--font-awesome-->
{{Html::script("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/js/fa-v4-shims.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/js/fontawesome-all.js")}}


<script>
    jQuery(document).ready(function(){
        jQuery.goup({
			containerColor: "#8bc5c5",
			arrowColor: "#fdf8f8",
			bottomOffset: 100,
			goupSpeed: "normal",
			title: "Scroll to top"
		});
    });
</script>

<form id="frm-logout" action="{{route('logout')}}" method="post" style="display: none;">
	{{csrf_field()}}
</form>

</body>

</html>
