{$rsshead}

{foreach from=$releases item=release}
<item>
	<title>{$release.searchname|escape:html}</title>
	<guid isPermaLink="true">{$serverroot}details/{$release.guid}</guid>
	<link>{$serverroot}{if $dl=="1"}getnzb{else}details{/if}/{$release.guid}{if $dl=="1"}.nzb&amp;i={$uid}&amp;r={$rsstoken}{/if}{if $del=="1"}&amp;del=1{/if}</link>
	<comments>{$serverroot}details/{$release.guid}#comments</comments> 	
	<pubDate>{$release.adddate|phpdate_format:"DATE_RSS"}</pubDate> 
	<category>{$release.category_name|escape:html}</category> 	
	<description>{if $api=="1"}{$release.searchname}{else}
<![CDATA[{strip}
	<div>
	{if $release.cover == 1}
		<img style="margin-left:10px;margin-bottom:10px;float:right;" src="{$serverroot}covers/movies/{$release.imdbID}-cover.jpg" width="120" border="0" alt="{$release.searchname|escape:"htmlall"}" />
	{/if}
	{if $release.mu_cover == 1}
		<img style="margin-left:10px;margin-bottom:10px;float:right;" src="{$serverroot}covers/music/{$release.musicinfoID}.jpg" width="120" border="0" alt="{$release.searchname|escape:"htmlall"}" />
	{/if}	
	{if $release.co_cover == 1}
		<img style="margin-left:10px;margin-bottom:10px;float:right;" src="{$serverroot}covers/console/{$release.consoleinfoID}.jpg" width="120" border="0" alt="{$release.searchname|escape:"htmlall"}" />
	{/if}	
	{if $release.bo_cover == 1}
		<img style="margin-left:10px;margin-bottom:10px;float:right;" src="{$serverroot}covers/book/{$release.bookinfoID}.jpg" width="120" border="0" alt="{$release.searchname|escape:"htmlall"}" />
	{/if}	
	<ul>
	<li>ID: {$release.guid}</li>
	<li>Name: <a href="{$serverroot}details/{$release.guid}">{$release.searchname}</a></li>
	<li>Size: {$release.size|fsize_format:"MB"} </li>
	<li>Attributes: Category - <a href="{$serverroot}browse?t={$release.categoryID}">{$release.category_name}</a></li>
	<li>Groups: <a href="{$serverroot}browse?g={$release.group_name}">{$release.group_name}</a></li>
	<li>Poster: {$release.fromname|escape:"htmlall"}</li>
	<li>PostDate: {$release.postdate|phpdate_format:"DATE_RSS"}</li>
	<li>Password: {if $release.passwordstatus == 0}None{elseif $release.passwordstatus == 2}Passworded Rar Archive{elseif $release.passwordstatus == 1}Contains Cab/Ace/RAR Archive{else}Unknown{/if}</li>
	
	{if $release.nfoID != ""}
		<li>Nfo: <a href="{$serverroot}api?t=getnfo&amp;id={$release.guid}&amp;raw=1&amp;i={$uid}&amp;r={$rsstoken}">{$release.searchname}.nfo</a></li>
	{/if}
	
	{if $release.parentCategoryID == 2000}
		{if $release.imdbID != ""}
		<li>Imdb Info: 
			<ul>
				<li>IMDB Link: <a href="http://www.imdb.com/title/tt{$release.imdbID}/">{$release.imdbtitle|escape:"htmlall"}</a></li>
				{if $release.rating != ""}<li>Rating: <span style="font-weight:bold; color:{if $release.rating < 4}#FF0000{elseif $release.rating < 7}#FFAA2A{else}#2AFF00{/if};">{$release.rating|escape:"htmlall"}</span></li>{/if}
				{if $release.plot != ""}<li>Plot: {$release.plot|escape:"htmlall"}</li>{/if}
				{if $release.year != ""}<li>Year: {$release.year|escape:"htmlall"}</li>{/if}
				{if $release.genre != ""}<li>Genre: {$release.genre|replace:"|":", "|escape:"htmlall"}</li>{/if}
				{if $release.director != ""}<li>Director: {$release.director|replace:"|":", "|escape:"htmlall"}</li>{/if}
				{if $release.actors != ""}<li>Actors: {$release.actors|replace:"|":", "|escape:"htmlall"}</li>{/if}
			</ul>
		</li>
		{/if}
	{/if}
	
	{if $release.parentCategoryID == 3000}
		{if $release.musicinfoID > 0}
		<li>Music Info: 
			<ul>
				{if $release.mu_url != ""}<li>Amazon: <a href="{$release.mu_url}">{$release.mu_title|escape:"htmlall"}</a></li>{/if}
				{if $release.mu_artist != ""}<li>Artist: {$release.mu_artist|escape:"htmlall"}</li>{/if}
				{if $release.mu_genre != ""}<li>Genre: {$release.mu_genre|escape:"htmlall"}</li>{/if}
				{if $release.mu_publisher != ""}<li>Publisher: {$release.mu_publisher|escape:"htmlall"}</li>{/if}
				{if $release.year != ""}<li>Released: {$release.mu_releasedate|date_format}</li>{/if}
				{if $release.mu_review != ""}<li>Review: {$release.mu_review|escape:"htmlall"}</li>{/if}
				{if $release.mu_tracks != ""}
				<li>Track Listing:
					<ol>
						{assign var="tracksplits" value="|"|explode:$release.mu_tracks}
						{foreach from=$tracksplits item=tracksplit}
						<li>{$tracksplit|trim|escape:"htmlall"}</li>
						{/foreach}		
					</ol>
				</li>				
				{/if}
			</ul>
		</li>
		{/if}
	{/if}	

	{if $release.parentCategoryID == 1000}
		{if $release.consoleinfoID > 0}
		<li>Console Info: 
			<ul>
				{if $release.co_url != ""}<li>Amazon: <a href="{$release.co_url}">{$release.co_title|escape:"htmlall"}</a></li>{/if}
				{if $release.co_genre != ""}<li>Genre: {$release.co_genre|escape:"htmlall"}</li>{/if}
				{if $release.co_publisher != ""}<li>Publisher: {$release.co_publisher|escape:"htmlall"}</li>{/if}
				{if $release.year != ""}<li>Released: {$release.co_releasedate|date_format}</li>{/if}
				{if $release.co_review != ""}<li>Review: {$release.co_review|escape:"htmlall"}</li>{/if}
			</ul>
		</li>
		{/if}
	{/if}	

	{if $release.categoryID == 7020}
		{if $release.bookinfoID > 0}
		<li>Console Info: 
			<ul>
				{if $release.bo_author != ""}<li>Author: <a href="{$serverroot}books?author={$release.bo_author|escape:"url"}">{$release.bo_author|escape:"htmlall"}</a></li>{/if}
				{if $release.bo_url != ""}<li>Amazon: <a href="{$release.bo_url}">{$release.bo_title|escape:"htmlall"}</a></li>{/if}
				{if $release.bo_publisher != ""}<li>Publisher: {$release.bo_publisher|escape:"htmlall"}</li>{/if}
				{if $release.bo_publishdate != ""}<li>Publish Date: {$release.bo_publishdate|date_format}</li>{/if}
				{if $release.bo_review != ""}<li>Review: {$release.bo_review|escape:"htmlall"}</li>{/if}
			</ul>
		</li>
		{/if}
	{/if}		
	
	</ul>
	
	</div>
	<div style="clear:both;">
	{/strip}]]>
	{/if}
