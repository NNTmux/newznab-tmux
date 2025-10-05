@extends('layouts.admin')

@section('title', $title ?? 'Binary Black/Whitelist Edit')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $title }}</h4>
            </div>
            <div>
                <a href="{{ url('/admin/binaryblacklist-list') }}" class="btn btn-sm btn-outline-primary">
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

        <form action="{{ url('/admin/binaryblacklist-edit?action=submit') }}" method="POST" id="blacklistForm" class="needs-validation">
            @csrf
            <input type="hidden" name="id" value="{{ $regex->id ?? '' }}"/>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="groupname" class="form-label fw-bold">Group Name:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-layer-group"></i></span>
                        <input type="text" id="groupname" name="groupname" class="form-control" value="{{ htmlspecialchars($regex->groupname ?? '') }}" required>
                    </div>
                    <small class="text-muted mt-1">
                        <i class="fa fa-info-circle me-1"></i>The full name of a valid newsgroup. (Wildcard in the format 'alt.binaries.*')
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
                        <i class="fa fa-info-circle me-1"></i>The regex to be applied. (Note: Beginning and Ending / are already included)
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
                        <i class="fa fa-info-circle me-1"></i>A description for this regex
                    </small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Message Field:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="border rounded p-3 bg-light">
                        @foreach($msgcol_ids as $i => $id)
                            <div class="form-check {{ $loop->last ? 'mb-0' : 'mb-2' }}">
                                <input class="form-check-input" type="radio" name="msgcol" id="msgcol{{ $id }}" value="{{ $id }}" {{ ($regex->msgcol ?? 1) == $id ? 'checked' : '' }}>
                                <label class="form-check-label" for="msgcol{{ $id }}">
                                    {{ $msgcol_names[$i] }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted mt-1">
                        <i class="fa fa-info-circle me-1"></i>Which field in the message to apply the black/white list to.
                    </small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Status:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="border rounded p-3 bg-light">
                        @foreach($status_ids as $i => $id)
                            <div class="form-check {{ $loop->last ? 'mb-0' : 'mb-2' }}">
                                <input class="form-check-input" type="radio" name="status" id="status{{ $id }}" value="{{ $id }}" {{ ($regex->status ?? 1) == $id ? 'checked' : '' }}>
                                <label class="form-check-label" for="status{{ $id }}">
                                    {{ $status_names[$i] }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted mt-1">
                        <i class="fa fa-info-circle me-1"></i>Only active regexes are applied during the release process.
                    </small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Type:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="border rounded p-3 bg-light">
                        @foreach($optype_ids as $i => $id)
                            <div class="form-check {{ $loop->last ? 'mb-0' : 'mb-2' }}">
                                <input class="form-check-input" type="radio" name="optype" id="optype{{ $id }}" value="{{ $id }}" {{ ($regex->optype ?? 1) == $id ? 'checked' : '' }}>
                                <label class="form-check-label" for="optype{{ $id }}">
                                    {{ $optype_names[$i] }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted mt-1">
                        <i class="fa fa-info-circle me-1"></i>Black will exclude all messages for a group which match this regex. White will include only those which match.
                    </small>
                </div>
            </div>
        </form>
    </div>

    <div class="card-footer">
        <div class="d-flex justify-content-between">
            <a href="{{ url('/admin/binaryblacklist-list') }}" class="btn btn-outline-secondary">
                <i class="fa fa-times me-2"></i>Cancel
            </a>
            <button type="submit" form="blacklistForm" class="btn btn-success">
                <i class="fa fa-save me-2"></i>Save Changes
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('blacklistForm');
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

