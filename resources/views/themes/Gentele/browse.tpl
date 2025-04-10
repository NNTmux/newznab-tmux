<div class="header">
                                            <div class="breadcrumb-wrapper">
                                                <nav aria-label="breadcrumb">
                                                    <ol class="breadcrumb">
                                                        <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
                                                        {if $parentcat == ''}
                                                            <li class="breadcrumb-item active"><a href="{{url("/browse/$catname")}}">Browse/{$catname}</a></li>
                                                        {else}
                                                            <li class="breadcrumb-item"><a href="{{url("/{if preg_match('/^alt\.binaries|a\.b|dk\./i', $parentcat)}browse/group?g={else}browse/{/if}{if ($parentcat == 'music')}Audio{else}{$parentcat}{/if}")}}">{$parentcat}</a></li>
                                                            {if ($catname != '' && $catname != 'all')}
                                                                <li class="breadcrumb-item active"><a href="{{url("/browse/{$parentcat}/{$catname}")}}">{$catname}</a></li>
                                                            {/if}
                                                        {/if}
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>

                                        {$site->adbrowse}

                                        {if count($results) > 0}
                                            {{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
                                            <div class="card-header bg-light">
                                                    <div class="row g-3">
                                                        <!-- Changed from col-md-4 to col-lg-4 for better small screen handling -->
                                                        <div class="col-lg-4 col-md-12">
                                                            {if isset($shows)}
                                                                <div class="mb-3">
                                                                    <a href="{{route('series')}}" class="me-2" title="View available TV series">Series List</a> |
                                                                    <a href="{{route('myshows')}}" class="mx-2" title="Manage your shows">Manage My Shows</a> |
                                                                    <a href="{{url("/rss/myshows?dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}" class="ms-2" title="All releases in your shows as an RSS feed">RSS Feed</a>
                                                                </div>
                                                            {/if}

                                                            {if isset($covgroup) && $covgroup != ''}
                                                                <div class="mb-3">
                                                                    <span class="me-2">View:</span>
                                                                    <a href="{{url("/{$covgroup}/{$category}")}}" class="me-2">Covers</a> |
                                                                    <b class="ms-2">List</b>
                                                                </div>
                                                            {/if}

                                                            <div class="nzb_multi_operations d-flex flex-wrap align-items-center">
                                                                <small class="me-2 mb-2">With Selected:</small>
                                                                <div class="btn-group mb-2">
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
                                                        <!-- Changed from col-md-8 to col-lg-8 and added order classes -->
                                                        <div class="col-lg-8 col-md-12 d-flex justify-content-lg-end justify-content-center align-items-center order-lg-2 order-md-1">
                                                            <div class="pagination-container overflow-auto w-100 d-flex justify-content-lg-end justify-content-center">
                                                                {$results->onEachSide(5)->links()}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="card-body px-0 py-0">
                                                    <div class="table-responsive">
                                                        <table class="table table-striped table-hover mb-0">
                                                            <thead class="thead-light">
                                                                <tr>
                                                                    <th style="width: 30px">
                                                                        <input id="check-all" type="checkbox" class="flat-all">
                                                                    </th>
                                                                    <th>
                                                                        <div class="d-flex align-items-center gap-2">
                                                                            <span>Name</span>
                                                                            <div class="sort-controls">
                                                                                <a href="{$orderbyname_asc}" class="sort-icon {if isset($orderby) && $orderby == 'name_asc'}active{/if}" title="Sort Ascending">
                                                                                    <i class="fas fa-sort-alpha-down"></i>
                                                                                </a>
                                                                                <a href="{$orderbyname_desc}" class="sort-icon {if isset($orderby) && $orderby == 'name_desc'}active{/if}" title="Sort Descending">
                                                                                    <i class="fas fa-sort-alpha-down-alt"></i>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </th>
                                                                    <th>
                                                                        <div class="d-flex align-items-center gap-2">
                                                                            <span>Category</span>
                                                                            <div class="sort-controls">
                                                                                <a href="{$orderbycat_asc}" class="sort-icon {if isset($orderby) && $orderby == 'cat_asc'}active{/if}" title="Sort Ascending">
                                                                                    <i class="fas fa-sort-alpha-down"></i>
                                                                                </a>
                                                                                <a href="{$orderbycat_desc}" class="sort-icon {if isset($orderby) && $orderby == 'cat_desc'}active{/if}" title="Sort Descending">
                                                                                    <i class="fas fa-sort-alpha-down-alt"></i>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </th>
                                                                    <th>
                                                                        <div class="d-flex align-items-center gap-2">
                                                                            <span>Posted</span>
                                                                            <div class="sort-controls">
                                                                                <a href="{$orderbyposted_asc}" class="sort-icon {if isset($orderby) && $orderby == 'posted_asc'}active{/if}" title="Sort Oldest First">
                                                                                    <i class="fas fa-sort-numeric-down"></i>
                                                                                </a>
                                                                                <a href="{$orderbyposted_desc}" class="sort-icon {if isset($orderby) && $orderby == 'posted_desc'}active{/if}" title="Sort Newest First">
                                                                                    <i class="fas fa-sort-numeric-down-alt"></i>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </th>
                                                                    <th>
                                                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                                                            <span>Size</span>
                                                                            <div class="sort-controls">
                                                                                <a href="{$orderbysize_asc}" class="sort-icon {if isset($orderby) && $orderby == 'size_asc'}active{/if}" title="Sort Smallest First">
                                                                                    <i class="fas fa-sort-numeric-down"></i>
                                                                                </a>
                                                                                <a href="{$orderbysize_desc}" class="sort-icon {if isset($orderby) && $orderby == 'size_desc'}active{/if}" title="Sort Largest First">
                                                                                    <i class="fas fa-sort-numeric-down-alt"></i>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </th>
                                                                    <th>
                                                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                                                            <span>Files</span>
                                                                            <div class="sort-controls">
                                                                                <a href="{$orderbyfiles_asc}" class="sort-icon {if isset($orderby) && $orderby == 'files_asc'}active{/if}" title="Sort Fewest First">
                                                                                    <i class="fas fa-sort-numeric-down"></i>
                                                                                </a>
                                                                                <a href="{$orderbyfiles_desc}" class="sort-icon {if isset($orderby) && $orderby == 'files_desc'}active{/if}" title="Sort Most First">
                                                                                    <i class="fas fa-sort-numeric-down-alt"></i>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </th>
                                                                    <th>
                                                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                                                            <span>Downloads</span>
                                                                            <div class="sort-controls">
                                                                                <a href="{$orderbystats_asc}" class="sort-icon {if isset($orderby) && $orderby == 'stats_asc'}active{/if}" title="Sort Fewest First">
                                                                                    <i class="fas fa-sort-numeric-down"></i>
                                                                                </a>
                                                                                <a href="{$orderbystats_desc}" class="sort-icon {if isset($orderby) && $orderby == 'stats_desc'}active{/if}" title="Sort Most First">
                                                                                    <i class="fas fa-sort-numeric-down-alt"></i>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </th>
                                                                    <th class="text-end">Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {foreach $resultsadd as $result}
                                                                    <tr id="guid{$result->guid}">
                                                                        <td>
                                                                            <input id="chk{$result->guid|substr:0:7}" type="checkbox" name="table_records" class="flat" value="{$result->guid}">
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
                                                                                <!-- Downloads indicator -->
                                                                                <span class="badge bg-secondary text-white">
                                                                                    <i class="fa fa-download me-1"></i>{$result->grabs} Grab{if $result->grabs != 1}s{/if}
                                                                                </span>

                                                                                <!-- Media badges -->
                                                                                {if $result->nfoid > 0}
                                                                                    <a href="#" data-bs-toggle="modal" data-bs-target="#nfoModal" data-guid="{$result->guid}" class="nfo-modal-trigger badge bg-info">
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

                                                                                <!-- Media type badges -->
                                                                                {if $result->videos_id > 0}
                                                                                    <a href="{{url("/series/{$result->videos_id}")}}" class="badge bg-success" rel="series">
                                                                                        <i class="fa fa-tv me-1"></i>View TV
                                                                                    </a>
                                                                                {/if}

                                                                                {if !empty($result->firstaired)}
                                                                                    <span class="seriesinfo badge bg-warning text-dark" title="{$result->guid}">
                                                                                        <i class="fa fa-calendar me-1"></i>Aired {if $result->firstaired|strtotime > $smarty.now}in future{else}{$result->firstaired|daysago}{/if}
                                                                                    </span>
                                                                                {/if}

                                                                                {if $result->anidbid > 0}
                                                                                    <a class="badge bg-success" href="{{url("/anime?id={$result->anidbid}")}}">
                                                                                        <i class="fa fa-play-circle me-1"></i>View Anime
                                                                                    </a>
                                                                                {/if}

                                                                                <!-- Stats badges -->
                                                                                {if !empty($result->failed)}
                                                                                    <span class="badge bg-danger">
                                                                                        <i class="fa fa-exclamation-triangle me-1"></i>{$result->failed} Failed
                                                                                    </span>
                                                                                {/if}

                                                                                <!-- Source badges -->
                                                                                <span class="badge bg-dark">
                                                                                    <i class="fa fa-users me-1"></i>{$result->group_name}
                                                                                </span>

                                                                                <span class="badge bg-dark">
                                                                                    <i class="fa fa-user me-1"></i>{$result->fromname}
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
                                                                        <td class="text-center">
                                                                            <div class="d-flex align-items-center justify-content-center">
                                                                                <i class="fa fa-hdd-o text-muted me-2"></i>
                                                                                <span class="fw-medium">{$result->size|filesize}</span>
                                                                            </div>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <div class="d-flex align-items-center justify-content-center">
                                                                                <a class="d-flex align-items-center text-decoration-none" title="View file list" href="#" data-bs-toggle="modal" data-bs-target="#filelistModal" data-guid="{$result.guid}">
                                                                                    <i class="far fa-file text-primary me-1"></i>
                                                                                    <span class="fw-medium">{$result->totalpart}</span>
                                                                                </a>
                                                                                {if $result->rarinnerfilecount > 0}
                                                                                    <div class="rarfilelist d-inline-block ms-2" title="View RAR contents">
                                                                                        <i class="fas fa-search-plus text-info"></i>
                                                                                    </div>
                                                                                {/if}
                                                                            </div>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <div class="d-flex align-items-center justify-content-center">
                                                                                <i class="fa fa-download text-muted me-2"></i>
                                                                                <span class="fw-medium">{$result->grabs}</span>
                                                                            </div>
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
                                                </div>

                                                <div class="card-footer">
                                                    <div class="row g-3">
                                                        <!-- Changed from col-md-4 to col-lg-4 to match header -->
                                                        <div class="col-lg-4 col-md-12">
                                                            <div class="nzb_multi_operations">
                                                                <div class="d-flex flex-wrap align-items-center gap-2">
                                                                    <small class="me-2 mb-2">With Selected:</small>
                                                                    <div class="btn-group mb-2">
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
                                                        <!-- Changed from col-md-8 to col-lg-8 and improved responsive behavior -->
                                                        <div class="col-lg-8 col-md-12 d-flex justify-content-lg-end justify-content-center align-items-center order-lg-2 order-md-1">
                                                            <div class="pagination-container overflow-auto w-100 d-flex justify-content-lg-end justify-content-center">
                                                                {$results->onEachSide(5)->links()}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            {{Form::close()}}
                                        {else}
                                            <div class="alert alert-info">No releases indexed yet!</div>
                                        {/if}
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

<!-- File List Modal -->
<div class="modal fade" id="filelistModal" tabindex="-1" aria-labelledby="filelistModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filelistModalLabel">File List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3 filelist-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading file list...</p>
                </div>
                <div id="filelistContent" class="d-none">
                    <div class="mb-3 d-flex justify-content-between">
                        <span class="text-muted small">Total Files: <span id="total-files">0</span></span>
                        <span class="text-muted small">Total Size: <span id="total-size">0 B</span></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 40px" class="text-center">#</th>
                                    <th>Filename</th>
                                    <th style="width: 60px" class="text-center">Type</th>
                                    <th style="width: 120px" class="text-center">Completion</th>
                                    <th style="width: 100px" class="text-center">Size</th>
                                </tr>
                            </thead>
                            <tbody id="filelist-tbody">
                                <!-- Files will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
{literal}
    // NFO Modal content loading
    document.addEventListener('DOMContentLoaded', function() {
        const nfoModal = document.getElementById('nfoModal');

        if (nfoModal) {
            nfoModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const guid = button.getAttribute('data-guid');
                const modalTitle = nfoModal.querySelector('.modal-title');
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

                    // Look for the pre element that likely contains the NFO
                    let nfoText = '';
                    const preElement = doc.querySelector('pre');

                    if (preElement) {
                        // Found a pre element, use its content
                        nfoText = preElement.textContent;
                    } else {
                        // Try to find the main content area
                        const mainContent = doc.querySelector('.card-body, .main-content, .content-area, main');
                        if (mainContent) {
                            nfoText = mainContent.textContent;
                        } else {
                            // Fallback: use the whole page but clean it up
                            nfoText = doc.body.textContent;
                        }
                    }

                    // Update the modal
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
    });

// File List Modal
document.addEventListener('DOMContentLoaded', function() {
    const filelistModal = document.getElementById('filelistModal');

    if (filelistModal) {
        filelistModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const guid = button.getAttribute('data-guid');
            const loading = filelistModal.querySelector('.filelist-loading');
            const contentElement = document.getElementById('filelistContent');
            const tbody = document.getElementById('filelist-tbody');
            const totalFiles = document.getElementById('total-files');
            const totalSize = document.getElementById('total-size');

            // Reset and show loading state
            loading.style.display = 'block';
            contentElement.classList.add('d-none');
            tbody.innerHTML = '';

            // Fetch the file list via AJAX
            fetch(`/filelist/${guid}?modal=true`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    // Extract data from the HTML response
                    const files = [];
                    const tableRows = doc.querySelectorAll('table tbody tr');
                    let totalSizeBytes = 0;

                    tableRows.forEach(row => {
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 5) {
                            const file = {
                                num: cells[0].textContent.trim(),
                                filename: cells[1].querySelector('.text-truncate').getAttribute('title'),
                                ext: cells[2].querySelector('.badge') ? cells[2].querySelector('.badge').textContent.trim() : '',
                                completion: cells[3].querySelector('.progress-bar') ?
                                    cells[3].querySelector('.progress-bar').getAttribute('aria-valuenow') : '100',
                                size: cells[4].textContent.trim()
                            };

                            // Parse filesize for total calculation
                            const sizeMatch = file.size.match(/(\d+(\.\d+)?)\s*(KB|MB|GB|TB)/i);
                            if (sizeMatch) {
                                const size = parseFloat(sizeMatch[1]);
                                const unit = sizeMatch[3].toUpperCase();
                                let bytes = size;
                                if (unit === 'KB') bytes *= 1024;
                                else if (unit === 'MB') bytes *= 1024 * 1024;
                                else if (unit === 'GB') bytes *= 1024 * 1024 * 1024;
                                else if (unit === 'TB') bytes *= 1024 * 1024 * 1024 * 1024;
                                totalSizeBytes += bytes;
                            }

                            files.push(file);
                        }
                    });

                    // Format total size
                    function formatFileSize(bytes) {
                        if (bytes < 1024) return bytes + ' B';
                        else if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
                        else if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
                        else if (bytes < 1024 * 1024 * 1024 * 1024) return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
                        else return (bytes / (1024 * 1024 * 1024 * 1024)).toFixed(2) + ' TB';
                    }

                    // Display total information
                    totalFiles.textContent = files.length;
                    totalSize.textContent = formatFileSize(totalSizeBytes);

                    // Populate the modal with files
                    files.forEach(file => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td class="text-center">${file.num}</td>
                            <td class="text-break">
                                <span class="d-inline-block text-truncate" style="max-width: 400px;" title="${file.filename}">${file.filename}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary text-uppercase">${file.ext}</span>
                            </td>
                            <td class="text-center">
                                <div class="progress" style="height: 20px">
                                    <div class="progress-bar ${file.completion < 100 ? 'bg-warning' : 'bg-success'}"
                                         role="progressbar"
                                         style="width: ${file.completion}%"
                                         aria-valuenow="${file.completion}"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                        ${file.completion}%
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="fa fa-hdd-o text-muted me-2"></i>
                                    <span class="fw-medium">${file.size}</span>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });

                    // Show content, hide loading
                    loading.style.display = 'none';
                    contentElement.classList.remove('d-none');
                })
                .catch(error => {
                    console.error('Error fetching file list:', error);
                    loading.style.display = 'none';
                    contentElement.classList.remove('d-none');
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading file list</td></tr>';
                });
        });
    }
});
{/literal}
</script>
