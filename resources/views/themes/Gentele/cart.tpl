<div class="well well-sm">
	<div class="header">
		<h2><strong>My Download Basket</strong></h2>
		<div class="breadcrumb-wrapper">
			<ol class="breadcrumb">
				<li><a href="{{url("{$site->home_link}")}}">Home</a></li>
				/ Download Basket
			</ol>
		</div>
	</div>
	<div class="alert alert-info" role="alert">
		<strong>RSS Feed</strong> <br/>
		Your download basket can also be accessed via an <a
				href="{{url("/rss/cart?dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}&amp;del=1")}}">RSS
			feed</a>. Some NZB downloaders can read this feed and automatically start downloading.
	</div>
	{if $results|@count > 0}
		{{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
			{{csrf_field()}}
			<div class="nzb_multi_operations">
				<small>With Selected:</small>
				<div class="btn-group">
					<input type="button" class="nzb_multi_operations_cartdelete btn btn-sm btn-danger" value="Delete"/>
					<input type="button" class="nzb_multi_operations_download_cart btn btn-sm btn-success"
						   value="Download"/>
				</div>
			</div>
		{{Form::close()}}
		<div class="row">
			<div class="col-lg-12 portlets">
				<div class="card card-default">
					<div class="card-body pagination2 table-responsive">
						<table class="data table table-striped responsive-utilities jambo-table bulk-action">
							<thead class="thead-light">
							<tr class="headings">
								<th><input id="check-all" type="checkbox" class="flat-all"/> Select All</th>
								<th class="column-title" style="display: table-cell;">Name</th>
								<th class="column-title" style="display: table-cell;">Added</th>
								<th class="column-title" style="display: table-cell;">Action</th>
							</tr>
							</thead>
							<tbody>
							{foreach $results as $result}
								<tr class="{cycle values=",alt"}">
									<td class="a-center ">
										<input id="chk{$result->release->guid|substr:0:7}" type="checkbox" class="flat"
											   value="{$result->release->guid}"/>
									</td>
									<td>
										<a title="View details"
										   href="{{url("/details/{$result->release->guid}")}}">{$result->release->searchname|escape:"htmlall"|wordwrap:75:"\n":true}</a>
									</td>
									<td class="less"
										title="Added on {$result.created_at}">{$result.created_at}</td>
									<td><a title="Delete from your cart" href="/cart/delete/{$result->release->guid}"
										   class="btn btn-danger btn-sm" style="padding-bottom:2px;">Delete</a></td>
								</tr>
							{/foreach}
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	{else}
		<div class="alert alert-danger" role="alert">There are no NZBs in your download basket.</div>
	{/if}
</div>
