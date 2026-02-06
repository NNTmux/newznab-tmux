@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-history mr-2"></i>{{ $title }}
                </h1>
            </div>
        </div>

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
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                    </div>
                    <div>
                        <label for="role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role</label>
                        <select id="role_id"
                                name="role_id"
                                class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
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
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                    </div>
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date From</label>
                        <input type="date"
                               id="date_from"
                               name="date_from"
                               value="{{ $filters['date_from'] }}"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                    </div>
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date To</label>
                        <input type="date"
                               id="date_to"
                               name="date_to"
                               value="{{ $filters['date_to'] }}"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-md hover:bg-blue-700 dark:hover:bg-blue-800">
                            <i class="fa fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="{{ url('admin/user-role-history') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- History Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            User
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Old Role
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            New Role
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Old Expiry
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            New Expiry
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Stacked
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Reason
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Changed By
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($history as $record)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ $record->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($record->user)
                                    <a href="{{ url('admin/user-role-history/' . $record->user_id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
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
                                    <span class="px-2 py-1 text-xs font-medium rounded bg-blue-200 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                        {{ $record->newRole->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                {{ $record->old_expiry_date ? $record->old_expiry_date->format('Y-m-d H:i') : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                {{ $record->new_expiry_date ? $record->new_expiry_date->format('Y-m-d H:i') : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                @if($record->is_stacked)
                                    <span class="px-2 py-1 text-xs font-medium rounded bg-green-200 dark:bg-green-900 text-green-800 dark:text-green-200">
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
                                        <a href="{{ url('admin/user-edit?id=' . $record->user_id) }}"
                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                           title="Edit User">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <a href="{{ url('admin/user-role-history/' . $record->user_id) }}"
                                           class="text-purple-600 dark:text-purple-400 hover:text-purple-900 dark:hover:text-purple-300"
                                           title="View User's History">
                                            <i class="fa fa-history"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <i class="fa fa-info-circle mr-2"></i>No role history records found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($history->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $history->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