</description>
	{if $dl=="1"}<enclosure url="{$serverroot}getnzb/{$release.guid}.nzb&amp;i={$uid}&amp;r={$rsstoken}{if $del=="1"}&amp;del=1{/if}" length="{$release.size}" type="application/x-nzb" />{/if}


	{foreach from=$release.category_ids|parray:"," item=cat}
<newznab:attr name="category" value="{$cat}" />
	{/foreach}<newznab:attr name="size" value="{$release.size}" />
	<newznab:attr name="files" value="{$release.totalpart}" />
	<newznab:attr name="poster" value="{$release.fromname|escape:html}" />
{if $release.season != ""}	<newznab:attr name="season" value="{$release.season}" />
{/if}
{if $release.episode != ""}	<newznab:attr name="episode" value="{$release.episode}" />
{/if}
{if $release.fullep != ""}	<newznab:attr name="fullep">{$release.fullep}</newznab:attr>
{/if}
{if $release.showtitle != ""}	<newznab:attr name="showtitle">{$release.showtitle|escape:html}</newznab:attr>
{/if}
{if $release.rageID != "-1" && $release.rageID != "-2"}	<newznab:attr name="rageid" value="{$release.rageID}" />
{/if}
{if $release.tvtitle != ""}	<newznab:attr name="tvtitle" value="{$release.tvtitle|escape:html}" />
{/if}
{if $release.tvairdate != ""}	<newznab:attr name="tvairdate" value="{$release.tvairdate|phpdate_format:"DATE_RSS"}" />
{/if}
{if $release.imdbID != ""}	<newznab:attr name="imdb" value="{$release.imdbID}" />
{/if}
{if $release.tvdbID != ""}	<newznab:attr name="tvdb-show" value="{$release.tvdbID}" />
{/if}
{if $release.ep_tvdbID != ""}	<newznab:attr name="tvdb-ep" value="{$release.ep_tvdbID}" />
{/if}
{if $release.rating != ""}	<newznab:attr name="rating" value="{$release.rating}" />
{/if}
{if $release.overview != ""}	<newznab:attr name="overview">{$release.overview|escape:html}</newznab:attr>
{/if}
{if $release.writer != ""}
    {foreach from=$release.writer|parray:"," item=writer}
    <newznab:attr name="writer" value="{$writer|trim|escape:html}" />
    {/foreach}
{/if}
{if $release.gueststars != ""}
    {foreach from=$release.gueststars|parray:"," item=gueststar}
    <newznab:attr name="gueststar" value="{$gueststar|trim|escape:html}" />
    {/foreach}
{/if}
{if $release.director != ""}
    {foreach from=$release.director|parray:"," item=director}
    <newznab:attr name="director" value="{$director|trim|escape:html}" />
    {/foreach}
{/if}
	<newznab:attr name="grabs" value="{$release.grabs}" />
	<newznab:attr name="comments" value="{$release.comments|escape:"htmlall"}" />
	<newznab:attr name="password" value="{$release.passwordstatus}" />
	<newznab:attr name="usenetdate" value="{$release.postdate|phpdate_format:"DATE_RSS"}" />	
	<newznab:attr name="group" value="{$release.group_name|escape:html}" />
		
</item>
{/foreach}

</channel>
</rss>