<div class="header">
	<h2>Browse > <strong>Groups</strong></h2>
	<div class="breadcrumb-wrapper">
		<ol class="breadcrumb">
			<li><a href="{{url("{$site->home_link}")}}">Home</a></li>
			/ Browse / Groups
		</ol>
	</div>
</div>
{$site->adbrowse}
{if $results|@count > 0}
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="col-lg-12 portlets">
					<div class="card card-default">
						<div class="card-body pagination2">
                            <div class="col-md-8">
                                {$results->onEachSide(5)->links()}
                            </div>
							<table class="data table table-striped responsive-utilities jambo-table Sortable"
								   style="table-layout: auto;" data-sort-order="desc">
								<thead class="thead-light">
								<tr>
									<th data-field="name" data-sortable="true">Name</th>
									<th>Description</th>
									<th data-field="updated" data-sortable="true">Updated</th>
								</tr>
								</thead>
								<tbody>
								{foreach $results as $result}
									<tr>
										<td>
											<a title="Browse releases from {$result.name|replace:"alt.binaries":"a.b"}"
											   href="{{url("/browse/group?g={$result.name}")}}">{$result.name|replace:"alt.binaries":"a.b"}</a>
										</td>
										<td>{$result.description}</td>
										<td>{$result.last_updated|timeago} ago</td>
									</tr>
								{/foreach}
								</tbody>
							</table>
                            <div class="col-md-8">
                                {$results->onEachSide(5)->links()}
                            </div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
{/if}
