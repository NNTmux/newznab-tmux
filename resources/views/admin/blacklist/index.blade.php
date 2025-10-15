@extends('layouts.admin')

@section('content')
<div class="max-w-full px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                    <i class="fa fa-ban mr-2"></i>{{ $title ?? 'Binary Black/White List' }}
                </h1>
                <a href="{{ url('/admin/binaryblacklist-edit') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                    <i class="fa fa-plus mr-2"></i>Add New Blacklist
                </a>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-blue-50 dark:bg-blue-900/20 border-b border-blue-100 dark:border-blue-800">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fa fa-info-circle text-blue-500 dark:text-blue-400 text-2xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
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
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-16">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Group</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-20">Type</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-24">Field</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-20">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Regex</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-36">Last Activity</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($binlist as $bin)
                            <tr id="row-{{ $bin->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $bin->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    <span class="inline-block truncate max-w-[150px]" title="{{ $bin->groupname }}">
                                        {{ str_replace('alt.binaries', 'a.b', $bin->groupname) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                    <span class="inline-block truncate max-w-[180px]" title="{{ $bin->description }}">
                                        {{ $bin->description }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($bin->optype == 1)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">Black</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">White</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($bin->msgcol == 1)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">Subject</span>
                                    @elseif($bin->msgcol == 2)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300">Poster</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">MessageID</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($bin->status == 1)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">Active</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">Disabled</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="max-w-[200px] break-words">
                                        <a href="{{ url('/admin/binaryblacklist-edit?id=' . $bin->id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300" title="{{ htmlspecialchars($bin->regex) }}">
                                            <code class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-2 py-1 rounded text-xs break-all">{{ htmlspecialchars($bin->regex) }}</code>
                                        </a>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500 dark:text-gray-400">
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
                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                           title="Edit this blacklist">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <button type="button"
                                                class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
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
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-300">Total entries: {{ count($binlist) }}</span>
                    <a href="{{ url('/admin/binaryblacklist-edit') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 text-sm">
                        <i class="fa fa-plus mr-2"></i>Add New Blacklist
                    </a>
                </div>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fa fa-ban text-gray-400 dark:text-gray-500 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No blacklist entries found</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-4">Get started by adding your first blacklist entry.</p>
                <a href="{{ url('/admin/binaryblacklist-edit') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
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
    const bgColor = type === 'success' ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800';
    const textColor = type === 'success' ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300';
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

