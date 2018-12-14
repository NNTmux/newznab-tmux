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
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<!-- Meta, title, CSS, favicons, etc. -->
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
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
				<div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
					<div class="menu_section">
						<h3>Main</h3>
						<ul class="nav side-menu">
							{if Auth::check()}
								<li>
                                    <a><i class="fa fa-home"></i><span> Browse</span> <span
												class="fa fa-chevron-down"></span></a>
									<ul class="nav child_menu" style="display: none">
										{if $userdata->hasPermissionTo('view console') == true && $userdata->hasDirectPermission('view console') == true}
											<li><a href="{$smarty.const.WWW_TOP}/Console"><i
														class="fa fa-gamepad"></i><span> Console</span></a></li>
										{/if}
										{if $userdata->hasPermissionTo('view movies') == true && $userdata->hasDirectPermission('view movies') == true}
										<li><a href="{$smarty.const.WWW_TOP}/Movies"><i
														class="fa fa-film"></i><span> Movies</span></a></li>
										{/if}
										{if $userdata->hasPermissionTo('view audio') == true && $userdata->hasDirectPermission('view audio') == true}
										<li><a href="{$smarty.const.WWW_TOP}/Audio"><i
														class="fa fa-music"></i><span> Audio</span></a></li>
										{/if}
										{if $userdata->hasPermissionTo('view pc') == true && $userdata->hasDirectPermission('view pc') == true}
										<li><a href="{$smarty.const.WWW_TOP}/Games"><i
														class="fa fa-gamepad"></i><span> Games</span></a></li>
										{/if}
										{if $userdata->hasPermissionTo('view tv') == true && $userdata->hasDirectPermission('view tv') == true}
										<li><a href="{$smarty.const.WWW_TOP}/series"><i
														class="fa fa-television"></i><span> TV</span></a></li>
										{/if}
										{if $userdata->hasPermissionTo('view adult') == true && $userdata->hasDirectPermission('view adult') == true}
										<li><a href="{$smarty.const.WWW_TOP}/XXX"><i class="fa fa-venus-mars"></i><span> Adult</span></a>
										</li>
										{/if}
										{if $userdata->hasPermissionTo('view books') == true && $userdata->hasDirectPermission('view books') == true}
										<li><a href="{$smarty.const.WWW_TOP}/Books"><i class="fa fa-book"></i><span> Books</span></a>
										</li>
										{/if}
										<li><a href="{$smarty.const.WWW_TOP}/browse/All"><i class="fa fa-list-ul"></i><span> Browse All Releases</span></a>
										</li>
										<li><a href="{$smarty.const.WWW_TOP}/browsegroup"><i class="fa fa-object-group"></i><span> Browse Groups</span></a>
										</li>
									</ul>
								</li>
							{/if}
							<div class="clearfix"></div>
							{if Auth::check()}
								<li>
                                    <a><i class="fa fa-edit"></i> Articles & Links <span class="fa fa-chevron-down"></span></a>

									<ul class="nav child_menu" style="display: none">
										<li><a href="{$smarty.const.WWW_TOP}/forum"><i class="fa fa-forumbee"></i> Forum</a>
										</li>
										<li><a href="{$smarty.const.WWW_TOP}/search"><i class="fab fa-searchengin"></i>
												Search</a>
										</li>
										<li>
                                            <a href="{$smarty.const.WWW_TOP}/rss"><i class="fa fa-rss"></i>RSS Feeds</a>
                                        </li>
										<li>
                                            <a href="{$smarty.const.WWW_TOP}/apihelp"><i class="fa fa-cloud"></i>API</a>
                                        </li>
                                        <li>
                                            <a href="{$smarty.const.WWW_TOP}/apiv2help"><i class="fa fa-cloud"></i>API V2</a>
                                        </li>
									</ul>
                                </li>
                                <div class="clearfix"></div>
								<li>
                                    <a href="{{route('logout')}}"
									   onclick="event.preventDefault(); document.getElementById('frm-logout').submit();">
										<i class="fa fa-unlock"></i><span>Sign Out</span>
									</a>
								</li>
							{else}
								<li>
                                    <a href="{$smarty.const.WWW_TOP}/login"><i class="fa fa-lock"></i><span> Sign In</span></a>
								</li>
							{/if}
							<li>
                                <a href="{$smarty.const.WWW_TOP}/contact-us"><i class="far fa-envelope"></i><span> Contact</span></a>
                            </li>
						</ul>
					</div>
				</div>
				<!-- /sidebar menu -->
			</div>
		</div>
		<!-- top navigation -->
		{if Auth::check()}
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
							<a href="https://github.com/NNTmux/newznab-tmux">NNTmux</a> <i
									class="fab fa-github-alt"></i>.</strong> This software is
						open source, released under the GPL license, proudly powered by <i class="fab fa-laravel"></i>
						<a href="https://github.com/laravel/framework/">Laravel</a>
					</div>
					<div class="clearfix"></div>
				</footer>
				<!-- /footer content -->
			</div>

		</div>
		<!-- /page content -->
	</div>

</div>
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
