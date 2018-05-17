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
	<meta charset="UTF-8">
	<title>{$meta_title}{if $meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
	<meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
	<meta name="csrf-token" content="{$csrf_token}">
	<!-- Bootstrap 3.3.6 -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/bootstrap-3.x/dist/css/bootstrap.min.css")}}
	<!-- iCheck -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/icheck/skins/square/blue.css")}}
	<!-- flexboxgrid -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/flexboxgrid/dist/flexboxgrid.min.css")}}
	<!-- Font Awesome Icons -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/css/fa-svg-with-js.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/animate.css/animate.min.css")}}
	<!-- Qtip2 CSS -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/jquery.qtip.css")}}
	<!-- Normalize.css -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/normalize.css")}}
	<!-- Ionicons -->
	{{Html::style("https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css")}}
	<!-- Theme style -->
	{{Html::style("{$smarty.const.WWW_ASSETS}/{$theme}/dist/css/AdminLTE.css")}}
	<!-- AdminLTE Skins. We have chosen the skin-blue for this starter
		  page. However, you can choose any other skin. Make sure you
		  apply the skin class to the body tag so the changes take effect.
	-->
	{{Html::style("{$smarty.const.WWW_ASSETS}/{$theme}/dist/css/skins/skin-blue.min.css")}}
	<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
	<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
	<!--[if lt IE 9]>
	{{Html::script("{$smarty.const.WWW_ASSETS}/html5shiv/dist/html5shiv.min.js")}}
	{{Html::script("{$smarty.const.WWW_ASSETS}/respond/dest/respond.min.js")}}
	<![endif]-->
</head>
<!--
  BODY TAG OPTIONS:
  =================
  Apply one or more of the following classes to get the
  desired effect
  |---------------------------------------------------------|
  | SKINS         | skin-blue                               |
  |               | skin-black                              |
  |               | skin-purple                             |
  |               | skin-yellow                             |
  |               | skin-red                                |
  |               | skin-green                              |
  |---------------------------------------------------------|
  |LAYOUT OPTIONS | fixed                                   |
  |               | layout-boxed                            |
  |               | layout-top-nav                          |
  |               | sidebar-collapse                        |
  |               | sidebar-mini                            |
  |---------------------------------------------------------|
  -->
<body class="skin-blue sidebar-mini layout-boxed">
<div class="wrapper">
	<!-- Main Header -->
	<header class="main-header">
		<!-- Logo -->
		<a href="{$site->home_link}" class="logo">
			<!-- mini logo for sidebar mini 50x50 pixels -->
			<span class="logo-mini"><b>N</b>Tm</span>
			<!-- logo for regular state and mobile devices -->
			<span class="logo-lg"><b>{$site->title}</b></span>
		</a>
		<!-- Header Navbar -->
		<nav class="navbar navbar-static-top" role="navigation">
			<!-- Sidebar toggle button-->
			<a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
				<span class="sr-only">Toggle navigation</span>
			</a>
			{$header_menu}
			<!-- Navbar Right Menu -->
			<div class="navbar-custom-menu">
				<ul class="nav navbar-nav">
					<!-- User Account Menu -->
					<li class="dropdown user user-menu">
						<!-- Menu Toggle Button -->
						<a href="#" class="dropdown-toggle" data-toggle="dropdown">
							<!-- The user image in the navbar-->
							{if $loggedin == "true"}
							<img src="{$smarty.const.WWW_ASSETS}/images/userimage.png"
								 class="user-image" alt="User Image"/>
							<!-- hidden-xs hides the username on small devices so only the image appears. -->
							<span class="hidden-xs">{$userdata.username}</span>
						</a>
						<ul class="dropdown-menu">
							<!-- The user image in the menu -->
							<li class="user-header">
								<img src="{$smarty.const.WWW_ASSETS}/images/userimage.png"
									 class="img-circle" alt="User Image"/>
								<p>
									{$userdata.username}
									<small>{$userdata.rolename}</small>
								</p>
							</li>
							<!-- Menu Body -->
							<li class="user-body">
								<div class="col-xs-12 text-center">
									<a href="{$smarty.const.WWW_TOP}/cart/index"><i class="fa fa-shopping-basket"></i>
										My Download Basket</a>
								</div>
								<div class="col-xs-12 text-center">
									<a href="{$smarty.const.WWW_TOP}/queue"><i class="fa fa-list-alt"></i> My Queue</a>
								</div>
								<div class="col-xs-12 text-center">
									<a href="{$smarty.const.WWW_TOP}/mymovies"><i class="fa fa-film"></i> My Movies</a>
								</div>
								<div class="col-xs-12 text-center">
									<a href="{$smarty.const.WWW_TOP}/myshows"><i class="fa fa-television"></i> My Shows</a>
								</div>
								<div class="col-xs-12 text-center">
									<a href="{$smarty.const.WWW_TOP}/profileedit"><i class="fa fa-cog fa-spin"></i>
										Account Settings</a>
								</div>
								{if isset($isadmin)}
									<div class="col-xs-12 text-center">
										<a href="{$smarty.const.WWW_TOP}/admin/index"><i class="fa fa-cogs fa-spin"></i>
											Admin</a>
									</div>
								{/if}
							</li>
							<!-- Menu Footer-->
							<li class="user-footer">
								<div class="pull-left">
									<a href="{$smarty.const.WWW_TOP}/profile" class="btn btn-default btn-flat"><i
												class="fa fa-user"></i> Profile</a>
								</div>
								<div class="pull-right">
									<a href="{$smarty.const.WWW_TOP}/logout" class="btn btn-default btn-flat"><i
												class="fa fa-unlock-alt"></i> Sign out</a>
								</div>
							</li>
						</ul>
						{else}
					<li><a href="{$smarty.const.WWW_TOP}/login"><i class="fa fa-lock"></i><span> Login</span></a></li>
					<li><a href="{$smarty.const.WWW_TOP}/register"><i
									class="fa fa-bookmark-o"></i><span> Register</span></a></li>
					{/if}
				</ul>
			</div>
		</nav>
	</header>
	<!-- Left side column. contains the logo and sidebar -->
	<aside class="main-sidebar">
		<!-- sidebar: style can be found in sidebar.less -->
		<section class="sidebar">
			<!-- Sidebar user panel -->
			{if $loggedin == "true"}
				<div class="user-panel">
					<div class="pull-left image">
						<img src="{$smarty.const.WWW_ASSETS}/images/user-loggedin.png" class="img-circle"
							 alt="User Image"/>
					</div>
					<div class="pull-left info">
						<p>{$userdata.username}</p>
						<a href="#"><i class="fa fa-circle text-success"></i><span>{$userdata.rolename}</span></a>
					</div>
				</div>
				<!-- search form -->
				<form id="headsearch_form" action="{$smarty.const.WWW_TOP}/search/" method="get">
					<input id="headsearch" name="search"
						   value="{if $header_menu_search == ""}Search...{else}{$header_menu_search|escape:"htmlall"}{/if}"
						   class="form-control" type="text" tabindex="1$"/>
					<div class="row small-gutter-left" style="padding-top:3px;">
						<div class="col-md-8">
							<select id="headcat" name="t" class="form-control" data-search="true">
								<option class="grouping" value="-1">All</option>
								{foreach $parentcatlist as $parentcat}
									<option {if $header_menu_cat == $parentcat.id}selected="selected"{/if}
											value="{$parentcat.id}"> [{$parentcat.title}]
									</option>
									{foreach $parentcat.subcatlist as $subcat}
										<option {if $header_menu_cat == $subcat.id}selected="selected"{/if}
												value="{$subcat.id}">&nbsp;&nbsp;&nbsp; > {$subcat.title}</option>
									{/foreach}
								{/foreach}
							</select>
						</div>
						<div class="col-md-3 small-gutter-left">
							<input id="headsearch_go" type="submit" class="btn btn-dark"
								   style="margin-top:0px; margin-left:4px;" value="Go"/>
						</div>
					</div>
				</form>
			{/if}
			<!-- /.search form -->
			<!-- Sidebar Menu -->
			<ul class="sidebar-menu">
				<li class="header">Main</li>
				<!-- Optionally, you can add icons to the links -->
				<li><a href="{$site->home_link}"><i class="fa fa-home"></i><span> Home</a></li>
				{if $loggedin == "true"}
					<li class="treeview">
						<a href="#"><i class="fa fa-list-ol"></i><span> Browse</span></a>
						<ul class="treeview-menu">
							<li><a href="{$smarty.const.WWW_TOP}/Console"><i
											class="fa fa-gamepad"></i><span> Console</span></a></li>
							<li><a href="{$smarty.const.WWW_TOP}/Movies"><i
											class="fa fa-film"></i><span> Movies</span></a></li>
							<li><a href="{$smarty.const.WWW_TOP}/Audio"><i
											class="fa fa-music"></i><span> Audio</span></a></li>
							<li><a href="{$smarty.const.WWW_TOP}/Games"><i
											class="fa fa-gamepad"></i><span> Games</span></a></li>
							<li><a href="{$smarty.const.WWW_TOP}/series"><i
											class="fa fa-television"></i><span> TV</span></a></li>
							<li><a href="{$smarty.const.WWW_TOP}/XXX"><i
											class="fa fa-venus-mars"></i><span> Adult</span></a></li>
							<li><a href="{$smarty.const.WWW_TOP}/Books"><i
											class="fa fa-book"></i><span> Books</span></a></li>
							<li><a href="{$smarty.const.WWW_TOP}/browse/All"><i
											class="fa fa-list-ul"></i><span> Browse All Releases</span></a></li>
							<li><a href="{$smarty.const.WWW_TOP}/browsegroup"><i class="fa fa-object-group"></i><span> Browse Groups</span></a>
							</li>
							</li>
						</ul>
					</li>
				{/if}
				<li class="treeview">
					<a href="#"><i class="fa fa-list-ol"></i><span> Articles & Links</span></a>
					<ul class="treeview-menu">
						<li><a href="{$smarty.const.WWW_TOP}/contact-us"><i
										class="far fa-envelope"></i><span> Contact</span>
								{if $loggedin == "true"}
						<li><a href="{$smarty.const.WWW_TOP}/forum"><i class="fa fa-forumbee"></i> Forum</a></li>
						<li><a href="{$smarty.const.WWW_TOP}/search"><i class="fab fa-searchengin"></i> Search</a></li>
						<li><a href="{$smarty.const.WWW_TOP}/rss"><i class="fa fa-rss"></i> RSS Feeds</a></li>
						<li><a href="{$smarty.const.WWW_TOP}/apihelp"><i class="fa fa-cloud"></i> API</a></li>
					</ul>
				</li>
				<li><a href="{$smarty.const.WWW_TOP}/logout"><i class="fa fa-unlock"></i><span> Sign out</span></a></li>
				{/if}
			</ul>
			<!-- /.sidebar-menu -->
		</section>
		<!-- /.sidebar -->
	</aside>
	<!-- Content Wrapper. Contains page content -->
	<div class="content-wrapper">
		<!-- Content Header (Page header) -->
		<!-- Main content -->
		<section class="content">
			<!-- Your Page Content Here -->
			{$content}
		</section>
		<!-- /.content -->
	</div>
	<!-- /.content-wrapper -->
	<!-- Main Footer -->
	<footer class="main-footer">
		<!-- To the right -->
		<div class="pull-right hidden-xs">
			Times change!
		</div>
		<!-- Default to the left -->
		<strong>Copyright &copy; {$smarty.now|date_format:"%Y"}
			<a href="https://github.com/NNTmux/newznab-tmux">NNTmux</a> <i class="fab fa-github-alt"></i>.</strong> This
		software is
		open source, released under the GPL license, proudly powered by <i class="fab fa-laravel"></i> <a
				href="https://github.com/laravel/framework/">Laravel</a>
	</footer>
</div>
<!-- ./wrapper -->
<!-- REQUIRED JS SCRIPTS -->
<!-- jQuery 3.1.0 -->
{{Html::script("{$smarty.const.WWW_ASSETS}/jquery-2.2.x/dist/jquery.min.js")}}
<!-- Bootstrap 3.3.6 JS -->
{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-3.x/dist/js/bootstrap.min.js")}}
<!-- icheck -->
{{Html::script("{$smarty.const.WWW_ASSETS}/icheck/icheck.min.js")}}
<!-- Bootstrap hover on mouseover script -->
{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js")}}
<!-- AdminLTE App -->
{{Html::script("{$smarty.const.WWW_ASSETS}/{$theme}/dist/js/app.min.js")}}
<!-- jQuery migrate script -->
{{Html::script("{$smarty.const.WWW_ASSETS}/jquery-migrate-1.4.x/jquery-migrate.min.js")}}
<!-- SlimScroll script -->
{{Html::script("{$smarty.const.WWW_ASSETS}/slimscroll/jquery.slimscroll.min.js")}}
<!-- Fastclick script -->
{{Html::script("{$smarty.const.WWW_ASSETS}/fastclick/lib/fastclick.js")}}
<!-- data table plugin -->
{{Html::script("{$smarty.const.WWW_ASSETS}/datatables/media/js/jquery.dataTables.min.js")}}
<!-- tinymce editor -->
{{Html::script("{$smarty.const.WWW_ASSETS}/tinymce-dist/tinymce.min.js")}}
<!-- newznab default scripts, needed for stuff to work -->
{{Html::script("{$smarty.const.WWW_ASSETS}/colorbox/jquery.colorbox-min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/autosize/dist/autosize.min.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.qtip2.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/js/sorttable.js")}}
<!-- Custom functions -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/functions.js")}}
<!-- PNotify -->
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.animate.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.desktop.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.callbacks.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.buttons.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.confirm.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/pnotify/dist/pnotify.nonblock.js")}}
<!--font-awesome-->
{{Html::script("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/js/fa-v4-shims.js")}}
{{Html::script("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/js/fontawesome-all.js")}}
<!-- Optionally, you can add Slimscroll and FastClick plugins.
	  Both of these plugins are recommended to enhance the
	  user experience. Slimscroll is required when using the
	  fixed layout. -->
</body>
</html>
