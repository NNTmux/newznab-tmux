@extends('layouts.admin')

@section('content')
<div class="max-w-full px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800">
                    <i class="fa fa-folder-open mr-2"></i>{{ $title ?? 'Category Regex List' }}
                </h1>
                <a href="{{ url('/admin/category-regexes-edit') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-plus mr-2"></i>Add New Regex
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
                        This page lists regular expressions used for categorizing releases.<br>
                        You can recategorize all releases by running <code class="bg-blue-100 px-2 py-1 rounded text-xs">misc/update/update_releases 6 true</code>
                    </p>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div id="message" class="px-6"></div>

        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200">
            <form name="groupsearch" action="" method="get" class="max-w-md">
                @csrf
                <label for="group" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search by Group:</label>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa fa-search text-gray-400"></i>
                        </div>
                        <input id="group"
                               type="text"
                               name="group"
                               value="{{ $group }}"
                               class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Search a group...">
                    </div>
                    <button class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700" type="submit">
                        Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Table -->
        @if($regex && count($regex) > 0)
            <!-- Pagination Top -->
            @if(method_exists($regex, 'links'))
                <div class="px-6 py-3 border-b border-gray-200">
                    {{ $regex->onEachSide(5)->links() }}
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Regex</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Ordinal</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Category</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200">
                        @foreach($regex as $row)
                            <tr id="row-{{ $row->id }}" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900">{{ $row->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <code class="bg-blue-50 text-blue-700 px-2 py-1 rounded text-xs">{{ $row->group_regex }}</code>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <span class="inline-block max-w-[200px] truncate" title="{{ $row->description }}">
                                        {{ $row->description }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="max-w-[200px] break-words">
                                        <code class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-xs break-all" title="{{ htmlspecialchars($row->regex) }}">
                                            {{ htmlspecialchars($row->regex) }}
                                        </code>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">{{ $row->ordinal }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($row->status == 1)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fa fa-check-circle mr-1"></i>Active
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fa fa-times-circle mr-1"></i>Disabled
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-800">
                                        <i class="fa fa-folder-open mr-1"></i>{{ $row->categories_id }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex gap-2 justify-center">
                                        <a href="{{ url('/admin/category_regexes-edit?id=' . $row->id) }}"
                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-900"
                                           title="Edit this regex">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <button type="button"
                                                class="text-red-600 hover:text-red-900"
                                                onclick="confirmDelete({{ $row->id }})"
                                                title="Delete this regex">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bottom -->
            @if(method_exists($regex, 'links'))
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $regex->onEachSide(5)->links() }}
                </div>
            @endif
        @else
            <div class="px-6 py-12 text-center">
                <i class="fa fa-exclamation-triangle text-gray-400 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No regex patterns found</h3>
                <p class="text-gray-500 mb-4">Try a different search term or add a new regex.</p>
                <a href="{{ url('/admin/category-regexes-edit') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-plus mr-2"></i>Add New Regex
                </a>
            </div>
        @endif

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50">
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">
                    @if($regex && method_exists($regex, 'total'))
                        Total entries: {{ $regex->total() }}
                    @elseif($regex)
                        Total entries: {{ count($regex) }}
                    @else
                        No entries
                    @endif
                </span>
                <a href="{{ url('/admin/category_regexes-edit') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 text-sm">
                    <i class="fa fa-plus mr-2"></i>Add New Regex
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this regex? This action cannot be undone.')) {
        deleteRegex(id);
    }
}

function deleteRegex(id) {
    fetch('{{ url("/admin/category_regexes-delete") }}?id=' + id, {
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
            showMessage('Regex deleted successfully', 'success');
        } else {
            showMessage('Error deleting regex', 'error');
        }
    })
    .catch(error => {
        showMessage('Error deleting regex', 'error');
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

