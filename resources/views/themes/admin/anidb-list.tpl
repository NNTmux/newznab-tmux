<h1>{$title}</h1>

{if $anidblist}

<div class="card card-body">
	<div style="float:right;">

		<form name="anidbsearch" action="">
			{{csrf_field()}}
			<label for="animetitle">Title</label>
			<input id="animetitle" type="text" name="animetitle" value="{$animetitle}" size="15"/>
			&nbsp;&nbsp;
			<input class="btn btn-success" type="submit" value="Go"/>
		</form>
	</div>

		{$anidblist->onEachSide(5)->links()}

	<br/><br/>

	<table style="width:100%;margin-top:10px;"
		   class="Sortable data table table-striped responsive-utilities jambo-table">

		<tr>
			<th style="width:60px;">AniDB Id</th>
			<th>Title</th>
			<th style="width:100px;" class="mid">Options</th>
		</tr>

		{foreach $anidblist as $anidb}
			<tr class="{cycle values=",alt"}">
				<td class="less"><a href="http://anidb.net/perl-bin/animedb.pl?show=anime&amp;aid={$anidb->anidbid}"
									title="View in AniDB">{$anidb->anidbid}</a></td>
				<td><a title="Edit"
					   href="{$smarty.const.WWW_TOP}/admin/anidb-edit?id={$anidb->anidbid}">{$anidb->title|escape:"htmlall"}</a>
				</td>
				<td class="mid"><a title="Delete this AniDB entry"
								   href="{$smarty.const.WWW_TOP}/admin/anidb-delete?id={$anidb->anidbid}">delete</a> | <a
							title="Remove this anidbid from all releases"
							href="{$smarty.const.WWW_TOP}/admin/anidb-remove?id={$anidb->anidbid}">remove</a></td>
			</tr>
		{/foreach}

	</table>

	<br/>

		{$anidblist->onEachSide(5)->links()}
	{else}
	<p>No AniDB episodes available.</p>
	{/if}
</div>
