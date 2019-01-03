<!DOCTYPE html>
<html lang="{{App::getLocale()}}">

<head>
	{literal}
	<script>
        /* <![CDATA[ */
        var WWW_TOP = "{/literal}{$smarty.const.WWW_TOP}{literal}";
        var SERVERROOT = "{/literal}{$serverroot}{literal}";
        var UID = "{/literal}{if Auth::check()}{$userdata.id}{else}{/if}{literal}";
        var RSSTOKEN = "{/literal}{if Auth::check()}{$userdata.api_token}{else}{/if}{literal}";
        /* ]]> */
	</script>
	{/literal}
	<meta charset=utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{csrf_token()}}">

	<title>{$meta_title}{if $meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
	{{Html::style("{$smarty.const.WWW_ASSETS}/css/all-css.css")}}

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
				{if Auth::check()}
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
				<div id="sidebar-container" class="sidebar-expanded d-none d-md-block">
					<ul class="list-group">
						<li class="bg-transparent list-group-item sidebar-separator-title text-muted d-flex align-items-center menu-collapsed">
							<small>MAIN MENU</small>
						</li>
						{if Auth::check()}
							<a href="#submenu1" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
								<div class="d-flex w-100 justify-content-start align-items-center">
									<span class="fa fa-dashboard fa-fw mr-3"></span>
									<span class="menu-collapsed">Browse</span>
									<span class="submenu-icon ml-auto"></span>
								</div>
							</a>
							<div id='submenu1' class="collapse sidebar-submenu">
								{if $userdata->hasPermissionTo('view console') == true && $userdata->hasDirectPermission('view console') == true}
									<a href="{$smarty.const.WWW_TOP}/Console" class="list-group-item list-group-item-action bg-dark text-white">
										<span class="fa fa-gamepad"></span>
										<span class="menu-collapsed">Console</span>
									</a>
								{/if}
								{if $userdata->hasPermissionTo('view movies') == true && $userdata->hasDirectPermission('view movies') == true}
									<a href="{$smarty.const.WWW_TOP}/Movies" class="list-group-item list-group-item-action bg-dark text-white">
										<span class="fa fa-film"></span>
										<span class="menu-collapsed">Movies</span>
									</a>
								{/if}
								{if $userdata->hasPermissionTo('view audio') == true && $userdata->hasDirectPermission('view audio') == true}
									<a href="{$smarty.const.WWW_TOP}/Audio" class="list-group-item list-group-item-action bg-dark text-white">
										<span class="fa fa-music"></span>
										<span class="menu-collapsed">Audio</span>
									</a>
								{/if}
								{if $userdata->hasPermissionTo('view pc') == true && $userdata->hasDirectPermission('view pc') == true}
									<a href="{$smarty.const.WWW_TOP}/Games" class="list-group-item list-group-item-action bg-dark text-white">
										<span class="fa fa-gamepad"></span>
										<span class="menu-collapsed">Games</span>
									</a>
								{/if}
								{if $userdata->hasPermissionTo('view tv') == true && $userdata->hasDirectPermission('view tv') == true}
									<a href="{$smarty.const.WWW_TOP}/series" class="list-group-item list-group-item-action bg-dark text-white">
										<span class="fa fa-television"></span>
										<span class="menu-collapsed">TV</span>
									</a>
								{/if}
								{if $userdata->hasPermissionTo('view adult') == true && $userdata->hasDirectPermission('view adult') == true}
									<a href="{$smarty.const.WWW_TOP}/XXX" class="list-group-item list-group-item-action bg-dark text-white">
										<span class="fa fa-venus-mars"></span>
										<span class="menu-collapsed">Adult</span>
									</a>
								{/if}
								{if $userdata->hasPermissionTo('view books') == true && $userdata->hasDirectPermission('view books') == true}
									<a href="{$smarty.const.WWW_TOP}/Books" class="list-group-item list-group-item-action bg-dark text-white">
										<span class="fa fa-book-open"></span>
										<span class="menu-collapsed">Books</span>
									</a>
								{/if}
								<a href="{$smarty.const.WWW_TOP}/browse/All" class="list-group-item list-group-item-action bg-dark text-white">
									<span class="fa fa-list-ul"></span>
									<span class="menu-collapsed">Browse All Releases</span>
								</a>
								<a href="{$smarty.const.WWW_TOP}/browsegroup" class="list-group-item list-group-item-action bg-dark text-white">
									<span class="fa fa-layer-group"></span>
									<span class="menu-collapsed">Browse Groups</span>
								</a>
							</div>
							<a href="#submenu2" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
								<div class="d-flex w-100 justify-content-start align-items-center">
									<span class="fa fa-edit fa-fw mr-3"></span>
									<span class="menu-collapsed">Articles & Links</span>
									<span class="submenu-icon ml-auto"></span>
								</div>
							</a>
							<!-- Submenu content -->
							<div id='submenu2' class="collapse sidebar-submenu">
								<a href="{$smarty.const.WWW_TOP}/forum" class="list-group-item list-group-item-action bg-dark text-white">
									<span class="fa fa-forumbee"></span>
									<span class="menu-collapsed">Forum</span>
								</a>
								<a href="{$smarty.const.WWW_TOP}/search" class="list-group-item list-group-item-action bg-dark text-white">
									<span class="fa fa-searchengin"></span>
									<span class="menu-collapsed">Search</span>
								</a>
								<a href="{$smarty.const.WWW_TOP}/rss" class="list-group-item list-group-item-action bg-dark text-white">
									<span class="fa fa-rss-square"></span>
									<span class="menu-collapsed">RSS Feeds</span>
								</a>
								<a href="{$smarty.const.WWW_TOP}/apihelp" class="list-group-item list-group-item-action bg-dark text-white">
									<span class="fa fa-hire-a-helper"></span>
									<span class="menu-collapsed">API</span>
								</a>
								<a href="{$smarty.const.WWW_TOP}/apiv2help" class="list-group-item list-group-item-action bg-dark text-white">
									<span class="fa fa-hire-a-helper"></span>
									<span class="menu-collapsed">API V2</span>
								</a>
							</div>
							<a href="{{route('logout')}}" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white"
							   onclick="event.preventDefault(); document.getElementById('frm-logout').submit();">
								<span class="fa fa-unlock mr-3"></span>
								<span>Sign Out</span>
							</a>
						{else}
							<a href="{$smarty.const.WWW_TOP}/login" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
								<span class="fa fa-lock mr-3"></span>
								<span>Sign In</span>
							</a>
						{/if}
						<a href="{$smarty.const.WWW_TOP}/contact-us" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
							<span class="fa fa-envelope-open-text mr-3"></span>
							<span>Contact</span>
						</a>
					</ul>
				</div>
				<!-- /sidebar menu -->
			</div>
		</div>
		<!-- top navigation -->
		{if Auth::check()}
			<div class="top_nav">
				<div class="nav_menu">
					{$header_menu}
				</div>
			</div>
		{/if}
		<!-- /top navigation -->

		<!-- page content -->
		<div class="right_col" role="main">
			<div class="clearfix"></div>
			<div class="row">
				<div class="col-md-12 col-sm-12 col-12">
					{$content}
					<div class="clearfix"></div>
				</div>
			</div>
		</div>
		<!-- /page content -->
	</div>
</div>
<!-- footer content -->
<footer>
	<div class="copyright-info">
		<strong>Copyright &copy; {$smarty.now|date_format:"%Y"}
			<a href="https://github.com/NNTmux/newznab-tmux">NNTmux</a> <i
					class="fab fa-github-alt"></i>.</strong> This software is
		open source, released under the GPL license, proudly powered by <i class="fab fa-laravel"></i>
		<a href="https://github.com/laravel/framework/">Laravel</a>
	</div>
	<div class="clearfix"></div>
</footer>
<!-- /footer content -->

<!-- jQuery 3.3.0 -->
{{Html::script("{$smarty.const.WWW_ASSETS}/js/all-js.js")}}


<script>
    jQuery(document).ready(function () {
        jQuery.goup({
            containerColor: "#8bc5c5",
            arrowColor: "#fdf8f8",
            bottomOffset: 100,
            goupSpeed: "normal",
            title: "Scroll to top"
        });
    });
</script>

{{Form::open(['id' => 'frm-logout', 'route' => 'logout'])}}
{{Form::close()}}

</body>

</html>
