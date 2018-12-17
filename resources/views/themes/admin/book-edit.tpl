<h1>{$title}</h1>

<div class="card card-body">
	<form enctype="multipart/form-data" action="book-edit?action=submit" method="post">
		{{csrf_field()}}

		<input type="hidden" name="id" value="{$book.id}"/>

		<table class="input data table table-striped responsive-utilities jambo-table">

			<tr>
				<td><label for="title">Title</label>:</td>
				<td>
					<input id="title" class="long" name="title" type="text" value="{$book.title|escape:'htmlall'}"/>
				</td>
			</tr>

			<tr>
				<td><label for="asin">ASIN</label>:</td>
				<td>
					<input id="asin" name="asin" type="text" value="{$book.asin|escape:'htmlall'}"/>
				</td>
			</tr>

			<tr>
				<td><label for="url">URL</label>:</td>
				<td>
					<input id="url" class="long" name="url" type="text" value="{$book.url|escape:'htmlall'}"/>
				</td>
			</tr>

			<tr>
				<td><label for="author">Author</label>:</td>
				<td>
					<input id="author" class="long" name="author" type="text" value="{$book.author|escape:'htmlall'}"/>
				</td>
			</tr>

			<tr>
				<td><label for="publisher">Publisher</label>:</td>
				<td>
					<input id="publisher" class="long" name="publisher" type="text"
						   value="{$book.publisher|escape:'htmlall'}"/>
				</td>
			</tr>

			<tr>
				<td><label for="publishdate">Published Date</label>:</td>
				<td>
					<input id="publishdate" name="publishdate" type="text"
						   value="{$book.publishdate|escape:'htmlall'}"/>
				</td>
			</tr>

			<tr>
				<td><label for="cover">Cover Image</label>:</td>
				<td>
					<input type="file" id="cover" name="cover"/>
					{if $book.cover == 1}
						<img style="max-width:200px; display:block;"
							 src="{$smarty.const.WWW_TOP}/covers/book/{$book.id}.jpg" alt=""/>
					{/if}
				</td>
			</tr>

			<tr>
				<td></td>
				<td>
					<input class="btn btn-success" type="submit" value="Save"/>
				</td>
			</tr>

		</table>

	</form>
</div>
