<!doctype html>
<html lang="en">
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="keywords" content="{$page->meta_keywords}{if $page->meta_keywords != "" && $site->metakeywords != ""},{/if}{$site->metakeywords}" />
	<meta name="description" content="{$page->meta_description}{if $page->meta_description != "" && $site->metadescription != ""} - {/if}{$site->metadescription}" />
	<meta name="application-name" content="newznab-{$site->version}" />
	<title>{$page->meta_title}{if $page->meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
{if $loggedin=="true"}	<link rel="alternate" type="application/rss+xml" title="{$site->title} Full Rss Feed" href="{$smarty.const.WWW_TOP}/rss?t=0&amp;dl=1&amp;i={$userdata.ID}&amp;r={$userdata.rsstoken}" />{/if}

	<link href="{$smarty.const.WWW_TOP}/templates/nntmux/styles/style.css" rel="stylesheet" type="text/css" media="screen" />
	<link href="{$smarty.const.WWW_TOP}/templates/nntmux/styles/jquery.qtip.css" rel="stylesheet" type="text/css" media="screen" />
{if $site->google_adsense_acc != ''}	<link href="http://www.google.com/cse/api/branding.css" rel="stylesheet" type="text/css" media="screen" />
{/if}
	<!-- Included CSS files - Bootstrap 2.3.2 - Font Awesome 3.2.0 - plugins master style.css -->
	<link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.2/css/bootstrap-combined.no-icons.min.css" rel="stylesheet">
	<!--<link href="//cdnjs.cloudflare.com/ajax/libs/font-awesome/3.2.0/css/font-awesome.css" rel="stylesheet" media="screen">-->
	<link href="{$smarty.const.WWW_TOP}/templates/nntmux/styles/jquery.qtip.css" rel="stylesheet" media="screen">
	<!-- <link href="{$smarty.const.WWW_TOP}/themes_shared/styles/subnav.css" rel="stylesheet" media="screen"> -->
	<link href="{$smarty.const.WWW_TOP}/templates/nntmux/styles/posterwall.css" rel="stylesheet" type="text/css" media="screen" />
	<link href="{$smarty.const.WWW_TOP}/templates/nntmux/styles/style.css" rel="stylesheet" media="screen">
	<link rel="shortcut icon" type="image/ico" href="{$smarty.const.WWW_TOP}/templates/nntmux/images/favicon.ico"/>
	<!--<link rel="search" type="application/opensearchdescription+xml" href="{$smarty.const.WWW_TOP}/opensearch" title="{$site->title|escape}" />-->
	<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_TOP}/templates/nntmux/scripts/jquery.colorbox-min.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_TOP}/templates/nntmux/scripts/jquery.autosize-min.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_TOP}/templates/nntmux/scripts/utils.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_TOP}/templates/nntmux/scripts/sorttable.js"></script>

	<!-- Manual Adjustment for Search input fields on browse pages. -->
	<style>
		select { min-width: 120px ; width: auto; }
		input { width: 180px; }
	</style>

	<script type="text/javascript">
	/* <![CDATA[ */
		var WWW_TOP = "{$smarty.const.WWW_TOP}";
		var SERVERROOT = "{$serverroot}";
		var UID = "{if $loggedin=="true"}{$userdata.ID}{else}{/if}";
		var RSSTOKEN = "{if $loggedin=="true"}{$userdata.rsstoken}{else}{/if}";
	/* ]]> */
	</script>
	{$page->head}
