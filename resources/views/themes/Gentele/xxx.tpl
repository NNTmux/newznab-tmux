<div class="header mb-3">
							                <div class="breadcrumb-wrapper">
							                    <nav aria-label="breadcrumb">
							                        <ol class="breadcrumb">
							                            <li class="breadcrumb-item"><a href="{{url({$site->home_link})}}">Home</a></li>
							                            <li class="breadcrumb-item">{if !empty({$catname->parent->title})}<a href="{{url("browse/{$catname->parent->title}")}}">{$catname->parent->title}</a>{else}<a href="{{url("/browse/{$catname->title}")}}">{$catname->title}</a>{/if}</li>
							                            <li class="breadcrumb-item active">{if !empty({$catname->parent->title})}<a href="{{url("/browse/{$catname->title}")}}">{$catname->title}</a>{else}All{/if}</li>
							                        </ol>
							                    </nav>
							                </div>
							            </div>

							            <div class="card shadow-sm mb-4">
							                <div class="card-header">
							                    {include file='search-filter.tpl'}
							                </div>
							            </div>

							            {{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
							            <div class="row">
							                <div class="col-12">
							                    <div class="card shadow-sm">
							                        <div class="card-body">
							                            <div class="d-flex justify-content-between align-items-center mb-3">
							                                <div class="nzb_multi_operations">
							                                    <div class="mb-2">
							                                        View: <strong>Covers</strong> | <a href="{{url("/browse/XXX/{$categorytitle}")}}">List</a>
							                                    </div>
							                                    <div class="d-flex align-items-center">
							                                        <span class="me-2">With Selected:</span>
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
							                                <div class="d-none d-md-block">
							                                    {$results->onEachSide(5)->links()}
							                                </div>
							                            </div>

							                            <hr>

							                            <!-- Items Grid -->
							                            <div class="row g-3">
							                                {foreach $resultsadd as $result}
							                                    {if isset($result->category_name)}
							                                        {assign var="catnamesplit" value=">"|explode:$result->category_name}
							                                    {/if}

							                                    {assign var="msplits" value=","|explode:$result->grp_release_id}
							                                    {assign var="mguid" value=","|explode:$result->grp_release_guid}
							                                    {assign var="mnfo" value=","|explode:$result->grp_release_nfoid}
							                                    {assign var="mgrp" value=","|explode:$result->grp_release_grpname}
							                                    {assign var="mname" value="#"|explode:$result->grp_release_name}
							                                    {assign var="mpostdate" value=","|explode:$result->grp_release_postdate}
							                                    {assign var="msize" value=","|explode:$result->grp_release_size}
							                                    {assign var="mtotalparts" value=","|explode:$result->grp_release_totalparts}
							                                    {assign var="mcomments" value=","|explode:$result->grp_release_comments}
							                                    {assign var="mgrabs" value=","|explode:$result->grp_release_grabs}
							                                    {assign var="mfailed" value=","|explode:$result->grp_release_failed}
							                                    {assign var="mpass" value=","|explode:$result->grp_release_password}
							                                    {assign var="minnerfiles" value=","|explode:$result->grp_rarinnerfilecount}
							                                    {assign var="mhaspreview" value=","|explode:$result->grp_haspreview}

							                                    <div class="col-lg-6 mb-3">
							                                        <div class="card shadow-sm h-100">
							                                            <div class="card-body p-3">
							                                                <div class="row">
							                                                    <div class="col-md-3 mb-3 mb-md-0 text-center">
							                                                        {foreach $msplits as $m}
							                                                            {if $m@first}
							                                                                <a href="{{url("/XXX?id={$result->id}")}}" class="d-block">
							                                                                    <img class="cover shadow-sm img-fluid rounded mb-2"
							                                                                         src="{if $result->cover == 1}{{url("/covers/xxx/{$result->id}-cover.jpg")}}{else}{{asset("/images/no-cover.png")}}"{/if}"
							                                                                         alt="{$result->title|escape:"htmlall"}"/>
							                                                                    {if !empty($mfailed[$m@index])}
							                                                                        <span class="badge bg-danger position-absolute top-0 end-0 m-2">
							                                                                            <i class="fa fa-exclamation-circle"
							                                                                               title="This release has failed to download for some users"></i>
							                                                                        </span>
							                                                                    {/if}
							                                                                </a>

							                                                                {if $result->classused == "ade"}
							                                                                    <a target="_blank" href="{$site->dereferrer_link}{$result->directurl}"
							                                                                       title="View AdultdvdEmpire page" class="d-block mb-2">
							                                                                        <img src="{{asset("/assets/images/icons/ade.png")}}" class="img-fluid" style="max-width: 100px">
							                                                                    </a>
							                                                                {elseif $result->classused == "adm"}
							                                                                    <a target="_blank" href="{$site->dereferrer_link}{$result->directurl}"
							                                                                       title="View AdultDVDMarketplace page" class="d-block mb-2">
							                                                                        <img src="{{asset("/assets/images/icons/adm.png")}}" class="img-fluid" style="max-width: 100px">
							                                                                    </a>
							                                                                {elseif $result->classused == "aebn"}
							                                                                    <a target="_blank" href="{$site->dereferrer_link}{$result->directurl}"
							                                                                       title="View AEBN page" class="d-block mb-2">
							                                                                        <img src="{{asset("/assets/images/icons/aebn.png")}}" class="img-fluid" style="max-width: 100px">
							                                                                    </a>
							                                                                {elseif $result->classused == "hotm"}
							                                                                    <a target="_blank" href="{$site->dereferrer_link}{$result->directurl}"
							                                                                       title="View HotMovies page" class="d-block mb-2">
							                                                                        <img src="{{asset("/assets/images/icons/hotmovies.png")}}" class="img-fluid" style="max-width: 100px">
							                                                                    </a>
							                                                                {elseif $result->classused == "pop"}
							                                                                    <a target="_blank" href="{$site->dereferrer_link}{$result->directurl}"
							                                                                       title="View Popporn page" class="d-block mb-2">
							                                                                        <img src="{{asset("/assets/images/icons/popporn.png")}}" class="img-fluid" style="max-width: 100px">
							                                                                    </a>
							                                                                {/if}

							                                                                <div class="d-flex flex-wrap gap-1 justify-content-center mt-2">
							                                                                    {if $mnfo[$m@index] > 0}
							                                                                        <a href="{{url("/nfo/{$mguid[$m@index]}")}}"
							                                                                           title="View NFO"
							                                                                           class="badge rounded-pill bg-secondary text-white"
							                                                                           data-bs-toggle="modal"
							                                                                           data-bs-target="#nfoModal">
							                                                                            NFO
							                                                                        </a>
							                                                                    {/if}
							                                                                    <a class="badge rounded-pill bg-secondary text-white"
							                                                                       href="{{url("/browse/group?g={$result->grp_release_grpname}")}}"
							                                                                       title="Browse releases in {$result->grp_release_grpname|replace:"alt.binaries":"a.b"}">
							                                                                        Group
							                                                                    </a>
							                                                                </div>
							                                                            {/if}
							                                                        {/foreach}
							                                                    </div>

							                                                    <div class="col-md-9">
							                                                        {foreach $msplits as $m}
							                                                            {if $m@first}
							                                                                <div class="mb-2">
							                                                                    <h5 class="release-title fw-bold">
							                                                                        <a class="text-decoration-none" href="{{url("/XXX?id={$result->id}")}}">
							                                                                            {$result->title|escape:"htmlall"}
							                                                                        </a>
							                                                                    </h5>
							                                                                </div>

							                                                                <div class="mb-3">
							                                                                    <div class="d-flex flex-wrap gap-1 mb-2 align-items-center">
							                                                                        <label class="me-1 mb-0">
							                                                                            <input type="checkbox" class="form-check-input" value="{$mguid[$m@index]}" id="chksingle"/>
							                                                                        </label>
							                                                                        {if isset($catsplit[0])}
							                                                                            <span class="badge rounded-pill bg-primary text-white">{$catsplit[0]}</span>
							                                                                        {/if}
							                                                                        {if isset($catsplit[1])}
							                                                                            <span class="badge rounded-pill bg-danger text-white">{$catsplit[1]}</span>
							                                                                        {/if}
							                                                                        <span class="badge rounded-pill bg-secondary text-white">{$msize[$m@index]|fsize_format:"MB"}</span>
							                                                                        <span class="badge rounded-pill bg-secondary text-white">Posted {$mpostdate[$m@index]|timeago} ago</span>
							                                                                    </div>

							                                                                    {if $result->genre != ''}
							                                                                        <div class="release-subtitle mb-1 text-muted small">
							                                                                            <strong>Genre:</strong> {$result->genre}
							                                                                        </div>
							                                                                    {/if}

							                                                                    {if $result->plot != ''}
							                                                                        <div class="release-subtitle mb-1 text-muted small">
							                                                                            <strong>Plot:</strong> {$result->plot}
							                                                                        </div>
							                                                                    {/if}

							                                                                    {if $result->actors != ''}
							                                                                        <div class="release-subtitle mb-2 text-muted small">
							                                                                            <strong>Cast:</strong> {$result->actors}
							                                                                        </div>
							                                                                    {/if}

							                                                                    <div class="release-name text-muted mb-3 small">
							                                                                        <a href="{{url("/details/{$mguid[$m@index]}")}}" class="text-decoration-none text-truncate d-inline-block" style="max-width: 100%;">
							                                                                            {$mname[$m@index]|escape:"htmlall"}
							                                                                        </a>
							                                                                    </div>

							                                                                    <div class="d-flex flex-wrap gap-2">
							                                                                        <a role="button" class="btn btn-sm btn-outline-secondary"
							                                                                           data-bs-toggle="tooltip" title="Download NZB"
							                                                                           href="{{url("/getnzb?id={$mguid[$m@index]}")}}">
							                                                                            <i class="fa fa-cloud-download"></i>
							                                                                            <span class="badge bg-success text-white ms-1">{$mgrabs[$m@index]}</span>
							                                                                        </a>

							                                                                        <a role="button" class="btn btn-sm btn-outline-secondary"
							                                                                           href="{{url("/details/{$mguid[$m@index]}/#comments")}}">
							                                                                            <i class="fa fa-comment-o"></i>
							                                                                            <span class="badge bg-info text-white ms-1">{$mcomments[$m@index]}</span>
							                                                                        </a>

                                                                                                    <a href="#" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Send to my download basket">
                                                                                                        <i id="guid{$mguid[$m@index]}" class="icon_cart fa fa-shopping-basket"></i>
                                                                                                    </a>

							                                                                        {if !empty($mfailed[$m@index])}
							                                                                            <span class="btn btn-sm btn-outline-danger"
							                                                                                  title="This release has failed to download for some users">
							                                                                                <i class="fa fa-thumbs-up me-1"></i>{$mgrabs[$m@index]} /
							                                                                                <i class="fa fa-thumbs-down mx-1"></i>{$mfailed[$m@index]}
							                                                                            </span>
							                                                                        {/if}
							                                                                    </div>
							                                                                </div>
							                                                            {/if}
							                                                        {/foreach}
							                                                    </div>
							                                                </div>
							                                            </div>
							                                        </div>
							                                    </div>
							                                {/foreach}
							                            </div>

							                            <hr>

							                            <div class="d-flex justify-content-between align-items-center mt-3">
							                                <div class="nzb_multi_operations">
							                                    <div class="mb-2">
							                                        View: <strong>Covers</strong> | <a href="{{url("/browse/XXX/{$categorytitle}")}}">List</a>
							                                    </div>
							                                    <div class="d-flex align-items-center">
							                                        <span class="me-2">With Selected:</span>
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
							                                <div>
							                                    {$results->onEachSide(5)->links()}
							                                </div>
							                            </div>
							                        </div>
							                    </div>
							                </div>
							            </div>
							            {{Form::close()}}

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
							                // NFO Modal content loading
							                document.addEventListener('DOMContentLoaded', function() {
							                    const nfoModal = document.getElementById('nfoModal');
							                    if (nfoModal) {
							                        nfoModal.addEventListener('show.bs.modal', function(event) {
							                            const button = event.relatedTarget;
							                            const nfoUrl = button.getAttribute('href');
							                            const loading = nfoModal.querySelector('.nfo-loading');
							                            const contentElement = document.getElementById('nfoContent');

							                            // Reset and show loading state
							                            loading.style.display = 'block';
							                            contentElement.classList.add('d-none');

							                            // Fetch the NFO content via AJAX
							                            fetch(nfoUrl, {
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
							                                    nfoText = preElement.textContent;
							                                } else {
							                                    const mainContent = doc.querySelector('.card-body, .main-content, .content-area, main');
							                                    if (mainContent) {
							                                        nfoText = mainContent.textContent;
							                                    } else {
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

							                    // Enable all tooltips
							                    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
							                    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
							                });

                                            // Add cart functionality
                                            document.addEventListener('DOMContentLoaded', function() {
                                                document.querySelectorAll('.cart-add-button').forEach(button => {
                                                    button.addEventListener('click', function(e) {
                                                        e.preventDefault();
                                                        const releaseId = this.getAttribute('data-id');
                                                        const originalIcon = this.innerHTML;

                                                        // Show loading state
                                                        this.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

                                                        fetch(`/cart/add?id=${releaseId}`, {
                                                            headers: {
                                                                'X-Requested-With': 'XMLHttpRequest'
                                                            }
                                                        })
                                                        .then(response => {
                                                            if (response.ok) {
                                                                // Success
                                                                this.innerHTML = '<i class="fa fa-check"></i>';
                                                                setTimeout(() => {
                                                                    this.innerHTML = originalIcon;
                                                                }, 1000);

                                                                // Update cart count if needed
                                                                const cartCountEl = document.querySelector('.cart-count');
                                                                if (cartCountEl) {
                                                                    const currentCount = parseInt(cartCountEl.textContent || '0');
                                                                    cartCountEl.textContent = currentCount + 1;
                                                                }
                                                            } else {
                                                                // Error
                                                                this.innerHTML = '<i class="fa fa-times"></i>';
                                                                setTimeout(() => {
                                                                    this.innerHTML = originalIcon;
                                                                }, 1000);
                                                            }
                                                        })
                                                        .catch(error => {
                                                            console.error('Error adding to cart:', error);
                                                            // Restore original icon on error
                                                            this.innerHTML = originalIcon;
                                                        });
                                                    });
                                                });
                                            });
							            {/literal}
							            </script>
