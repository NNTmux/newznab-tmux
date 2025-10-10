@extends('layouts.admin')

@section('title', $title ?? 'Category Edit')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ $title }}</h4>
            <a href="{{ url('/admin/category-list') }}" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left me-2"></i>Back to Categories
            </a>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger m-3">
            <i class="fa fa-exclamation-circle me-2"></i>{{ session('error') }}
        </div>
    @endif

    <div class="card-body">
        @if($category)
        <form action="{{ url('/admin/category-edit?action=submit') }}" method="POST" id="categoryForm">
            @csrf
            <input type="hidden" name="id" value="{{ $category->id }}"/>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Title:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <p class="form-control-plaintext">{{ $category->title }}</p>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="parentcat" class="form-label fw-bold">Parent Category:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-folder-open"></i></span>
                        <input type="text" class="form-control" value="{{ $category->parent->title ?? 'N/A' }}" disabled>
                    </div>
                    <small class="text-muted">Parent category cannot be changed from this interface</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="description" class="form-label fw-bold">Description:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-align-left"></i></span>
                        <input type="text" id="description" name="description" class="form-control" value="{{ $category->description ?? '' }}"/>
                    </div>
                    <small class="text-muted">Brief explanation of what belongs in this category</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="minsizetoformrelease" class="form-label fw-bold">Minimum Size (Bytes):</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-download"></i></span>
                        <input type="number" id="minsizetoformrelease" name="minsizetoformrelease" class="form-control" value="{{ $category->minsizetoformrelease ?? 0 }}"/>
                    </div>
                    <small class="text-muted">Minimum file size for releases in this category (in bytes). Set to 0 to disable.</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label for="maxsizetoformrelease" class="form-label fw-bold">Maximum Size (Bytes):</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-upload"></i></span>
                        <input type="number" id="maxsizetoformrelease" name="maxsizetoformrelease" class="form-control" value="{{ $category->maxsizetoformrelease ?? 0 }}"/>
                    </div>
                    <small class="text-muted">Maximum file size for releases in this category (in bytes). Set to 0 to disable.</small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Status:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    @foreach($status_ids as $index => $statusId)
                        <div class="form-check form-check-inline">
                            <input type="radio" id="status_{{ $statusId }}" name="status" value="{{ $statusId }}"
                                   class="form-check-input" {{ ($category->status ?? 0) == $statusId ? 'checked' : '' }}>
                            <label for="status_{{ $statusId }}" class="form-check-label">{{ $status_names[$index] }}</label>
                        </div>
                    @endforeach
                    <div class="mt-2">
                        <small class="text-muted">Inactive categories won't appear in menus but can still be used for release matching</small>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-bold">Preview:</label>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="form-check form-check-inline">
                        <input type="radio" id="disablepreview_0" name="disablepreview" value="0"
                               class="form-check-input" {{ ($category->disablepreview ?? 0) == 0 ? 'checked' : '' }}>
                        <label for="disablepreview_0" class="form-check-label">Enabled</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="radio" id="disablepreview_1" name="disablepreview" value="1"
                               class="form-check-input" {{ ($category->disablepreview ?? 0) == 1 ? 'checked' : '' }}>
                        <label for="disablepreview_1" class="form-check-label">Disabled</label>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">Disabling prevents ffmpeg from generating previews for releases in this category</small>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" onclick="window.location='{{ url('/admin/category-list') }}'">
                        <i class="fa fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </form>
        @else
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle me-2"></i>No category selected. Please select a category to edit.
        </div>
        <div class="text-center mt-3">
            <a href="{{ url('/admin/category-list') }}" class="btn btn-primary">
                <i class="fa fa-list me-2"></i>View Category List
            </a>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('categoryForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            const minSize = document.getElementById('minsizetoformrelease').value;
            const maxSize = document.getElementById('maxsizetoformrelease').value;

            // Validate min/max size relationship
            if (parseInt(minSize) > parseInt(maxSize) && parseInt(maxSize) > 0) {
                event.preventDefault();
                alert('Minimum size cannot be greater than maximum size');
                return false;
            }
        });
    }
});
</script>
@endpush
@endsection

