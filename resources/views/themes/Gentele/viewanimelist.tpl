<div class="well well-sm">
	<div class="header">
		<h2>Anime > <strong>List</strong></h2>
		<div class="breadcrumb-wrapper">
			<ol class="breadcrumb">
				<li><a href="{{url("{$site->home_link}")}}">Home</a></li>
				/ Anime List
			</ol>
		</div>
	</div>
	{$site->adbrowse}
	<p><b>Jump to</b>:
		&nbsp;&nbsp;[ {if $animeletter == '0-9'}<b><u>{/if}<a
						href="{{url("/animelist")}}">0-9</a>{if $animeletter == '0-9'}</u></b>{/if}
		{foreach $animerange as $range}
		{if $range == $animeletter}<b><u>{/if}<a
		href="{{url("/animelist?id={$range}")}}">{$range}</a>{if $range == $animeletter}</u></b>{/if}
		{/foreach}]
	</p>
	{$site->adbrowse}
	{if $animelist|@count > 0}
		<table style="width:100%;" class="data table table-striped responsive-utilities jambo-table" id="browsetable">
			{foreach $animelist as $aletter => $anime}
				<tr>
					<td colspan="10">
						<h2>{$aletter}...</h2>
                        {{Form::open(['class' => 'form float-right', 'method' => 'get', 'name' => 'anidbsearch', 'style' =>'margin-top:-35px;'])}}
							{{Form::label('title', 'Search:')}}
                            {{Form::text('title', $animetitle, ['class' => 'form-inline', 'style' => 'width: 150px;', 'id'=> 'title appendedInputButton',
                            'placeholder' => 'Search here'])}}
                            {{Form::button('Search', ['class' => 'btn btn-success', 'type' => 'submit'])}}
						{{Form::close()}}
					</td>
				</tr>
				<tr>
					<th width="35%">Name</th>
					<th>Type</th>
					<th width="35%">Categories</th>
					<th>Rating</th>
					<th>View</th>
				</tr>
				{foreach $anime as $a}
					<tr>
						<td><a class="title" title="View anime"
							   href="{{url("/anime?id={$a->anidbid}")}}">{$a->title|escape:"htmlall"}</a>{if {$a->startdate} != ''}
							<br/><span class="badge bg-info">({$a->startdate|date_format}
								- {/if}{if $a->enddate != ''}{$a->enddate|date_format}){/if}</span></td>
						<td>{if {$a->type} != ''}{$a->type|escape:"htmlall"}{/if}</td>
						<td>{if {$a->categories} != ''}{$a->categories|escape:"htmlall"|replace:'|':', '}{/if}</td>
						<td>{if {$a->rating} != ''}{$a->rating}{/if}</td>
						<td><a title="View at AniDB" target="_blank" class="badge bg-info"
							   href="{$site->dereferrer_link}http://anidb.net/perl-bin/animedb.pl?show=anime&aid={$a->anidbid}">AniDB</a>
						</td>
					</tr>
				{/foreach}
			{/foreach}
		</table>
	{else}
		<div class="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Hmm!</strong> No results for this query.
		</div>
	{/if}
</div>
