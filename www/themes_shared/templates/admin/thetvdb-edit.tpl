
<h1>{$page->title}</h1>

<form enctype="multipart/form-data" action="{$SCRIPT_NAME}?action=submit" method="POST">

<input type="hidden" name="from" value="{$smarty.get.from}" />

<table class="input">

<tr>
	<td><label for="tvdbid">tvdbid</label>:</td>
	<td>
		<input id="tvdbid" class="long" name="tvdbid" type="text" value="{$series.tvdbid|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="actors">actors</label>:</td>
	<td>
		<input id="actors" class="long" name="actors" type="text" value="{$series.actors|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="airsday">airsday</label>:</td>
	<td>
		<input id="airsday" class="long" name="airsday" type="text" value="{$series.airsday|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="airstime">airstime</label>:</td>
	<td>
		<input id="airstime" class="long" name="airstime" type="text" value="{$series.airstime|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="contentrating">contentrating</label>:</td>
	<td>
		<input id="contentrating" class="long" name="contentrating" type="text" value="{$series.contentrating|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="firstaired">firstaired</label>:</td>
	<td>
		<input id="firstaired" class="long" name="firstaired" type="text" value="{$series.firstaired|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="genre">genre</label>:</td>
	<td>
		<input id="genre" class="long" name="genre" type="text" value="{$series.genre|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="imdbid">imdbid</label>:</td>
	<td>
		<input id="imdbid" class="long" name="imdbid" type="text" value="{$series.imdbid|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="network">network</label>:</td>
	<td>
		<input id="network" class="long" name="network" type="text" value="{$series.network|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="overview">overview</label>:</td>
	<td>
		<input id="overview" class="long" name="overview" type="text" value="{$series.overview|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="rating">rating</label>:</td>
	<td>
		<input id="rating" class="long" name="rating" type="text" value="{$series.rating|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="ratingcount">ratingcount</label>:</td>
	<td>
		<input id="ratingcount" class="long" name="ratingcount" type="text" value="{$series.ratingcount|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="runtime">runtime</label>:</td>
	<td>
		<input id="runtime" class="long" name="runtime" type="text" value="{$series.runtime|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="seriesname">seriesname</label>:</td>
	<td>
		<input id="seriesname" class="long" name="seriesname" type="text" value="{$series.seriesname|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="status">status</label>:</td>
	<td>
		<input id="status" class="long" name="status" type="text" value="{$series.status|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td></td>
	<td>
		<input type="submit" value="Save" />
	</td>
</tr>

</table>

</form>