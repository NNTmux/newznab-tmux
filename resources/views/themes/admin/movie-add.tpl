<div class="well well-sm">
	<h1>{$title}</h1>

	<form enctype="multipart/form-data" action="movie-add" method="post">
		{{csrf_field()}}
		<table class="input data table table-striped responsive-utilities jambo-table">

			<tr>
				<td></td>
				<td>Enter the full 7 digit IMDB id into the box below and click Add.</td>
			</tr>

			<tr>
				<td><label for="title">IMDB id</label>:</td>
				<td>
					<input id="id" class="long" name="id" type="text" value=""/>
				</td>
			</tr>

			<tr>
				<td></td>
				<td>
					<input class="btn btn-default" type="submit" value="Add"/>
				</td>
			</tr>

		</table>

	</form>
</div>
