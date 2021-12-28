<div class="well well-sm">
	<div class="header">
		<h2> Series > <strong>List</strong></h2>
		<div class="breadcrumb-wrapper">
			<ol class="breadcrumb">
				<li><a href="{{url("{$site->home_link}")}}">Home</a></li>
				/ TV Series List
			</ol>
		</div>
	</div>
	<p>
		<b>Jump to</b>:
		&nbsp;[ {if $seriesletter == '0-9'}<b><u>{/if}<a
						href="{{url("/series/0-9")}}">0-9</a>{if $seriesletter == '0-9'}</u></b>{/if}
		{foreach $seriesrange as $range}
		{if $range == $seriesletter}<b><u>{/if}<a
		href="{{url("/series/{$range}")}}">{$range}</a>{if $range == $seriesletter}</u></b>{/if}
		{/foreach}]
	</p>
	<div class="btn-group">
		<a class="btn btn-success" href="{{route('myshows')}}" title="List my watched shows">My shows</a>
		<a class="btn btn-success" href="{{url("/myshows/browse")}}" title="browse your shows">Find all my
			shows</a>
	</div>
	{$site->adbrowse}
	{if $serieslist|@count > 0}
		<table class="data table table-striped responsive-utilities jambo-table icons" id="browsetable">
			<div class="col-md-12 float-right">
			    {{Form::open(['class' => 'form float-right', 'style' => 'margin-top:-35px;'])}}
                    {{Form::open(['name' => 'showsearch', 'class' => 'navbar-form', 'method' => 'get'])}}
						<div class="input-group">
							<input class="form-inline" style="width: 150px;"
								   id="title appendedInputButton"
								   type="text" name="title" {if isset($serieslist.title)} value="{$serieslist.title}"{else}{/if}"
								   placeholder="Search here"/>
                            {{Form::button('Go', ['class' => 'btn btn-success', 'type' => 'submit'])}}
						</div>
					{{Form::close()}}
				{{Form::close()}}
			</div>
			{foreach $serieslist as $sletter => $series}
				<tr>
					<td colspan="10">
						<div class="row">
							<div class="col-md-3">
								<h2>{$sletter}</h2>
							</div>
						</div>
					</td>
				</tr>
				<tr>
					<th>
						<div class="text-left">Name</div>
					</th>
					<th style="width:80px">
						<div class="text-center">Network</div>
					</th>
					<th style="width:80px">
						<div class="text-center">Country</div>
					</th>
					<th style="width:120px">
						<div class="text-center">Option</div>
					</th>
					<th style="width:180px">
						<div class="text-center">View</div>
					</th>
				</tr>
				{foreach $series as $s}
					<tr>
						<td><a class="title" title="View series"
							   href="{{url("/series/{$s.id}")}}">{if !empty($s.title)}{$s.title|escape:"htmlall"}{/if}</a>{if $s.prevdate != ''}
						<br/><span class="badge bg-info">Last: {$s.previnfo|escape:"htmlall"}
							aired {$s.prevdate|date_format}</span>{/if}</td>
						<td>{$s.publisher|escape:"htmlall"}</td>
						<td>{$s.countries_id|escape:"htmlall"}</td>
						<td class="mid">
							{if $s.userseriesid != null}
								<a href="{{url("/myshows?action=edit&id={$s.id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
								   class="myshows btn btn-sm btn-warning" rel="edit" name="series{$s.id}"
								   title="Edit">Edit</a>
								<br/>
								<a href="{{url("/myshows?action=delete&id={$s.id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
								   class="myshows btn btn-sm btn-danger" rel="remove" name="series{$s.id}"
								   title="Remove from My Shows">Remove</a>
							{else}
								<a href="{{url("/myshows?action=add&id={$s.id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
								   class="myshows btn btn-sm btn-success" rel="add" name="series{$s.id}"
								   title="Add to My Shows">Add</a>
							{/if}
						</td>
						<td class="mid">
							<a title="View series" href="{{url("/series/{$s.id}")}}">Series</a><br/>
							{if $s.id > 0}
								{if $s.tvdb > 0}
									<a title="View at TVDB" target="_blank"
									   href="{$site->dereferrer_link}http://thetvdb.com/?tab=series&id={$s.tvdb}">TVDB</a>
								{/if}
								{if $s.tvmaze > 0}
									<a title="View at TVMaze" target="_blank"
									   href="{$site->dereferrer_link}http://tvmaze.com/shows/{$s.tvmaze}">TVMaze</a>
								{/if}
								{if $s.trakt > 0}
									<a title="View at Trakt" target="_blank"
									   href="{$site->dereferrer_link}http://www.trakt.tv/shows/{$s.trakt}">Trakt</a>
								{/if}
								{if $s.tvrage > 0}
									<a title="View at TVRage" target="_blank"
									   href="{$site->dereferrer_link}http://www.tvrage.com/shows/id-{$s.tvrage}">TVRage</a>
								{/if}
								{if $s.tmdb > 0}
									<a title="View at TheMovieDB" target="_blank"
									   href="{$site->dereferrer_link}https://www.themoviedb.org/tv/{$s.tmdb}">TMDB</a>
								{/if}
								<a title="RSS Feed for {$s.title|escape:"htmlall"}"
								   href="{{url("/rss/full-feed?show={$s.id}&amp;dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}"><i
											class="fa fa-rss"></i></a>
							{/if}
						</td>
					</tr>
				{/foreach}
			{/foreach}
		</table>
	{else}
		<div class="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Hmm!</strong> No result on that search term.
		</div>
	{/if}
</div>
