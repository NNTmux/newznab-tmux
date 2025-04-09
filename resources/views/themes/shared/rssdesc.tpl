<div class="card card-default shadow-sm mb-4">
		    <div class="card-header bg-light">
		        <h3 class="mb-0"><i class="fa fa-rss me-2 text-warning"></i>{$title}</h3>
		    </div>
		    <div class="card-body">
		        <p class="lead">
		            Here you can find RSS feeds for various categories and content types. These feeds provide either descriptions or
		            direct NZB downloads based on your preferences.
		        </p>

		        {if $loggedin=="true"}
		            <div class="card card-body bg-light mb-4">
		                <h4 class="mb-3"><i class="fa fa-key me-2 text-secondary"></i>Your API Token</h4>
		                <div class="input-group">
		                    <input type="text" class="form-control font-monospace" value="api_token={$userdata.api_token}" readonly id="apiTokenInput">
		                    <button class="btn btn-outline-secondary" type="button" id="copyApiToken" title="Copy to clipboard">
		                        <i class="fa fa-copy"></i>
		                    </button>
		                </div>
		            </div>
		        {/if}

		        <h4 class="mb-3"><i class="fa fa-cog me-2 text-secondary"></i>RSS Configuration Options</h4>
		        <div class="table-responsive">
		            <table class="table table-striped table-bordered">
		                <thead class="table-light">
		                    <tr>
		                        <th>Parameter</th>
		                        <th>Description</th>
		                        <th>Example</th>
		                    </tr>
		                </thead>
		                <tbody>
		                    <tr>
		                        <td><code>api_token</code></td>
		                        <td>Add this to your feed URL to allow NZB downloads without logging in</td>
		                        <td><code>&amp;api_token={$userdata.api_token}</code></td>
		                    </tr>
		                    <tr>
		                        <td><code>del=1</code></td>
		                        <td>Remove NZB from your cart after download</td>
		                        <td><code>&amp;del=1</code></td>
		                    </tr>
		                    <tr>
		                        <td><code>dl=1</code></td>
		                        <td>Change the default link to download an NZB</td>
		                        <td><code>&amp;dl=1</code></td>
		                    </tr>
		                    <tr>
		                        <td><code>num=50</code></td>
		                        <td>Change the number of results returned (default: 25, max: 100)</td>
		                        <td><code>&amp;num=50</code></td>
		                    </tr>
		                    <tr>
		                        <td><code>airdate=20</code></td>
		                        <td>Return TV shows only aired in the last x days (default: all)</td>
		                        <td><code>&amp;airdate=20</code></td>
		                    </tr>
		                </tbody>
		            </table>
		        </div>

		        <div class="alert alert-info mt-4">
		            <i class="fa fa-info-circle me-2"></i>
		            Most NZB clients which support NZB RSS feeds will appreciate the full URL, with download link and your user token.
		            The feeds include additional attributes to help provide better filtering in your NZB client, such as size, group,
		            and categorization.
		        </div>

		        <div class="alert alert-primary">
		            <i class="fa fa-lightbulb-o me-2"></i>
		            <strong>Pro Tip:</strong> If you want to chain multiple categories together or do more advanced searching, use the
		            <a href="{{url("/apihelp")}}" class="alert-link">API</a>, which returns its data in an RSS-compatible format.
		        </div>

		        <h4 class="mt-5 mb-3"><i class="fa fa-list me-2 text-secondary"></i>Available Feeds</h4>

		        <!-- General Feeds -->
		        <div class="card mb-4">
		            <div class="card-header bg-light">
		                <h5 class="mb-0"><i class="fa fa-globe me-2 text-primary"></i>General Feeds</h5>
		            </div>
		            <div class="card-body p-0">
		                <div class="list-group list-group-flush">
		                    <div class="list-group-item">
		                        <div class="d-flex justify-content-between align-items-center mb-2">
		                            <strong><i class="fa fa-rss me-2 text-warning"></i>Full Site Feed</strong>
		                            <a href="{{url("/rss/full-feed?dl=1&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary" target="_blank">
		                                <i class="fa fa-external-link me-1"></i>Open Feed
		                            </a>
		                        </div>
		                        <div class="input-group">
		                            <input type="text" class="form-control form-control-sm font-monospace" value="{{url("/rss/full-feed?dl=1&amp;api_token={$userdata.api_token}")}}" readonly id="fullFeedUrl">
		                            <button class="btn btn-sm btn-outline-secondary copy-btn" type="button" data-copy-target="fullFeedUrl">
		                                <i class="fa fa-copy"></i>
		                            </button>
		                        </div>
		                        <small class="text-muted mt-2 d-block">You can define limit and num parameters, which will decide how many items to show and what offset to use (default values: limit 100 and offset 0).</small>
		                    </div>

		                    <div class="list-group-item">
		                        <div class="d-flex justify-content-between align-items-center mb-2">
		                            <strong><i class="fa fa-shopping-basket me-2 text-info"></i>My Cart Feed</strong>
		                            <a href="{{url("/rss/cart?dl=1&amp;api_token={$userdata.api_token}&amp;del=1")}}" class="btn btn-sm btn-outline-primary" target="_blank">
		                                <i class="fa fa-external-link me-1"></i>Open Feed
		                            </a>
		                        </div>
		                        <div class="input-group">
		                            <input type="text" class="form-control form-control-sm font-monospace" value="{{url("/rss/cart?dl=1&amp;api_token={$userdata.api_token}&amp;del=1")}}" readonly id="cartFeedUrl">
		                            <button class="btn btn-sm btn-outline-secondary copy-btn" type="button" data-copy-target="cartFeedUrl">
		                                <i class="fa fa-copy"></i>
		                            </button>
		                        </div>
		                    </div>

		                    <div class="list-group-item">
		                        <div class="d-flex justify-content-between align-items-center mb-2">
		                            <strong><i class="fa fa-tv me-2 text-success"></i>My Shows Feed</strong>
		                            <a href="{{url("/rss/myshows?dl=1&amp;api_token={$userdata.api_token}&amp;del=1")}}" class="btn btn-sm btn-outline-primary" target="_blank">
		                                <i class="fa fa-external-link me-1"></i>Open Feed
		                            </a>
		                        </div>
		                        <div class="input-group">
		                            <input type="text" class="form-control form-control-sm font-monospace" value="{{url("/rss/myshows?dl=1&amp;api_token={$userdata.api_token}&amp;del=1")}}" readonly id="myShowsFeedUrl">
		                            <button class="btn btn-sm btn-outline-secondary copy-btn" type="button" data-copy-target="myShowsFeedUrl">
		                                <i class="fa fa-copy"></i>
		                            </button>
		                        </div>
		                    </div>

		                    <div class="list-group-item">
		                        <div class="d-flex justify-content-between align-items-center mb-2">
		                            <strong><i class="fa fa-film me-2 text-danger"></i>My Movies Feed</strong>
		                            <a href="{{url("/rss/mymovies?dl=1&amp;api_token={$userdata.api_token}&amp;del=1")}}" class="btn btn-sm btn-outline-primary" target="_blank">
		                                <i class="fa fa-external-link me-1"></i>Open Feed
		                            </a>
		                        </div>
		                        <div class="input-group">
		                            <input type="text" class="form-control form-control-sm font-monospace" value="{{url("/rss/mymovies?dl=1&amp;api_token={$userdata.api_token}&amp;del=1")}}" readonly id="myMoviesFeedUrl">
		                            <button class="btn btn-sm btn-outline-secondary copy-btn" type="button" data-copy-target="myMoviesFeedUrl">
		                                <i class="fa fa-copy"></i>
		                            </button>
		                        </div>
		                    </div>
		                </div>
		            </div>
		        </div>

		        <!-- Category Feeds -->
		        <div class="row">
		            <div class="col-md-6">
		                <div class="card mb-4">
		                    <div class="card-header bg-light">
		                        <h5 class="mb-0"><i class="fa fa-folder me-2 text-primary"></i>Parent Categories</h5>
		                    </div>
		                    <div class="card-body p-0">
		                        <div class="list-group list-group-flush">
		                            {foreach $parentcategorylist as $category}
		                                <div class="list-group-item">
		                                    <div class="d-flex justify-content-between align-items-center mb-2">
		                                        <strong><i class="fa fa-folder-open me-2 text-warning"></i>{$category.title}</strong>
		                                        <a href="{{url("/rss/category?id={$category.id}&amp;dl=1&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary" target="_blank">
		                                            <i class="fa fa-external-link me-1"></i>Open Feed
		                                        </a>
		                                    </div>
		                                    <div class="input-group">
		                                        <input type="text" class="form-control form-control-sm font-monospace" value="{{url("/rss/category?id={$category.id}&amp;dl=1&amp;api_token={$userdata.api_token}")}}" readonly id="parentCat{$category.id}Url">
		                                        <button class="btn btn-sm btn-outline-secondary copy-btn" type="button" data-copy-target="parentCat{$category.id}Url">
		                                            <i class="fa fa-copy"></i>
		                                        </button>
		                                    </div>
		                                </div>
		                            {/foreach}
		                        </div>
		                    </div>
		                </div>
		            </div>

		            <div class="col-md-6">
		                <div class="card mb-4">
		                    <div class="card-header bg-light">
		                        <h5 class="mb-0"><i class="fa fa-folder-open me-2 text-primary"></i>Sub Categories</h5>
		                    </div>
		                    <div class="card-body p-0">
		                        <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
		                            {foreach $categorylist as $category}
		                                {if !empty($category.title)}
		                                    <div class="list-group-item">
		                                        <div class="d-flex justify-content-between align-items-center mb-2">
		                                            <strong><i class="fa fa-tag me-2 text-info"></i>{$category.title}</strong>
		                                            <a href="{{url("/rss/category?id={$category.id}&amp;dl=1&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary" target="_blank">
		                                                <i class="fa fa-external-link me-1"></i>Open Feed
		                                            </a>
		                                        </div>
		                                        <div class="input-group">
		                                            <input type="text" class="form-control form-control-sm font-monospace" value="{{url("/rss/category?id={$category.id}&amp;dl=1&amp;api_token={$userdata.api_token}")}}" readonly id="subCat{$category.id}Url">
		                                            <button class="btn btn-sm btn-outline-secondary copy-btn" type="button" data-copy-target="subCat{$category.id}Url">
		                                                <i class="fa fa-copy"></i>
		                                            </button>
		                                        </div>
		                                    </div>
		                                {/if}
		                            {/foreach}
		                        </div>
		                    </div>
		                </div>
		            </div>
		        </div>

		        <!-- Special Feeds -->
		        <div class="card mb-4">
		            <div class="card-header bg-light">
		                <h5 class="mb-0"><i class="fa fa-star me-2 text-primary"></i>Special Feeds</h5>
		            </div>
		            <div class="card-body p-0">
		                <div class="list-group list-group-flush">
		                    <div class="list-group-item">
		                        <div class="d-flex justify-content-between align-items-center mb-2">
		                            <strong><i class="fa fa-filter me-2 text-success"></i>Multiple Categories</strong>
		                            <a href="{{url("/rss/category?id={$catClass::MOVIE_ROOT},{$catClass::MUSIC_MP3}&amp;dl=1&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary" target="_blank">
		                                <i class="fa fa-external-link me-1"></i>Open Feed
		                            </a>
		                        </div>
		                        <div class="input-group">
		                            <input type="text" class="form-control form-control-sm font-monospace" value="{{url("/rss/category?id={$catClass::MOVIE_ROOT},{$catClass::MUSIC_MP3}&amp;dl=1&amp;api_token={$userdata.api_token}")}}" readonly id="multiCatUrl">
		                            <button class="btn btn-sm btn-outline-secondary copy-btn" type="button" data-copy-target="multiCatUrl">
		                                <i class="fa fa-copy"></i>
		                            </button>
		                        </div>
		                        <small class="text-muted mt-2 d-block">Multiple categories separated by comma</small>
		                    </div>

		                    <div class="list-group-item">
		                        <div class="d-flex justify-content-between align-items-center mb-2">
		                            <strong><i class="fa fa-tv me-2 text-primary"></i>TV Series (TVRage ID)</strong>
		                            <a href="{{url("/rss/full-feed?show={$show}&amp;dl=1&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary" target="_blank">
		                                <i class="fa fa-external-link me-1"></i>Open Feed
		                            </a>
		                        </div>
		                        <div class="input-group">
		                            <input type="text" class="form-control form-control-sm font-monospace" value="{{url("/rss/full-feed?show={$show}&amp;dl=1&amp;api_token={$userdata.api_token}")}}" readonly id="tvShowUrl">
		                            <button class="btn btn-sm btn-outline-secondary copy-btn" type="button" data-copy-target="tvShowUrl">
		                                <i class="fa fa-copy"></i>
		                            </button>
		                        </div>
		                    </div>

		                    <div class="list-group-item">
		                        <div class="d-flex justify-content-between align-items-center mb-2">
		                            <strong><i class="fa fa-calendar me-2 text-warning"></i>TV Series (Last 7 Days)</strong>
		                            <a href="{{url("/rss/full-feed?show={$show}&amp;airdate=7&amp;dl=1&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary" target="_blank">
		                                <i class="fa fa-external-link me-1"></i>Open Feed
		                            </a>
		                        </div>
		                        <div class="input-group">
		                            <input type="text" class="form-control form-control-sm font-monospace" value="{{url("/rss/full-feed?show={$show}&amp;airdate=7&amp;dl=1&amp;api_token={$userdata.api_token}")}}" readonly id="tvRecentUrl">
		                            <button class="btn btn-sm btn-outline-secondary copy-btn" type="button" data-copy-target="tvRecentUrl">
		                                <i class="fa fa-copy"></i>
		                            </button>
		                        </div>
		                        <small class="text-muted mt-2 d-block">TV Series aired in the last seven days (Using the Video ID and airdate)</small>
		                    </div>

		                    <div class="list-group-item">
		                        <div class="d-flex justify-content-between align-items-center mb-2">
		                            <strong><i class="fa fa-play me-2 text-danger"></i>Anime Feed (AniDB ID)</strong>
		                            <a href="{{url("/rss/full-feed?anidb={$anidb}&amp;dl=1&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary" target="_blank">
		                                <i class="fa fa-external-link me-1"></i>Open Feed
		                            </a>
		                        </div>
		                        <div class="input-group">
		                            <input type="text" class="form-control form-control-sm font-monospace" value="{{url("/rss/full-feed?anidb={$anidb}&amp;dl=1&amp;api_token={$userdata.api_token}")}}" readonly id="animeUrl">
		                            <button class="btn btn-sm btn-outline-secondary copy-btn" type="button" data-copy-target="animeUrl">
		                                <i class="fa fa-copy"></i>
		                            </button>
		                        </div>
		                    </div>
		                </div>
		            </div>
		        </div>
		    </div>
		</div>

		<script>
		{literal}
		document.addEventListener('DOMContentLoaded', function() {
		    // Copy API token functionality
		    const copyApiToken = document.getElementById('copyApiToken');
		    if (copyApiToken) {
		        copyApiToken.addEventListener('click', function() {
		            const tokenInput = document.getElementById('apiTokenInput');
		            tokenInput.select();
		            tokenInput.setSelectionRange(0, 99999);
		            navigator.clipboard.writeText(tokenInput.value);

		            // Show feedback
		            const originalText = copyApiToken.innerHTML;
		            copyApiToken.innerHTML = '<i class="fa fa-check"></i>';
		            copyApiToken.classList.add('btn-success');
		            copyApiToken.classList.remove('btn-outline-secondary');

		            setTimeout(function() {
		                copyApiToken.innerHTML = originalText;
		                copyApiToken.classList.remove('btn-success');
		                copyApiToken.classList.add('btn-outline-secondary');
		            }, 2000);
		        });
		    }

		    // Handle all copy buttons
		    const copyButtons = document.querySelectorAll('.copy-btn');
		    copyButtons.forEach(button => {
		        button.addEventListener('click', function() {
		            const targetId = this.getAttribute('data-copy-target');
		            const targetInput = document.getElementById(targetId);

		            targetInput.select();
		            targetInput.setSelectionRange(0, 99999);
		            navigator.clipboard.writeText(targetInput.value);

		            // Show feedback
		            const originalText = this.innerHTML;
		            this.innerHTML = '<i class="fa fa-check"></i>';
		            this.classList.add('btn-success');
		            this.classList.remove('btn-outline-secondary');

		            setTimeout(() => {
		                this.innerHTML = originalText;
		                this.classList.remove('btn-success');
		                this.classList.add('btn-outline-secondary');
		            }, 2000);
		        });
		    });
		});
		{/literal}
		</script>
