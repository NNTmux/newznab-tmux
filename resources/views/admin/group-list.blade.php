@extends('layouts.admin')

@section('title', $title ?? 'Group List')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ $title }}</h4>
            <div class="d-flex gap-2">
                <a href="{{ url('/admin/group-list-active') }}" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-check-circle me-2"></i>Active Groups
                </a>
                <a href="{{ url('/admin/group-list-inactive') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-times-circle me-2"></i>Inactive Groups
                </a>
                <a href="{{ url('/admin/group-list') }}" class="btn btn-sm btn-outline-info">
                    <i class="fa fa-list me-2"></i>All Groups
                </a>
                <a href="{{ url('/admin/group-bulk') }}" class="btn btn-sm btn-success">
                    <i class="fa fa-plus-circle me-2"></i>Bulk Add
                </a>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="alert alert-info mb-4">
            <i class="fa fa-info-circle me-2"></i>
            Below is a list of all usenet groups available to be indexed. Click 'Activate' to start indexing a group.
            Backfill works independently of active.
        </div>

        @if(isset($msg) && $msg != '')
            <div class="alert alert-success" id="message">{{ $msg }}</div>
        @endif

        @if($grouplist && $grouplist->count() > 0)
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6">
                    <form name="groupsearch" method="GET" class="mb-0">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-search"></i></span>
                            <input id="groupname" type="text" name="groupname" value="{{ $groupname ?? '' }}" class="form-control" placeholder="Search for group...">
                            <button type="submit" class="btn btn-primary">Go</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-4 col-md-6 d-flex justify-content-center align-items-center">
                    <div class="pagination-container overflow-auto w-100 d-flex justify-content-center">
                        {{ $grouplist->onEachSide(5)->links() }}
                    </div>
                </div>
                <div class="col-lg-4 col-md-12 d-flex flex-column justify-content-center align-items-lg-end align-items-center mt-3 mt-lg-0">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#resetAllModal">
                            <i class="fa fa-refresh me-1"></i> Reset All
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#purgeAllModal">
                            <i class="fa fa-trash me-1"></i> Purge All
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>Group</th>
                            <th>First Post</th>
                            <th>Last Post</th>
                            <th>Last Updated</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Backfill</th>
                            <th class="text-center">Releases</th>
                            <th class="text-center">Min Files</th>
                            <th class="text-center">Min Size</th>
                            <th class="text-center">Backfill Days</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grouplist as $group)
                            <tr id="grouprow-{{ $group->id }}">
                                <td>
                                    <a href="{{ url('/admin/group-edit?id=' . $group->id) }}" class="text-decoration-none fw-semibold">{{ str_replace('alt.binaries', 'a.b', $group->name) }}</a>
                                    @if($group->description)
                                        <div class="text-muted small">{{ $group->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span>{{ $group->first_record_postdate }}</span>
                                        <small class="text-muted">{{ \Carbon\Carbon::parse($group->first_record_postdate)->diffForHumans() }}</small>
                                    </div>
                                </td>
                                <td>{{ $group->last_record_postdate }}</td>
                                <td>
                                    <span data-bs-toggle="tooltip" title="{{ $group->last_updated }}">{{ \Carbon\Carbon::parse($group->last_updated)->diffForHumans() }}</span>
                                </td>
                                <td class="text-center" id="group-{{ $group->id }}">
                                    @if($group->active == 1)
                                        <button type="button" onclick="ajax_group_status({{ $group->id }}, 0)" class="btn btn-sm btn-success">
                                            <i class="fa fa-check-circle me-1"></i>Active
                                        </button>
                                    @else
                                        <button type="button" onclick="ajax_group_status({{ $group->id }}, 1)" class="btn btn-sm btn-outline-secondary">
                                            <i class="fa fa-times-circle me-1"></i>Inactive
                                        </button>
                                    @endif
                                </td>
                                <td class="text-center" id="backfill-{{ $group->id }}">
                                    @if($group->backfill == 1)
                                        <button type="button" onclick="ajax_backfill_status({{ $group->id }}, 0)" class="btn btn-sm btn-info">
                                            <i class="fa fa-check-circle me-1"></i>Enabled
                                        </button>
                                    @else
                                        <button type="button" onclick="ajax_backfill_status({{ $group->id }}, 1)" class="btn btn-sm btn-outline-secondary">
                                            <i class="fa fa-times-circle me-1"></i>Disabled
                                        </button>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary">{{ $group->num_releases ?? 0 }}</span>
                                </td>
                                <td class="text-center">
                                    @if(empty($group->minfilestoformrelease))
                                        <span class="text-muted">n/a</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $group->minfilestoformrelease }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(empty($group->minsizetoformrelease))
                                        <span class="text-muted">n/a</span>
                                    @else
                                        <span class="badge bg-secondary">{{ human_filesize($group->minsizetoformrelease) }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary">{{ $group->backfill_target }}</span>
                                </td>
                                <td class="text-center" id="groupdel-{{ $group->id }}">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ url('/admin/group-edit?id=' . $group->id) }}" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit this group">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        <button type="button" onclick="ajax_group_reset({{ $group->id }})" class="btn btn-warning" data-bs-toggle="tooltip" title="Reset this group">
                                            <i class="fa fa-refresh"></i>
                                        </button>
                                        <button type="button" onclick="confirmGroupDelete({{ $group->id }})" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete this group">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                        <button type="button" onclick="confirmGroupPurge({{ $group->id }})" class="btn btn-danger" data-bs-toggle="tooltip" title="Purge this group">
                                            <i class="fa fa-eraser"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle me-2"></i>No groups available (eg. none have been added).
            </div>
        @endif
    </div>

    @if($grouplist && $grouplist->count() > 0)
        <div class="card-footer">
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <form name="groupsearch" method="GET" class="mb-0">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-search"></i></span>
                            <input id="groupname" type="text" name="groupname" value="{{ $groupname ?? '' }}" class="form-control" placeholder="Search for group...">
                            <button type="submit" class="btn btn-primary">Go</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-4 col-md-6 d-flex justify-content-center align-items-center mt-3 mt-lg-0">
                    <div class="pagination-container overflow-auto w-100 d-flex justify-content-center">
                        {{ $grouplist->onEachSide(5)->links() }}
                    </div>
                </div>
                <div class="col-lg-4 col-md-12 d-flex justify-content-lg-end justify-content-center mt-3 mt-lg-0">
                    <div class="text-muted">
                        Showing {{ $grouplist->count() }} of {{ $grouplist->total() }} groups
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Reset All Confirmation Modal -->
<div class="modal fade" id="resetAllModal" tabindex="-1" aria-labelledby="resetAllModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetAllModalLabel">Confirm Reset All Groups</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger"><i class="fa fa-exclamation-triangle me-2"></i>Are you sure you want to reset all groups?</p>
                <p>This will reset the article pointers for all groups back to their current state.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="ajax_group_reset_all()">Reset All</button>
            </div>
        </div>
    </div>
