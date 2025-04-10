<div class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{route('series')}}">TV Series</a></li>
            <li class="breadcrumb-item active">{$seriestitles|escape:"htmlall"}</li>
        </ol>
    </nav>

    {if isset($nodata) && $nodata != ""}
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-circle me-2"></i><strong>Sorry!</strong> {$nodata}
        </div>
    {else}
        <!-- Series Info Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fa fa-tv me-2"></i>{$seriestitles} ({$show.publisher})</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Series Image -->
                    <div class="col-md-3 text-center mb-3 mb-md-0">
                        {if $show.image != 0}
                            <img class="img-fluid rounded shadow" style="max-height:300px;" alt="{$seriestitles} Poster"
                                 src="{{url("/covers/tvshows/{$show.id}.jpg")}}"/>
                        {/if}

                        <!-- My Shows Controls -->
                        <div class="mt-3">
                            <div class="d-flex justify-content-center align-items-center gap-2">
                                <span class="text-muted me-2">My Shows:</span>
                                <div class="btn-group">
                                    {if $myshows.id != ''}
                                        <a class="myshows btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Edit Categories"
                                           href="{{url("/myshows?action=edit&id={$show.id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
                                           rel="edit" name="series{$show.id}">
                                            <i class="fa fa-pencil-alt"></i>
                                        </a>
                                        <a class="myshows btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Remove"
                                           href="{{url("/myshows?action=delete&id={$show.id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
                                           rel="remove" name="series{$show.id}">
                                            <i class="fa fa-minus"></i>
                                        </a>
                                    {else}
                                        <a class="myshows btn btn-sm btn-success" data-bs-toggle="tooltip" title="Add to My Shows"
                                           href="{{url("/myshows?action=add&id={$show.id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
                                           rel="add" name="series{$show.id}">
                                            <i class="fa fa-plus"></i>
                                        </a>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Series Info -->
                    <div class="col-md-9">
                        <div class="mb-3">
                            <p class="mb-3">{$seriessummary|escape:"htmlall"|nl2br|magicurl}</p>

                            <!-- External Links -->
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <a class="btn btn-sm btn-success"
                                   href="{{url("/rss/full-feed?show={$show.id}{if $category != ''}&amp;t={$category}{/if}&amp;dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}">
                                    <i class="fa fa-rss me-1"></i> RSS Feed
                                </a>

                                {if $show.tvdb > 0}
                                    <a class="btn btn-sm btn-info" target="_blank"
                                       href="{$site->dereferrer_link}http://thetvdb.com/?tab=series&id={$show.tvdb}"
                                       title="View at TheTVDB">
                                        <i class="fa fa-database me-1"></i> TheTVDB
                                    </a>
                                {/if}

                                {if $show.tvmaze > 0}
                                    <a class="btn btn-sm btn-info" target="_blank"
                                       href="{$site->dereferrer_link}http://tvmaze.com/shows/{$show.tvmaze}"
                                       title="View at TVMaze">
                                        <i class="fa fa-tv me-1"></i> TVMaze
                                    </a>
                                {/if}

                                {if $show.trakt > 0}
                                    <a class="btn btn-sm btn-info" target="_blank"
                                       href="{$site->dereferrer_link}http://www.trakt.tv/shows/{$show.trakt}"
                                       title="View at TraktTv">
                                        <i class="fa fa-film me-1"></i> Trakt
                                    </a>
                                {/if}

                                {if $show.tvrage > 0}
                                    <a class="btn btn-sm btn-info" target="_blank"
                                       href="{$site->dereferrer_link}http://www.tvrage.com/shows/id-{$show.tvrage}"
                                       title="View at TV Rage">
                                        <i class="fa fa-external-link-alt me-1"></i> TV Rage
                                    </a>
                                {/if}

                                {if $show.tmdb > 0}
                                    <a class="btn btn-sm btn-info" target="_blank"
                                       href="{$site->dereferrer_link}https://www.themoviedb.org/tv/{$show.tmdb}"
                                       title="View at TheMovieDB">
                                        <i class="fa fa-film me-1"></i> TMDB
                                    </a>
                                {/if}
                            </div>
                        </div>

                        <!-- My Shows Link -->
                        <div class="d-flex align-items-center">
                            <a href="{{route('myshows')}}" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-list-alt me-1"></i> Manage My Shows
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seasons Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa fa-list-ol me-2"></i>Episodes</h5>

                <div class="nzb_multi_operations">
                    <div class="d-flex align-items-center gap-2">
                        <small>With Selected:</small>
                        <div class="btn-group">
                            <button type="button" class="nzb_multi_operations_download btn btn-sm btn-success" data-bs-toggle="tooltip" title="Download NZBs">
                                <i class="fa fa-cloud-download-alt"></i>
                            </button>
                            <button type="button" class="nzb_multi_operations_cart btn btn-sm btn-info" data-bs-toggle="tooltip" title="Send to Download Basket">
                                <i class="fa fa-shopping-basket"></i>
                            </button>
                            {if isset($isadmin)}
                                <button type="button" class="nzb_multi_operations_edit btn btn-sm btn-warning">
                                    <i class="fa fa-edit"></i>
                                </button>
                                <button type="button" class="nzb_multi_operations_delete btn btn-sm btn-danger">
                                    <i class="fa fa-trash-alt"></i>
                                </button>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <!-- Seasons Tabs -->
                <ul class="nav nav-tabs px-3 pt-3" id="seasonTabs" role="tablist">
                    {foreach $seasons as $seasonnum => $season}
                        <li class="nav-item">
                            <a class="nav-link {if $season@first}active{/if}"
                               id="season{$seasonnum}-tab"
                               data-bs-toggle="tab"
                               href="#season{$seasonnum}"
                               role="tab"
                               aria-controls="season{$seasonnum}"
                               aria-selected="{if $season@first}true{else}false{/if}">
                                <i class="fa fa-calendar-alt me-1"></i>Season {$seasonnum}
                            </a>
                        </li>
                    {/foreach}
                </ul>

                <!-- Tab Content -->
                <div class="tab-content p-0" id="seasonTabsContent">
                    {foreach $seasons as $seasonnum => $season}
                        <div class="tab-pane fade {if $season@first}show active{/if}"
                             id="season{$seasonnum}"
                             role="tabpanel"
                             aria-labelledby="season{$seasonnum}-tab">

                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 30px">
                                                <input id="check-all-season{$seasonnum}" type="checkbox" class="flat check-all" data-season="{$seasonnum}">
                                            </th>
                                            <th>Episode</th>
                                            <th>Category</th>
                                            <th>Posted</th>
                                            <th>Size</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach $season as $episode}
                                            {foreach $episode as $result}
                                            <tr id="guid{$result->guid}">
                                                <td>
                                                    <input id="guid{$result->guid|substr:0:7}" type="checkbox" class="flat" name="table_data{$seasonnum}" value="{$result->guid}">
                                                </td>
                                                <td>
                                                    <div class="mb-1">
                                                        <a href="{{url("/details/{$result->guid}")}}" class="title fw-semibold">{$result->searchname|escape:"htmlall"|replace:".":" "}</a>
                                                        {if !empty($result->failed)}
                                                            <i class="fa fa-exclamation-circle text-danger ms-1" title="This release has failed to download for some users"></i>
                                                        {/if}
                                                        {if $lastvisit|strtotime < $result->adddate|strtotime}
                                                            <span class="badge bg-success ms-1">New</span>
                                                        {/if}
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                                        <!-- Media badges -->
                                                        {if $result->nfoid > 0}
                                                            <a href="#" data-bs-toggle="modal" data-bs-target="#nfoModal" data-guid="{$result->guid}" class="badge bg-info">
                                                                <i class="fa fa-file-text-o me-1"></i>NFO
                                                            </a>
                                                        {/if}

                                                        {if $result->jpgstatus == 1 && $userdata->can('preview') == true}
                                                            <a href="{{url("/covers/sample/{$result->guid}_thumb.jpg")}}" name="name{$result->guid}" data-fancybox class="badge bg-primary" rel="preview">
                                                                <i class="fa fa-image me-1"></i>Sample
                                                            </a>
                                                        {/if}

                                                        {if $result->haspreview == 1 && $userdata->can('preview') == true}
                                                            <a href="{{url("/covers/preview/{$result->guid}_thumb.jpg")}}" name="name{$result->guid}" data-fancybox class="badge bg-primary" rel="preview">
                                                                <i class="fa fa-film me-1"></i>Preview
                                                            </a>
                                                        {/if}

                                                        <!-- Source badges -->
                                                        <span class="badge bg-dark">
                                                            <i class="fa fa-users me-1"></i>{$result->group_name}
                                                        </span>

                                                        {if !empty($result->fromname)}
                                                            <span class="badge bg-dark">
                                                                <i class="fa fa-user me-1"></i>{$result->fromname}
                                                            </span>
                                                        {/if}

                                                        <!-- Downloads indicator -->
                                                        <span class="badge bg-secondary text-white">
                                                            <i class="fa fa-download me-1"></i>{$result->grabs|default:0} Grab{if $result->grabs != 1}s{/if}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary text-white rounded-pill">
                                                        <i class="fa fa-folder-open me-1"></i>{$result->category_name}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fa fa-clock-o text-muted me-2"></i>
                                                        <span title="{$result->postdate}">{$result->postdate|timeago}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fa fa-hdd-o text-muted me-2"></i>
                                                        <span class="fw-medium">{$result->size|filesize}</span>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex justify-content-end gap-2">
                                                        <a href="{{url("/getnzb?id={$result->guid}")}}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Download NZB">
                                                            <i class="fa fa-cloud-download-alt"></i>
                                                        </a>
                                                        <a href="{{url("/details/{$result->guid}/#comments")}}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Comments">
                                                            <i class="fa fa-comments"></i>
                                                        </a>
                                                        <a href="#" class="btn btn-sm btn-outline-secondary add-to-cart" data-guid="{$result->guid}" data-bs-toggle="tooltip" title="Send to download basket">
                                                            <i id="icon_cart_{$result->guid}" class="fa fa-shopping-basket"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            {/foreach}
                                        {/foreach}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    {/foreach}
                </div>
            </div>

            <div class="card-footer">
                <div class="nzb_multi_operations">
                    <div class="d-flex align-items-center gap-2">
                        <small>With Selected:</small>
                        <div class="btn-group">
                            <button type="button" class="nzb_multi_operations_download btn btn-sm btn-success" data-bs-toggle="tooltip" title="Download NZBs">
                                <i class="fa fa-cloud-download-alt"></i>
                            </button>
                            <button type="button" class="nzb_multi_operations_cart btn btn-sm btn-info" data-bs-toggle="tooltip" title="Send to Download Basket">
                                <i class="fa fa-shopping-basket"></i>
                            </button>
                            {if isset($isadmin)}
                                <button type="button" class="nzb_multi_operations_edit btn btn-sm btn-warning">
                                    <i class="fa fa-edit"></i>
                                </button>
                                <button type="button" class="nzb_multi_operations_delete btn btn-sm btn-danger">
                                    <i class="fa fa-trash-alt"></i>
                                </button>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {/if}
</div>

<!-- NFO Modal -->
<div class="modal fade" id="nfoModal" tabindex="-1" aria-labelledby="nfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nfoModalLabel">NFO Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3 nfo-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading NFO content...</p>
                </div>
                <pre id="nfoContent" class="bg-dark text-light p-3 rounded d-none" style="white-space: pre; font-family: monospace; overflow-x: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
{literal}
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Check-all functionality for each season
    document.querySelectorAll('.check-all').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const seasonNum = this.getAttribute('data-season');
            const isChecked = this.checked;

            document.querySelectorAll(`input[name="table_data${seasonNum}"]`).forEach(function(item) {
                item.checked = isChecked;
            });
        });
    });

    // NFO Modal content loading
    const nfoModal = document.getElementById('nfoModal');
    if (nfoModal) {
        nfoModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const guid = button.getAttribute('data-guid');
            const loading = nfoModal.querySelector('.nfo-loading');
            const contentElement = document.getElementById('nfoContent');

            // Reset and show loading state
            loading.style.display = 'block';
            contentElement.classList.add('d-none');
            contentElement.textContent = '';

            // Fetch the NFO content via AJAX
            fetch(`/nfo/${guid}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                // Extract just the NFO content from the response
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const preElement = doc.querySelector('pre');
                let nfoText = '';

                if (preElement) {
                    nfoText = preElement.textContent;
                } else {
                    const mainContent = doc.querySelector('.card-body, .main-content, .content-area, main');
                    if (mainContent) {
                        nfoText = mainContent.textContent;
                    } else {
                        nfoText = doc.body.textContent;
                    }
                }

                loading.style.display = 'none';
                contentElement.classList.remove('d-none');
                contentElement.textContent = nfoText.trim();
            })
            .catch(error => {
                console.error('Error fetching NFO content:', error);
                loading.style.display = 'none';
                contentElement.classList.remove('d-none');
                contentElement.textContent = 'Error loading NFO content';
            });
        });
    }

    // Add to cart functionality
    document.querySelectorAll('.add-to-cart').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const guid = this.getAttribute('data-guid');
            const icon = document.getElementById(`guid${guid}`);

            // Add to cart logic would go here
            // This is a placeholder for the AJAX call

            // Visual feedback
            icon.classList.remove('fa-shopping-basket');
            icon.classList.add('fa-check');
            setTimeout(() => {
                icon.classList.remove('fa-check');
                icon.classList.add('fa-shopping-basket');
            }, 2000);
        });
    });
});
{/literal}
</script>
