<div class="card card-default shadow-sm mb-4">
			    <div class="card-header bg-light">
			        <h3 class="mb-0"><i class="fa fa-code me-2 text-primary"></i>{$title}</h3>
			    </div>
			    <div class="card-body">
			        <p class="lead">
			            Here lives the documentation for the API for accessing NZB and index data. API functions can be called by either
			            logged in users, or by providing an API key.
			        </p>

			        {if $loggedin=="true"}
			            <div class="card card-body bg-light mb-4">
			                <h4 class="mb-3"><i class="fa fa-key me-2 text-secondary"></i>Your API Credentials</h4>
			                <div class="input-group">
			                    <input type="text" class="form-control font-monospace" value="apikey={$userdata.api_token}" readonly id="apikeyInput">
			                    <button class="btn btn-outline-secondary" type="button" id="copyApiKey" title="Copy to clipboard">
			                        <i class="fa fa-copy"></i>
			                    </button>
			                </div>
			            </div>
			        {/if}

			        <h4 class="mb-3"><i class="fa fa-plug me-2 text-secondary"></i>Available Functions</h4>
			        <p>Use the parameter <code>?t=</code> to specify the function being called.</p>

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
			                            available search categories and version number of the newznab protocol being used.
			                            <div class="text-muted small mt-1">
			                                <i class="fa fa-info-circle me-1"></i>No credentials required
			                            </div>
			                        </td>
			                        <td>
			                            <a href="{{url("/api/v1/api?t=caps")}}" class="btn btn-sm btn-outline-primary">
			                                <i class="fa fa-external-link-alt me-1"></i>
			                                <code>?t=caps</code>
			                            </a>
			                        </td>
			                    </tr>
			                    <tr>
			                        <td><strong>Search</strong></td>
			                        <td>
			                            Returns a list of NZBs matching a query. You can filter by site category by including
			                            a comma separated list of categories.
			                            <div class="mt-2">
			                                <span class="badge bg-info text-white">OPTIONS</span>
			                                <div class="mt-1 ms-2">
			                                    <code>extended=1</code> - Return extended information in results
			                                </div>
			                            </div>
			                        </td>
			                        <td>
			                            <div class="d-flex flex-column gap-2">
			                                <a href="{{url("/api/v1/api?t=search&amp;q=linux&amp;apikey={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
			                                    <i class="fa fa-external-link-alt me-1"></i>
			                                    <code>?t=search&amp;q=linux</code>
			                                </a>
			                                <a href="{{url("/api/v1/api?t=search&amp;cat={$catClass::GAME_ROOT},{$catClass::MOVIE_ROOT}&amp;apikey={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
			                                    <i class="fa fa-external-link-alt me-1"></i>
			                                    <code>?t=search&amp;cat={$catClass::GAME_ROOT},{$catClass::MOVIE_ROOT}</code>
			                                </a>
			                            </div>
			                        </td>
			                    </tr>
			                    <tr>
			                        <td><strong>TV Search</strong></td>
			                        <td>
			                            Returns a list of NZBs matching a query, category, or TV ID. Filter by season, episode, or various database IDs.
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
			                            <a href="{{url("/api/v1/api?t=tvsearch&amp;q=law%20and%20order&amp;season=7&amp;ep=12&amp;apikey={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
			                                <i class="fa fa-external-link-alt me-1"></i>
			                                <code>?t=tvsearch&amp;q=law and order&amp;season=7&amp;ep=12</code>
			                            </a>
			                        </td>
			                    </tr>
			                    <tr>
			                        <td><strong>Movies</strong></td>
			                        <td>
			                            Returns a list of NZBs matching a query, an IMDB ID and optionally a category.
			                            <div class="mt-2">
			                                <span class="badge bg-info text-white">OPTIONS</span>
			                                <div class="mt-1 ms-2">
			                                    <code>extended=1</code> - Return extended information in results
			                                </div>
			                            </div>
			                        </td>
			                        <td>
			                            <a href="{{url("/api/v1/api?t=movie&amp;imdbid=1418646&amp;apikey={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
			                                <i class="fa fa-external-link-alt me-1"></i>
			                                <code>?t=movie&amp;imdbid=1418646</code>
			                            </a>
			                        </td>
			                    </tr>
			                    <tr>
			                        <td><strong>Details</strong></td>
			                        <td>Returns detailed information about an NZB.</td>
			                        <td>
			                            <a href="{{url("/api/v1/api?t=details&amp;id=9ca52909ba9b9e5e6758d815fef4ecda&amp;apikey={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
			                                <i class="fa fa-external-link-alt me-1"></i>
			                                <code>?t=details&amp;id=9ca52909ba9b9e5e6758d815fef4ecda</code>
			                            </a>
			                        </td>
			                    </tr>
			                    <tr>
			                        <td><strong>Info</strong></td>
			                        <td>
			                            Returns NFO contents for an NZB. Retrieve the NFO as file by specifying o=file in the request URI.
			                        </td>
			                        <td>
			                            <a href="{{url("/api/v1/api?t=info&amp;id=9ca52909ba9b9e5e6758d815fef4ecda&amp;apikey={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
			                                <i class="fa fa-external-link-alt me-1"></i>
			                                <code>?t=info&amp;id=9ca52909ba9b9e5e6758d815fef4ecda</code>
			                            </a>
			                        </td>
			                    </tr>
			                    <tr>
			                        <td><strong>Get</strong></td>
			                        <td>Downloads the NZB file associated with an ID.</td>
			                        <td>
			                            <a href="{{url("/api/v1/api?t=get&amp;id=9ca52909ba9b9e5e6758d815fef4ecda&amp;apikey={$userdata.api_token}")}}" class="btn btn-sm btn-outline-primary">
			                                <i class="fa fa-external-link-alt me-1"></i>
			                                <code>?t=get&amp;id=9ca52909ba9b9e5e6758d815fef4ecda</code>
			                            </a>
			                        </td>
			                    </tr>
			                </tbody>
			            </table>
			        </div>

			        <h4 class="mt-4 mb-3"><i class="fa fa-file-code me-2 text-secondary"></i>Output Format</h4>
			        <p>Select your preferred output format (not applicable to functions which return an NZB/NFO file).</p>

			        <div class="row">
			            <div class="col-md-6 mb-3">
			                <div class="card h-100">
			                    <div class="card-body">
			                        <h5 class="card-title"><i class="fa fa-code me-2 text-primary"></i>XML (default)</h5>
			                        <p class="card-text">Returns the data in an XML document.</p>
			                        <code class="d-block bg-light p-2 rounded">?t=search&amp;q=linux&amp;o=xml</code>
			                    </div>
			                </div>
			            </div>
			            <div class="col-md-6 mb-3">
			                <div class="card h-100">
			                    <div class="card-body">
			                        <h5 class="card-title"><i class="fa fa-brackets-curly me-2 text-primary"></i>JSON</h5>
			                        <p class="card-text">Returns the data in a JSON object.</p>
			                        <code class="d-block bg-light p-2 rounded">?t=search&amp;q=linux&amp;o=json</code>
			                    </div>
			                </div>
			            </div>
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
