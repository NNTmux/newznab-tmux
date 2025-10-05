@extends('layouts.admin')

@section('title', $title ?? 'Failed Releases List')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ $title }}</h4>
            <a href="{{ url('/admin/release-list') }}" class="btn btn-outline-secondary">
                <i class="fa fa-list me-2"></i>View All Releases
            </a>
        </div>
    </div>

    <div class="card-body">
        <form name="failrelsearch" method="get" action="{{ url('/admin/failrel-list') }}" id="failrel-search-form" class="mb-4">
            <div class="row">
                <div class="col-md-6 offset-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search by name or category"
                               id="failrelsearch" name="failrelsearch" value="{{ request('failrelsearch') }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-search me-2"></i>Search
                        </button>
                    </div>
                </div>
            </div>
        </form>

        @if($releaselist && $releaselist->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th class="text-end">Size</th>
                            <th class="text-center">Files</th>
                            <th>Post Date</th>
                            <th>Add Date</th>
                            <th class="text-center">Grabs</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($releaselist as $release)
                            <tr>
                                <td>
                                    <a href="{{ url('/admin/release-edit?id=' . $release->guid) }}" class="title fw-semibold text-truncate d-inline-block" style="max-width: 300px;" title="{{ $release->name }}">
                                        {{ $release->searchname }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ $release->category_name ?? 'Unknown' }}</span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-info">{{ human_filesize($release->size) }}</span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ url('/admin/release-files?id=' . $release->guid) }}" class="badge bg-primary">
                                        <i class="fa fa-file me-1"></i>{{ $release->totalpart ?? 0 }}
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-calendar text-muted me-2"></i>
                                        <span title="{{ $release->postdate }}">{{ \Carbon\Carbon::parse($release->postdate)->format('Y-m-d H:i') }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-calendar-plus-o text-muted me-2"></i>
                                        <span title="{{ $release->adddate }}">{{ \Carbon\Carbon::parse($release->adddate)->format('Y-m-d H:i') }}</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success">
                                        <i class="fa fa-download me-1"></i>{{ $release->grabs ?? 0 }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ url('/admin/release-edit?id=' . $release->guid) }}" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit release">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        @if($release->guid)
                                            <a href="{{ url('/details/' . $release->guid) }}" class="btn btn-info" data-bs-toggle="tooltip" title="View release">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        @endif
                                        <a href="{{ url('/admin/release-delete/' . $release->guid) }}" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete release" onclick="return confirm('Are you sure you want to delete this release?')">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="alert alert-info">
                <i class="fa fa-info-circle me-2"></i>No failed releases available.
            </div>
        @endif
    </div>

    @if($releaselist && $releaselist->count() > 0)
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    Showing {{ $releaselist->firstItem() }} to {{ $releaselist->lastItem() }} of {{ $releaselist->total() }} failed releases
                </div>
                <div class="pagination-container overflow-auto">
                    {{ $releaselist->onEachSide(5)->links() }}
                </div>
            </div>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<style>
/* Improve table responsiveness */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Badge styling */
.badge {
    font-weight: 500;
    padding: 0.4em 0.6em;
}

/* Pagination container */
.pagination-container {
    max-width: 100%;
}

/* Improve action buttons on small screens */
@media (max-width: 767.98px) {
    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
}
</style>
@endsection

