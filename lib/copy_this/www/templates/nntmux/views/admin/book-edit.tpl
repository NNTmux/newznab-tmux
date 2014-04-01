 
<h1>{$page->title}</h1>

<form enctype="multipart/form-data" action="{$SCRIPT_NAME}?action=submit" method="post">

<input type="hidden" name="id" value="{$book.ID}" />

<table class="input">

<tr>
	<td><label for="title">Title</label>:</td>
	<td>
		<input id="title" class="long" name="title" type="text" value="{$book.title|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="asin">ASIN</label>:</td>
	<td>
		<input id="asin" name="asin" type="text" value="{$book.asin|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="url">URL</label>:</td>
	<td>
		<input id="url" class="long" name="url" type="text" value="{$book.url|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="author">Author</label>:</td>
	<td>
		<input id="author" class="long" name="author" type="text" value="{$book.author|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="publisher">Publisher</label>:</td>
	<td>
		<input id="publisher" class="long" name="publisher" type="text" value="{$book.publisher|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="publishdate">Published Date</label>:</td>
	<td>
		<input id="publishdate" name="publishdate" type="text" value="{$book.publishdate|escape:'htmlall'}" />
	</td>
</tr>

<tr>
	<td><label for="cover">Cover Image</label>:</td>
	<td>
		<input type="file" id="cover" name="cover" />
		{if $book.cover == 1}
			<img style="max-width:200px; display:block;" src="{$smarty.const.WWW_TOP}/../covers/book/{$book.ID}.jpg" alt="" />
		{/if}
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