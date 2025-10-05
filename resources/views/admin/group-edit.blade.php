@extends('layouts.admin')

@section('title', $title ?? 'Group Edit')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ $title }}</h4>
            <a href="{{ url('/admin/group-list') }}" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left me-2"></i>Back to Groups
            </a>
        </div>
    </div>

    @if(isset($error) && $error != '')
        <div class="alert alert-danger m-3">
            <i class="fa fa-exclamation-circle me-2"></i>{{ $error }}
        </div>
    @endif

    <div class="card-body">
        <form action="{{ url('/admin/group-edit?action=submit') }}" method="POST" id="groupForm">
            @csrf
            <input type="hidden" name="id" value="{{ $group['id'] ?? '' }}"/>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="name" class="form-label fw-bold">Group Name:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-users"></i></span>
                        <input type="text" id="name" name="name" class="form-control" value="{{ $group['name'] ?? '' }}"/>
                    </div>
                    <small class="text-muted">Changing the name to an invalid group will break things.</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="description" class="form-label fw-bold">Description:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-align-left"></i></span>
                        <textarea id="description" name="description" class="form-control" rows="3">{{ $group['description'] ?? '' }}</textarea>
                    </div>
                    <small class="text-muted">Brief explanation of this group's content</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="backfill_target" class="form-label fw-bold">Backfill Days:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                        <input type="number" id="backfill_target" name="backfill_target" class="form-control" value="{{ $group['backfill_target'] ?? 0 }}"/>
                    </div>
                    <small class="text-muted">Number of days to attempt to backfill this group. Adjust as necessary.</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="minfilestoformrelease" class="form-label fw-bold">Minimum Files To Form Release:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-file"></i></span>
                        <input type="number" id="minfilestoformrelease" name="minfilestoformrelease" class="form-control" value="{{ $group['minfilestoformrelease'] ?? 0 }}"/>
                    </div>
                    <small class="text-muted">The minimum number of files to make a release. If left blank, will use the site wide setting.</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="minsizetoformrelease" class="form-label fw-bold">Minimum File Size (bytes):</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-download"></i></span>
                        <input type="number" id="minsizetoformrelease" name="minsizetoformrelease" class="form-control" value="{{ $group['minsizetoformrelease'] ?? 0 }}"/>
                    </div>
                    <small class="text-muted">The minimum total size in bytes to make a release. If left blank, will use the site wide setting.</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="first_record" class="form-label fw-bold">First Record ID:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-angle-double-left"></i></span>
                        <input type="number" id="first_record" name="first_record" class="form-control" value="{{ $group['first_record'] ?? 0 }}"/>
                    </div>
                    <small class="text-muted">The oldest record number for the group.</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="last_record" class="form-label fw-bold">Last Record ID:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-angle-double-right"></i></span>
                        <input type="number" id="last_record" name="last_record" class="form-control" value="{{ $group['last_record'] ?? 0 }}"/>
                    </div>
                    <small class="text-muted">The newest record number for the group.</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Active:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="form-check form-check-inline">
                        <input type="radio" id="active_1" name="active" value="1" class="form-check-input" {{ (isset($group['active']) && $group['active'] == 1) ? 'checked' : '' }}>
                        <label for="active_1" class="form-check-label">Yes</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="radio" id="active_0" name="active" value="0" class="form-check-input" {{ (isset($group['active']) && $group['active'] == 0) ? 'checked' : '' }}>
                        <label for="active_0" class="form-check-label">No</label>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">Inactive groups will not have headers downloaded for them.</small>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Backfill:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="form-check form-check-inline">
                        <input type="radio" id="backfill_1" name="backfill" value="1" class="form-check-input" {{ (isset($group['backfill']) && $group['backfill'] == 1) ? 'checked' : '' }}>
                        <label for="backfill_1" class="form-check-label">Yes</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="radio" id="backfill_0" name="backfill" value="0" class="form-check-input" {{ (isset($group['backfill']) && $group['backfill'] == 0) ? 'checked' : '' }}>
                        <label for="backfill_0" class="form-check-label">No</label>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">If set to No, backfill will ignore this group. This works even if the above setting is No.</small>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card-footer">
        <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary" onclick="window.location='{{ url('/admin/group-list') }}'">
                <i class="fa fa-times me-2"></i>Cancel
            </button>
            <button type="submit" form="groupForm" class="btn btn-success">
                <i class="fa fa-save me-2"></i>Save Changes
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('groupForm');
    form.addEventListener('submit', function(event) {
        // Basic validation example - can be expanded
        const firstRecord = document.getElementById('first_record').value;
        const lastRecord = document.getElementById('last_record').value;

        // Validate record IDs relationship
        if (parseInt(firstRecord) > parseInt(lastRecord) && parseInt(lastRecord) > 0) {
            event.preventDefault();
            alert('First record ID cannot be greater than last record ID');
            return false;
        }
    });
});
</script>
@endsection

