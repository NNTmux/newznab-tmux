{$rsshead}

{foreach from=$releases item=release}
	<!--suppress ALL -->
	<item>
		<title>{$release.searchname}</title>
	<guid isPermaLink="true">{$serverroot}details/{$release.guid}</guid>
	<link>{$serverroot}getnzb/{$release.guid}.nzb&amp;i={$uid}&amp;r={$rsstoken}{if $del=="1"}&amp;del=1{/if}</link>
		<comments>{$serverroot}details/{$release.guid}#comments</comments>
		<pubDate>{$release.adddate|phpdate_format:"DATE_RSS"}</pubDate>
		<category>{$release.category_name|escape:html}</category>
		<description>{$release.searchname}</description>
	<enclosure url="{$serverroot}getnzb/{$release.guid}.nzb&amp;i={$uid}&amp;r={$rsstoken}{if $del=="1"}&amp;del=1{/if}" length="{$release.size}" type="application/x-nzb" />

		{foreach from=$release.category_ids|parray:"," item=cat}
<attr name="category" value="{$cat}" />
	{/foreach}
<attr name="size" value="{$release.size}" />
	<attr name="files" value="{$release.totalpart}" />
	<attr name="poster" value="{$release.fromname|escape:html}" />
	<attr name="guid" value="{$release.guid}" />
{if $release.season != ""}	<attr name="season" value="{$release.season}" />
{/if}
{if $release.episode != ""}	<attr name="episode" value="{$release.episode}" />
{/if}
{if $release.rageID != "-1" && $release.rageID != "-2"}	<attr name="rageid" value="{$release.rageID}" />
{if $release.tvtitle != ""}	<attr name="tvtitle" value="{$release.tvtitle|escape:html}" />
{/if}
{if $release.tvairdate != ""}	<attr name="tvairdate" value="{$release.tvairdate|phpdate_format:"DATE_RSS"}" />
{/if}
{/if}
{if $release.imdbID != ""}	<attr name="imdb" value="{$release.imdbID}" />
{/if}
{if $mov.title != ""}	<attr name="imdbtitle" value="{$mov.title|escape:html}" />
{/if}
{if $mov.tagline != ""}	<attr name="imdbtagline" value="{$mov.tagline|escape:html}" />
{/if}
{if $mov.plot != ""}	<attr name="imdbplot" value="{$mov.plot|escape:html}" />
{/if}
{if $mov.rating != ""}	<attr name="imdbscore" value="{$mov.rating}" />
{/if}
{if $mov.genre != ""}	<attr name="genre" value="{$mov.genre|escape:html}" />
{/if}
{if $mov.year != ""}	<attr name="imdbyear" value="{$mov.year}" />
{/if}
{if $mov.director != ""}	<attr name="imdbdirector" value="{$mov.director|escape:html}" />
{/if}
{if $mov.actors != ""}	<attr name="imdbactors" value="{$mov.actors|escape:html}" />
{/if}
{if $mov.cover == 1}	<attr name="coverurl" value="{$serverroot}covers/movies/{$release.imdbID}-cover.jpg" />
{/if}
{if $mov.backdrop == 1}	<attr name="backdropurl" value="{$serverroot}covers/movies/{$release.imdbID}-backdrop.jpg" />
{/if}
{if $release.musicinfoID != "" && $release.mi_title != ""}	<attr name="album" value="{$release.mi_title|escape:html}" />
{/if}
{if $release.musicinfoID != "" && $release.mi_artist != ""}	<attr name="artist" value="{$release.mi_artist|escape:html}" />
{/if}
{if $release.musicinfoID != "" && $release.mi_publisher != ""}	<attr name="label" value="{$release.mi_publisher|escape:html}" />
{/if}
{if $release.musicinfoID != "" && $release.mi_tracks != ""}	<attr name="tracks" value="{$release.mi_tracks|escape:html}" />
{/if}
{if $release.musicinfoID != "" && $release.mi_review != ""}	<attr name="review" value="{$release.mi_review|escape:html}" />
{/if}
{if $release.musicinfoID != "" && $release.mi_cover == "1"}	<attr name="coverurl" value="{$serverroot}covers/music/{$release.musicinfoID}.jpg" />
{/if}
{if $release.musicinfoID != "" && $release.music_genrename != ""}	<attr name="genre" value="{$release.music_genrename|escape:html}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_author != ""}	<attr name="author" value="{$release.bi_author|escape:html}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_title != ""}	<attr name="booktitle" value="{$release.bi_title|escape:html}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_cover == "1"}	<attr name="coverurl" value="{$serverroot}covers/book/{$release.bookinfoID}.jpg" />
{/if}
{if $release.bookinfoID != "" && $release.bi_review != ""}	<attr name="review" value="{$release.bi_review|escape:html}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_publishdate != ""}	<attr name="publishdate" value="{$release.bi_publishdate|phpdate_format:"DATE_RSS"}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_publisher != ""}	<attr name="publisher" value="{$release.bi_publisher|escape:html}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_pages != ""}	<attr name="pages" value="{$release.bi_pages|escape:html}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_isbn != ""}	<attr name="isbn" value="{$release.bi_isbn|escape:html}" />
{/if}
	<attr name="grabs" value="{$release.grabs}" />
	<attr name="comments" value="{$release.comments}" />
	<attr name="password" value="{$release.passwordstatus}" />
		<attr name="usenetdate" value="{$release.postdate|phpdate_format:"DATE_RSS"}"/>
		<attr name="group" value="{$release.group_name|escape:html}" />

</item>
{/foreach}

</channel>
</rss>
