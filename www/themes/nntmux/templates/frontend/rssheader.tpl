<?xml version="1.0" encoding="utf-8" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:newznab="http://www.newznab.com/DTD/2010/feeds/attributes/">
<channel>
<atom:link href="{$smarty.const.WWW_TOP}{$smarty.server.REQUEST_URI|escape:"htmlall"|substr:1}" rel="self" type="application/rss+xml" />
<title>{$site->title|escape}{if $rsstitle!=""} {$rsstitle|escape:"htmlall"}{/if}</title>
<description>{$site->title|escape} RSS Feed</description>
<link>{$smarty.const.WWW_TOP}</link>
<language>en-gb</language>
<webMaster>{$site->email} ({$site->title|escape})</webMaster>
<category>{$site->meta_keywords}</category>
<image>
	<url>{$smarty.const.WWW_TOP}themes/nntmux/images/banner.jpg</url>
	<title>{$site->title|escape}</title>
	<link>{$smarty.const.WWW_TOP}</link>
	<description>Visit {$site->title|escape} - {$site->strapline|escape}</description>
</image>