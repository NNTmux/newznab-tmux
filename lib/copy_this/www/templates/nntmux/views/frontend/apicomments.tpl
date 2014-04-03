{$rsshead}

{foreach from=$comments item=comm}
<item>
	<title>{$comm.username}</title>
	<guid isPermaLink="true">{$serverroot}details/{$comm.guid}&amp;comment={$comm.ID}</guid>
	<link>{$serverroot}details/{$comm.guid}&amp;comment={$comm.ID}</link>
	<pubDate>{$comm.createddate|phpdate_format:"DATE_RSS"}</pubDate> 
	<description>{$comm.text|escape:"htmlall"}</description>
</item>
{/foreach}

</channel>
</rss>
