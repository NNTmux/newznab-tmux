<div class="card shadow-sm mb-4">
			    <div class="card-header bg-light">
			        <div class="row align-items-center">
			            <div class="col-md-6">
			                <h3 class="mb-0">{$title}</h3>
			            </div>
			            <div class="col-md-6">
			                <form name="presearch" method="get" action="{{URL("/admin/predb")}}" id="custom-search-form" class="ms-md-auto">
			                    {{csrf_field()}}
			                    <div class="input-group">
			                        <input type="text" class="form-control" placeholder="Search predb" id="presearch" name="presearch" value="{$lastSearch|escape:'html'}">
			                        <button type="submit" class="btn btn-primary">
			                            <i class="fa fa-search"></i>
			                        </button>
			                    </div>
			                </form>
			            </div>
			        </div>
			    </div>

			    <div class="card-body p-0">
			        <div class="table-responsive">
			            <table class="table table-striped table-hover mb-0">
			                <thead class="thead-light">
			                    <tr>
			                        <th>
			                            <div class="d-flex align-items-center">
			                                <i class="fa fa-calendar-alt text-muted me-2"></i>
			                                <span>Date</span>
			                            </div>
			                        </th>
			                        <th>
			                            <div class="d-flex align-items-center">
			                                <i class="fa fa-file-alt text-muted me-2"></i>
			                                <span>Title</span>
			                            </div>
			                        </th>
			                        <th>
			                            <div class="d-flex align-items-center">
			                                <i class="fa fa-hashtag text-muted me-2"></i>
			                                <span>Reqid</span>
			                            </div>
			                        </th>
			                        <th>
			                            <div class="d-flex align-items-center">
			                                <i class="fa fa-hdd text-muted me-2"></i>
			                                <span>Size</span>
			                            </div>
			                        </th>
			                        <th>
			                            <div class="d-flex align-items-center">
			                                <i class="fa fa-copy text-muted me-2"></i>
			                                <span>Files</span>
			                            </div>
			                        </th>
			                        <th colspan="2" class="text-center">Actions</th>
			                    </tr>
			                </thead>
			                <tbody>
			                    {foreach $results as $result}
			                        <tr>
			                            <td class="align-middle">
			                                <span class="text-muted">
			                                    <i class="far fa-clock me-1"></i>
			                                    {$result.predate|date_format:"%Y-%m-%d %H:%M:%S"}
			                                </span>
			                            </td>
			                            <td class="align-middle">
			                                {if isset($result.guid)}
			                                    <a class="fw-semibold text-decoration-none {if $result.nuked == 1}text-success{elseif $result.nuked > 1}text-danger{/if}"
			                                       href="{{URL("/details/{$result.guid}")}}">
			                                        <span title="{if $result.nuked == 1}UNNUKED: {$result.nukereason|escape:"htmlall"}{elseif $result.nuked > 1}NUKED: {$result.nukereason|escape:"htmlall"}{else}{$result.title|escape:"htmlall"}{/if}">
			                                            {$result.title|escape:"htmlall"|truncate:90:"...":true}
			                                        </span>
			                                        {if $result.nuked == 1}
			                                            <span class="badge bg-success ms-2">UNNUKED</span>
			                                        {elseif $result.nuked > 1}
			                                            <span class="badge bg-danger ms-2">NUKED</span>
			                                        {/if}
			                                    </a>
			                                {else}
			                                    <span class="{if $result.nuked == 1}text-success{elseif $result.nuked > 1}text-danger{/if}"
			                                          title="{if $result.nuked == 1}UNNUKED: {$result.nukereason|escape:"htmlall"}{elseif $result.nuked > 1}NUKED: {$result.nukereason|escape:"htmlall"}{else}{$result.title|escape:"htmlall"}{/if}">
			                                        {$result.title|escape:"htmlall"|truncate:90:"...":true}
			                                        {if $result.nuked == 1}
			                                            <span class="badge bg-success ms-2">UNNUKED</span>
			                                        {elseif $result.nuked > 1}
			                                            <span class="badge bg-danger ms-2">NUKED</span>
			                                        {/if}
			                                    </span>
			                                {/if}
			                            </td>
			                            <td class="align-middle text-center">
			                                {if is_numeric($result.requestid) && $result.requestid != 0}
			                                    <a class="badge bg-primary text-decoration-none"
			                                       href="{{URL("/search?searchadvr=&searchadvsubject={$result.requestid}&searchadvposter=&searchadvdaysnew=&searchadvdaysold=&searchadvgroups=-1&searchadvcat=-1&searchadvsizefrom=-1&searchadvsizeto=-1&searchadvhasnfo=0&searchadvhascomments=0&search_type=adv")}}">
			                                        {$result.requestid}
			                                    </a>
			                                {else}
			                                    <span class="badge bg-secondary">N/A</span>
			                                {/if}
			                            </td>
			                            <td class="align-middle text-center">
			                                {if not in_array($result.size, array('NULL', '', '0MB'))}
			                                    <span class="badge bg-info rounded-pill">
			                                        {if strpos($result.size, 'MB') != false && ($result.size|regex_replace:"/(\.\d|,|MB)+/":''|count_characters) > 3}
			                                            {math equation=($result.size|regex_replace:'/(\.\d|,|MB)+/':'' / 1024)|round}GB
			                                        {else}
			                                            {$result.size|regex_replace:"/(\.\d|,)+/":''}
			                                        {/if}
			                                    </span>
			                                {else}
			                                    <span class="badge bg-secondary">N/A</span>
			                                {/if}
			                            </td>
			                            <td class="align-middle text-center">
			                                {if isset($result.files)}
			                                    <span class="badge bg-dark rounded-pill">{$result.files}</span>
			                                {else}
			                                    <span class="badge bg-secondary">N/A</span>
			                                {/if}
			                            </td>
			                            <td class="align-middle text-center">
			                                <a href="{$site->dereferrer_link}http://nzbindex.com/search/?q={$result.title}"
			                                   target="_blank"
			                                   class="btn btn-sm btn-outline-secondary"
			                                   data-bs-toggle="tooltip"
			                                   title="Search on NzbIndex">
			                                    <i class="fa fa-search"></i> NzbIndex
			                                </a>
			                            </td>
			                            <td class="align-middle text-center">
			                                <a href="{$site->dereferrer_link}http://binsearch.info/?q={$result.title}"
			                                   target="_blank"
			                                   class="btn btn-sm btn-outline-secondary"
			                                   data-bs-toggle="tooltip"
			                                   title="Search on BinSearch">
			                                    <i class="fa fa-search"></i> BinSearch
			                                </a>
			                            </td>
			                        </tr>
			                    {/foreach}
			                </tbody>
			            </table>
			        </div>
			    </div>

			    <div class="card-footer bg-light">
			        <div class="d-flex justify-content-between align-items-center">
			            <div>
			                <span class="text-muted">
			                    <i class="fa fa-info-circle me-1"></i>
			                    Showing results for predb entries
			                </span>
			            </div>
			            <div class="pagination-container">
			                {$results->onEachSide(5)->links()}
			            </div>
			        </div>
			    </div>
			</div>

			<script>
			{literal}
			    document.addEventListener('DOMContentLoaded', function() {
			        // Initialize tooltips
			        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
			        tooltipTriggerList.map(function (tooltipTriggerEl) {
			            return new bootstrap.Tooltip(tooltipTriggerEl);
			        });
			    });
			{/literal}
			</script>
