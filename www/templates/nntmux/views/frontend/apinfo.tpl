{$rsshead}

<item>
	<title>{$release.searchname|escape:"htmlall"}</title>
	<guid isPermaLink="true">{$serverroot}details/{$release.guid}</guid>
	<link>{$serverroot}nfo/{$release.guid}</link>
	<pubDate>{$release.postdate|phpdate_format:"DATE_RSS"}</pubDate> 
	<description>{$nfoutf|escape:"htmlall"}</description>
	<enclosure url="{$serverroot}api?t=getnfo&amp;id={$release.guid}&amp;raw=1&amp;i={$uid}&amp;r={$rsstoken}" length="{$nfoutf|count_characters:true}" type="text/x-nfo" />
</item>

</channel>
</rss>
