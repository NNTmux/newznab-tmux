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
							                                                <span>View: <strong>Covers</strong> | <a href="{{url("/browse/Movies/{$categorytitle}")}}">List</a></span>
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

							                                <!-- Movie Grid -->
							                                <div class="row g-4">
							                                    {foreach $results as $result}
							                                        {if isset($result['category_name'])}
							                                            {assign var="catnamesplit" value=$result['category_split']}
							                                        {/if}

							                                        <div class="col-md-6 mb-4">
							                                            <div class="card h-100 shadow-sm hover-shadow">
							                                                <div class="card-body">
							                                                    <div class="row">
							                                                        <!-- Movie Poster Column -->
							                                                        <div class="col-md-4 mb-3 mb-md-0">
							                                                            <div class="mb-2">
							                                                                {foreach $result['languages'] as $movielanguage}
							                                                                    {release_flag($movielanguage, browse)}
							                                                                {/foreach}
							                                                            </div>

							                                                            {if !empty($result['release_ids'][0])}
							                                                                <div class="position-relative mb-2">
							                                                                    <a href="{{url("/Movies?imdb={$result['imdbid']}")}}">
							                                                                        <img class="cover img-fluid rounded shadow"
							                                                                                 src="{{asset("/assets/images/placeholder.png")}}"
							                                                                                 data-src="{if isset($result['cover']) && $result['cover'] == 1}{{url("/covers/movies/{$result['imdbid']}-cover.jpg")}}{else}{{asset("/assets/images/no-cover.png")}}{/if}"
							                                                                                 alt="{$result['title']|escape:"htmlall"}"
							                                                                                 loading="lazy"
							                                                                                 onload="this.style.opacity='1'"
							                                                                                 style="opacity:0;transition:opacity 0.3s"/>

							                                                                        {if !empty($result['release_failed'][0])}
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
							                                                                    <a target="_blank" href="{$site->dereferrer_link}http://www.imdb.com/title/tt{$result['imdbid']}/"
							                                                                       title="View IMDB page" class="badge bg-secondary text-white text-decoration-none badge-link" rel="imdb">
							                                                                       <i class="fa fa-film me-1"></i>IMDB
							                                                                    </a>

							                                                                    <a target="_blank" href="{$site->dereferrer_link}http://trakt.tv/search/imdb/tt{$result['imdbid']}/"
							                                                                       title="View Trakt page" class="badge bg-secondary text-white text-decoration-none badge-link" rel="trakt">
							                                                                       <i class="fa fa-tv me-1"></i>TRAKT
							                                                                    </a>

							                                                                    {if (!empty($result['tmdbid']))}
							                                                                        <a class="badge bg-secondary text-white text-decoration-none badge-link" rel="tmdb" target="_blank"
							                                                                           href="{$site->dereferrer_link}http://www.themoviedb.org/movie/{$result['tmdbid']}"
							                                                                           title="View TheMovieDB page">
							                                                                           <i class="fa fa-database me-1"></i>TMDB
							                                                                        </a>
							                                                                    {/if}

							                                                                    {if $result['release_nfoids'][0] > 0}
							                                                                        <a href="{{url("/nfo/{$result['release_guids'][0]}")}}" data-guid="{$result['release_guids'][0]}"
							                                                                           title="View NFO" class="modal_nfo badge bg-secondary text-white text-decoration-none badge-link" data-bs-toggle="modal" data-bs-target="#nfoModal">
							                                                                           <i class="fa fa-file-text me-1"></i>NFO
							                                                                        </a>
							                                                                    {/if}

							                                                                    <a class="badge bg-secondary text-white text-decoration-none badge-link"
							                                                                       href="{{url("/browse/group?g={$result['release_groups'][0]}")}}"
							                                                                       title="Browse releases in {$result['release_groups'][0]|replace:"alt.binaries":"a.b"}">
							                                                                       <i class="fa fa-users me-1"></i>Group
							                                                                    </a>

							                                                                    <a class="badge bg-success text-white text-decoration-none badge-link"
							                                                                       href="{{url("/mymovies?id=add&imdb={$result['imdbid']}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}"
							                                                                       title="Add to My Movies">
							                                                                       <i class="fa fa-plus-circle me-1"></i>Add
							                                                                    </a>
							                                                                </div>
							                                                            {/if}
							                                                        </div>

							                                                        <!-- Movie Info Column -->
							                                                        <div class="col-md-8">
							                                                            {if !empty($result['release_ids'][0])}
							                                                                <!-- Movie Title -->
							                                                                <h5 class="mb-2">
							                                                                    <a class="text-decoration-none title-link" href="{{url("/Movies?imdb={$result['imdbid']}")}}">
							                                                                        {$result['title']|escape:"htmlall"}
							                                                                        <span class="year-badge badge bg-primary ms-1">{$result['year']}</span>
							                                                                        {if $result['rating'] != ''}
							                                                                            <span class="rating-badge badge bg-warning text-dark ms-1">
							                                                                                <i class="fa fa-star me-1"></i>{$result['rating']}/10
							                                                                            </span>
							                                                                        {/if}
							                                                                    </a>
							                                                                </h5>

							                                                                <!-- Movie Details -->
							                                                                <div class="mb-3 small">
							                                                                    {if $result['genre'] != ''}
							                                                                        <div class="d-flex align-items-center mb-1">
							                                                                            <i class="fa fa-tags text-muted me-2"></i>
							                                                                            <div class="genre-tags">
							                                                                                {foreach explode(", ", $result['genre']) as $genre}
							                                                                                    <span class="badge bg-light text-dark me-1">{$genre}</span>
							                                                                                {/foreach}
							                                                                            </div>
							                                                                        </div>
							                                                                    {/if}

							                                                                    {if $result['plot'] != ''}
							                                                                        <div class="mb-1 text-truncate-3 plot-text"><i class="fa fa-quote-left text-muted me-2"></i>{$result['plot']}</div>
							                                                                    {/if}

							                                                                    {if $result['director'] != ''}
							                                                                        <div class="d-flex align-items-center mb-1">
							                                                                            <i class="fa fa-video-camera text-muted me-2"></i>
							                                                                            <span><strong>Director:</strong> {$result['director']}</span>
							                                                                        </div>
							                                                                    {/if}

							                                                                    {if $result['actors'] != ''}
							                                                                        <div class="d-flex align-items-center mb-1">
							                                                                            <i class="fa fa-users text-muted me-2"></i>
							                                                                            <span><strong>Starring:</strong> {$result['actors']}</span>
							                                                                        </div>
							                                                                    {/if}
							                                                                </div>

							                                                                <!-- Release Info -->
							                                                                <div class="card bg-light mb-3">
							                                                                    <div class="card-body p-3">
							                                                                        <div class="d-flex justify-content-between align-items-start mb-2">
							                                                                            <div>
							                                                                                <label class="me-2 mb-0">
							                                                                                    <input type="checkbox" class="form-check-input" value="{$result['release_guids'][0]}" id="chksingle"/>
							                                                                                </label>

							                                                                                {if isset($result['category_split'][0])}
							                                                                                    <span class="badge bg-primary"><i class="fa fa-folder me-1"></i>{$result['category_split'][0]}</span>
							                                                                                {/if}

							                                                                                {if isset($result['category_split'][1])}
							                                                                                    <span class="badge bg-danger"><i class="fa fa-folder-open me-1"></i>{$result['category_split'][1]}</span>
							                                                                                {/if}

							                                                                                {if $result['rtrating'] != ''}
							                                                                                    <span class="badge bg-info">
							                                                                                        <i class="fa fa-percent me-1"></i>RT: {$result['rtrating']}
							                                                                                    </span>
							                                                                                {/if}
							                                                                            </div>

							                                                                            <div class="d-flex align-items-center">
							                                                                                <i class="fa fa-hdd-o text-muted me-2"></i>
							                                                                                <span class="badge bg-secondary">{$result['release_sizes'][0]|filesize}</span>
							                                                                            </div>
							                                                                        </div>

							                                                                        <div class="d-flex align-items-center text-muted mb-2">
							                                                                            <i class="fa fa-clock-o me-2"></i>
							                                                                            <span>Posted {$result['release_postdates'][0]|timeago} ago</span>
							                                                                        </div>

							                                                                        <!-- Release Name -->
							                                                                        <div class="text-truncate mb-3">
							                                                                            <a href="{{url("/details/{$result['release_guids'][0]}")}}" class="text-muted text-decoration-none">
							                                                                                <i class="fa fa-file-archive-o text-muted me-2"></i>
							                                                                                {$result['release_names'][0]|escape:"htmlall"}
							                                                                            </a>
							                                                                        </div>

							                                                                        <!-- Action Buttons -->
							                                                                        <div class="d-flex gap-2">
							                                                                            <a class="btn btn-sm btn-primary"
							                                                                               data-bs-toggle="tooltip" data-bs-placement="top" title="Download NZB"
							                                                                               href="{{url("/getnzb?id={$result['release_guids'][0]}")}}">
							                                                                                <i class="fa fa-cloud-download me-1"></i>
							                                                                                <span class="badge bg-light text-dark">{$result['release_grabs'][0]}</span>
							                                                                            </a>

							                                                                            <a class="btn btn-sm btn-outline-secondary"
							                                                                               href="{{url("/details/{$result['release_guids'][0]}/#comments")}}">
							                                                                                <i class="fa fa-comment-o me-1"></i>
							                                                                                <span class="badge bg-light text-dark">{$result['release_comments'][0]}</span>
							                                                                            </a>

							                                                                            <a href="#" class="btn btn-sm btn-outline-info add-to-cart"
							                                                                               data-bs-toggle="tooltip" data-bs-placement="top" title="Send to my download basket">
							                                                                                <i id="guid{$result['release_guids'][0]}" class="icon_cart fa fa-shopping-basket"></i>
							                                                                            </a>

							                                                                            {if !empty($result['release_failed'][0])}
							                                                                                <span class="btn btn-sm btn-outline-danger">
							                                                                                    <i class="fa fa-exclamation-triangle me-1"></i>
							                                                                                    {$result['release_failed'][0]} Failed
							                                                                                </span>
							                                                                            {/if}
							                                                                        </div>
							                                                                    </div>
							                                                                </div>
							                                                            {/if}
							                                                        </div>
							                                                    </div>
							                                                </div>
							                                            </div>
							                                        </div>
							                                    {/foreach}
                                </div>

                                <!-- Bottom Controls -->
                                <hr>
                                <div class="row mt-4">
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <span>View: <strong>Covers</strong> | <a href="{{url("/browse/Movies/{$categorytitle}")}}">List</a></span>
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

                    /* Optimized hover effects - reduce GPU usage */
                    .hover-shadow {
                        transition: box-shadow 0.2s ease-out;
                    }
                    .hover-shadow:hover {
                        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
                    }

                    /* Simplified badge animations */
                    .badge-link {
                        transition: transform 0.15s ease-out;
                    }
                    .badge-link:hover {
                        transform: translateY(-1px);
                    }

                    /* Optimized cover image loading */
                    .cover {
                        background-color: #f8f9fa;
                        min-height: 200px;
                        object-fit: cover;
                    }

                    /* Disable expensive animations on mobile */
                    @media (max-width: 768px) {
                        .hover-shadow:hover {
                            box-shadow: none !important;
                        }
                        .badge-link:hover {
                            transform: none;
                        }
                        .add-to-cart:hover .icon_cart {
                            animation: none;
                        }
                    }

                    /* Simplified title animations */
                    .title-link .year-badge,
                    .title-link .rating-badge {
                        transition: background-color 0.15s ease-out;
                    }

                    /* Remove shake animation - too expensive */
                    .add-to-cart {
                        transition: opacity 0.15s ease-out;
                    }
                    .add-to-cart:hover {
                        opacity: 0.8;
                    }

                    /* Optimize genre tags */
                    .genre-tags .badge {
                        transition: background-color 0.1s ease-out;
                    }
                    .genre-tags .badge:hover {
                        background-color: #e9ecef !important;
                    }

                    /* Loading spinner optimization */
                    .spinner-border {
                        width: 2rem;
                        height: 2rem;
                    }
                    </style>

                    <script>
                    {literal}
                        // Lazy loading implementation
                        document.addEventListener('DOMContentLoaded', function() {
                            // Intersection Observer for lazy loading
                            const imageObserver = new IntersectionObserver((entries, observer) => {
                                entries.forEach(entry => {
                                    if (entry.isIntersecting) {
                                        const img = entry.target;
                                        const src = img.getAttribute('data-src');
                                        if (src) {
                                            img.src = src;
                                            img.removeAttribute('data-src');
                                            observer.unobserve(img);
                                        }
                                    }
                                });
                            }, {
                                rootMargin: '50px 0px',
                                threshold: 0.01
                            });

                            // Observe all images with data-src
                            document.querySelectorAll('img[data-src]').forEach(img => {
                                imageObserver.observe(img);
                            });

                            // Optimized NFO Modal loading
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

                                    // Use fetch with abort controller for better performance
                                    const controller = new AbortController();
                                    const timeout = setTimeout(() => controller.abort(), 10000); // 10s timeout

                                    fetch(`/nfo/${guid}`, {
                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest'
                                        },
                                        signal: controller.signal
                                    })
                                    .then(response => {
                                        clearTimeout(timeout);
                                        if (!response.ok) throw new Error('Network response was not ok');
                                        return response.text();
                                    })
                                    .then(html => {
                                        const parser = new DOMParser();
                                        const doc = parser.parseFromString(html, 'text/html');

                                        let nfoText = '';
                                        const preElement = doc.querySelector('pre');

                                        if (preElement) {
                                            nfoText = preElement.textContent;
                                        } else {
                                            const mainContent = doc.querySelector('.card-body, .main-content, .content-area, main');
                                            nfoText = mainContent ? mainContent.textContent : 'No NFO content found';
                                        }

                                        loading.style.display = 'none';
                                        contentElement.classList.remove('d-none');
                                        contentElement.textContent = nfoText.trim();
                                    })
                                    .catch(error => {
                                        clearTimeout(timeout);
                                        console.error('Error fetching NFO content:', error);
                                        loading.style.display = 'none';
                                        contentElement.classList.remove('d-none');
                                        contentElement.textContent = 'Error loading NFO content';
                                    });
                                });
                            }

                            // Debounced tooltip initialization to reduce overhead
                            let tooltipTimeout;
                            function initTooltips() {
                                clearTimeout(tooltipTimeout);
                                tooltipTimeout = setTimeout(() => {
                                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                                        return new bootstrap.Tooltip(tooltipTriggerEl, {
                                            delay: { show: 500, hide: 100 }
                                        });
                                    });
                                }, 100);
                            }

                            initTooltips();
                        });
                    {/literal}
                    </script>
