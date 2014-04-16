{$rsshead}

<newznab:response offset="{$offset}" total="{if $releases|@count > 0}{$releases[0]._totalrows}{else}0{/if}" />
{foreach from=$releases item=release}
<item>
	<title>{$release.searchname|escape:html}</title>
	<guid isPermaLink="true">{$serverroot}details/{$release.guid}</guid>
	<link>{$serverroot}getnzb/{$release.guid}.nzb&amp;i={$uid}&amp;r={$rsstoken}</link>
	<comments>{$serverroot}details/{$release.guid}#comments</comments> 	
	<pubDate>{$release.adddate|phpdate_format:"DATE_RSS"}</pubDate> 
	<category>{$release.category_name|escape:html}</category> 	
	<description>{$release.searchname|escape:html}</description>
	<enclosure url="{$serverroot}getnzb/{$release.guid}.nzb&amp;i={$uid}&amp;r={$rsstoken}" length="{$release.size}" type="application/x-nzb" />

	{foreach from=$release.category_ids|parray:"," item=cat}
<newznab:attr name="category" value="{$cat}" />
	{/foreach}
<newznab:attr name="size" value="{$release.size}" />
	<newznab:attr name="guid" value="{$release.guid}" />
{if $extended=="1" || isset($attrs.files)}	<newznab:attr name="files" value="{$release.totalpart}" />
{/if}
{if $extended=="1" || isset($attrs.poster)}	<newznab:attr name="poster" value="{$release.fromname|escape:html}" />
{/if}
{if $release.season != "" && ($extended=="1" || isset($attrs.season))}	<newznab:attr name="season" value="{$release.season}" />
{/if}
{if $release.episode != "" && ($extended=="1" || isset($attrs.episode))}	<newznab:attr name="episode" value="{$release.episode}" />
{/if}
{if $release.rageID != "-1" && $release.rageID != "-2" && ($extended=="1" || isset($attrs.rageid))}	<newznab:attr name="rageid" value="{$release.rageID}" />
{/if}
{if $release.tvtitle != "" && ($extended=="1" || isset($attrs.tvtitle))}	<newznab:attr name="tvtitle" value="{$release.tvtitle|escape:html}" />
{/if}
{if $release.tvairdate != "" && ($extended=="1" || isset($attrs.tvairdate))}	<newznab:attr name="tvairdate" value="{$release.tvairdate|phpdate_format:"DATE_RSS"}" />
{/if}
{if $release.imdbID != "" && ($extended=="1" || isset($attrs.imdb))}	<newznab:attr name="imdb" value="{$release.imdbID}" />
{/if}
{if $release.moi_title != "" && ($extended=="1" || isset($attrs.imdbtitle))}	<newznab:attr name="imdbtitle" value="{$release.moi_title|escape:html}" />
{/if}
{if $release.moi_tagline != "" && ($extended=="1" || isset($attrs.imdbtagline))}	<newznab:attr name="imdbtagline" value="{$release.moi_tagline|escape:html}" />
{/if}
{if $release.moi_plot != "" && ($extended=="1" || isset($attrs.imdbplot))}	<newznab:attr name="imdbplot" value="{$release.moi_plot|escape:html}" />
{/if}
{if $release.moi_rating != "" && ($extended=="1" || isset($attrs.imdbscore))}	<newznab:attr name="imdbscore" value="{$release.moi_rating}" />
{/if}
{if $release.moi_genre != "" && ($extended=="1" || isset($attrs.genre))}	<newznab:attr name="genre" value="{$release.moi_genre|escape:html}" />
{/if}
{if $release.moi_year != "" && ($extended=="1" || isset($attrs.imdbyear))}	<newznab:attr name="imdbyear" value="{$release.moi_year}" />
{/if}
{if $release.moi_director != "" && ($extended=="1" || isset($attrs.imdbdirector))}	<newznab:attr name="imdbdirector" value="{$release.moi_director|escape:html}" />
{/if}
{if $release.moi_actors != "" && ($extended=="1" || isset($attrs.imdbactors))}	<newznab:attr name="imdbactors" value="{$release.moi_actors|escape:html}" />
{/if}
{if $release.moi_cover == 1 && ($extended=="1" || isset($attrs.coverurl))}	<newznab:attr name="coverurl" value="{$serverroot}covers/movies/{$release.imdbID}-cover.jpg" />
{/if}
{if $release.moi_backdrop == 1 && ($extended=="1" || isset($attrs.backdropurl))}	<newznab:attr name="backdropurl" value="{$serverroot}covers/movies/{$release.imdbID}-backdrop.jpg" />
{/if}
{if $release.musicinfoID != "" && $release.mi_title != ""  && ($extended=="1" || isset($attrs.album))}	<newznab:attr name="album" value="{$release.mi_title|escape:html}" />
{/if}
{if $release.musicinfoID != "" && $release.mi_artist != "" && ($extended=="1" || isset($attrs.artist))}	<newznab:attr name="artist" value="{$release.mi_artist|escape:html}" />
{/if}
{if $release.musicinfoID != "" && $release.mi_publisher != "" && ($extended=="1" || isset($attrs.label))}	<newznab:attr name="label" value="{$release.mi_publisher|escape:html}" />
{/if}
{if $release.musicinfoID != "" && $release.mi_tracks != "" && ($extended=="1" || isset($attrs.tracks))}	<newznab:attr name="tracks" value="{$release.mi_tracks|escape:html}" />
{/if}
{if $release.musicinfoID != "" && $release.mi_review != "" && ($extended=="1" || isset($attrs.review))}	<newznab:attr name="review" value="{$release.mi_review|escape:html}" />
{/if}
{if $release.musicinfoID != "" && $release.mi_cover == "1" && ($extended=="1" || isset($attrs.coverurl))}	<newznab:attr name="coverurl" value="{$serverroot}covers/music/{$release.musicinfoID}.jpg" />
{/if}
{if $release.musicinfoID != "" && $release.music_genrename != "" && ($extended=="1" || isset($attrs.genre))}	<newznab:attr name="genre" value="{$release.music_genrename|escape:html}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_author != "" && ($extended=="1" || isset($attrs.author))}	<newznab:attr name="author" value="{$release.bi_author|escape:html}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_title != "" && ($extended=="1" || isset($attrs.booktitle))}	<newznab:attr name="booktitle" value="{$release.bi_title|escape:html}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_cover == "1" && ($extended=="1" || isset($attrs.coverurl))}	<newznab:attr name="coverurl" value="{$serverroot}covers/book/{$release.bookinfoID}.jpg" />
{/if}
{if $release.bookinfoID != "" && $release.bi_review != "" && ($extended=="1" || isset($attrs.review))}	<newznab:attr name="review" value="{$release.bi_review|escape:html}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_publishdate != "" && ($extended=="1" || isset($attrs.publishdate))}	<newznab:attr name="publishdate" value="{$release.bi_publishdate|phpdate_format:"DATE_RSS"}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_publisher != "" && ($extended=="1" || isset($attrs.publisher))}	<newznab:attr name="publisher" value="{$release.bi_publisher|escape:html}" />
{/if}
{if $release.bookinfoID != "" && $release.bi_pages != "" && ($extended=="1" || isset($attrs.pages))}	<newznab:attr name="pages" value="{$release.bi_pages|escape:html}" />
{/if}

{if $extended=="1" || isset($attrs.grabs)}	<newznab:attr name="grabs" value="{$release.grabs}" />
{/if}
{if $extended=="1" || isset($attrs.comments)}	<newznab:attr name="comments" value="{$release.comments}" />
{/if}
{if $extended=="1" || isset($attrs.password)}	<newznab:attr name="password" value="{$release.passwordstatus}" />
{/if}
{if $extended=="1" || isset($attrs.usenetdate)}	<newznab:attr name="usenetdate" value="{$release.postdate|phpdate_format:"DATE_RSS"}" />
{/if}
{if $extended=="1" || isset($attrs.group)}	{$attrs.group}<newznab:attr name="group" value="{$release.group_name|escape:html}" />
{/if}

</item>
{/foreach}

</channel>
</rss>
