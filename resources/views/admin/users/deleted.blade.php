@extends('layouts.admin')

@section('content')
<div class="space-y-6" x-data="adminDeletedUsers">
    <x-admin.page-header :title="$title" icon="fas fa-trash-restore">
        <x-slot:actions>
            <x-admin.button :href="url('admin/user-list')" tone="gray" icon="fas fa-arrow-left">Back to Active Users</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

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
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-md focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="text"
                               id="email"
                               name="email"
                               value="{{ $email ?? '' }}"
                               placeholder="Filter by email"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-md focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="host" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Host/IP</label>
                        <input type="text"
                               id="host"
                               name="host"
                               value="{{ $host ?? '' }}"
                               placeholder="Filter by host"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-md focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="created_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Created From</label>
                        <input type="date"
                               id="created_from"
                               name="created_from"
                               value="{{ $created_from ?? '' }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="created_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Created To</label>
                        <input type="date"
                               id="created_to"
                               name="created_to"
                               value="{{ $created_to ?? '' }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="deleted_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deleted From</label>
                        <input type="date"
                               id="deleted_from"
                               name="deleted_from"
                               value="{{ $deleted_from ?? '' }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="deleted_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deleted To</label>
                        <input type="date"
                               id="deleted_to"
                               name="deleted_to"
                               value="{{ $deleted_to ?? '' }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-primary-500 focus:border-primary-500">
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-md hover:bg-accent-hover">
                        <i aria-hidden="true" class="fas fa-search mr-2"></i>Filter
                    </button>
                    <a href="{{ url('admin/deleted-users') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                        <i aria-hidden="true" class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>


        <!-- Validation Error Messages (for bulk actions) -->
        <div id="validationError" class="mx-6 mt-4 p-4 bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800 rounded-lg hidden">
            <p class="text-warning-800 dark:text-warning-300">
                <i aria-hidden="true" class="fas fa-exclamation-triangle mr-2"></i><span id="validationErrorMessage"></span>
            </p>
        </div>

        <!-- Deleted Users Table -->
        @if(count($deletedusers) > 0)
            <form method="post" action="{{ url('admin/deleted-users/bulk') }}" id="bulkActionForm">
                @csrf
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bulk Actions:</label>
                        <select name="action" id="bulkAction" class="px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-primary-500 focus:border-primary-500">
                            <option value="">Select Action</option>
                            <option value="restore">Restore Selected</option>
                            <option value="delete">Permanently Delete Selected</option>
                        </select>
                        <button type="submit"
                                id="bulkActionBtn"
                                class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-md hover:bg-accent-hover">
                            <i aria-hidden="true" class="fas fa-check mr-2"></i>Apply
                        </button>
                    </div>
                </div>

                <x-admin.data-table>
                    <x-slot:head>
                                <x-admin.th>
                                    <input aria-label="Select all" type="checkbox" id="selectAll" class="h-4 w-4 text-link dark:text-primary-400 focus:ring-primary-500 border-gray-300 dark:border-gray-600 rounded">
                                </x-admin.th>
                                <x-admin.th :sort="str_starts_with($orderby ?? '', 'username') ? (str_ends_with($orderby, 'desc') ? 'desc' : 'asc') : null">
                                    <a href="{{ url('admin/deleted-users?ob=username_' . ($orderby == 'username_asc' ? 'desc' : 'asc') . ($queryString ? '&' . $queryString : '')) }}" class="hover:text-gray-700 dark:hover:text-gray-300">
                                        Username
                                        @if(str_starts_with($orderby ?? '', 'username'))
                                            <i aria-hidden="true" class="fas fa-sort-{{ str_ends_with($orderby, 'desc') ? 'down' : 'up' }} ml-1"></i>
                                        @endif
                                    </a>
                                </x-admin.th>
                                <x-admin.th :sort="str_starts_with($orderby ?? '', 'email') ? (str_ends_with($orderby, 'desc') ? 'desc' : 'asc') : null">
                                    <a href="{{ url('admin/deleted-users?ob=email_' . ($orderby == 'email_asc' ? 'desc' : 'asc') . ($queryString ? '&' . $queryString : '')) }}" class="hover:text-gray-700 dark:hover:text-gray-300">
                                        Email
                                        @if(str_starts_with($orderby ?? '', 'email'))
                                            <i aria-hidden="true" class="fas fa-sort-{{ str_ends_with($orderby, 'desc') ? 'down' : 'up' }} ml-1"></i>
                                        @endif
                                    </a>
                                </x-admin.th>
                                <x-admin.th>Role</x-admin.th>
                                <x-admin.th>Host</x-admin.th>
                                <x-admin.th>Verified</x-admin.th>
                                <x-admin.th :sort="str_starts_with($orderby ?? '', 'createdat') ? (str_ends_with($orderby, 'desc') ? 'desc' : 'asc') : null">
                                    <a href="{{ url('admin/deleted-users?ob=createdat_' . ($orderby == 'createdat_asc' ? 'desc' : 'asc') . ($queryString ? '&' . $queryString : '')) }}" class="hover:text-gray-700 dark:hover:text-gray-300">
                                        Created
                                        @if(str_starts_with($orderby ?? '', 'createdat'))
                                            <i aria-hidden="true" class="fas fa-sort-{{ str_ends_with($orderby, 'desc') ? 'down' : 'up' }} ml-1"></i>
                                        @endif
                                    </a>
                                </x-admin.th>
                                <x-admin.th :sort="str_starts_with($orderby ?? '', 'deletedat') ? (str_ends_with($orderby, 'desc') ? 'desc' : 'asc') : null">
                                    <a href="{{ url('admin/deleted-users?ob=deletedat_' . ($orderby == 'deletedat_asc' ? 'desc' : 'asc') . ($queryString ? '&' . $queryString : '')) }}" class="hover:text-gray-700 dark:hover:text-gray-300">
                                        Deleted
                                        @if(str_starts_with($orderby ?? '', 'deletedat'))
                                            <i aria-hidden="true" class="fas fa-sort-{{ str_ends_with($orderby, 'desc') ? 'down' : 'up' }} ml-1"></i>
                                        @endif
                                    </a>
                                </x-admin.th>
                                <x-admin.th>Deleted By</x-admin.th>
                                <x-admin.th>GDPR Erasure</x-admin.th>
                                <x-admin.th>Actions</x-admin.th>
                    </x-slot:head>

                            @foreach($deletedusers as $user)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input aria-label="Select item" type="checkbox" name="user_ids[]" value="{{ $user->id }}" class="user-checkbox h-4 w-4 text-link dark:text-primary-400 focus:ring-primary-500 border-gray-300 dark:border-gray-600 rounded">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $user->username }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <x-admin.badge tone="neutral">{{ $user->rolename ?? 'N/A' }}</x-admin.badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $user->host ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($user->verified)
                                            <x-admin.badge tone="success" icon="fas fa-check">Yes</x-admin.badge>
                                        @else
                                            <x-admin.badge tone="danger" icon="fas fa-times">No</x-admin.badge>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $user->created_at ? formatDateTime($user->created_at) : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $user->deleted_at ? formatDateTime($user->deleted_at) : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($user->deleted_by === 'Self')
                                            <x-admin.badge tone="primary" icon="fas fa-user">Self</x-admin.badge>
                                        @elseif($user->deleted_by === 'System')
                                            <x-admin.badge tone="neutral" icon="fas fa-robot">System</x-admin.badge>
                                        @elseif($user->deleted_by)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-warning-100 dark:bg-warning-900 text-warning-800 dark:text-warning-200" title="Deleted by admin: {{ $user->deleted_by }}">
                                                <i aria-hidden="true" class="fas fa-user-shield mr-1"></i>{{ $user->deleted_by }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($user->gdpr_erasure_request_id)
                                            <a href="{{ route('admin.gdpr-requests.show', $user->gdpr_erasure_request_id) }}" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-teal-100 dark:bg-teal-900 text-teal-800 dark:text-teal-200 hover:bg-teal-200 dark:hover:bg-teal-800" title="View completed GDPR erasure request #{{ $user->gdpr_erasure_request_id }}">
                                                <i aria-hidden="true" class="fas fa-shield-alt mr-1"></i>GDPR
                                            </a>
                                        @else
                                            <x-admin.badge tone="neutral" icon="fas fa-minus">No</x-admin.badge>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex gap-2">
                                            <button aria-label="Restore User" type="button"
                                                    class="restore-user-btn text-success-600 dark:text-success-400 hover:text-success-900 dark:hover:text-success-300 bg-transparent border-0 p-0 cursor-pointer"
                                                    title="Restore User"
                                                    data-user-id="{{ $user->id }}"
                                                    data-username="{{ $user->username }}">
                                                <i aria-hidden="true" class="fas fa-undo"></i>
                                            </button>
                                            <button aria-label="Permanently Delete" type="button"
                                                    class="delete-user-btn text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300 bg-transparent border-0 p-0 cursor-pointer"
                                                    title="Permanently Delete"
                                                    data-user-id="{{ $user->id }}"
                                                    data-username="{{ $user->username }}">
                                                <i aria-hidden="true" class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                </x-admin.data-table>
            </form>

            <!-- Pagination -->
            <x-admin.pagination :paginator="$deletedusers" />
        @else
            <x-admin.empty-state icon="fas fa-trash-restore" title="No deleted users found" message="There are no soft-deleted users matching your filters." />
        @endif
    </x-admin.card>
</div>

<!-- Hidden form for individual actions -->
<form id="individualActionForm" method="POST" class="hidden">
    @csrf
</form>
@endsection
