<div class="container-fluid px-4 py-3">
				    <!-- Breadcrumb -->
				    <nav aria-label="breadcrumb" class="mb-3">
				        <ol class="breadcrumb">
				            <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
				            <li class="breadcrumb-item"><a href="{{url("/browse")}}">Browse</a></li>
				            <li class="breadcrumb-item"><a href="{{url("/details/{$rel.guid}")}}">Release Details</a></li>
				            <li class="breadcrumb-item active">File List</li>
				        </ol>
				    </nav>

				    <!-- File List Card -->
				    <div class="card shadow-sm mb-4">
				        <div class="card-header bg-light">
				            <div class="d-flex justify-content-between align-items-center">
				                <h5 class="mb-0"><i class="fa fa-file-text me-2"></i>File List</h5>
				                <div class="text-muted small">Total Files: {$files|@count}</div>
				            </div>
				        </div>

				        <div class="card-body p-0">
				            <!-- Release Info -->
				            <div class="p-3 bg-light border-bottom">
				                <h5 class="mb-2">{$title}</h5>
				                <p class="mb-0">
				                    <span class="text-muted me-2">For release:</span>
				                    <a href="{{url("/details/{$rel.guid}")}}" class="text-decoration-none fw-semibold">{$rel.searchname|escape:'htmlall'}</a>
				                </p>
				            </div>

				            <!-- Files Table -->
				            <div class="table-responsive">
				                <table class="table table-striped table-hover mb-0">
				                    <thead class="thead-light">
				                        <tr>
				                            <th style="width: 50px" class="text-center">#</th>
				                            <th>Filename</th>
				                            <th style="width: 60px" class="text-center">Type</th>
				                            <th style="width: 120px" class="text-center">Completion</th>
				                            <th style="width: 100px" class="text-center">Size</th>
				                        </tr>
				                    </thead>
				                    <tbody>
				                        {foreach $files as $i=>$file name="iteration"}
				                        <tr>
				                            <td class="text-center">{$smarty.foreach.iteration.index+1}</td>
				                            <td class="text-break">
				                                <span class="d-inline-block text-truncate" style="max-width: 600px;" title="{$file.title|escape:'htmlall'}">{$file.title|escape:'htmlall'}</span>
				                            </td>
				                            {assign var="icon" value='assets/images/fileicons/'|cat:$file.ext|cat:".png"}
				                            {if $file.ext == "" || !is_file("$icon")}
				                                {assign var="icon" value='file'}
				                            {else}
				                                {assign var="icon" value=$file.ext}
				                            {/if}
				                            <td class="text-center">
				                                <div class="d-flex justify-content-center align-items-center">
				                                    <img title=".{$file.ext}" alt="{$file.ext}" src="{{asset("/assets/images/fileicons/{$icon}.png")}}" width="24" height="24" class="me-1" />
				                                    <span class="badge bg-secondary text-uppercase">{$file.ext}</span>
				                                </div>
				                            </td>
				                            {if $file.partstotal != 0}
				                                {assign var="completion" value=($file.partsactual/$file.partstotal*100)|number_format:1}
				                            {else}
				                                {assign var="completion" value=0|number_format:1}
				                            {/if}
				                            <td class="text-center">
				                                <div class="progress" style="height: 20px">
				                                    <div class="progress-bar {if $completion < 100}bg-warning{else}bg-success{/if}"
				                                         role="progressbar"
				                                         style="width: {$completion}%"
				                                         aria-valuenow="{$completion}"
				                                         aria-valuemin="0"
				                                         aria-valuemax="100">
				                                        {$completion}%
				                                    </div>
				                                </div>
				                            </td>
				                            <td class="text-center">
				                                <div class="d-flex align-items-center justify-content-center">
				                                    <i class="fa fa-hdd-o text-muted me-2"></i>
				                                    <span class="fw-medium">{$file.size|filesize}</span>
				                                </div>
				                            </td>
				                        </tr>
				                        {/foreach}
				                    </tbody>
				                </table>
				            </div>
				        </div>

				        <div class="card-footer bg-light">
				            <div class="d-flex justify-content-between align-items-center">
				                <a href="{{url("/details/{$rel.guid}")}}" class="btn btn-sm btn-outline-secondary">
				                    <i class="fa fa-arrow-left me-1"></i> Back to Release Details
				                </a>

				                <div class="text-muted small">
				                    <i class="fa fa-info-circle me-1"></i>
				                    {if isset($rel.completion) && $rel.completion < 100}
				                    Release is {$rel.completion|number_format:1}% complete
				                    {else}
				                    Release is 100% complete
				                    {/if}
				                </div>
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
				});
				{/literal}
				</script>
