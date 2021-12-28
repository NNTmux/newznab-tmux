<div class="well well-sm">
	<div class="header">
		<h2>My > <strong>TV Shows</strong></h2>
		<div class="breadcrumb-wrapper">
			<ol class="breadcrumb">
				<li><a href="{{url("{$site->home_link}")}}">Home</a></li>
				/ My TV Shows
			</ol>
		</div>
	</div>
	<div class="btn-group">
		<a class="btn btn-sm btn-success" title="View available TV series" href="{{route('series')}}">View
			all series</a>
		<a class="btn btn-sm btn-success" title="View a list of all releases in your shows"
		   href="{{url("/myshows/browse")}}">View releases for My Shows</a>
		<a class="btn btn-sm btn-success" title="All releases in your shows as an RSS feed"
		   href="{{url("/rss/myshows?dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}">RSS Feed for
			My Shows <i class="fa fa-rss"></i></a>
	</div>
	<hr>
	{if $shows|@count > 0}
		<div class="box-body">
			<div class="row">
				<div class="col-lg-12 portlets">
					<div class="card card-default">
						<div class="card-body pagination2">
							<table class="data table table-striped responsive-utilities jambo-table">
								<tr>
									<th>Name</th>
									<th width="80">Category</th>
									<th width="110">Added</th>
									<th width="130" class="mid">Options</th>
								</tr>
								{foreach $shows as $show}
									<tr>
										<td>
											<a title="View details"
											   href="{{url("/series/{$show.videos_id}")}}">{$show.title|escape:"htmlall"|wordwrap:75:"\n":true}</a>
										</td>
										<td>
											<span class="badge bg-info">{if $show.categoryNames != ''}{$show.categoryNames|escape:"htmlall"}{else}All{/if}</span>
										</td>
										<td title="Added on {$show.created_at}">{$show.created_at|date_format}</td>
										<td>
											<div class="btn-group">
												<a class="btn btn-xs btn-warning myshows"
												   href="{{url("/myshows?action=edit&id={$show.videos_id}")}}"
												   rel="edit" name="series{$show.videos_id}"
												   title="Edit Categories">Edit</a>
												<a class="btn btn-xs btn-danger confirm_action myshows"
												   href="{{url("/myshows?action=delete&id={$show.videos_id}")}}"
												   rel="remove" name="series{$show.videos_id}"
												   title="Remove from My Shows">Remove</a>
											</div>
										</td>
									</tr>
								{/foreach}
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	{else}
		<div class="alert alert-danger">
			<strong>Sorry!</strong> No shows bookmarked yet!
		</div>
	{/if}
</div>
