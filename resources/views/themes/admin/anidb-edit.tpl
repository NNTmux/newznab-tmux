
<h1>{$page->title}</h1>

<div class="well well-sm">

<form enctype="multipart/form-data" action="{$SCRIPT_NAME}?action=submit" method="POST">
	{{csrf_field()}}

<input type="hidden" name="from" value="{$smarty.get.from}" />

<table class="input data table table-striped responsive-utilities jambo-table">

<tr>
	<td><label for="anidbid">AniDB Id</label>:</td>
	<td>
		<input id="anidbid" class="long" name="anidbid" type="text" value="{$anime.anidbid|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="title">Anime Name</label>:</td>
	<td>
		<input id="title" class="long" name="title" type="text" value="{$anime.title|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="type">Type</label>:</td>
	<td>
		<input id="type" class="long" name="type" type="text" value="{$anime.type|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="startdate">Start date</label>:</td>
	<td>
		<input id="startdate" class="long" name="startdate" type="text" value="{$anime.startdate|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="enddate">End date</label>:</td>
	<td>
		<input id="enddate" class="long" name="enddate" type="text" value="{$anime.enddate|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="related">Related</label>:</td>
	<td>
		<input id="related" class="long" name="related" type="text" value="{$anime.related|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="creators">Creators</label>:</td>
	<td>
		<input id="creators" class="long" name="creators" type="text" value="{$anime.creators|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="description">Description</label>:</td>
	<td>
		<textarea id="description" name="description">{$anime.description|escape:'htmlall'}</textarea>
	</td>
</tr>

<tr>
	<td><label for="rating">Rating</label>:</td>
	<td>
		<input id="rating" class="long" name="rating" type="text" value="{$anime.rating|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="categories">Categories</label>:</td>
	<td>
		<input id="categories" class="long" name="categories" type="text" value="{$anime.categories|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="characters">Characters</label>:</td>
	<td>
		<input id="characters" class="long" name="characters" type="text" value="{$anime.characters|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="epnos">Episode numbers</label>:</td>
	<td>
		<input id="epnos" class="long" name="epnos" type="text" value="{$anime.episode_no|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="airdates">Episode air dates</label>:</td>
	<td>
		<input id="airdates" class="long" name="airdates" type="text" value="{$anime.airdate|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="episodetitles">Episode titles</label>:</td>
	<td>
		<input id="episodetitles" class="long" name="episodetitles" type="text" value="{$anime.episode_title|escape:'htmlall'}" />
	</td>
</tr>



<tr>
	<td></td>
	<td>
		<input class="btn btn-default" type="submit" value="Save" />
	</td>
</tr>

</table>

</form>
	</div>
