@extends('layouts.admin')

@section('title', $title ?? 'Collection Regex List')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $title }}</h4>
            </div>
            <div>
                <a href="{{ url('/admin/collection_regexes-edit') }}" class="btn btn-sm btn-primary">
                    <i class="fa fa-plus me-2"></i>Add New Regex
                </a>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="alert alert-info mb-4">
            <div class="d-flex">
                <div class="me-3">
                    <i class="fa fa-info-circle fa-2x"></i>
                </div>
                <div>
                    <p class="mb-0">
                        This page lists regular expressions used for grouping binaries into collections.<br>
                        You can test your regex patterns using the test feature.
                    </p>
                </div>
            </div>
        </div>

        <div id="message"></div>

        <div class="row mb-4">
            <div class="col-md-6">
                <form name="groupsearch" action="" method="get">
                    @csrf
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-search"></i></span>
                        <input id="group" type="text" name="group" value="{{ $group }}" class="form-control" placeholder="Search a group...">
                        <button class="btn btn-primary" type="submit">Search</button>
                    </div>
                </form>
            </div>
        </div>

        @if($regex && count($regex) > 0)
            @if(method_exists($regex, 'links'))
                <div class="mb-3">
                    {{ $regex->onEachSide(5)->links() }}
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 70px">ID</th>
                            <th>Group</th>
                            <th>Description</th>
                            <th>Regex</th>
                            <th style="width: 90px">Ordinal</th>
                            <th style="width: 100px">Status</th>
                            <th style="width: 120px" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($regex as $row)
                            <tr id="row-{{ $row->id }}">
                                <td>{{ $row->id }}</td>
                                <td>
                                    <code class="text-primary">{{ $row->group_regex }}</code>
                                </td>
                                <td>
                                    <span data-bs-toggle="tooltip" title="{{ $row->description }}">{{ \Illuminate\Support\Str::limit($row->description, 50) }}</span>
                                </td>
                                <td>
                                    <code class="regex-code" data-bs-toggle="tooltip" title="{{ htmlspecialchars($row->regex) }}">{{ \Illuminate\Support\Str::limit(htmlspecialchars($row->regex), 50) }}</code>
                                </td>
                                <td class="text-center">{{ $row->ordinal }}</td>
                                <td class="text-center">
                                    @if($row->status == 1)
                                        <span class="badge bg-success"><i class="fa fa-check-circle me-1"></i>Active</span>
                                    @else
                                        <span class="badge bg-danger"><i class="fa fa-times-circle me-1"></i>Disabled</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="{{ url('/admin/collection_regexes-edit?id=' . $row->id) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit this regex">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete({{ $row->id }})" data-bs-toggle="tooltip" title="Delete this regex">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($regex, 'links'))
                <div class="mt-4">
                    {{ $regex->onEachSide(5)->links() }}
                </div>
            @endif
        @else
            <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle me-2"></i>No regex patterns found. Try a different search term or add a new regex.
            </div>
        @endif
    </div>

    <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span class="text-muted">
                    @if($regex && method_exists($regex, 'total'))
                        Total entries: {{ $regex->total() }}
                    @elseif($regex)
                        Total entries: {{ count($regex) }}
                    @else
                        No entries
                    @endif
                </span>
            </div>
            <div>
                <a href="{{ url('/admin/collection_regexes-edit') }}" class="btn btn-sm btn-primary">
                    <i class="fa fa-plus me-2"></i>Add New Regex
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this regex? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

let deleteId = null;

function confirmDelete(id) {
    deleteId = id;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (deleteId) {
        fetch('{{ url("/admin/collection_regexes-delete") }}?id=' + deleteId, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('row-' + deleteId).remove();
                showMessage('Regex deleted successfully', 'success');
                bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
            } else {
                showMessage('Error deleting regex', 'danger');
            }
        })
        .catch(error => {
            showMessage('Error deleting regex', 'danger');
        });
    }
});

function showMessage(message, type = 'success') {
    const messageDiv = document.getElementById('message');
    messageDiv.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="fa fa-${type === 'success' ? 'check' : 'exclamation'}-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    setTimeout(() => {
        messageDiv.innerHTML = '';
    }, 5000);
}
</script>
@endpush
@endsection

