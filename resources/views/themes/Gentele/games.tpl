<div class="container-fluid px-4 py-3">
							    <!-- Breadcrumb -->
							    <nav aria-label="breadcrumb" class="mb-3">
							        <ol class="breadcrumb">
							            <li class="breadcrumb-item"><a href="{{url({$site->home_link})}}">Home</a></li>
							            <li class="breadcrumb-item">
							                {if !empty({$catname->parent->title})}<a href="{{url("browse/{$catname->parent->title}")}}">{$catname->parent->title}</a>{else}<a href="{{url("/browse/{$catname->title}")}}">{$catname->title}</a>{/if}
							            </li>
							            <li class="breadcrumb-item active">
							                {if !empty({$catname->parent->title})}<a href="{{url("/browse/{$catname->title}")}}">{$catname->title}</a>{else}All{/if}
							            </li>
							        </ol>
							    </nav>

							    <!-- Search Filters -->
							    <div class="card shadow-sm mb-4">
							        <div class="card-header bg-light">
							            {include file='search-filter.tpl'}
							        </div>
							    </div>

							    <!-- Main Content -->
							    <div class="card shadow-sm mb-4">
							        <div class="card-body">
							            <!-- Top Controls -->
							            <div class="row mb-3 align-items-center">
							                <div class="col-md-4">
							                    {{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
							                    <div class="d-flex align-items-center">
							                        <div class="me-3">
							                            <span>View: <strong>Covers</strong> | <a href="{{url("/browse/Pc/Games/{$categorytitle}")}}">List</a></span>
							                        </div>
							                        <div class="d-flex align-items-center">
							                            <small class="me-2">With Selected:</small>
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
							                <div class="col-md-8 d-flex justify-content-end">
							                    {$results->onEachSide(5)->links()}
							                </div>
							            </div>

							            <hr>

							            <!-- Game Grid -->
							            <div class="row g-4">
							                {if count($results) > 0}
							                    {foreach $results as $result}
							                        <div class="col-md-6 mb-4">
							                            <div class="card h-100 shadow-sm hover-shadow">
							                                <div class="card-body">
							                                    <div class="row">
							                                        <!-- Game Cover Column -->
							                                        <div class="col-md-4 mb-3 mb-md-0">
							                                            <div class="position-relative mb-2">
							                                                <a href="{{url("/details/{$result->guid}")}}" title="View details">
							                                                    <img class="cover img-fluid rounded shadow"
							                                                         src="{if $result->cover == 1}{{url("/covers/games/{$result->gamesinfo_id}.jpg")}}{else}{{asset("/assets/images/no-cover.png")}}{/if}"
							                                                         alt="{$result->title|escape:"htmlall"}"/>

							                                                    {if !empty($result->failed)}
							                                                        <div class="position-absolute top-0 end-0 p-2">
							                                                            <span class="badge bg-danger rounded-pill">
							                                                                <i class="fa fa-exclamation-circle me-1"></i>Failed
							                                                            </span>
							                                                        </div>
							                                                    {/if}
							                                                </a>
							                                            </div>

							                                            <!-- External Links Badges -->
							                                            <div class="d-flex flex-wrap gap-1 mb-2 external-links">
							                                                {if $result->classused == "Steam"}
							                                                    <a target="_blank" href="{$site->dereferrer_link}{$result->url|escape:"htmlall"}"
							                                                       title="View Steam page" class="badge bg-secondary text-white text-decoration-none badge-link" rel="steam">
							                                                       <i class="fa fa-steam me-1"></i>Steam
							                                                    </a>
							                                                {/if}

							                                                {if $result->classused == "IGDB"}
							                                                    <a target="_blank" href="{$site->dereferrer_link}{$result->url}"
							                                                       title="View IGDB page" class="badge bg-secondary text-white text-decoration-none badge-link" rel="igdb">
							                                                       <i class="fa fa-database me-1"></i>IGDB
							                                                    </a>
							                                                {/if}

							                                                {if $result->nfoid > 0}
							                                                    <a href="{{url("/nfo/{$result->guid}")}}" data-guid="{$result->guid}"
							                                                       title="View NFO" class="badge bg-secondary text-white text-decoration-none badge-link" data-bs-toggle="modal" data-bs-target="#nfoModal">
							                                                       <i class="fa fa-file-text me-1"></i>NFO
							                                                    </a>
							                                                {/if}

							                                                <a class="badge bg-secondary text-white text-decoration-none badge-link"
							                                                   href="{{url("/browse/group?g={$result->group_name}")}}"
							                                                   title="Browse releases in {$result->group_name|replace:"alt.binaries":"a.b"}">
							                                                   <i class="fa fa-users me-1"></i>Group
							                                                </a>
							                                            </div>
							                                        </div>

							                                        <!-- Game Info Column -->
							                                        <div class="col-md-8">
							                                            <!-- Game Title -->
							                                            <h5 class="mb-2">
							                                                <a class="text-decoration-none title-link" href="{{url("/details/{$result->guid}")}}">
							                                                    {$result->title|escape:"htmlall"}
							                                                    {if isset($result->releasedate) && $result->releasedate != ""}
							                                                        <span class="year-badge badge bg-primary ms-1">{$result->releasedate|date_format:"%Y"}</span>
							                                                    {/if}
							                                                </a>
							                                            </h5>

							                                            <!-- Game Details -->
							                                            <div class="mb-3 small">
							                                                {if isset($result->genre) && $result->genre != ""}
							                                                    <div class="d-flex align-items-center mb-1">
							                                                        <i class="fa fa-tags text-muted me-2"></i>
							                                                        <div class="genre-tags">
							                                                            {foreach explode(", ", $result->genre) as $genre}
							                                                                <span class="badge bg-light text-dark me-1">{$genre}</span>
							                                                            {/foreach}
							                                                        </div>
							                                                    </div>
							                                                {/if}

							                                                {if isset($result->publisher) && $result->publisher != ""}
							                                                    <div class="d-flex align-items-center mb-1">
							                                                        <i class="fa fa-building text-muted me-2"></i>
							                                                        <span><strong>Publisher:</strong> {$result->publisher}</span>
							                                                    </div>
							                                                {/if}

							                                                {if isset($result->esrb) && $result->esrb != ""}
							                                                    <div class="d-flex align-items-center mb-1">
							                                                        <i class="fa fa-certificate text-muted me-2"></i>
							                                                        <span><strong>Rating:</strong> {$result->esrb}</span>
							                                                    </div>
							                                                {/if}

							                                                {if isset($result->releasedate) && $result->releasedate != ""}
							                                                    <div class="d-flex align-items-center mb-1">
							                                                        <i class="fa fa-calendar text-muted me-2"></i>
							                                                        <span><strong>Released:</strong> {$result->releasedate|date_format}</span>
							                                                    </div>
							                                                {/if}

							                                                {if isset($result->review) && $result->review != ""}
							                                                    <div class="mb-1 text-truncate-3 review-text">
							                                                        <i class="fa fa-quote-left text-muted me-2"></i>{$result->review|stripslashes|escape:'htmlall'}
							                                                    </div>
							                                                {/if}
							                                            </div>

							                                            <!-- Release Info -->
							                                            <div class="card bg-light mb-3">
							                                                <div class="card-body p-3">
							                                                    <div class="d-flex justify-content-between align-items-start mb-2">
							                                                        <div>
							                                                            <label class="me-2 mb-0">
							                                                                <input type="checkbox" class="form-check-input" value="{$result->guid}" id="chk{$result->guid|substr:0:7}"/>
							                                                            </label>
							                                                        </div>

							                                                        <div class="d-flex align-items-center">
							                                                            <i class="fa fa-hdd-o text-muted me-2"></i>
							                                                            <span class="badge bg-secondary">{$result->size|filesize}</span>
							                                                        </div>
							                                                    </div>

							                                                    <div class="d-flex align-items-center text-muted mb-2">
							                                                        <i class="fa fa-clock-o me-2"></i>
							                                                        <span>Posted {$result->postdate|timeago} ago</span>
							                                                    </div>

							                                                    <!-- Action Buttons -->
							                                                    <div class="d-flex gap-2">
							                                                        <a class="btn btn-sm btn-primary"
							                                                           data-bs-toggle="tooltip" data-bs-placement="top" title="Download NZB"
							                                                           href="{{url("/getnzb?id={$result->guid}")}}">
							                                                            <i class="fa fa-cloud-download me-1"></i>
							                                                            <span class="badge bg-light text-dark">{$result->grabs}</span>
							                                                        </a>

							                                                        <a class="btn btn-sm btn-outline-secondary"
							                                                           href="{{url("/details/{$result->guid}/#comments")}}">
							                                                            <i class="fa fa-comment-o me-1"></i>
							                                                            <span class="badge bg-light text-dark">{$result->comments}</span>
							                                                        </a>

							                                                        <a href="#" class="btn btn-sm btn-outline-info add-to-cart"
							                                                           data-bs-toggle="tooltip" data-bs-placement="top" title="Send to my download basket">
							                                                            <i id="guid{$result->guid}" class="icon_cart fa fa-shopping-basket"></i>
							                                                        </a>

							                                                        {if !empty($result->failed)}
							                                                            <span class="btn btn-sm btn-outline-danger">
							                                                                <i class="fa fa-exclamation-triangle me-1"></i>
							                                                                {$result->failed} Failed
							                                                            </span>
							                                                        {/if}
							                                                    </div>
							                                                </div>
							                                            </div>
							                                        </div>
							                                    </div>
							                                </div>
							                            </div>
							                        </div>
							                    {/foreach}
							                {else}
							                    <div class="col-12">
							                        <div class="alert alert-info">No games with covers available!</div>
							                    </div>
							                {/if}
							            </div>

							            <!-- Bottom Controls -->
							            <hr>
							            <div class="row mt-4">
							                <div class="col-md-4">
							                    <div class="d-flex align-items-center">
							                        <div class="me-3">
							                            <span>View: <strong>Covers</strong> | <a href="{{url("/browse/Pc/Games/{$categorytitle}")}}">List</a></span>
							                        </div>
							                        <div class="d-flex align-items-center">
							                            <small class="me-2">With Selected:</small>
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
							                    <div class="col-md-8 d-flex justify-content-end">
							                        {$results->onEachSide(5)->links()}
							                    </div>
							                {/if}
							            </div>
							            {{Form::close()}}
							        </div>
							    </div>
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

							<style>
							.text-truncate-3 {
							    display: -webkit-box;
							    -webkit-line-clamp: 3;
							    -webkit-box-orient: vertical;
							    overflow: hidden;
							}
							.hover-shadow:hover {
							    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
							    transition: box-shadow 0.3s ease-in-out;
							}
							.badge-link {
							    transition: all 0.2s ease;
							}
							.badge-link:hover {
							    transform: translateY(-2px);
							    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
							}
							.title-link:hover .year-badge {
							    transition: all 0.2s ease;
							    background-color: #0d6efd !important;
							}
							.add-to-cart:hover .icon_cart {
							    animation: shake 0.5s ease-in-out;
							}
							@keyframes shake {
							    0%, 100% { transform: rotate(0deg); }
							    25% { transform: rotate(-10deg); }
							    75% { transform: rotate(10deg); }
							}
							.genre-tags .badge {
							    transition: all 0.2s ease;
							}
							.genre-tags .badge:hover {
							    background-color: #e9ecef !important;
							    cursor: default;
							}
							</style>

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
							{/literal}
							</script>
