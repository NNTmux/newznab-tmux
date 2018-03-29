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
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=9" />
	<meta name="keywords" content="{$meta_keywords}{if $meta_keywords != "" && $site->metakeywords != ""},{/if}{$site->metakeywords}" />
	<meta name="description" content="{$meta_description}{if $meta_description != "" && $site->metadescription != ""} - {/if}{$site->metadescription}" />
	<meta name="robots" content="noindex,nofollow"/>
	<meta name="application-name" content="newznab-{$site->version}" />
	<title>{$meta_title}{if $meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
	<meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
	<meta name="csrf-token" content="{$csrf_token}">

{if $loggedin == "true"}
	<link rel="alternate" type="application/rss+xml" title="{$site->title} Full Rss Feed" href="{$smarty.const.WWW_TOP}/rss?t=0&amp;dl=1&amp;i={$userdata.id}&amp;r={$userdata.rsstoken}" />
{/if}

{if $site->google_adsense_acc != ''}
	{{Html::style("http://www.google.com/cse/api/branding.css")}}
{/if}
	{{Html::style("{$smarty.const.WWW_ASSETS}/bootstrap-3.x/dist/css/bootstrap.min.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/css/fa-svg-with-js.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/{$theme}/styles/extra.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/{$theme}/styles/jquery.pnotify.default.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/{$theme}/styles/style.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/{$theme}/styles/bootstrap.cyborg.css")}}
	{{Html::style("{$smarty.const.WWW_ASSETS}/{$theme}/styles/bootstrap-fixes.css")}}

	<!-- FAVICON -->
	<link rel="search" type="application/opensearchdescription+xml" href="/opensearch" title="{$site->title|escape}" />
	<link rel="shortcut icon" type="image/ico" href="{$smarty.const.WWW_ASSETS}/images/favicon.ico"/>

	<!-- Javascripts -->
	<!-- jQuery 3.1.0 -->
	{{Html::script("{$smarty.const.WWW_ASSETS}/jquery-2.2.x/dist/jquery.min.js")}}
	{{Html::script("{$smarty.const.WWW_ASSETS}/jquery-migrate-1.4.x/jquery-migrate.min.js")}}
	{{Html::script("{$smarty.const.WWW_ASSETS}/colorbox/jquery.colorbox-min.js")}}
	{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.qtip2.js")}}
	{{Html::script("{$smarty.const.WWW_ASSETS}/autosize/dist/autosize.min.js")}}
	{{Html::script("{$smarty.const.WWW_ASSETS}/js/sorttable.js")}}
	{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js")}}
	<!-- Added the Bootstrap JS -->
	{{Html::script("{$smarty.const.WWW_ASSETS}/bootstrap-3.x/dist/js/bootstrap.min.js")}}
	<!-- tinymce editor -->
	{{Html::script("{$smarty.const.WWW_ASSETS}/tinymce-builded/js/tinymce/tinymce.min.js")}}
	{{Html::script("{$smarty.const.WWW_ASSETS}/{$theme}/scripts/utils.js")}}
	<!-- Pines Notify -->
	{{Html::script("{$smarty.const.WWW_ASSETS}/js/jquery.pnotify.js")}}
	<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
	<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
	<!--[if lt IE 9]>
	{{Html::script("{$smarty.const.WWW_ASSETS}/html5shiv/dist/html5shiv.min.js")}}
	{{Html::script("{$smarty.const.WWW_ASSETS}/respond/dest/respond.min.js")}}
	<![endif]-->
	<!--font-awesome-->
	{{Html::script("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/js/fa-v4-shims.js")}}
	{{Html::script("{$smarty.const.WWW_ASSETS}/font-awesome/svg-with-js/js/fontawesome-all.js")}}

	{$page->head}
</head>
<body {$page->body}>
<!-- NAV
	================================================== -->
	<!-- If you want the navbar "white" remove Navbar-inverse -->
	<div class="navbar navbar-inverse navbar-fixed-top">
		<div class="container" style="padding-left:30px; padding-right:30px;">
			<div class="container">
						{if $loggedin == "true"}
							{$header_menu}
						{/if}
					{if $loggedin == "true"}
						    <div class="btn-group">
								<a class="btn" href="{$smarty.const.WWW_TOP}/profile"><i class="icon-user icon-white"></i> {$userdata.username} </a>
								<a class="btn dropdown-toggle" data-toggle="dropdown" href="#"><span class="caret"></span></a>
								<ul class="dropdown-menu">
										<li><a href="{$smarty.const.WWW_TOP}/profile"><i class="icon-user icon-white"></i> Profile</a></li>
										<li class="divider"></li>
										<li><a href="{$smarty.const.WWW_TOP}/mymovies"><i class="icon-hdd icon-white"></i> My Movies</a></li>
										<li><a href="{$smarty.const.WWW_TOP}/myshows"><i class="icon-hdd icon-white"></i> My Shows</a></li>
										<li class="divider"></li>
										<li><a href="{$smarty.const.WWW_TOP}/queue"><i class="icon-tasks icon-white"></i> Queue</a></li>
										<li><a href="{$smarty.const.WWW_TOP}/cart"><i class="icon-shopping-cart icon-white"></i> Download Basket</a></li>
									{if isset($isadmin)}
											<li class="divider"></li>
											<li>
													<li><a href="{$smarty.const.WWW_TOP}/admin"><i class="icon-cog icon-white"></i> Admin</a></li>
											</li>
									{/if}
										<li class="divider"></li>
										<li><a href="{$smarty.const.WWW_TOP}/logout"><i class="icon-off icon-white"></i> Logout</a></li>
								</ul>
							</div>
					{else}
							<ul class="nav pull-right">
							<li class="">
								<a href="{$smarty.const.WWW_TOP}/login">Login</a>
							</li>
							</ul>
					{/if}
			</div>
		</div>
	</div>
	</br>
	</br>
	</br>
	<!-- Container
		================================================== -->
		<div class="container-fluid">
			<div class="row-fluid">
				<div class="col-md-2">
					<ul class="nav nav-list">
					{$main_menu}
					{$useful_menu}
					</ul>
				</div>
				<div class="col-md-10">
					{$content}
				</div>
			</div>
		</div>
			{if $loggedin == "true"}
				<input type="hidden" name="UID" value="{$userdata.id}" />
				<input type="hidden" name="RSSTOKEN" value="{$userdata.rsstoken}" />
			{/if}
</body>
</html>