</head>
<body {$page->body}>

	{strip}
	<div id="statusbar">
		{if $loggedin=="true"}
			Welcome back <a href="{$smarty.const.WWW_TOP}/profile">{$userdata.username}</a>. <a href="{$smarty.const.WWW_TOP}/logout">Logout</a>
		{else}
			<a href="{$smarty.const.WWW_TOP}/login">Login</a> or <a href="{$smarty.const.WWW_TOP}/register">Register</a>
		{/if}
	</div>
	{/strip}

	<div id="logo">
		<a class="logolink" title="{$site->title} Logo" href="{$smarty.const.WWW_TOP}{$site->home_link}"><img class="logoimg" alt="{$site->title} Logo" src="{$smarty.const.WWW_TOP}/templates/nntmux/images/clearlogo.png" /></a>

		<h1><a href="{$smarty.const.WWW_TOP}{$site->home_link}">{$site->title}</a></h1>
		<p><em>{$site->strapline}</em></p>

		{$site->adheader}

	</div>
	<hr />

	<div id="header">
		<div id="menu">

			{if $loggedin=="true"}
				{$header_menu}
			{/if}

		</div>
	</div>

	<div id="page">

		<div id="content">
			{$page->content}
		</div>

		<div id="sidebar">
			<ul>

			{$main_menu}

			{$article_menu}

			{$useful_menu}

			{$recentposts_menu}

			{if $site->google_adsense_acc != '' && $site->google_adsense_search != ''}
			{literal}
				<li>
				<h2>Search for {/literal}{$site->term_plural}{literal}</h2>
				<div style="padding-left:20px;">
					<div class="cse-branding-bottom" style="background-color:#FFFFFF;color:#000000">
					  <div class="cse-branding-form">
					    <form action="http://www.google.co.uk/cse" id="cse-search-box" target="_blank">
					      <div>
					        <input type="hidden" name="cx" value="partner-{/literal}{$site->google_adsense_acc}{literal}:{/literal}{$site->google_adsense_search}{literal}" />
					        <input type="hidden" name="ie" value="UTF-8" />
					        <input type="text" name="q" size="10" />
					        <input type="submit" name="sa" value="Search" />
					      </div>
					    </form>
					  </div>
					  <div class="cse-branding-logo">
					    <img src="http://www.google.com/images/poweredby_transparent/poweredby_FFFFFF.gif" alt="Google" />
					  </div>
					  <div class="cse-branding-text">
					    Custom Search
					  </div>
					</div>
				</div>
				</li>
			{/literal}
			{/if}

			<li>
				<a title="Sickbeard - The ultimate usenet PVR" href="http://www.sickbeard.com/"><img class="menupic" alt="Sickbeard - The ultimate usenet PVR" src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sickbeard.png" /></a>
			</li>
			<li>
				<a title="Sabznbd - A great usenet binary downloader" href="http://www.sabnzbd.org/"><img class="menupic" alt="Sabznbd - A great usenet binary downloader" src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sabnzbd.png" /></a>
			</li>
				<li>
					<a title="NZBGet - The most efficient usenet downloader" href="http://nzbget.net/"><img class="menupic" alt="NZBGet - The most efficient usenet downloader" src="{$smarty.const.WWW_TOP}/templates/nntmux/images/nzbget.png" /></a>
				</li>
				<li>
					<a title="Couchpotato - Download movies automatically, easily and in the best quality as soon as they are available" href="https://couchpota.to/"><img class="menupic" alt="Couchpotato - Download movies automatically, easily and in the best quality as soon as they are available" src="{$smarty.const.WWW_TOP}/templates/nntmux/images/couchpotato.png" /></a>
				</li>
			</ul>
		</div>

		<div style="clear: both;text-align:right;">
			<a class="w3validator" href="http://validator.w3.org/check?uri=referer">
			<img src="{$smarty.const.WWW_TOP}/templates/nntmux/images/valid-xhtml10.png" alt="Valid XHTML 1.0 Transitional" height="31" width="88" />
			</a>
		</div>

	</div>

	<div class="footer">
	<p>
		{$site->footer}
		<br /><br /><br /><a title="newznab - A usenet indexing web application with community features." href="http://www.newznab.com/">newznab</a> all rights reserved {$smarty.now|date_format:"%Y"}. <br/> <a title="Chat about newznab" href="http://www.newznab.com/chat.html">newznab chat</a> <br/><a href="{$smarty.const.WWW_TOP}/terms-and-conditions">{$site->title} terms and conditions</a>
	</p>
	</div>
	{if $site->google_analytics_acc != ''}
	{literal}
	<script type="text/javascript">
	/* <![CDATA[ */
	  var _gaq = _gaq || [];
	  _gaq.push(['_setAccount', '{/literal}{$site->google_analytics_acc}{literal}']);
	  _gaq.push(['_trackPageview']);
	  _gaq.push(['_trackPageLoadTime']);

	  (function() {
		var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
		ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
		var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	  })();
	/* ]]> */
	</script>

	{/literal}
	{/if}

{if $loggedin=="true"}
<input type="hidden" name="UID" value="{$userdata.ID}" />
<input type="hidden" name="RSSTOKEN" value="{$userdata.rsstoken}" />
{/if}

</body>
</html>
