<div class="card card-body">
	<h1>{$title}</h1>
    <form name="moviesearch" method="get" action="{{URL("/admin/movie-list")}}" id="custom-search-form"
          class="form-inline form-horizontal col-4 col-lg-4 float-right">
        {{csrf_field()}}
        <div id="search" class="input-group col-12 col-lg-12">
            <input type="text" class="form-inline" placeholder="Search movie list" id="moviesearch" name="moviesearch"
                   value="{$lastSearch|escape:'html'}">
            <span class="input-group-btn">
			<button type="submit" value="Go" class="btn btn-success">
				<i class="fab fa-searchengin"></i>
			</button>
		</span>
        </div>
    </form>

	{if $movielist}
		{$movielist->onEachSide(5)->links()}
		<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table Sortable">
			<tr>
				<th>IMDB ID</th>
				<th>TMDb ID</th>
				<th>Title</th>
				<th>Cover</th>
				<th>Backdrop</th>
				<th>Created</th>
				<th></th>
			</tr>
			{foreach $movielist as $movie}
				<tr class="{cycle values=",alt"}">
					<td class="less"><a href="http://www.imdb.com/title/tt{$movie->imdbid}"
										title="View in IMDB">{$movie->imdbid}</a></td>
					<td class="less"><a href="http://www.themoviedb.org/movie/{$movie->tmdbid}"
										title="View in TMDb">{$movie->tmdbid}</a></td>
					<td><a title="Edit"
						   href="{{url("/admin/movie-edit?id={$movie->imdbid}")}}">{$movie->title}
							({$movie->year})</a></td>
					<td class="less">{$movie->cover}</td>
					<td class="less">{$movie->backdrop}</td>
					<td class="less">{$movie->created_at|date_format}</td>
					<td class="less"><a title="Update"
										href="{{url("/admin/movie-add?id={$movie->imdbid}&amp;update=1")}}">Update</a>
					</td>
				</tr>
			{/foreach}
		</table>
	{else}
		<p>No Movies available.</p>
	{/if}
</div>
