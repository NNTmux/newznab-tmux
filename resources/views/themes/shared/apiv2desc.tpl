<div class="card card-default shadow-sm mb-4">
        <div class="card-header bg-light">
            <h3 class="mb-0"><i class="fa fa-code me-2 text-primary"></i>{$title}</h3>
        </div>
        <div class="card-body">
            <p class="lead">
                Here lives the documentation for the API v2 for accessing NZB and index data. API functions can be called by providing an API token.
            </p>

            {if $loggedin=="true"}
                <div class="card card-body bg-light mb-4">
                    <h4 class="mb-3"><i class="fa fa-key me-2 text-secondary"></i>Your API Credentials</h4>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace" value="api_token={$userdata.api_token}" readonly id="apikeyInput">
                        <button class="btn btn-outline-secondary" type="button" id="copyApiKey" title="Copy to clipboard">
                            <i class="fa fa-copy"></i>
                        </button>
                    </div>
                </div>
            {/if}

            <h4 class="mb-3"><i class="fa fa-plug me-2 text-secondary"></i>Available Functions</h4>

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Function</th>
                            <th>Description</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Capabilities</strong></td>
                            <td>
                                Reports the capabilities of the server. Includes information about the server name,
                                available search categories and version number of the nntmux being used.
                                <div class="text-muted small mt-1">
                                    <i class="fa fa-info-circle me-1"></i>No credentials required
                                </div>
                            </td>
                            <td>
                                <a href="{{url("/api/v2/capabilities")}}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-external-link-alt me-1"></i>
                                    <code>capabilities</code>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Search</strong></td>
                            <td>
                                Returns a list of NZBs matching a query. You can filter by site category by including
                                a comma separated list of categories.
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-2">
                                    <a href="{{url("/api/v2/search?id=linux&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-external-link-alt me-1"></i>
                                        <code>search?id=linux</code>
                                    </a>
                                    <a href="{{url("/api/v2/search?cat={$catClass::GAME_ROOT},{$catClass::MOVIE_ROOT}&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-external-link-alt me-1"></i>
                                        <code>search?cat={$catClass::GAME_ROOT},{$catClass::MOVIE_ROOT}</code>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>TV Search</strong></td>
                            <td>
                                Returns a list of NZBs matching a query, category, TVRageID, season or episode.
                                <div class="mt-2">
                                    <span class="badge bg-info text-white">ID OPTIONS</span>
                                    <div class="mt-1 ms-2">
                                        <code>rid=25056</code> - TVRage<br>
                                        <code>tvdbid=153021</code> - TVDB<br>
                                        <code>traktid=1393</code> - Trakt<br>
                                        <code>tvmazeid=73</code> - TVMaze<br>
                                        <code>imdbid=1520211</code> - IMDB<br>
                                        <code>tmdbid=1402</code> - TMDB
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-2">
                                    <a href="{{url("/api/v2/tv?id=law%20and%20order&amp;season=7&amp;ep=12&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-external-link-alt me-1"></i>
                                        <code>tv?id=law and order&amp;season=7&amp;ep=12</code>
                                    </a>
                                    <a href="{{url("/api/v2/tv?rid=2204&amp;cat={$catClass::GAME_ROOT},{$catClass::MOVIE_ROOT}&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-external-link-alt me-1"></i>
                                        <code>tv?rid=2204&amp;cat={$catClass::GAME_ROOT},{$catClass::MOVIE_ROOT}</code>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Movies</strong></td>
                            <td>
                                Returns a list of NZBs matching a query, an ID (IMDB, TMDB, or Trakt) and optionally a category.
                                <div class="mt-2">
                                    <span class="badge bg-info text-white">ID OPTIONS</span>
                                    <div class="mt-1 ms-2">
                                        <code>imdbid=1418646</code> - IMDB<br>
                                        <code>tmdbid=43418</code> - TMDB<br>
                                        <code>traktid=29200</code> - Trakt
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-2">
                                    <a href="{{url("/api/v2/movies?imdbid=1418646&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-external-link-alt me-1"></i>
                                        <code>movies?imdbid=1418646</code>
                                    </a>
                                    <a href="{{url("/api/v2/movies?imdbid=1418646&amp;cat={$catClass::MOVIE_SD},{$catClass::MOVIE_HD}&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-external-link-alt me-1"></i>
                                        <code>movies?imdbid=1418646&amp;cat={$catClass::MOVIE_SD},{$catClass::MOVIE_HD}</code>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Details</strong></td>
                            <td>Returns detailed information about an NZB.</td>
                            <td>
                                <a href="{{url("/api/v2/details?id=9ca52909ba9b9e5e6758d815fef4ecda&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-external-link-alt me-1"></i>
                                    <code>details?id=9ca52909ba9b9e5e6758d815fef4ecda</code>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Get NZB</strong></td>
                            <td>Downloads the NZB file associated with an ID.</td>
                            <td>
                                <a href="{{url("/api/v2/getnzb?id=9ca52909ba9b9e5e6758d815fef4ecda&amp;api_token={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-external-link-alt me-1"></i>
                                    <code>getnzb?id=9ca52909ba9b9e5e6758d815fef4ecda</code>
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h4 class="mt-4 mb-3"><i class="fa fa-file-code me-2 text-secondary"></i>Output Format</h4>
            <div class="card bg-light mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="fa fa-brackets-curly me-3 text-primary fa-2x"></i>
                        <div>
                            <h5 class="mb-1">JSON Format</h5>
                            <p class="mb-0">All information is returned in JSON format.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-4">
                <i class="fa fa-info-circle me-2"></i>
                <strong>Note:</strong> When using these API endpoints in your applications, always send your API token with each request.
            </div>
        </div>
    </div>

    <script>
    {literal}
    document.addEventListener('DOMContentLoaded', function() {
        // Copy API key functionality
        const copyBtn = document.getElementById('copyApiKey');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const apiKeyInput = document.getElementById('apikeyInput');
                apiKeyInput.select();
                apiKeyInput.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(apiKeyInput.value);

                // Show feedback
                const originalText = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fa fa-check"></i>';
                copyBtn.classList.add('btn-success');
                copyBtn.classList.remove('btn-outline-secondary');

                setTimeout(function() {
                    copyBtn.innerHTML = originalText;
                    copyBtn.classList.remove('btn-success');
                    copyBtn.classList.add('btn-outline-secondary');
                }, 2000);
            });
        }
    });
    {/literal}
    </script>
