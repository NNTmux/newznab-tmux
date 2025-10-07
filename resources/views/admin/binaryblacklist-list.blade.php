@extends('layouts.admin')

@section('content')
<div class="max-w-full px-4 py-6">
    <div class="bg-white rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800">
                    <i class="fa fa-ban mr-2"></i>{{ $title ?? 'Binary Black/White List' }}
                </h1>
                <a href="{{ url('/admin/binaryblacklist-edit') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-plus mr-2"></i>Add New Blacklist
                </a>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-blue-50 border-b border-blue-100">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fa fa-info-circle text-blue-500 text-2xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        Binaries can be prevented from being added to the index if they match a regex in the blacklist.
                        They can also be included only if they match a regex (whitelist).
                        <strong>Click Edit or on the blacklist to enable/disable.</strong>
                    </p>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div id="message" class="px-6"></div>

        <!-- Table -->
        @if(count($binlist) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Type</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Field</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Regex</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-36">Last Activity</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($binlist as $bin)
                            <tr id="row-{{ $bin->id }}" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900">{{ $bin->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-block truncate max-w-[150px]" title="{{ $bin->groupname }}">
                                        {{ str_replace('alt.binaries', 'a.b', $bin->groupname) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <span class="inline-block truncate max-w-[180px]" title="{{ $bin->description }}">
                                        {{ $bin->description }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($bin->optype == 1)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Black</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">White</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($bin->msgcol == 1)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Subject</span>
                                    @elseif($bin->msgcol == 2)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Poster</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">MessageID</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($bin->status == 1)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Disabled</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="max-w-[200px] break-words">
                                        <a href="{{ url('/admin/binaryblacklist-edit?id=' . $bin->id) }}" class="text-blue-600 hover:text-blue-900" title="{{ htmlspecialchars($bin->regex) }}">
                                            <code class="bg-gray-100 px-2 py-1 rounded text-xs break-all">{{ htmlspecialchars($bin->regex) }}</code>
                                        </a>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                    @if($bin->last_activity)
                                        <span title="{{ $bin->last_activity }}">
                                            <i class="fa fa-clock-o mr-1 text-gray-400"></i>{{ $bin->last_activity }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">Never</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex gap-2 justify-center">
                                        <a href="{{ url('/admin/binaryblacklist-edit?id=' . $bin->id) }}"
                                           class="text-blue-600 hover:text-blue-900"
                                           title="Edit this blacklist">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <button type="button"
                                                class="text-red-600 hover:text-red-900"
                                                onclick="if(confirm('Are you sure? This will delete the blacklist from this list.')) { ajax_binaryblacklist_delete({{ $bin->id }}) }"
                                                title="Delete this blacklist">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Total entries: {{ count($binlist) }}</span>
                    <a href="{{ url('/admin/binaryblacklist-edit') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                        <i class="fa fa-plus mr-2"></i>Add New Blacklist
                    </a>
                </div>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fa fa-ban text-gray-400 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No blacklist entries found</h3>
                <p class="text-gray-500 mb-4">Get started by adding your first blacklist entry.</p>
                <a href="{{ url('/admin/binaryblacklist-edit') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-plus mr-2"></i>Add New Blacklist
                </a>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
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
            const row = document.getElementById('row-' + id);
            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
            showMessage('Blacklist entry deleted successfully', 'success');
        } else {
            showMessage('Error deleting blacklist entry', 'error');
        }
    })
    .catch(error => {
        showMessage('Error deleting blacklist entry', 'error');
    });
}

function showMessage(message, type = 'success') {
    const messageDiv = document.getElementById('message');
    const bgColor = type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
    const textColor = type === 'success' ? 'text-green-800' : 'text-red-800';
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';

    messageDiv.innerHTML = `
        <div class="mt-4 p-4 ${bgColor} border rounded-lg">
            <div class="flex items-center justify-between">
                <p class="${textColor}">
                    <i class="fa fa-${icon} mr-2"></i>${message}
                </p>
                <button type="button" class="${textColor} hover:opacity-75" onclick="this.parentElement.parentElement.remove()">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>
    `;
    setTimeout(() => {
        messageDiv.innerHTML = '';
    }, 5000);
}
</script>
@endpush
@endsection

