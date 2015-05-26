<?xml version="1.0" encoding="utf-8" ?> 
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:newznab="http://www.newznab.com/DTD/2010/feeds/attributes/">
<channel>
<atom:link href="{$serverroot}{$smarty.server.REQUEST_URI|escape:"htmlall"|substr:1}" rel="self" type="application/rss+xml" />
<title>{$site->title|escape}{if $rsstitle!=""} {$rsstitle|escape:"htmlall"}{/if}</title>
<description>{if $rssdesc==""}{$site->title|escape} Feed{else}{$rssdesc|escape:"htmlall"}{/if}</description>
<link>{$serverroot}</link>
<language>en-gb</language>
<webMaster>{$site->email} ({$site->title|escape})</webMaster>
<category>{$site->meta_keywords}</category>
<image>
	<url>{$serverroot}templates/default/images/banner.jpg</url>
	<title>{$site->title|escape}</title>
	<link>{$serverroot}</link>
	<description>Visit {$site->title|escape} - {$site->strapline|escape}</description>
</image>