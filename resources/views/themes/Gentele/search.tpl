<div class="card mb-4">
                        <div class="card-header">
                            <h2>{{config('app.name')}} > <strong>Search</strong></h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
                                    <li class="breadcrumb-item active">Search</li>
                                </ol>
                            </nav>
                        </div>

                        <div class="card-body">
                           <!-- Search Type Toggle -->
                            <div class="text-center mb-3">
                                <a href="#" class="btn btn-outline-secondary btn-sm" onclick="
                                    if (jQuery(this).text().trim() === 'Advanced Search') {
                                        jQuery(this).text('Basic Search');
                                    } else {
                                        jQuery(this).text('Advanced Search');
                                    }
                                    jQuery('#sbasic,#sadvanced').toggle();
                                    return false;">
                                    {if $sadvanced}Basic{else}Advanced{/if} Search
                                </a>
                            </div>

                            <!-- Basic Search Form -->
                            {{Form::open(['url' => 'search', 'method' => 'get', 'class' => 'mb-4'])}}
                            <div id="sbasic" class="row justify-content-center" {if $sadvanced}style="display:none;"{/if}>
                                <div class="col-md-8 col-lg-6">
                                    <div class="input-group">
                                        <input id="search" class="form-control" maxlength="500" name="search"
                                               value="{$search|escape:'htmlall'}" type="search"
                                               placeholder="What are you looking for?"/>
                                        <input type="hidden" name="t" value="{if $category[0]!=""}{$category[0]}{else}-1{/if}" id="search_cat"/>
                                        <input type="hidden" name="search_type" value="basic" id="search_type"/>
                                        <button type="submit" class="btn btn-success" id="search_search_button">Search</button>
                                    </div>
                                </div>
                            </div>
                            {{Form::close()}}

                            <!-- Advanced Search Form -->
                            {{Form::open(['url' => 'search', 'method' => 'get'])}}
                            <div id="sadvanced" class="card" {if not $sadvanced}style="display:none"{/if}>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="searchadvr" class="form-label">Release Name:</label>
                                                <input class="form-control" id="searchadvr" name="searchadvr"
                                                       value="{$searchadvr|escape:'htmlall'}" type="text">
                                            </div>

                                            <div class="mb-3">
                                                <label for="searchadvsubject" class="form-label">Usenet Name:</label>
                                                <input class="form-control" id="searchadvsubject" name="searchadvsubject"
                                                       value="{$searchadvsubject|escape:'htmlall'}" type="text">
                                            </div>

                                            <div class="mb-3">
                                                <label for="searchadvposter" class="form-label">Poster:</label>
                                                <input class="form-control" id="searchadvposter" name="searchadvposter"
                                                       value="{$searchadvposter|escape:'htmlall'}" type="text">
                                            </div>

                                            <div class="mb-3">
                                                <label for="searchadvfilename" class="form-label">Filename:</label>
                                                <input class="form-control" id="searchadvfilename" name="searchadvfilename"
                                                       value="{$searchadvfilename|escape:'htmlall'}" type="text"/>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="searchadvdaysnew" class="form-label">Min age (days):</label>
                                                <input class="form-control" id="searchadvdaysnew" name="searchadvdaysnew"
                                                       value="{$searchadvdaysnew|escape:'htmlall'}" type="text">
                                            </div>

                                            <div class="mb-3">
                                                <label for="searchadvdaysold" class="form-label">Max age (days):</label>
                                                <input class="form-control" id="searchadvdaysold" name="searchadvdaysold"
                                                       value="{$searchadvdaysold|escape:'htmlall'}" type="text">
                                            </div>

                                            <div class="mb-3">
                                                <label for="searchadvgroups" class="form-label">Group:</label>
                                                {html_options class="form-select" id="searchadvgroups" name="searchadvgroups" options=$grouplist selected=$selectedgroup}
                                            </div>

                                            <div class="mb-3">
                                                <label for="searchadvcat" class="form-label">Category:</label>
                                                {html_options class="form-select" id="searchadvcat" name="searchadvcat" options=$catlist selected=$selectedcat}
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Min/Max Size:</label>
                                                <div class="d-flex gap-2">
                                                    {html_options class="form-select" id="searchadvsizefrom" name="searchadvsizefrom" options=$sizelist selected=$selectedsizefrom}
                                                    {html_options class="form-select" id="searchadvsizeto" name="searchadvsizeto" options=$sizelist selected=$selectedsizeto}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-end mt-3">
                                        <input type="hidden" name="search_type" value="adv" id="search_type">
                                        {{Form::submit('Search', ['class' => 'btn btn-success', 'id' => 'search_adv_button'])}}
                                    </div>
                                </div>
                            </div>
                            {{Form::close()}}

                            <!-- No Results Message -->
                            {if $results|@count == 0 && ($search || $subject|| $searchadvr|| $searchadvsubject || $selectedgroup || $selectedsizefrom || $searchadvdaysold) != ""}
                                <div class="alert alert-info text-center mt-4">
                                    <h5 class="alert-heading">Your search did not match any releases.</h5>
                                    <hr>
                                    <p class="mb-0">Suggestions:</p>
                                    <ul class="list-unstyled mt-2">
                                        <li>Make sure all words are spelled correctly.</li>
                                        <li>Try different keywords.</li>
                                        <li>Try more general keywords.</li>
                                        <li>Try fewer keywords.</li>
                                    </ul>
                                </div>
                            {elseif ($search || $subject || $searchadvr || $searchadvsubject || $selectedgroup || $selectedsizefrom || $searchadvdaysold) == ""}
                                <!-- Empty Initial Search State -->
                            {else}
                                <!-- Search Results -->
                                <div class="card mt-4">
                                    {{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get', 'url' => 'search'])}}
                                    <div class="card-header">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                {if isset($shows)}
                                                    <div class="mb-2">
                                                        <a href="{{route('series')}}" title="View available TV series">Series List</a> |
                                                        <a title="Manage your shows" href="{{route("myshows")}}">Manage My Shows</a> |
                                                        <a title="All releases in your shows as an RSS feed" href="{{url("/rss/myshows?dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}">Rss Feed</a>
                                                    </div>
                                                {/if}

                                                <div class="nzb_multi_operations">
                                                    {if isset($section) && $section != ''}
                                                        <div class="mb-2">
                                                            View: <a href="{{url("/{$section}?t={$category}")}}">Covers</a> | <b>List</b>
                                                        </div>
                                                    {/if}

                                                    <div class="d-flex align-items-center gap-2">
                                                        <span>With Selected:</span>
                                                        <div class="btn-group">
                                                            <button type="button" class="nzb_multi_operations_download btn btn-sm btn-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Download NZBs">
                                                                <i class="fa fa-cloud-download"></i>
                                                            </button>
                                                            <button type="button" class="nzb_multi_operations_cart btn btn-sm btn-info" data-bs-toggle="tooltip" data-bs-placement="top" title="Send to my Download Basket">
                                                                <i class="fa fa-shopping-basket"></i>
                                                            </button>
                                                            {if isset($isadmin)}
                                                                <button type="button" class="nzb_multi_operations_edit btn btn-sm btn-warning">Edit</button>
                                                                <button type="button" class="nzb_multi_operations_delete btn btn-sm btn-danger">Delete</button>
                                                            {/if}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {if count($results) > 0}
                                                <div class="col-md-6 text-end">
                                                    {$results->onEachSide(5)->links()}
                                                </div>
                                            {/if}
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover" id="browsetable">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th width="30"><input id="check-all" type="checkbox" class="flat-all"/></th>
                                                    <th>
                                                        Name
                                                        <div class="d-inline-block">
                                                            <a title="Sort Descending" href="{$orderbyname_desc}"><i class="fas fa-chevron-down"></i></a>
                                                            <a title="Sort Ascending" href="{$orderbyname_asc}"><i class="fas fa-chevron-up"></i></a>
                                                        </div>
                                                    </th>
                                                    <th>
                                                        Category
                                                        <div class="d-inline-block">
                                                            <a title="Sort Descending" href="{$orderbycat_desc}"><i class="fas fa-chevron-down"></i></a>
                                                            <a title="Sort Ascending" href="{$orderbycat_asc}"><i class="fas fa-chevron-up"></i></a>
                                                        </div>
                                                    </th>
                                                    <th>
                                                        Posted
                                                        <div class="d-inline-block">
                                                            <a title="Sort Descending" href="{$orderbyposted_desc}"><i class="fas fa-chevron-down"></i></a>
                                                            <a title="Sort Ascending" href="{$orderbyposted_asc}"><i class="fas fa-chevron-up"></i></a>
                                                        </div>
                                                    </th>
                                                    <th class="text-center">
                                                        Size
                                                        <div class="d-inline-block">
                                                            <a title="Sort Descending" href="{$orderbysize_desc}"><i class="fas fa-chevron-down"></i></a>
                                                            <a title="Sort Ascending" href="{$orderbysize_asc}"><i class="fas fa-chevron-up"></i></a>
                                                        </div>
                                                    </th>
                                                    <th class="text-center">
                                                        Files
                                                        <div class="d-inline-block">
                                                            <a title="Sort Descending" href="{$orderbyfiles_desc}"><i class="fas fa-chevron-down"></i></a>
                                                            <a title="Sort Ascending" href="{$orderbyfiles_asc}"><i class="fas fa-chevron-up"></i></a>
                                                        </div>
                                                    </th>
                                                    <th class="text-center">
                                                        Downloads
                                                        <div class="d-inline-block">
                                                            <a title="Sort Descending" href="{$orderbystats_desc}"><i class="fas fa-chevron-down"></i></a>
                                                            <a title="Sort Ascending" href="{$orderbystats_asc}"><i class="fas fa-chevron-up"></i></a>
                                                        </div>
                                                    </th>
                                                    <th class="text-end">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            {foreach $results as $result}
                                                <tr class="{cycle values=",alt"}{if $lastvisit|strtotime<$result->adddate|strtotime} new{/if}" id="guid{$result->guid}">
                                                    <td>
                                                        <input id="chk{$result->guid|substr:0:7}" type="checkbox" class="flat" value="{$result->guid}">
                                                    </td>
                                                    <td class="item">
                                                        <label for="chk{$result->guid|substr:0:7}">
                                                            <a class="title" title="View details" href="{{url("/details/{$result->guid}")}}">
                                                                {$result->searchname|escape:"htmlall"|truncate:150:"...":true}
                                                            </a>
                                                            {if !empty($result->failed)}
                                                                <i class="fa fa-exclamation-circle text-danger" title="This release has failed to download for some users"></i>
                                                            {/if}
                                                        </label>

                                                        <div class="mt-1">
                                                            <div class="float-end">
                                                                {release_flag($result->searchname, browse)}
                                                                {if $result->passwordstatus == 1}
                                                                    <img title="RAR/ZIP is Passworded." src="{{asset("/assets/images/icons/lock.gif")}}" alt="RAR/ZIP is Passworded.">
                                                                {/if}
                                                                {if $result->videostatus > 0}
                                                                    <a class="model_prev badge bg-info" href="{{url("/details/{$result->guid}")}}" title="This release has a video preview." rel="preview">
                                                                        <i class="icon-youtube-play"></i>
                                                                    </a>
                                                                {/if}
                                                                {if $result->nfoid > 0}
                                                                    <a href="{{url("/nfo/{$result->guid}")}}" title="View Nfo" class="modal_nfo badge bg-info" rel="nfo">Nfo</a>
                                                                {/if}
                                                                {if $result->imdbid > 0}
                                                                    <a href="#" name="name{$result->imdbid}" title="View movie info" class="modal_imdb badge bg-info" rel="movie">Cover</a>
                                                                {/if}
                                                                {if $result->haspreview == 1 && $userdata->can('preview') == true}
                                                                    <a href="{{url("/covers/preview/{$result->guid}_thumb.jpg")}}" name="name{$result->guid}" data-fancybox title="Screenshot of {$result->searchname|escape:"htmlall"}" class="badge bg-info" rel="preview">Preview</a>
                                                                {/if}
                                                                {if $result->jpgstatus == 1 && $userdata->can('preview') == true}
                                                                    <a href="{{url("/covers/sample/{$result->guid}_thumb.jpg")}}" name="name{$result->guid}" data-fancybox title="Sample of {$result->searchname|escape:"htmlall"}" class="badge bg-info" rel="preview">Sample</a>
                                                                {/if}
                                                                {if $result->musicinfo_id > 0}
                                                                    <a href="#" name="name{$result->musicinfo_id}" title="View music info" class="modal_music badge bg-info" rel="music">Cover</a>
                                                                {/if}
                                                                {if $result->consoleinfo_id > 0}
                                                                    <a href="#" name="name{$result->consoleinfo_id}" title="View console info" class="modal_console badge bg-info" rel="console">Cover</a>
                                                                {/if}
                                                                {if $result->videos_id > 0}
                                                                    <a class="badge bg-info" href="{{url("/series/{$result->videos_id}")}}" title="View all episodes">View Series</a>
                                                                {/if}
                                                                {if $result->anidbid > 0}
                                                                    <a class="badge bg-info" href="{{url("/anime?id={$result->anidbid}")}}" title="View all episodes">View Anime</a>
                                                                {/if}
                                                                {if isset($result->firstaired) && $result->firstaired != ''}
                                                                    <span class="seriesinfo badge bg-info" title="{$result->guid}">
                                                                        Aired {if $result->firstaired|strtotime > $smarty.now}in future{else}{$result->firstaired|daysago}{/if}
                                                                    </span>
                                                                {/if}
                                                                {if $result->group_name != ""}
                                                                    <a class="badge bg-info" href="{{url("/browse/group?g={$result->group_name|escape:"htmlall"}")}}" title="Browse {$result->group_name}">
                                                                        {$result->group_name|escape:"htmlall"|replace:"alt.binaries.":"a.b."}
                                                                    </a>
                                                                {/if}
                                                                {if !empty($result->failed)}
                                                                    <span class="badge bg-info">
                                                                        <i class="fa fa-thumbs-o-up"></i> {$result->grabs} Grab{if $result->grabs != 1}s{/if} /
                                                                        <i class="fa fa-thumbs-o-down"></i> {$result->failed} Failed Download{if $result->failed != 1}s{/if}
                                                                    </span>
                                                                {/if}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="category">
                                                        <a title="Browse {$result->category_name}" href="{{url("/browse/{$result->parent_category}/{$result->sub_category}")}}">
                                                            {$result->category_name}
                                                        </a>
                                                    </td>
                                                    <td class="posted" title="{$result->postdate}">
                                                        {$result->postdate|timeago}
                                                    </td>
                                                    <td class="size text-center">
                                                        {$result->size|filesize}
                                                        {if $result->completion > 0}
                                                            <br>
                                                            {if $result->completion < 100}
                                                                <span class="text-warning">{$result->completion}%</span>
                                                            {else}
                                                                {$result->completion}%
                                                            {/if}
                                                        {/if}
                                                    </td>
                                                    <td class="files text-center">
                                                        <a title="View file list" href="{{url("/filelist/{$result->guid}")}}">
                                                            {$result->totalpart}
                                                        </a>
                                                        {if $result->rarinnerfilecount > 0}
                                                            <div class="rarfilelist">
                                                                <img src="{{asset("/assets/images/icons/magnifier.png")}}" alt="{$result->guid}">
                                                            </div>
                                                        {/if}
                                                    </td>
                                                    <td class="stats text-center">
                                                        <a title="View comments" href="{{url("/details/{$result->guid}/#comments")}}">
                                                            {$result->comments} cmt{if $result->comments != 1}s{/if}
                                                        </a><br>
                                                        {$result->grabs} grab{if $result->grabs != 1}s{/if}
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="d-flex justify-content-end gap-2">
                                                            <a href="{{url("/getnzb?id={$result->guid}")}}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Download NZB">
                                                                <i class="fa fa-cloud-download"></i>
                                                            </a>
                                                            <a href="{{url("/details/{$result->guid}/#comments")}}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Comments">
                                                                <i class="fa fa-comments-o"></i>
                                                            </a>
                                                            <a href="#" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Send to my download basket">
                                                                <i id="guid{$result->guid}" class="icon_cart fa fa-shopping-basket"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            {/foreach}
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="card-footer">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <div class="nzb_multi_operations">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <small>With Selected:</small>
                                                        <div class="btn-group">
                                                            <button type="button" class="nzb_multi_operations_download btn btn-sm btn-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Download NZBs">
                                                                <i class="fa fa-cloud-download"></i>
                                                            </button>
                                                            <button type="button" class="nzb_multi_operations_cart btn btn-sm btn-info" data-bs-toggle="tooltip" data-bs-placement="top" title="Send to my Download Basket">
                                                                <i class="fa fa-shopping-basket"></i>
                                                            </button>
                                                            {if isset($isadmin)}
                                                                <button type="button" class="nzb_multi_operations_edit btn btn-sm btn-warning">Edit</button>
                                                                <button type="button" class="nzb_multi_operations_delete btn btn-sm btn-danger">Delete</button>
                                                            {/if}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {if count($results) > 0}
                                                <div class="col-md-6 text-end">
                                                    {$results->onEachSide(5)->links()}
                                                </div>
                                            {/if}
                                        </div>
                                    </div>
                                    {{Form::close()}}
                                </div>
                            {/if}
                        </div>
                    </div>
