<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:newznab="{$smarty.const.WWW_TOP}rss-info/">
	<channel>
		<atom:link href="{$smarty.const.WWW_TOP}{$smarty.server.REQUEST_URI|escape:"htmlall"|substr:1}" rel="self" type="application/rss+xml" />
		<title>{$site->title|escape}</title>
		<description>{$site->title|escape} Nzb Feed</description>
		<link>{$smarty.const.WWW_TOP}</link>
		<language>en-gb</language>
		<webMaster>{$site->email} ({$site->title|escape})</webMaster>
		<category>{$site->meta_keywords}</category>
		<image>
			<url>{$smarty.const.WWW_TOP}templates/nntmux/images/logo.png</url>
			<title>{$site->title|escape}</title>
			<link>{$smarty.const.WWW_TOP}</link>
			<description>Visit {$site->title|escape} - {$site->strapline|escape}</description>
		</image>
<item>
	<title>{$release.searchname|escape:"htmlall"}</title>
	<guid isPermaLink="true">{$smarty.const.WWW_TOP}details/{$release.guid}</guid>
	<link>{$smarty.const.WWW_TOP}nfo/{$release.guid}</link>
	<pubDate>{$release.postdate|phpdate_format:"DATE_RSS"}</pubDate>
	<description>{$nfoutf|escape:"htmlall"}</description>
	<enclosure url="{$smarty.const.WWW_TOP}api?t=getnfo&amp;id={$release.guid}&amp;raw=1&amp;i={$uid}&amp;r={$rsstoken}" length="{$nfoutf|count_characters:true}" type="text/x-nfo" />
</item>

</channel>
</rss>
