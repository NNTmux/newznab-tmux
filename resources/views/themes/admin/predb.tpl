<h1>{$title}</h1>
<div class="card card-body">
	<form name="presearch" method="get" action="{{URL("/admin/predb")}}" id="custom-search-form"
		  class="form-inline form-horizontal col-4 col-lg-4 float-right">
		{{csrf_field()}}
		<div id="search" class="input-group col-12 col-lg-12">
			<input type="text" class="form-inline" placeholder="Search predb" id="presearch" name="presearch"
				   value="{$lastSearch|escape:'html'}">
			<span class="input-group-btn">
			<button type="submit" value="Go" class="btn btn-success">
				<i class="fab fa-searchengin"></i>
			</button>
		</span>
		</div>
	</form>
	{$results->onEachSide(5)->links()}
	<table class="data table table-striped responsive-utilities jambo-table">
		<tr>
			<th> Date</th>
			<th> Title</th>
			<th> Reqid</th>
			<th> Size</th>
			<th> Files</th>
			<th></th>
			<th></th>
		</tr>
		{foreach $results as $result}
			<tr class="{cycle values=",alt"}">
				<td class="predb" style="text-align:center;">
					{$result.predate|date_format:"%Y-%m-%d %H:%M:%S"}
				</td>
				<td class="predb" style="text-align:center;">
					{if isset($result.guid)}
						<a style="font-style:italic;text-decoration:underline;color:#{if $result.nuked == 1}009933{elseif $result.nuked > 1}990000{/if};"
						   class="title" title="View details"
						   href="{{URL("/details/{$result.guid}")}}">
							<span title="{if $result.nuked == 1}UNNUKED: {$result.nukereason|escape:"htmlall"}{elseif $result.nuked > 1}NUKED: {$result.nukereason|escape:"htmlall"}{else}{$result.title|escape:"htmlall"}{/if}">{$result.title|escape:"htmlall"}</span>
						</a>
					{else}
						<span style="color:#{if $result.nuked == 1}009933{elseif $result.nuked > 1}990000{/if};"
							  title="{if $result.nuked == 1}UNNUKED: {$result.nukereason|escape:"htmlall"}{elseif $result.nuked > 1}NUKED: {$result.nukereason|escape:"htmlall"}{else}{$result.title|escape:"htmlall"}{/if}">{$result.title|escape:"htmlall"}</span>
					{/if}
				</td>
				<td class="predb" style="text-align:center;">
					{if is_numeric($result.requestid) && $result.requestid != 0}
						<a
								class="requestid"
								title="{$result.requestid}"
								href="{{URL("/search?searchadvr=&searchadvsubject={$result.requestid}
															&searchadvposter=&searchadvdaysnew=&searchadvdaysold=&searchadvgroups=-1&searchadvcat=-1
															&searchadvsizefrom=-1&searchadvsizeto=-1&searchadvhasnfo=0&searchadvhascomments=0&search_type=adv")}}"
						>
							{$result.requestid}
						</a>
					{else}
						N/A
					{/if}
				</td>
				<td class="predb" style="text-align:center;">
					{if not in_array($result.size, array('NULL', '', '0MB'))}
						{if strpos($result.size, 'MB') != false && ($result.size|regex_replace:"/(\.\d|,|MB)+/":''|count_characters) > 3}
							{math equation=($result.size|regex_replace:'/(\.\d|,|MB)+/':'' / 1024)|round}GB
						{else}
							{$result.size|regex_replace:"/(\.\d|,)+/":''}
						{/if}
					{else}
						N/A
					{/if}
				</td>
				<td class="predb" style="text-align:center;">
					{if isset($result.files)}
						{$result.files}
					{else}
						N/A
					{/if}
				</td>
				<td class="predb" style="text-align:center;">
					<a
							style="float: right;"
							title="NzbIndex"
							href="{$site->dereferrer_link}http://nzbindex.com/search/?q={$result.title}"
							target="_blank"
					>
						<img src="{{asset("/assets/images/icons/nzbindex.png")}}"/>
						&nbsp;
					</a>
				</td>
				<td class="predb" style="text-align:center;">
					<a
							style="float: right;"
							title="BinSearch"
							href="{$site->dereferrer_link}http://binsearch.info/?q={$result.title}"
							target="_blank"
					>
						<img src="{{asset("/assets/images/icons/binsearch.png")}}"/>
						&nbsp;
					</a>
				</td>
			</tr>
		{/foreach}
	</table>
	<hr>
	<div style="padding-bottom:10px;">
		{$results->onEachSide(5)->links()}
	</div>
</div>