</div>

<!-- Purge All Confirmation Modal -->
<div class="modal fade" id="purgeAllModal" tabindex="-1" aria-labelledby="purgeAllModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="purgeAllModalLabel">Confirm Purge All Groups</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger"><i class="fa fa-exclamation-triangle me-2"></i>Are you sure you want to purge all groups?</p>
                <p>This will delete all releases and binaries for all groups. This action cannot be undone!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="ajax_group_purge_all()">Purge All</button>
            </div>
        </div>
    </div>
</div>

<script>
function ajax_group_status(id, status) {
    $.ajax({
        type: 'POST',
        url: '{{ url('/admin/ajax') }}',
        data: {
            _token: '{{ csrf_token() }}',
            action: 'toggle_group_active_status',
            group_id: id,
            group_status: status
        },
        success: function(data) {
            if (data.success) {
                $('#group-' + id).html(data.html);
            }
        }
    });
}

function ajax_backfill_status(id, status) {
    $.ajax({
        type: 'POST',
        url: '{{ url('/admin/ajax') }}',
        data: {
            _token: '{{ csrf_token() }}',
            action: 'toggle_group_backfill',
            group_id: id,
            backfill: status
        },
        success: function(data) {
            if (data.success) {
                $('#backfill-' + id).html(data.html);
            }
        }
    });
}

function ajax_group_reset(id) {
    if (confirm('Are you sure you want to reset this group?')) {
        $.ajax({
            type: 'POST',
            url: '{{ url('/admin/ajax') }}',
            data: {
                _token: '{{ csrf_token() }}',
                action: 'reset_group',
                group_id: id
            },
            success: function(data) {
                if (data.success) {
                    location.reload();
                }
            }
        });
    }
}

function confirmGroupDelete(id) {
    if (confirm('Are you sure you want to delete this group?')) {
        $.ajax({
            type: 'POST',
            url: '{{ url('/admin/ajax') }}',
            data: {
                _token: '{{ csrf_token() }}',
                action: 'delete_group',
                group_id: id
            },
            success: function(data) {
                if (data.success) {
                    $('#grouprow-' + id).fadeOut();
                }
            }
        });
    }
}

function confirmGroupPurge(id) {
    if (confirm('Are you sure you want to purge this group? This will delete all releases and binaries!')) {
        $.ajax({
            type: 'POST',
            url: '{{ url('/admin/ajax') }}',
            data: {
                _token: '{{ csrf_token() }}',
                action: 'purge_group',
                group_id: id
            },
            success: function(data) {
                if (data.success) {
                    location.reload();
                }
            }
        });
    }
}

function ajax_group_reset_all() {
    $.ajax({
        type: 'POST',
        url: '{{ url('/admin/ajax') }}',
        data: {
            _token: '{{ csrf_token() }}',
            action: 'reset_all_groups'
        },
        success: function(data) {
            if (data.success) {
                $('#resetAllModal').modal('hide');
                location.reload();
            }
        }
    });
}

function ajax_group_purge_all() {
    $.ajax({
        type: 'POST',
        url: '{{ url('/admin/ajax') }}',
        data: {
            _token: '{{ csrf_token() }}',
            action: 'purge_all_groups'
        },
        success: function(data) {
            if (data.success) {
                $('#purgeAllModal').modal('hide');
                location.reload();
            }
        }
    });
}

// Initialize tooltips
$(function () {
    $('[data-bs-toggle="tooltip"]').tooltip();
});
</script>
@endsection

