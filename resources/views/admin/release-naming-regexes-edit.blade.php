@extends('layouts.admin')

@section('title', $title ?? 'Release Naming Regex Edit')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $title }}</h4>
            </div>
            <div>
                <a href="{{ url('/admin/release_naming_regexes-list') }}" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-arrow-left me-2"></i>Back to List
                </a>
            </div>
        </div>
    </div>

    <div class="card-body">
        @if($error)
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa fa-exclamation-triangle me-2"></i>{{ $error }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form action="{{ url('/admin/release_naming_regexes-edit?action=submit') }}" method="POST" id="regexForm" class="needs-validation">
            @csrf
            <input type="hidden" name="id" value="{{ $regex->id ?? '' }}"/>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="group_regex" class="form-label fw-bold">Group:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-users"></i></span>
                        <input type="text" id="group_regex" name="group_regex" class="form-control" value="{{ htmlspecialchars($regex->group_regex ?? '') }}" required>
                    </div>
                    <small class="text-muted mt-1">
                        Regex to match against a group or multiple groups. Delimiters are already added, and PCRE_CASELESS is added after for case insensitivity.<br>
                        Example of matching a single group: <code>alt\.binaries\.example</code><br>
                        Example of matching multiple groups: <code>alt\.binaries.*</code>
                    </small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="regex" class="form-label fw-bold">Regex:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-code"></i></span>
                        <textarea id="regex" name="regex" class="form-control" rows="4" required>{{ htmlspecialchars($regex->regex ?? '') }}</textarea>
                    </div>
                    <small class="text-muted mt-1">
                        Regex to use when renaming releases.<br>
                        The regex delimiters are not added, you MUST add them. See <a href="http://php.net/manual/en/regexp.reference.delimiters" target="_blank">this</a> page.<br>
                        To make the regex case insensitive, add <code>i</code> after the last delimiter.
                    </small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="description" class="form-label fw-bold">Description:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-align-left"></i></span>
                        <textarea id="description" name="description" class="form-control" rows="3">{{ htmlspecialchars($regex->description ?? '') }}</textarea>
                    </div>
                    <small class="text-muted mt-1">
                        Description for this regex. You can include an example release name this regex would match on.
                    </small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="ordinal" class="form-label fw-bold">Ordinal:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-sort-numeric-asc"></i></span>
                        <input type="number" id="ordinal" name="ordinal" class="form-control" value="{{ $regex->ordinal ?? 0 }}" min="0" required>
                    </div>
                    <small class="text-muted mt-1">
                        The order to run this regex in. Must be a number, 0 or higher.<br>
                        If multiple regex have the same ordinal, MySQL will randomly sort them.
                    </small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Active:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="border rounded p-3 bg-light">
                        @foreach($status_ids as $k => $id)
                            <div class="form-check {{ $loop->last ? 'mb-0' : 'mb-2' }}">
                                <input class="form-check-input" type="radio" name="status" id="status{{ $id }}" value="{{ $id }}" {{ ($regex->status ?? 1) == $id ? 'checked' : '' }}>
                                <label class="form-check-label" for="status{{ $id }}">
                                    {{ $status_names[$k] }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted mt-1">
                        Only active regex are used during the release naming process.
                    </small>
                </div>
            </div>
        </form>
    </div>

    <div class="card-footer">
        <div class="d-flex justify-content-between">
            <a href="{{ url('/admin/release_naming_regexes-list') }}" class="btn btn-outline-secondary">
                <i class="fa fa-times me-2"></i>Cancel
            </a>
            <button type="submit" form="regexForm" class="btn btn-success">
                <i class="fa fa-save me-2"></i>Save Changes
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('regexForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    }
});
</script>
@endpush
@endsection

