{if isset($nodata) && $nodata != ""}
<div class="header">
    <div class="breadcrumb-wrapper">
        <ol class="breadcrumb">
            <li><a href="{{url("{$site->home_link}")}}">Home</a></li>
            / TV Series
        </ol>
    </div>
</div>
<div class="alert">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <strong>Sorry!</strong>
    {$nodata}
</div>
{else}
<div class="header">
    <div class="breadcrumb-wrapper">
        <ol class="breadcrumb">
            <li><a href="{{url("{$site->home_link}")}}">Home</a></li>
            / TV Series
        </ol>
    </div>
</div>
<div class="card card-body card-header">
    <div class="tvseriesheading">
        <h1>
            <div style="text-align: center;">{$seriestitles} ({$show.publisher})</div>
        </h1>
        {if $show.image != 0}
            <div style="text-align: center;">
                <img class="shadow img img-polaroid" style="max-height:300px;" alt="{$seriestitles} Logo"
                     src="{{url("/covers/tvshows/{$show.id}.jpg")}}"/>
            </div>
            <br/>
        {/if}
        <p>
            <span class="descinitial">{$seriessummary|escape:"htmlall"|nl2br|magicurl}</span>
        </p>
    </div>
</div>
<div class="btn-group">
    <a class="btn btn-sm btn-success"
       href="{{url("/rss/full-feed?show={$show.id}{if $category != ''}&amp;t={$category}{/if}&amp;dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}">RSS
        for TV Show <i class="fa fa-rss"></i></a>
    {if $show.tvdb > 0}
        <a class="btn btn-sm btn-info" target="_blank"
           href="{$site->dereferrer_link}http://thetvdb.com/?tab=series&id={$show.tvdb}"
           title="View at TheTVDB">TheTVDB</a>
    {/if}
    {if $show.tvmaze > 0}
        <a class="btn btn-sm btn-info" target="_blank"
           href="{$site->dereferrer_link}http://tvmaze.com/shows/{$show.tvmaze}"
           title="View at TVMaze">TVMaze</a>
    {/if}
    {if $show.trakt > 0}
        <a class="btn btn-sm btn-info" target="_blank"
           href="{$site->dereferrer_link}http://www.trakt.tv/shows/{$show.trakt}"
           title="View at TraktTv">Trakt</a>
    {/if}
    {if $show.tvrage > 0}
        <a class="btn btn-sm btn-info" target="_blank"
           href="{$site->dereferrer_link}http://www.tvrage.com/shows/id-{$show.tvrage}"
           title="View at TV Rage">TV Rage</a>
    {/if}
    {if $show.tmdb > 0}
        <a class="btn btn-sm btn-info" target="_blank"
           href="{$site->dereferrer_link}https://www.themoviedb.org/tv/{$show.tmdb}"
           title="View at TheMovieDB">TMDB</a>
    {/if}
