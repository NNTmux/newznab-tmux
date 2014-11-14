<h1>Browse Console</h1>

<form name="browseby" action="console">
	<table class="rndbtn" border="0" cellpadding="2" cellspacing="0">
		<tr>
			<th class="left"><label for="title">Title</label></th>
			<th class="left"><label for="platform">Platform</label></th>
			<th class="left"><label for="genre">Genre</label></th>
			<th class="left"><label for="category">Category</label></th>
			<th></th>
		</tr>
		<tr>
			<td><input id="title" type="text" name="title" value="{$title}" size="15"/></td>
			<td><input id="platform" type="text" name="platform" value="{$platform}" size="15"/></td>
			<td>
				<select id="genre" name="genre">
					<option class="grouping" value=""></option>
					{foreach from=$genres item=gen}
						<option {if $gen.ID == $genre}selected="selected"{/if} value="{$gen.ID}">{$gen.title}</option>
					{/foreach}
				</select>
			</td>
			<td>
				<select id="category" name="t">
					<option class="grouping" value="1000"></option>
					{foreach from=$catlist item=ct}
						<option {if $ct.ID==$category}selected="selected"{/if} value="{$ct.ID}">{$ct.title}</option>
					{/foreach}
				</select>
			</td>
			<td><input type="submit" value="Go"/></td>
		</tr>
	</table>
</form>
<p></p>

{$site->adbrowse}

<div class="btn-group">
	<span class="btn btn-mini active"><i class="icon-picture"></i></span><a class="btn btn-mini" href="{$smarty.const.WWW_TOP}/browse?t={$category}" title="List"><i class="icon-list"></i></a>
</div>

