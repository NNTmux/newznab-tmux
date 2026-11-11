@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-history" />
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">

        <!-- Search Filters -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="get" action="{{ url('admin/user-role-history') }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                        <input type="text"
                               id="username"
                               name="username"
                               value="{{ $filters['username'] }}"
                               placeholder="Filter by username"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400">
                    </div>
                    <div>
                        <label for="role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role</label>
                        <select id="role_id"
                                name="role_id"
                                class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400">
                            <option value="">All Roles</option>
                            @foreach($roles as $roleId => $roleName)
                                <option value="{{ $roleId }}" {{ $filters['role_id'] == $roleId ? 'selected' : '' }}>
                                    {{ $roleName }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="change_reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Change Reason</label>
                        <input type="text"
                               id="change_reason"
                               name="change_reason"
                               value="{{ $filters['change_reason'] }}"
                               placeholder="Filter by reason"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400">
                    </div>
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date From</label>
                        <input type="date"
                               id="date_from"
                               name="date_from"
                               value="{{ $filters['date_from'] }}"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400">
                    </div>
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date To</label>
                        <input type="date"
                               id="date_to"
                               name="date_to"
                               value="{{ $filters['date_to'] }}"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-md hover:bg-accent-hover dark:hover:bg-primary-800">
                            <i aria-hidden="true" class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="{{ url('admin/user-role-history') }}" class="text-sm text-link dark:text-primary-400 hover:underline">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- History Table -->
        <x-admin.data-table>
            <x-slot:head>
                <x-admin.th scope="col">Date</x-admin.th>
                <x-admin.th scope="col">User</x-admin.th>
                <x-admin.th scope="col">Old Role</x-admin.th>
                <x-admin.th scope="col">New Role</x-admin.th>
                <x-admin.th scope="col">Old Expiry</x-admin.th>
                <x-admin.th scope="col">New Expiry</x-admin.th>
                <x-admin.th scope="col">Stacked</x-admin.th>
                <x-admin.th scope="col">Reason</x-admin.th>
                <x-admin.th scope="col">Changed By</x-admin.th>
                <x-admin.th scope="col">Actions</x-admin.th>
            </x-slot:head>

                    @forelse($history as $record)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ formatDateTimeSeconds($record->created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($record->user)
                                    <a href="{{ url('admin/user-role-history/' . $record->user_id) }}" class="text-link dark:text-primary-400 hover:underline">
                                        {{ $record->user->username }}
                                    </a>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">User #{{ $record->user_id }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                @if($record->oldRole)
                                    <span class="px-2 py-1 text-xs font-medium rounded bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                        {{ $record->oldRole->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                @if($record->newRole)
                                    <span class="px-2 py-1 text-xs font-medium rounded bg-primary-200 dark:bg-primary-900 text-primary-800 dark:text-primary-200">
                                        {{ $record->newRole->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                {{ $record->old_expiry_date ? formatDateTime($record->old_expiry_date) : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                {{ $record->new_expiry_date ? formatDateTime($record->new_expiry_date) : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                @if($record->is_stacked)
                                    <span class="px-2 py-1 text-xs font-medium rounded bg-success-200 dark:bg-success-900 text-success-800 dark:text-success-200">
                                        Yes
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">No</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                {{ $record->change_reason ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                @if($record->changedByUser)
                                    {{ $record->changedByUser->username }}
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">System</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex gap-2">
                                    @if($record->user)
                                        <a aria-label="Edit User" href="{{ url('admin/user-edit?id=' . $record->user_id) }}"
                                           class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                           title="Edit User">
                                            <i aria-hidden="true" class="fas fa-edit"></i>
                                        </a>
                                        <a aria-label="View User's History" href="{{ url('admin/user-role-history/' . $record->user_id) }}"
                                           class="text-purple-600 dark:text-purple-400 hover:text-purple-900 dark:hover:text-purple-300"
                                           title="View User's History">
                                            <i aria-hidden="true" class="fas fa-history"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <i aria-hidden="true" class="fas fa-info-circle mr-2"></i>No role history records found
                            </td>
                        </tr>
                    @endforelse
        </x-admin.data-table>

        <!-- Pagination -->
        <x-admin.pagination :paginator="$history" />
    </div>
</div>
@endsection
