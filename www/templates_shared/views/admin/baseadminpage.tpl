<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=9" />
	<meta name="keywords" content="" />
	<meta name="description" content="" />
	<title>{$site->title|default:'newznab'} - {$page->meta_title|default:$page->title}</title>
	<link href="{$smarty.const.WWW_TOP}/../templates_shared/styles/style.css" rel="stylesheet" type="text/css" media="screen" />
	<link href="{$smarty.const.WWW_TOP}/../templates_shared/styles/admin.css" rel="stylesheet" type="text/css" media="screen" />
	<link rel="shortcut icon" type="image/ico" href="{$smarty.const.WWW_TOP}/../templates_shared/images/favicon.ico"/>
	<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_TOP}/../templates_shared/scripts/sorttable.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_TOP}/../templates_shared/scripts/utils-admin.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_TOP}/../templates_shared/scripts/jquery.multifile.js"></script>
	<script type="text/javascript" src="{$smarty.const.WWW_TOP}/../templates_shared/scripts/jquery.autosize-min.js"></script>
	<script type="text/javascript">var WWW_TOP = "{$smarty.const.WWW_TOP}/..";</script>

	{$page->head}
</head>
<body>
	<div id="logo" style="cursor: pointer;">
		<h1><a href="/"></a></h1>
		<p><em></em></p>
	</div>
	<hr />

	<div id="header">
		<div id="menu">
		</div>
		<!-- end #menu -->
	</div>

	<div id="page">

		<div id="adpanel">

		</div>

		<div id="content">
			{$page->content}
		</div>
		<!-- end #content -->

		<div id="sidebar">
		<ul>
		<li>
		{$admin_menu}
		</li>

		</ul>
		</div>
		<!-- end #sidebar -->

		<div style="clear: both;">&nbsp;</div>

	</div>
	<!-- end #page -->

	<div id="searchfooter">
		<center>
		</center>
	</div>

	<div class="footer">
	<p>
		{$site->footer}
		<br /><br /><br />Copyright &copy; {$smarty.now|date_format:"%Y"} {$site->title}. All rights reserved.
	</p>
	</div>
	<!-- end #footer -->

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

</body>
</html>