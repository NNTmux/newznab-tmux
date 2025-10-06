<div class="header">
    <div class="breadcrumb-wrapper">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url($site->home_link) }}">Home</a></li>
                <li class="breadcrumb-item active">Browse Movies</li>
            </ol>
        </nav>
    </div>
</div>

{!! $site->adbrowse ?? '' !!}

@if(count($results ?? []) > 0)
    <div class="card">
        <div class="card-body px-0 py-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover data mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Posted</th>
                            <th>Size</th>
                            <th>Files</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $result)
                            <tr>
                                <td>
                                    <div class="mb-1">
                                        <a href="{{ url("/details/{$result->guid}") }}" class="title fw-semibold">{{ e(str_replace('.', ' ', $result->searchname ?? '')) }}</a>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary text-white rounded-pill">
                                        <i class="fa fa-folder-open me-1"></i>{{ $result->category_name ?? '' }}
                                    </span>
                                </td>
                                <td>
                                    <span title="{{ $result->postdate ?? '' }}">{{ isset($result->postdate) ? \Carbon\Carbon::parse($result->postdate)->diffForHumans() : '' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-medium">{{ isset($result->size) ? \App\Extensions\helper\formatBytes($result->size) : '' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-medium">{{ $result->totalpart ?? 0 }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ url("/getnzb?id={$result->guid}") }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="fa fa-cloud-download"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-info">
        <i class="fa fa-info-circle me-2"></i>No results found.
    </div>
@endif

