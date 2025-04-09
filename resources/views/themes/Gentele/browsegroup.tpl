<div class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{url({$site->home_link})}}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{url("/browse")}}">Browse</a></li>
            <li class="breadcrumb-item active">Groups</li>
        </ol>
    </nav>

    {$site->adbrowse}

    <!-- Search Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <form method="get" action="{{url("/browsegroup")}}" class="py-1">
                <div class="row g-2 align-items-center">
                    <div class="col-md-9">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fa fa-search text-muted"></i>
                            </span>
                            <input type="text" name="search" value="{$search|default:''}"
                                   placeholder="Search group names..."
                                   class="form-control border-start-0" />
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa fa-search me-2"></i>Search
                            </button>
                            {if isset($search)}
                                <a href="{{url("/browsegroup")}}" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center">
                                    <i class="fa fa-times-circle me-2"></i>Clear
                                </a>
                            {/if}
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    {if $results|@count > 0}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <!-- Top Controls -->
            <div class="row mb-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="mb-0">Browse Groups</h5>
                </div>
                <div class="col-md-8 d-flex justify-content-end">
                    {$results->onEachSide(5)->links()}
                </div>
            </div>

            <hr>

            <!-- Groups Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $results as $result}
                            <tr>
                                <td>
                                    <a class="text-decoration-none fw-semibold"
                                       title="Browse releases from {$result.name|replace:"alt.binaries":"a.b"}"
                                       href="{{url("/browse/group?g={$result.name}")}}">
                                        {$result.name|replace:"alt.binaries":"a.b"}
                                    </a>
                                </td>
                                <td>{$result.description}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-clock-o text-muted me-2"></i>
                                        <span title="{$result.last_updated}">{$result.last_updated|timeago} ago</span>
                                    </div>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>

            <!-- Bottom Controls -->
            <hr>
            <div class="row mt-4">
                <div class="col-md-4">
                    <span class="text-muted">Found {$results->total()} groups</span>
                    {if isset($search) && $search != ''}
                        <span class="text-muted ms-2">for "<strong>{$search}</strong>"</span>
                    {/if}
                </div>
                <div class="col-md-8 d-flex justify-content-end">
                    {$results->onEachSide(5)->links()}
                </div>
            </div>
        </div>
    </div>
    {else}
    <div class="alert alert-info">
        {if isset($search) && $search != ''}
            No groups found matching "<strong>{$search}</strong>". <a href="{{url("/browsegroup")}}">Show all groups</a>
        {else}
            No groups found.
        {/if}
    </div>
    {/if}
</div>

<style>
thead th {
    font-weight: 600;
    border-bottom-width: 2px;
}
</style>