</div>
<br/>
<div class="box-body"
<div class="card card-body">
    {{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
    <div class="nzb_multi_operations">
        With Selected:
        <div class="btn-group">
            <button type="button"
                    class="nzb_multi_operations_download btn btn-sm btn-success"
                    data-bs-toggle="tooltip" data-bs-placement="top" title data-original-title="Download NZBs">
                <i class="fa fa-cloud-download"></i></button>
            <button type="button"
                    class="nzb_multi_operations_cart btn btn-sm btn-info"
                    data-bs-toggle="tooltip" data-bs-placement="top" title
                    data-original-title="Send to my Download Basket">
                <i class="fa fa-shopping-basket"></i></button>
            {if isset($isadmin)}
                <input type="button"
                       class="nzb_multi_operations_edit btn btn-sm btn-warning"
                       value="Edit"/>
                <input type="button"
                       class="nzb_multi_operations_delete btn btn-sm btn-danger"
                       value="Delete"/>
            {/if}
        </div>
    </div>
    {{Form::close()}}
    <div>
        <a title="Manage your shows" href="{{route('myshows')}}">My Shows</a> :
        <div class="btn-group">
            {if $myshows.id != ''}
                <a class="myshows btn btn-sm btn-warning" data-bs-toggle="tooltip" data-bs-placement="top" title
                   data-original-title="Edit Categories for this show"
                   href="{{url("/myshows?action=edit&id={$show.id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
                   rel="edit" name="series{$show.id}">
                    <i class="fa fa-pencil"></i>
                </a>
                <a class="myshows btn btn-sm btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title
                   data-original-title="Remove from My Shows"
                   href="{{url("/myshows?action=delete&id={$show.id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
                   rel="remove" name="series{$show.id}">
                    <i class="fa fa-minus"></i>
                </a>
            {else}
                <a class="myshows btn btn-sm btn-success" data-bs-toggle="tooltip" data-bs-placement="top" title
                   data-original-title="Add to My Shows"
                   href="{{url("/myshows?action=add&id={$show.id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
                   rel="add" name="series{$show.id}">
                    <i class="fa fa-plus"></i>
                </a>
            {/if}
        </div>
    </div>
</div>
<br clear="all"/>
<a id="latest"></a>

<div class="row">
    <div class="col-lg-12 col-sm-12 col-12">
        <div class="card card-default">
            <div class="card-body card-header">
                <ul class="nav nav-tabs" id="{$seasonnum}" role="tablist">
                    {foreach $seasons as $seasonnum => $season}
                        <li class="nav-item">
                            <a class="nav-link" title="View Season {$seasonnum}" href="#season{$seasonnum}" id="season{$seasonnum}-tab" data-bs-toggle="tab" role="tab" aria-controls="{$seasonnum}" aria-selected="{if $season@first} true {else} false{/if}">{$seasonnum}</a>
                        </li>
                    {/foreach}
                </ul>
                <div class="tab-content" id="{$seasonnum}Content">
                    {foreach $seasons as $seasonnum => $season}
                        <div class="tab-pane{if $season@first} active{/if} fade in show" id="season{$seasonnum}" role="tabpanel" aria-labelledby="{$seasonnum}">
                            <table class="data table table-striped">
                                <tbody>
                                <thead class="thead-light">
                                <tr>
                                    <th>Ep</th>
                                    <th>Name</th>
                                    <th>Select All <input id="check-all" type="checkbox" class="flat-all"/></th>
                                    <th>Category</th>
                                    <th>Posted</th>
                                    <th>Size</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                {foreach $season as $episodes}
                                    {foreach $episodes as $result}
                                        <tr class="{cycle values=",alt"}"
                                            id="guid{$result->guid}">
                                            {if $result@total>1 && $result@index == 0}
                                                <td rowspan="{$result@total}" width="30">
                                                    <h4>{$episodes@key}</h4></td>
                                            {elseif $result@total == 1}
                                                <td><h4>{$episodes@key}</h4></td>
                                            {/if}
                                            <td>
                                                <a title="View details"
                                                   href="{{url("/details/{$result->guid}")}}">{$result->searchname|escape:"htmlall"|replace:".":" "}</a>

                                                <div>
                                                    {if $result->nfoid > 0}<span>
                                                        <a href="{{url("/nfo/{$result->guid}")}}"
                                                           class="modal_nfo badge bg-info text-muted">NFO</a>
                                                        </span>{/if}
                                                    {if $result->image == 1 && $userdata->can('preview') == true}
                                                    <a
                                                        href="{{url("/covers/preview/{$result->guid}_thumb.jpg")}}"
                                                        name="name{$result->guid}"
                                                        data-fancybox
                                                        title="View Screenshot"
                                                        class="badge bg-info"
                                                        rel="preview">Preview</a>{/if}
                                                    <span class="badge bg-info">{$result->grabs}
																		Grab{if $result->grabs != 1}s{/if}</span>
                                                    {if $result->firstaired != ""}<span
                                                        class="badge bg-success"
                                                        title="{$result->title} Aired on {$result->firstaired|date_format}">
                                                        Aired {if $result->firstaired|strtotime > $smarty.now}in future{else}{$result->firstaired|daysago}{/if}</span>{/if}
                                                    {if $result->reid > 0}<span
                                                        class="mediainfo badge bg-info"
                                                        title="{$result->guid}">Media</span>{/if}
                                                </div>
                                            </td>
                                            <td width="10"><input
                                                    id="guid{$result->guid}"
                                                    type="checkbox"
                                                    class="flat" name="table_data{$seasonnum}"
                                                    value="{$result->guid}"/></td>
                                            <td>
                                                <span class="badge bg-info">{$result->category_name}</span>
                                            </td>
                                            <td width="40"
                                                title="{{Timezone::convertToLocal(Illuminate\Support\Carbon::parse($result->postdate, config('app.timezone')), 'Y-m-d h:i:s')}}">{{{Timezone::convertToLocal(Illuminate\Support\Carbon::parse($result->postdate), 'Y-m-d h:i:s')}}|timeago}</td>
                                            <td>
                                                {$result->size|filesize}
                                            </td>
                                            <td>
                                                <a href="{{url("/getnzb?id={$result->guid}")}}"
                                                   class="icon_nzb text-muted"><i
                                                        class="fa fa-cloud-download text-muted"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title
                                                        data-original-title="Download NZB"></i></a>
                                                <a href="{{url("/details/{$result->guid}/#comments")}}"><i
                                                        class="fa fa-comments-o text-muted"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title
                                                        data-original-title="Comments"></i></a>
                                                <a href="#"><i
                                                        id="guid{$result->guid}"
                                                        class="icon_cart text-muted fa fa-shopping-basket"
                                                        data-bs-toggle="tooltip"
                                                        data-bs-placement="top" title
                                                        data-original-title="Send to my download basket"></i></a>
                                            </td>
                                        </tr>
                                    {/foreach}
                                {/foreach}
                                </tbody>
                            </table>
                        </div>
                    {/foreach}
                </div>
                {/if}
            </div>
        </div>
    </div>
</div>

