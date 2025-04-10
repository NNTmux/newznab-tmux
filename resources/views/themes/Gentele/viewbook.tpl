<div class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{route('Books')}}">Books</a></li>
            <li class="breadcrumb-item active">{$book.title|escape:"htmlall"}</li>
        </ol>
    </nav>

    <!-- Book Info Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fa fa-book me-2"></i>Book Details</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Book Cover -->
                <div class="col-md-3 text-center mb-3 mb-md-0">
                    <img src="{{url("/covers/book/{if $book.cover == 1}{$book.id}{else}no-cover{/if}.jpg")}}"
                         class="img-fluid rounded shadow" style="max-height:300px;"
                         alt="{$book.title|escape:"htmlall"}"/>

                    <!-- Action Buttons -->
                    <div class="mt-3">
                        <div class="d-flex justify-content-center gap-2">
                            <a href="{{url("/browse/Books?author={$book.author|urlencode}")}}" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-search me-1"></i> Find More by Author
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Book Info -->
                <div class="col-md-9">
                    <h3 class="mb-3">{$book.title|escape:"htmlall"}</h3>
                    <div class="mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="book-info-item mb-2">
                                    <span class="fw-bold text-secondary me-2"><i class="fa fa-user me-2"></i>Author:</span>
                                    <span>{$book.author|escape:"htmlall"}</span>
                                </div>

                                {if $book.publisher != ""}
                                <div class="book-info-item mb-2">
                                    <span class="fw-bold text-secondary me-2"><i class="fa fa-building me-2"></i>Publisher:</span>
                                    <span>{$book.publisher|escape:"htmlall"}</span>
                                </div>
                                {/if}

                                {if $book.publishdate != ""}
                                <div class="book-info-item mb-2">
                                    <span class="fw-bold text-secondary me-2"><i class="fa fa-calendar me-2"></i>Published:</span>
                                    <span>{$book.publishdate|date_format}</span>
                                </div>
                                {/if}
                            </div>

                            <div class="col-md-6">
                                {if $book.pages != ""}
                                <div class="book-info-item mb-2">
                                    <span class="fw-bold text-secondary me-2"><i class="fa fa-file-text me-2"></i>Pages:</span>
                                    <span>{$book.pages}</span>
                                </div>
                                {/if}

                                {if isset($book.genre) && $book.genre != ""}
                                <div class="book-info-item mb-2">
                                    <span class="fw-bold text-secondary me-2"><i class="fa fa-tags me-2"></i>Genre:</span>
                                    <span>{$book.genre|escape:"htmlall"}</span>
                                </div>
                                {/if}

                                {if isset($book.isbn) && $book.isbn != ""}
                                <div class="book-info-item mb-2">
                                    <span class="fw-bold text-secondary me-2"><i class="fa fa-barcode me-2"></i>ISBN:</span>
                                    <span>{$book.isbn}</span>
                                </div>
                                {/if}
                            </div>
                        </div>
                    </div>

                    <!-- External Links -->
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        {if isset($book.goodreads) && $book.goodreads > 0}
                        <a class="btn btn-sm btn-info" target="_blank"
                           href="{$site->dereferrer_link}https://www.goodreads.com/book/show/{$book.goodreads}"
                           title="View on Goodreads">
                            <i class="fa fa-external-link-alt me-1"></i> Goodreads
                        </a>
                        {/if}

                        {if isset($book.amazon) && $book.amazon != ""}
                        <a class="btn btn-sm btn-info" target="_blank"
                           href="{$site->dereferrer_link}https://www.amazon.com/dp/{$book.amazon}"
                           title="View on Amazon">
                            <i class="fa fa-shopping-cart me-1"></i> Amazon
                        </a>
                        {/if}

                        <a class="btn btn-sm btn-success"
                           href="{{url("/rss/book?id={$book.id}&dl=1&i={$userdata.id}&api_token={$userdata.api_token}")}}">
                            <i class="fa fa-rss me-1"></i> RSS Feed
                        </a>
                    </div>
                </div>
            </div>

            <!-- Book Review -->
            {if $book.review != ""}
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fa fa-quote-left me-2"></i>Review</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">{$book.review|escape:"htmlall"|nl2br|magicurl}</p>
                        </div>
                    </div>
                </div>
            </div>
            {/if}
        </div>
    </div>

    <!-- Releases Card -->
    {if isset($releases) && count($releases) > 0}
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa fa-download me-2"></i>Available Releases</h5>

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
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 30px">
                                <input id="check-all" type="checkbox" class="flat-all">
                            </th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Posted</th>
                            <th>Size</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $releases as $result}
                        <tr id="guid{$result->guid}">
                            <td>
                                <input id="guid{$result->guid|substr:0:7}" type="checkbox" class="flat" value="{$result->guid}">
                            </td>
                            <td>
                                <div class="mb-1">
                                    <a href="{{url("/details/{$result->guid}")}}" class="title fw-semibold">{$result->searchname|escape:"htmlall"|replace:".":" "}</a>
                                    {if isset($result->failed) && $result->failed > 0}
                                        <i class="fa fa-exclamation-circle text-danger ms-1" title="This release has failed to download for some users"></i>
                                    {/if}
                                    {if $lastvisit|strtotime < $result->adddate|strtotime}
                                        <span class="badge bg-success ms-1">New</span>
                                    {/if}
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <!-- Media badges -->
                                    <span class="badge bg-secondary text-white">
                                        <i class="fa fa-download me-1"></i>{$result->grabs|default:0} Grab{if $result->grabs != 1}s{/if}
                                    </span>

                                    {if isset($result->nfoid) && $result->nfoid > 0}
                                        <a href="#" data-bs-toggle="modal" data-bs-target="#nfoModal" data-guid="{$result->guid}" class="badge bg-info">
                                            <i class="fa fa-file-text-o me-1"></i>NFO
                                        </a>
                                    {/if}

                                    <!-- Source badges -->
                                    {if isset($result->group_name)}
                                        <span class="badge bg-dark">
                                            <i class="fa fa-users me-1"></i>{$result->group_name}
                                        </span>
                                    {/if}
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
                    </tbody>
                </table>
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

    // Check-all functionality
    const checkAll = document.getElementById('check-all');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            const isChecked = this.checked;
            document.querySelectorAll('input.flat').forEach(function(item) {
                item.checked = isChecked;
            });
        });
    }

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
            const icon = document.getElementById(`icon_cart_${guid}`);

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
