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
                <div class="flex gap-2">
                    <a href="{{ url('admin/user-edit?id=' . $user->id) }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                        <i class="fa fa-edit mr-2"></i>Edit User
                    </a>
                    <a href="{{ url('admin/user-role-history') }}" class="px-4 py-2 bg-gray-600 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-700 dark:hover:bg-gray-800">
                        <i class="fa fa-arrow-left mr-2"></i>Back to All
                    </a>
                </div>
            </div>
        </div>

        <!-- User Info -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $user->username }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                    <div class="text-lg text-gray-900 dark:text-gray-100">{{ $user->email }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Role</label>
                    <div class="text-lg text-gray-900 dark:text-gray-100">
                        @if($user->role)
                            <span class="px-3 py-1 text-sm font-medium rounded bg-blue-200 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                {{ $user->role->name }}
                            </span>
                        @else
                            <span class="text-gray-500">No role assigned</span>
                        @endif
                    </div>
                </div>
            </div>
            @if($user->rolechangedate)
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Role Expiry</label>
                    <div class="text-lg text-gray-900 dark:text-gray-100">
                        {{ \Carbon\Carbon::parse($user->rolechangedate)->format('Y-m-d H:i:s') }}
                        @if(\Carbon\Carbon::parse($user->rolechangedate)->isPast())
                            <span class="ml-2 px-2 py-1 text-xs font-medium rounded bg-red-200 dark:bg-red-900 text-red-800 dark:text-red-200">
                                Expired
                            </span>
                        @else
                            <span class="ml-2 px-2 py-1 text-xs font-medium rounded bg-green-200 dark:bg-green-900 text-green-800 dark:text-green-200">
                                Active
                            </span>
                        @endif
                    </div>
                </div>
            @endif
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
                            Effective Date
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
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($history as $record)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ $record->created_at->format('Y-m-d H:i:s') }}
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
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                {{ $record->effective_date ? $record->effective_date->format('Y-m-d H:i') : '-' }}
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <i class="fa fa-info-circle mr-2"></i>No role history records found for this user
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

