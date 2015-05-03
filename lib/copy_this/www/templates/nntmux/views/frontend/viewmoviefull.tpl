{if $results|@count > 0}

	{foreach from=$results item=result}
		<div id="moviefull">
			{if $result.banner == 1}<img class="shadow img-thumbnail" alt="{$result.title|escape:"htmlall"} Logo"
										src="{$smarty.const.WWW_TOP}/covers/movies/{$result.imdbid}-banner.jpg"/>
			{elseif $result.cover == 1}<img class="shadow pic" alt="{$result.title|escape:"htmlall"} Logo"
											 src="{$smarty.const.WWW_TOP}/covers/movies/{$result.imdbid}-cover.jpg" width="180" border="0"/>{/if}
			<h1>{$result.title|escape:"htmlall"} ({$result.year})</h1>

			<h2>{if $result.genre != ''}{$result.genre|replace:"|":" / "}{/if}</h2>

			<p>
				{if $result.tagline != ''}<strong>{$result.tagline|escape:"htmlall"}</strong>{/if}
				{if $result.plot != ''}<br/><span class="descinitial">{$result.plot|escape:"htmlall"}</span>{/if}
				{if $result.rating != ''}
					<br/>
					<br/>
					<strong>Rating:</strong>
					{$result.rating}/10{if $result.ratingcount != ''}
					<small>({$result.ratingcount|number_format} votes)</small>{/if}{/if}
				{if $result.director != ''}<br/><strong>Director:</strong>{$result.director|replace:"|":", "}{/if}
				{if $result.actors != ''}<br/><strong>Actors:</strong>{$result.actors|replace:"|":", "}{/if}
				{if in_array("1", ","|explode:$result.grp_haspreview) && $userdata.canpreview == 1}
					<br/>
					<strong>Previews:</strong>
					<a id="showmoviepreviews" href="#">Show previews</a>
				{/if}
				{if $result.trailer != ''}
					<br/>
					<strong>Trailer:</strong>
				<a name="name{$result.imdbid}" class="modal_imdbtrailer" href="#">Show trailer</a>{/if}
			</p>
		</div>
		{if in_array("1", ","|explode:$result.grp_haspreview) && $userdata.canpreview == 1}
			<div id="moviepreviews" style="display:none;">
				{assign var="msplits" value=","|explode:$result.grp_release_id}
				{assign var="mhaspreview" value=","|explode:$result.grp_haspreview}
				{assign var="mguid" value=","|explode:$result.grp_release_guid}
				{assign var="mname" value="#"|explode:$result.grp_release_name}
				{foreach from=$msplits item=m}
					{if $mhaspreview[$m@index] == 1}
						<a href="{$smarty.const.WWW_TOP}/details/{$mguid[$m@index]}/{$mname[$m@index]|escape:"seourl"}">
							<img class="shadow pic"
								 src="{$smarty.const.WWW_TOP}/covers/preview/{$mguid[$m@index]}_thumb.jpg"/><br/>

							<div class="previewtitle">{$mname[$m@index]}</div>
						</a>
					{/if}
				{/foreach}
			</div>
		{/if}
		<form id="nzb_multi_operations_form" action="get">

			<div class="nzb_multi_operations">
				{if $section != ''}View:
					<a href="{$smarty.const.WWW_TOP}/{$section}?t={$category}">Covers</a>
					|
					<b>List</b>
					<br/>
				{/if}
				<small>With Selected:</small>
				<input type="button" class="rndbtn nzb_multi_operations_download" value="Download NZBs"/>
				<input type="button" class="rndbtn nzb_multi_operations_cart" value="Add to Cart"/>
				{if $sabintegrated}
					<input type="button" class="rndbtn nzb_multi_operations_sab" value="Send to my Queue"/>
				{/if}
				{if $isadmin}
					<input type="button" class="rndbtn nzb_multi_operations_edit" value="Edit"/>
					<input type="button" class="rndbtn nzb_multi_operations_delete" value="Del"/>
				{/if}
			</div>

			<table style="width:100%;" class="data highlight icons" id="browsetable">
				<tr>
					<th><input id="chkSelectAll" type="checkbox" class="nzb_check_all"/><label for="chkSelectAll"
																							   style="display:none;">Select
							All</label></th>
					<th>name<br/><a title="Sort Descending" href="{$orderbyname_desc}"><img
									src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
									alt="Sort Descending"/></a><a title="Sort Ascending" href="{$orderbyname_asc}"><img
									src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif"
									alt="Sort Ascending"/></a></th>
					<th>category<br/><a title="Sort Descending" href="{$orderbycat_desc}"><img
									src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
									alt="Sort Descending"/></a><a title="Sort Ascending" href="{$orderbycat_asc}"><img
									src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif"
									alt="Sort Ascending"/></a></th>
					<th>posted<br/><a title="Sort Descending" href="{$orderbyposted_desc}"><img
									src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
									alt="Sort Descending"/></a><a title="Sort Ascending"
																  href="{$orderbyposted_asc}"><img
									src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif"
									alt="Sort Ascending"/></a></th>
					<th>size<br/><a title="Sort Descending" href="{$orderbysize_desc}"><img
									src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
									alt="Sort Descending"/></a><a title="Sort Ascending" href="{$orderbysize_asc}"><img
									src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif"
									alt="Sort Ascending"/></a></th>
					<th>files<br/><a title="Sort Descending" href="{$orderbyfiles_desc}"><img
									src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
									alt="Sort Descending"/></a><a title="Sort Ascending" href="{$orderbyfiles_asc}"><img
									src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif"
									alt="Sort Ascending"/></a></th>
					<th>stats<br/><a title="Sort Descending" href="{$orderbystats_desc}"><img
									src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_down.gif"
									alt="Sort Descending"/></a><a title="Sort Ascending" href="{$orderbystats_asc}"><img
									src="{$smarty.const.WWW_TOP}/templates/nntmux/images/sorting/arrow_up.gif"
									alt="Sort Ascending"/></a></th>
					<th></th>
				</tr>

				{assign var="msplits" value=","|explode:$result.grp_release_id}
				{assign var="mguid" value=","|explode:$result.grp_release_guid}
				{assign var="mnfo" value=","|explode:$result.grp_release_nfoid}
				{assign var="mgrp" value=","|explode:$result.grp_release_grpname}
				{assign var="mname" value="#"|explode:$result.grp_release_name}
				{assign var="mpostdate" value=","|explode:$result.grp_release_postdate}
				{assign var="msize" value=","|explode:$result.grp_release_size}
				{assign var="mtotalparts" value=","|explode:$result.grp_release_totalparts}
				{assign var="mcomments" value=","|explode:$result.grp_release_comments}
				{assign var="mgrabs" value=","|explode:$result.grp_release_grabs}
				{assign var="mpass" value=","|explode:$result.grp_release_password}
				{assign var="minnerfiles" value=","|explode:$result.grp_rarinnerfilecount}
				{assign var="mhaspreview" value=","|explode:$result.grp_haspreview}
				{assign var="mcat" value=","|explode:$result.grp_release_categoryid}
				{assign var="mcatname" value=","|explode:$result.grp_release_categoryname}

				{foreach from=$msplits item=m}
					<tr class="{cycle values=",alt"}" id="guid{$mguid[$m@index]}">
						<td class="check"><input id="chk{$mguid[$m@index]|substr:0:7}" type="checkbox" class="nzb_check"
												 value="{$mguid[$m@index]}"/></td>
						<td class="item">
							<label for="chk{$mguid[$m@index]|substr:0:7}"><a class="title" title="View details"
																			 href="{$smarty.const.WWW_TOP}/details/{$mguid[$m@index]}/{$mname[$m@index]|escape:"seourl"}">{$mname[$m@index]|escape:"htmlall"|replace:".":" "}</a></label>

							{if $mpass[$m@index] == 2}
								<img title="Passworded Rar Archive"
									 src="{$smarty.const.WWW_TOP}/templates/nntmux/images/icons/lock.gif"
									 alt="Passworded Rar Archive"/>
							{elseif $mpass[$m@index] == 1}
								<img title="Contains .cab/ace/rar Archive"
									 src="{$smarty.const.WWW_TOP}/templates/nntmux/images/icons/lock.gif"
									 alt="Contains .cab/ace/rar Archive"/>
							{/if}

							<div class="resextra">
								<div class="btns">{strip}
										{if $mnfo[$m@index] > 0}<a href="{$smarty.const.WWW_TOP}/nfo/{$mguid[$m@index]}"
																   title="View Nfo" class="modal_nfo rndbtn" rel="nfo">
												Nfo</a>{/if}
										{if $mhaspreview[$m@index] == 1 && $userdata.canpreview == 1}<a
											href="{$smarty.const.WWW_TOP}/covers/preview/{$mguid[$m@index]}_thumb.jpg"
											name="name{$mguid[$m@index]}" title="View Screenshot"
											class="modal_prev rndbtn" rel="preview">Preview</a>{/if}
										{if $mre[$m@index] > 0}<span class="mediainfo rndbtn"title="{$mguid[$m@index]}">
												Media</span>{/if}
									{/strip}</div>
							</div>
						</td>
						<td class="less"><a title="Browse {$mcatname[$m@index]}"
											href="{$smarty.const.WWW_TOP}/browse?t={$mcat[$m@index]}">{$mcatname[$m@index]}</a>
						</td>
						<td class="less mid" title="{$mpostdate[$m@index]}">{$mpostdate[$m@index]|timeago}</td>
						<td class="less right">{$msize[$m@index]|fsize_format:"MB"}</td>
						<td class="less mid">
							<a title="View file list"
							   href="{$smarty.const.WWW_TOP}/filelist/{$mguid[$m@index]}">{$mtotalparts[$m@index]}</a>
							{if $minnerfiles[$m@index] > 0}
								<div class="rarfilelist">
									<img src="{$smarty.const.WWW_TOP}/templates/nntmux/images/icons/magnifier.png"
										 alt="{$mguid[$m@index]}" class="tooltip"/>
								</div>
							{/if}
						</td>
						<td class="less nowrap"><a title="View comments"
												   href="{$smarty.const.WWW_TOP}/details/{$mguid[$m@index]}/#comments">{$mcomments[$m@index]}
								cmt{if $mcomments[$m@index] != 1}s{/if}</a><br/>{$mgrabs[$m@index]}
							grab{if $mgrabs[$m@index] != 1}s{/if}</td>
						<td class="icons">
							<div class="icon icon_nzb"><a title="Download Nzb"
														  href="{$smarty.const.WWW_TOP}/getnzb/{$mguid[$m@index]}/{$mname[$m@index]|escape:"url"}">
									&nbsp;</a></div>
							<div class="icon icon_cart" title="Add to Cart"></div>
							{if $sabintegrated}
								<div class="icon icon_sab" title="Send to my Queue"></div>
							{/if}
						</td>
					</tr>
				{/foreach}

			</table>

			<div class="nzb_multi_operations">
				<small>With Selected:</small>
				<input type="button" class="rndbtn nzb_multi_operations_download" value="Download NZBs"/>
				<input type="button" class="rndbtn nzb_multi_operations_cart" value="Add to Cart"/>
				{if $sabintegrated}
					<input type="button" class="rndbtn nzb_multi_operations_sab" value="Send to my Queue"/>
				{/if}
				{if $isadmin}
					<input type="button" class="rndbtn nzb_multi_operations_edit" value="Edit"/>
					<input type="button" class="rndbtn nzb_multi_operations_delete" value="Del"/>
				{/if}
			</div>

		</form>
	{/foreach}
{/if}