@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                    <i class="fa fa-trash-restore mr-2"></i>{{ $title }}
                </h1>
                <a href="{{ url('admin/user-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fa fa-arrow-left mr-2"></i>Back to Active Users
                </a>
            </div>
        </div>

        <!-- Search Filters -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="get" action="{{ url('admin/deleted-users') }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                        <input type="text"
                               id="username"
                               name="username"
                               value="{{ $username ?? '' }}"
                               placeholder="Filter by username"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="text"
                               id="email"
                               name="email"
                               value="{{ $email ?? '' }}"
                               placeholder="Filter by email"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="host" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Host/IP</label>
                        <input type="text"
                               id="host"
                               name="host"
                               value="{{ $host ?? '' }}"
                               placeholder="Filter by host"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="created_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Created From</label>
                        <input type="date"
                               id="created_from"
                               name="created_from"
                               value="{{ $created_from ?? '' }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="created_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Created To</label>
                        <input type="date"
                               id="created_to"
                               name="created_to"
                               value="{{ $created_to ?? '' }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="deleted_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deleted From</label>
                        <input type="date"
                               id="deleted_from"
                               name="deleted_from"
                               value="{{ $deleted_from ?? '' }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="deleted_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deleted To</label>
                        <input type="date"
                               id="deleted_to"
                               name="deleted_to"
                               value="{{ $deleted_to ?? '' }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-md hover:bg-blue-700">
                        <i class="fa fa-search mr-2"></i>Filter
                    </button>
                    <a href="{{ url('admin/deleted-users') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                        <i class="fa fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                <p class="text-green-800 dark:text-green-300">
                    <i class="fa fa-check-circle mr-2"></i>{{ session('success') }}
                </p>
            </div>
        @endif

        @if(session('error'))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-red-800 dark:text-red-300">
                    <i class="fa fa-exclamation-circle mr-2"></i>{{ session('error') }}
                </p>
            </div>
        @endif

        <!-- Deleted Users Table -->
        @if(count($deletedusers) > 0)
            <form method="post" action="{{ url('admin/deleted-users/bulk') }}" id="bulkActionForm">
                @csrf
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bulk Actions:</label>
                        <select name="action" id="bulkAction" class="px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Action</option>
                            <option value="restore">Restore Selected</option>
                            <option value="delete">Permanently Delete Selected</option>
                        </select>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-md hover:bg-blue-700"
                                onclick="return confirmBulkAction()">
                            <i class="fa fa-check mr-2"></i>Apply
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" id="selectAll" class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <a href="{{ url('admin/deleted-users?ob=username_' . ($orderby == 'username_asc' ? 'desc' : 'asc') . ($queryString ? '&' . $queryString : '')) }}" class="hover:text-gray-700 dark:hover:text-gray-300">
                                        Username
                                        @if(str_starts_with($orderby ?? '', 'username'))
                                            <i class="fa fa-sort-{{ str_ends_with($orderby, 'desc') ? 'down' : 'up' }} ml-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <a href="{{ url('admin/deleted-users?ob=email_' . ($orderby == 'email_asc' ? 'desc' : 'asc') . ($queryString ? '&' . $queryString : '')) }}" class="hover:text-gray-700 dark:hover:text-gray-300">
                                        Email
                                        @if(str_starts_with($orderby ?? '', 'email'))
                                            <i class="fa fa-sort-{{ str_ends_with($orderby, 'desc') ? 'down' : 'up' }} ml-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Host</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <a href="{{ url('admin/deleted-users?ob=createdat_' . ($orderby == 'createdat_asc' ? 'desc' : 'asc') . ($queryString ? '&' . $queryString : '')) }}" class="hover:text-gray-700 dark:hover:text-gray-300">
                                        Created
                                        @if(str_starts_with($orderby ?? '', 'createdat'))
                                            <i class="fa fa-sort-{{ str_ends_with($orderby, 'desc') ? 'down' : 'up' }} ml-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <a href="{{ url('admin/deleted-users?ob=deletedat_' . ($orderby == 'deletedat_asc' ? 'desc' : 'asc') . ($queryString ? '&' . $queryString : '')) }}" class="hover:text-gray-700 dark:hover:text-gray-300">
                                        Deleted
                                        @if(str_starts_with($orderby ?? '', 'deletedat'))
                                            <i class="fa fa-sort-{{ str_ends_with($orderby, 'desc') ? 'down' : 'up' }} ml-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($deletedusers as $user)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" class="user-checkbox h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $user->username }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                            {{ $user->rolename ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $user->host ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $user->created_at ? $user->created_at->format('Y-m-d H:i') : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $user->deleted_at ? $user->deleted_at->format('Y-m-d H:i') : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex gap-2">
                                            <a href="{{ url('admin/deleted-users/restore/' . $user->id) }}"
                                               class="text-green-600 hover:text-green-900"
                                               title="Restore User"
                                               onclick="return confirm('Are you sure you want to restore user \'{{ $user->username }}\'?')">
                                                <i class="fa fa-undo"></i>
                                            </a>
                                            <a href="{{ url('admin/deleted-users/permanent-delete/' . $user->id) }}"
                                               class="text-red-600 hover:text-red-900"
                                               title="Permanently Delete"
                                               onclick="return confirm('Are you sure you want to PERMANENTLY delete user \'{{ $user->username }}\'? This action cannot be undone!')">
                                                <i class="fa fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $deletedusers->links() }}
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fa fa-trash-restore text-gray-400 dark:text-gray-600 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No deleted users found</h3>
                <p class="text-gray-500 dark:text-gray-400">There are no soft-deleted users matching your filters.</p>
            </div>
        @endif
    </div>
</div>

<script>
// Select all checkbox functionality
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Confirm bulk action
function confirmBulkAction() {
    const action = document.getElementById('bulkAction').value;
    const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');

    if (!action) {
        alert('Please select an action.');
        return false;
    }

    if (checkedBoxes.length === 0) {
        alert('Please select at least one user.');
        return false;
    }

    if (action === 'delete') {
        return confirm(`Are you sure you want to PERMANENTLY delete ${checkedBoxes.length} user(s)? This action cannot be undone!`);
    } else if (action === 'restore') {
        return confirm(`Are you sure you want to restore ${checkedBoxes.length} user(s)?`);
    }

    return true;
}
</script>
@endsection

