<div class="header">
											    <h2>View > <strong>Movie</strong></h2>
											    <div class="breadcrumb-wrapper">
											        <ol class="breadcrumb">
											            <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
											            <li class="breadcrumb-item active">View Movie</li>
											        </ol>
											    </div>
											</div>

											{if $results|@count > 0}
											    <div class="card shadow-sm mb-4">
											        {foreach $results as $result}
											            {if !empty($result['id'])}
											                <div class="card-body p-4">
											                    <div id="moviefull" class="row">
											                        <div class="col-md-8 mb-4 mb-md-0">
											                            <h1 class="display-5 mb-2">{$result['title']|escape:"htmlall"}
											                                <span class="badge bg-secondary">{$result['year']}</span>
											                                {if $result['rating'] != ''}
											                                    <span class="badge bg-warning text-dark ms-2">
											                                        <i class="fa fa-star"></i> {$result['rating']}/10
											                                    </span>
											                                {/if}
											                            </h1>

											                            <div class="mb-3">
											                                <a class="btn btn-outline-primary me-2" target="_blank"
											                                    href="{$site['dereferrer_link']}http://www.imdb.com/title/tt{$result['imdbid']}/"
											                                    name="imdb{$result['imdbid']}" title="View IMDB page">
											                                    <i class="fa fa-film me-1"></i> IMDB
											                                </a>
											                                <a class="btn btn-outline-primary me-2" target="_blank"
											                                    href="{$site['dereferrer_link']}http://trakt.tv/search/imdb/tt{$result['imdbid']}/"
											                                    name="trakt{$result['imdbid']}" title="View Trakt page" rel="trakt">
											                                    <i class="fa fa-television me-1"></i> TRAKT
											                                </a>
											                                {if (!empty($result['tmdbid']))}
											                                    <a class="btn btn-outline-primary" target="_blank"
											                                        href="{$site['dereferrer_link']}http://www.themoviedb.org/movie/{$result['tmdbid']}"
											                                        name="tmdb{$result['tmdbid']}" title="View TheMovieDB page">
											                                        <i class="fa fa-database me-1"></i> TMDB
											                                    </a>
											                                {/if}
											                            </div>

											                            {if $result['genre'] != ''}
											                                <div class="genre-tags mb-3">
											                                    {foreach explode("|", $result['genre']) as $genre}
											                                        <span class="badge bg-light text-dark me-2 mb-2">{$genre|trim}</span>
											                                    {/foreach}
											                                </div>
											                            {/if}

											                            {if $result['tagline'] != ''}
											                                <p class="lead fst-italic mb-3">"{$result['tagline']|escape:"htmlall"}"</p>
											                            {/if}

											                            {if $result['plot'] != ''}
											                                <h5 class="border-bottom pb-2 mb-2">Plot</h5>
											                                <p class="mb-4">{$result['plot']|escape:"htmlall"}</p>
											                            {/if}

											                            <div class="row">
											                                {if $result['director'] != ''}
											                                    <div class="col-md-6 mb-3">
											                                        <h5 class="border-bottom pb-2 mb-2">Director</h5>
											                                        <p>{$result['director']|replace:"|":", "}</p>
											                                    </div>
											                                {/if}

											                                {if $result['actors'] != ''}
											                                    <div class="col-md-6 mb-3">
											                                        <h5 class="border-bottom pb-2 mb-2">Actors</h5>
											                                        <p>{$result['actors']|replace:"|":", "}</p>
											                                    </div>
											                                {/if}
											                            </div>
											                        </div>

											                        <div class="col-md-4 text-center">
											                            <div class="card shadow-sm">
											                                {if $result['cover'] == 1}
											                                    <img class="img-fluid rounded card-img-top"
											                                        alt="{$result['title']|escape:"htmlall"} Cover"
											                                        src="{{url("/covers/movies/{$result['imdbid']}-cover.jpg")}}"/>
											                                {else}
											                                    <img class="img-fluid rounded card-img-top"
											                                        alt="{$result['title']|escape:"htmlall"} Cover"
											                                        src="{asset("/assets/images/nomoviecover.jpg")}}"/>
											                                {/if}

											                                {if $result['rating'] != '' && isset($result['ratingcount']) && $result['ratingcount'] != ''}
											                                    <div class="card-footer bg-light">
											                                        <small class="text-muted">Rated by {$result['ratingcount']|number_format} viewers</small>
											                                    </div>
											                                {/if}
											                            </div>
											                        </div>
											                    </div>
											                </div>

											                {{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
											                <div class="card shadow-sm mt-4">
											                    <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap">
											                        <div class="d-flex align-items-center mb-2 mb-md-0">
											                            {if isset($section) && $section != ''}
											                                <span class="me-3">View:
											                                    <a href="{{url("/{$section}?t={$category}")}}">Covers</a> |
											                                    <strong>List</strong>
											                                </span>
											                            {/if}
											                            <div class="d-flex align-items-center">
											                                <small class="me-2">With Selected:</small>
											                                <div class="btn-group">
											                                    <button type="button" class="nzb_multi_operations_download btn btn-sm btn-success"
											                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Download NZBs">
											                                        <i class="fa fa-cloud-download"></i>
											                                    </button>
											                                    <button type="button" class="nzb_multi_operations_cart btn btn-sm btn-info"
											                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Send to my Download Basket">
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

											                    <div class="card-body p-0">
											                        <div class="table-responsive">
											                            <table class="table table-hover table-striped mb-0">
											                                <thead class="table-light">
											                                    <tr>
											                                        <th class="text-center" style="width: 30px;">
											                                            <div class="form-check">
											                                                <input id="check-all" type="checkbox" class="form-check-input flat-all"
											                                                       data-bs-toggle="tooltip" data-bs-placement="top" title="Select/Deselect All"/>
											                                                <label class="visually-hidden" for="check-all">Select All</label>
											                                            </div>
											                                        </th>
											                                        <th>Name</th>
											                                        <th style="width: 110px;">Category</th>
											                                        <th style="width: 120px;">Posted</th>
											                                        <th class="text-end" style="width: 80px;">Size</th>
											                                        <th class="text-center" style="width: 140px;">Action</th>
											                                    </tr>
											                                </thead>
											                                <tbody>
											                                    {assign var="msplits" value=","|explode:$result['grp_release_id']}
											                                    {assign var="mguid" value=","|explode:$result['grp_release_guid']}
											                                    {assign var="mnfo" value=","|explode:$result['grp_release_nfoid']}
											                                    {assign var="mgrp" value=","|explode:$result['grp_release_grpname']}
											                                    {assign var="mname" value="#"|explode:$result['grp_release_name']}
											                                    {assign var="mpostdate" value=","|explode:$result['grp_release_postdate']}
											                                    {assign var="msize" value=","|explode:$result['grp_release_size']}
											                                    {assign var="mtotalparts" value=","|explode:$result['grp_release_totalparts']}
											                                    {assign var="mcomments" value=","|explode:$result['grp_release_comments']}
											                                    {assign var="mgrabs" value=","|explode:$result['grp_release_grabs']}
											                                    {assign var="mpass" value=","|explode:$result['grp_release_password']}
											                                    {assign var="minnerfiles" value=","|explode:$result['grp_rarinnerfilecount']}
											                                    {assign var="mhaspreview" value=","|explode:$result['grp_haspreview']}
											                                    {assign var="mcatname" value=","|explode:$result['grp_release_catname']}

											                                    {foreach $msplits as $m}
											                                        <tr id="guid{$mguid[$m@index]}">
											                                            <td class="text-center">
											                                                <div class="form-check">
											                                                    <input id="guid{$mguid[$m@index]}" type="checkbox" class="form-check-input flat" value="{$mguid[$m@index]}"/>
											                                                    <label class="visually-hidden" for="guid{$mguid[$m@index]}">Select release</label>
											                                                </div>
											                                            </td>
											                                            <td>
											                                                <a class="text-decoration-none fw-medium" title="View details" href="{{url("/details/{$mguid[$m@index]}")}}">
											                                                    {$mname[$m@index]|escape:"htmlall"|replace:".":" "}
											                                                </a>
											                                                <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
											                                                    <div class="d-flex align-items-center">
                                                                                                    <span class="badge bg-secondary text-white">
                                                                                                        <i class="fa fa-download me-1"></i>{$mgrabs[$m@index]} Grab{if $mgrabs[$m@index] != 1}s{/if}</span>
											                                                    </div>
											                                                    {if isset($mnfo[$m@index]) && $mnfo[$m@index] > 0}
											                                                        <a href="#" class="badge bg-secondary rounded-pill text-white nfo-modal-link" data-bs-toggle="modal" data-bs-target="#nfoModal" data-guid="{$mguid[$m@index]}">
											                                                            <i class="far fa-file text-white me-1"></i>NFO
											                                                        </a>
											                                                    {/if}
											                                                    {if $mpass[$m@index] == 2 || $mpass[$m@index] == 1}
											                                                        <span class="badge bg-warning text-dark rounded-pill">
											                                                            <i class="fa fa-lock me-1"></i>Password
											                                                        </span>
											                                                    {/if}
											                                                </div>
											                                            </td>
											                                            <td>
											                                                <div class="d-flex align-items-center">
                                                                                                <span class="badge bg-secondary text-white rounded-pill"><i class="fa fa-folder-open me-1"></i>{$mcatname[$m@index]}</span>
											                                                </div>
											                                            </td>
											                                            <td>
											                                                <div class="d-flex align-items-center">
                                                                                                <i class="fa fa-clock-o text-muted me-2"></i>
											                                                    <span class="fw-medium" title="{$mpostdate[$m@index]}">{$mpostdate[$m@index]|timeago}</span>
											                                                </div>
											                                            </td>
											                                            <td class="text-center">
											                                                <div class="d-flex align-items-center justify-content-center">
											                                                    <i class="fa fa-hdd-o text-muted me-2"></i>
											                                                    <span class="fw-medium">{$msize[$m@index]|filesize}</span>
											                                                </div>
											                                            </td>
											                                            <td class="text-end">
											                                                <div class="d-flex justify-content-end gap-2">
											                                                    <a href="{{url("/getnzb?id={$mguid[$m@index]}")}}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Download NZB">
											                                                        <i class="fa fa-cloud-download"></i>
											                                                    </a>
											                                                    <a href="{{url("/details/{$mguid[$m@index]}/#comments")}}" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Comments">
											                                                        <i class="fa fa-comments-o"></i>
											                                                    </a>
											                                                    <a href="#" class="btn btn-sm btn-outline-secondary add-to-cart" data-bs-toggle="tooltip" data-bs-placement="top" title="Send to my download basket" data-id="{$mguid[$m@index]}">
											                                                        <i id="guid{$mguid[$m@index]}" class="icon_cart fa fa-shopping-basket"></i>
											                                                    </a>
											                                                </div>
											                                            </td>
											                                        </tr>
											                                    {/foreach}
											                                </tbody>
											                            </table>
											                        </div>
											                    </div>

											                    {if $results|@count > 10}
											                        <div class="card-footer bg-light">
											                            <div class="row align-items-center">
											                                <div class="col-md-8 mb-3 mb-md-0">
											                                    <div class="d-flex align-items-center flex-wrap">
											                                        {if isset($section) && $section != ''}
											                                            <span class="me-3">View:
											                                                <a href="{{url("/{$section}?t={$category}")}}">Covers</a> |
											                                                <strong>List</strong>
											                                            </span>
											                                        {/if}
											                                        <div class="d-flex align-items-center">
											                                            <small class="me-2">With Selected:</small>
											                                            <div class="btn-group">
											                                                <button type="button" class="nzb_multi_operations_download btn btn-sm btn-success"
											                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Download NZBs">
											                                                    <i class="fa fa-cloud-download"></i>
											                                                </button>
											                                                <button type="button" class="nzb_multi_operations_cart btn btn-sm btn-info"
											                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Send to my Download Basket">
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
											                                <div class="col-md-4 d-flex justify-content-md-end">
											                                    {$pager}
											                                </div>
											                            </div>
											                        </div>
											                    {/if}
											                </div>
											                {{Form::close()}}
											            {/if}
											        {/foreach}
											    </div>
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

											<style>
											.badge-link {
											    transition: all 0.2s ease;
											}
											.badge-link:hover {
											    transform: translateY(-2px);
											    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
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
											        const nfoModalLinks = document.querySelectorAll('.nfo-modal-link');

											        nfoModalLinks.forEach(link => {
											            link.addEventListener('click', function(e) {
											                e.preventDefault();
											                const guid = this.getAttribute('data-guid');
											                const modal = new bootstrap.Modal(nfoModal);
											                modal.show();

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
											        });

											        // Add to cart functionality
											        const addToCartButtons = document.querySelectorAll('.add-to-cart');
											        addToCartButtons.forEach(button => {
											            button.addEventListener('click', function(e) {
											                e.preventDefault();
											                const id = this.getAttribute('data-id');
											                // Implement cart functionality here

											                // Animation effect
											                const icon = this.querySelector('.icon_cart');
											                icon.classList.add('shake-animation');
											                setTimeout(() => {
											                    icon.classList.remove('shake-animation');
											                }, 500);
											            });
											        });
											    });
											{/literal}
											</script>
