@extends('layouts.admin')

@section('title', $title ?? 'Binary Black/White List')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $title }}</h4>
            </div>
            <div>
                <a href="{{ url('/admin/binaryblacklist-edit') }}" class="btn btn-sm btn-primary">
                    <i class="fa fa-plus me-2"></i>Add New Blacklist
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
                        Binaries can be prevented from being added to the index if they match a regex in the blacklist.
                        They can also be included only if they match a regex (whitelist).
                        <strong>Click Edit or on the blacklist to enable/disable.</strong>
                    </p>
                </div>
            </div>
        </div>

        <div id="message"></div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 60px;" class="text-center">ID</th>
                        <th>Group</th>
                        <th>Description</th>
                        <th style="width: 80px;" class="text-center">Type</th>
                        <th style="width: 80px;" class="text-center">Field</th>
                        <th style="width: 80px;" class="text-center">Status</th>
                        <th style="width: 300px;">Regex</th>
                        <th style="width: 150px;" class="text-center">Last Activity</th>
                        <th style="width: 120px;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($binlist as $bin)
                        <tr id="row-{{ $bin->id }}">
                            <td class="text-center fw-bold">{{ $bin->id }}</td>
                            <td>
                                <span class="d-inline-block text-truncate" style="max-width: 150px;" title="{{ $bin->groupname }}">
                                    {{ str_replace('alt.binaries', 'a.b', $bin->groupname) }}
                                </span>
                            </td>
                            <td>
                                <span class="d-inline-block text-truncate" style="max-width: 200px;" title="{{ $bin->description }}">
                                    {{ $bin->description }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($bin->optype == 1)
                                    <span class="badge bg-danger">Black</span>
                                @else
                                    <span class="badge bg-success">White</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($bin->msgcol == 1)
                                    <span class="badge bg-info">Subject</span>
                                @elseif($bin->msgcol == 2)
                                    <span class="badge bg-warning text-dark">Poster</span>
                                @else
                                    <span class="badge bg-secondary">MessageID</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($bin->status == 1)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Disabled</span>
                                @endif
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 290px;">
                                    <a href="{{ url('/admin/binaryblacklist-edit?id=' . $bin->id) }}" class="text-decoration-none" title="{{ htmlspecialchars($bin->regex) }}">
                                        <code>{{ htmlspecialchars($bin->regex) }}</code>
                                    </a>
                                </div>
                            </td>
                            <td class="text-center">
                                @if($bin->last_activity)
                                    <span class="d-inline-block" title="{{ $bin->last_activity }}">
                                        <i class="fa fa-clock-o me-1 text-muted"></i>{{ $bin->last_activity }}
                                    </span>
                                @else
                                    <span class="text-muted">Never</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="{{ url('/admin/binaryblacklist-edit?id=' . $bin->id) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit this blacklist">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="if(confirm('Are you sure? This will delete the blacklist from this list.')) { ajax_binaryblacklist_delete({{ $bin->id }}) }" data-bs-toggle="tooltip" title="Delete this blacklist">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="alert alert-warning mb-0">
                                    <i class="fa fa-exclamation-triangle me-2"></i>No blacklist entries found
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span class="text-muted">Total entries: {{ count($binlist) }}</span>
            </div>
            <div>
                <a href="{{ url('/admin/binaryblacklist-edit') }}" class="btn btn-sm btn-primary">
                    <i class="fa fa-plus me-2"></i>Add New Blacklist
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function ajax_binaryblacklist_delete(id) {
    fetch('{{ url("/admin/binaryblacklist-delete") }}?id=' + id, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('row-' + id).remove();
            showMessage('Blacklist entry deleted successfully', 'success');
        } else {
            showMessage('Error deleting blacklist entry', 'danger');
        }
    })
    .catch(error => {
        showMessage('Error deleting blacklist entry', 'danger');
    });
}

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

