<!DOCTYPE html>
<html lang="en">

<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
	<meta http-equiv="X-UA-Compatible" content="IE=9"/>
	<meta name="keywords"
		  content="{$page->meta_keywords}{if $page->meta_keywords != "" && $site->metakeywords != ""},{/if}{$site->metakeywords}"/>
	<meta name="description"
		  content="{$page->meta_description}{if $page->meta_description != "" && $site->metadescription != ""} - {/if}{$site->metadescription}"/>
	<meta name="robots" content="noindex,nofollow"/>
	<meta name="application-name" content="newznab-{$site->version}"/>
	<title>{$page->meta_title}{if $page->meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
	{if $loggedin=="true"}
		<link rel="alternate" type="application/rss+xml" title="{$site->title} Full Rss Feed"
			  href="{$smarty.const.WWW_TOP}/rss?t=0&amp;dl=1&amp;i={$userdata.id}&amp;r={$userdata.rsstoken}" />{/if}

	{if $site->google_adsense_acc != ''}
		<link href="https://www.google.com/cse/api/branding.css" rel="stylesheet" type="text/css" media="screen"/>
	{/if}
	<link href="{$smarty.const.WWW_TOP}/templates/omicron/css/bootstrap-spacelab.min.css" rel="stylesheet"/>
	<!-- FAVICON -->
	<link rel="shortcut icon" type="image/ico" href="{$smarty.const.WWW_TOP}/templates/omicron/images/favicon.ico"/>
	<link rel="search" type="application/opensearchdescription+xml" href="{$smarty.const.WWW_TOP}/opensearch"
		  title="{$site->title|escape}"/>
	<script type="text/javascript">
		/* <![CDATA[ */
		var WWW_TOP = "{$smarty.const.WWW_TOP}";
		var SERVERROOT = "{$serverroot}";
		var UID = "{if $loggedin=="true"}{$userdata.id}{else}{/if}";
		var RSSTOKEN = "{if $loggedin=="true"}{$userdata.rsstoken}{else}{/if}";
		/* ]]> */
	</script>
	{$page->head}
</head>
<body {$page->body}>

<!-- NAV  -->
{$header_menu}
<!-- /NAV  -->

<!-- Container  -->
<div class="container-fluid">
	<div class="row">
		<div class="col-md-2">
			<ul class="nav nav-list">
				{$main_menu}

				{$article_menu}

				{$useful_menu}

				{$recentposts_menu}

			</ul>
		</div>

		<div class="col-md-10">
			{$page->content}
		</div>

	</div>
</div>
<!-- /Container  -->
<!-- Footer -->
<footer class="footer navbar-fixed-bottom">
	<div class="container">
		<p>{$site->footer} All rights reserved {$smarty.now|date_format:"%Y"}</p>
		<ul class="footer-links">
			<li><a href="//github.com/DariusIII">Themed by DariusIII <i class="fa fa-github-alt"></i></a></li>
			<li class="muted">·</li>
			<li><a href="{$smarty.const.WWW_TOP}/terms-and-conditions">{$site->title} terms and conditions</a></li>
		</ul>
	</div>
</footer>
<!-- /Footer -->
{if $site->google_analytics_acc != ''}
{literal}
	<script type="text/javascript">
		/* <![CDATA[ */
		var _gaq = _gaq || [];
		_gaq.push(['_setAccount', '{/literal}{$site->google_analytics_acc}{literal}']);
		_gaq.push(['_trackPageview']);
		_gaq.push(['_trackPageLoadTime']);

		(function () {
			var ga = document.createElement('script');
			ga.type = 'text/javascript';
			ga.async = true;
			ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
			var s = document.getElementsByTagName('script')[0];
			s.parentNode.insertBefore(ga, s);
		})();
		/* ]]> */
	</script>
{/literal}
{/if}

{if $loggedin=="true"}
	<input type="hidden" name="UID" value="{$userdata.id}"/>
	<input type="hidden" name="RSSTOKEN" value="{$userdata.rsstoken}"/>
{/if}

<!-- Javascripts -->
<script type="text/javascript" src="https://code.jquery.com/jquery-2.1.4.js"></script>
<script type="text/javascript" src="https://code.jquery.com/jquery-migrate-1.2.1.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_TOP}/templates/omicron/scripts/jquery.colorbox-min.js"></script>
<script type="text/javascript" src="http://cdn.jsdelivr.net/qtip2/2.2.1/jquery.qtip.min.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_TOP}/templates/omicron/scripts/utils.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_TOP}/templates/omicron/scripts/autosize-min.js"></script>
<script type="text/javascript" src="{$smarty.const.WWW_TOP}/templates/omicron/scripts/sorttable.js"></script>

<!-- Added the Bootstrap 3 JS -->
<script type="text/javascript" src="{$smarty.const.WWW_TOP}/templates/omicron/js/bootstrap-spacelab.js"></script>

<!-- Pines Notify -->
<script type="text/javascript" src="{$smarty.const.WWW_TOP}/templates/omicron/scripts/pnotify.custom.min.js"></script>
<link href="{$smarty.const.WWW_TOP}/templates/omicron/scripts/pnotify.custom.min.js}" media="all" rel="stylesheet"
	  type="text/css"/>

<!-- Modernizr -->
<script type="text/javascript"
		src="{$smarty.const.WWW_TOP}/templates/omicron/scripts/modernizr.custom.44570.js"></script>
</body>
</html>