{if $results|@count > 0}

	<form id="nzb_multi_operations_form" action="get">

		<table width="100%">
			<tr>
				<td>{$pager}</td>
				<td style="text-align:right">
					<div class="nzb_multi_operations">
						<small>With selected:</small>
						<div class="btn-group">
							<button type="button" class="btn btn-mini nzb_multi_operations_download"><i class="icon-download"></i> Download NZBs</button>
							<button type="button" class="btn btn-mini nzb_multi_operations_cart"><i class="icon-shopping-cart"></i> Add to cart</button>
							{if $sabintegrated}<button type="button" class="btn btn-mini nzb_multi_operations_sab"><i class="icon-download-alt"></i> Send to my Queue</button>{/if}
							{if $isadmin}
								<button type="button" class="btn btn-mini btn-inverse nzb_multi_operations_edit"><i class="icon-edit icon-white"></i></button>
								<button type="button" class="btn btn-mini btn-inverse nzb_multi_operations_delete"><i class="icon-trash icon-white"></i></button>
								<button type="button" class="btn btn-mini btn-inverse nzb_multi_operations_rebuild"><i class="icon-repeat icon-white"></i></button>
							{/if}
						</div>
						<div class="btn-group">
							<span class="btn btn-mini active"><i class="icon-picture"></i></span><a class="btn btn-mini" href="{$smarty.const.WWW_TOP}/browse?t={$category}" title="List"><i class="icon-list"></i></a>
						</div>
					</div>
				</td>
			</tr>
		</table>

		<table style="width:100%;" class="data highlight icons" id="coverstable">
			<tr>
				<th width="130"><input type="checkbox" class="nzb_check_all"/></th>
				<th>title<br/><a title="Sort Descending" href="{$orderbytitle_desc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
								alt=""/></a><a title="Sort Ascending" href="{$orderbytitle_asc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif" alt=""/></a>
				</th>
				<th>platform<br/><a title="Sort Descending" href="{$orderbyplatform_desc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
								alt=""/></a><a title="Sort Ascending" href="{$orderbyplatform_asc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif" alt=""/></a>
				</th>
				<th>genre<br/><a title="Sort Descending" href="{$orderbygenre_desc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
								alt=""/></a><a title="Sort Ascending" href="{$orderbygenre_asc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif" alt=""/></a>
				</th>
				<th>release date<br/><a title="Sort Descending" href="{$orderbyreleasedate_desc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
								alt=""/></a><a title="Sort Ascending" href="{$orderbyreleasedate_asc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif" alt=""/></a>
				</th>
				<th>posted<br/><a title="Sort Descending" href="{$orderbyposted_desc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
								alt=""/></a><a title="Sort Ascending" href="{$orderbyposted_asc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif" alt=""/></a>
				</th>
				<th>size<br/><a title="Sort Descending" href="{$orderbysize_desc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
								alt=""/></a><a title="Sort Ascending" href="{$orderbysize_asc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif" alt=""/></a>
				</th>
				<th>files<br/><a title="Sort Descending" href="{$orderbyfiles_desc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
								alt=""/></a><a title="Sort Ascending" href="{$orderbyfiles_asc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif" alt=""/></a>
				</th>
				<th>stats<br/><a title="Sort Descending" href="{$orderbystats_desc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
								alt=""/></a><a title="Sort Ascending" href="{$orderbystats_asc}"><img
								src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif" alt=""/></a>
				</th>
			</tr>

			{foreach from=$results item=result}
				<tr class="{cycle values=",alt"}">
					<td class="mid">
						<div class="movcover">
							<a class="title" title="View details"
							   href="{$smarty.const.WWW_TOP}/details/{$result.guid}/{$result.searchname|escape:"seourl"}">
								<img class="shadow"
									 src="{$smarty.const.WWW_TOP}/covers/console/{if $result.cover == 1}{$result.consoleinfoID}.jpg{else}no-cover.jpg{/if}"
									 width="120" border="0" alt="{$result.title|escape:"htmlall"}"/>
							</a>

							<div class="movextra">
								{if $result.nfoID > 0}<a href="{$smarty.const.WWW_TOP}/nfo/{$result.guid}"
														 title="View Nfo" class="rndbtn modal_nfo" rel="nfo">
										Nfo</a>{/if}
								{if $result.url != ""}<a class="rndbtn" target="_blank"
														 href="{$site->dereferrer_link}{$result.url}"
														 name="amazon{$result.consoleinfoID}" title="View amazon page">
										Amazon</a>{/if}
								<a class="rndbtn" href="{$smarty.const.WWW_TOP}/browse?g={$result.group_name}"
								   title="Browse releases in {$result.group_name|replace:"alt.binaries":"a.b"}">Grp</a>
							</div>
						</div>
					</td>
					<td colspan="8" class="left" id="guid{$result.guid}">
						<h2><a class="title" title="View details"
							   href="{$smarty.const.WWW_TOP}/details/{$result.guid}/{$result.searchname|escape:"seourl"}">{$result.title|escape:"htmlall"}
								- {$result.platform|escape:"htmlall"}</a></h2>
						{if $result.genre != ""}<b>Genre:</b>{$result.genre}<br/>{/if}
						{if $result.esrb != ""}<b>Rating:</b>{$result.esrb}<br/>{/if}
						{if $result.publisher != ""}<b>Publisher:</b>{$result.publisher}<br/>{/if}
						{if $result.releasedate != ""}<b>Released:</b>{$result.releasedate|date_format}<br/>{/if}
						{if $result.review != ""}<b>Review:</b>{$result.review|escape:'htmlall'}<br/>{/if}
						<br/>

						<div class="movextra">
							<b>{$result.searchname|escape:"htmlall"}</b> <a class="rndbtn"
																			href="{$smarty.const.WWW_TOP}/console?platform={$result.platform}"
																			title="View similar nzbs">Similar</a>
							{if $isadmin}
								<a class="rndbtn"
								   href="{$smarty.const.WWW_TOP}/admin/release-edit.php?id={$result.releaseID}&amp;from={$smarty.server.REQUEST_URI|escape:"url"}"
								   title="Edit Release">Edit</a>
								<a class="rndbtn confirm_action"
								   href="{$smarty.const.WWW_TOP}/admin/release-delete.php?id={$result.releaseID}&amp;from={$smarty.server.REQUEST_URI|escape:"url"}"
								   title="Delete Release">Del</a>
							{/if}
							<br/>
							<b>Info:</b> {$result.postdate|timeago},  {$result.size|fsize_format:"MB"}, <a
									title="View file list"
									href="{$smarty.const.WWW_TOP}/filelist/{$result.guid}">{$result.totalpart} files</a>,
							<a title="View comments for {$result.searchname|escape:"htmlall"}"
							   href="{$smarty.const.WWW_TOP}/details/{$result.guid}/#comments">{$result.comments}
								cmt{if $result.comments != 1}s{/if}</a>, {$result.grabs}
							grab{if $result.grabs != 1}s{/if}
							<br/>

							<div class="icon"><input type="checkbox" class="nzb_check" value="{$result.guid}"/></div>
							<div class="icon icon_nzb"><a title="Download Nzb"
														  href="{$smarty.const.WWW_TOP}/getnzb/{$result.guid}/{$result.searchname|escape:"htmlall"}">
									&nbsp;</a></div>
							<div class="icon icon_cart" title="Add to Cart"></div>
							{if $sabintegrated}
								<div class="icon icon_sab" title="Send to my Queue"></div>
							{/if}
							{if $weHasVortex}
								<div class="icon icon_nzbvortex" title="Send to NZBVortex"></div>
							{/if}
						</div>
					</td>
				</tr>
			{/foreach}

		</table>

		<div class="nzb_multi_operations">
			<small>With Selected:</small>
			<input type="button" class="rndbtn nzb_multi_operations_download" value="Download NZBs"/>
			<input type="button" class="rndbtn nzb_multi_operations_cart" value="Add to Cart"/>
			{if $sabintegrated}<input type="button" class="rndbtn nzb_multi_operations_sab" value="Send to my Queue"/>{/if}
		</div>

		<br/>

		{$pager}

	</form>
{/if}

<br/><br/><br/>
