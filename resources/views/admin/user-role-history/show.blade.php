@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-history">
        <x-slot:actions>
            <x-admin.button :href="url('admin/user-edit?id='.$user->id)" icon="fas fa-edit">Edit User</x-admin.button>
            <x-admin.button :href="url('admin/user-role-history')" tone="gray" icon="fas fa-arrow-left">Back to All</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">

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
                            <span class="px-3 py-1 text-sm font-medium rounded bg-primary-200 dark:bg-primary-900 text-primary-800 dark:text-primary-200">
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
                            <span class="ml-2 px-2 py-1 text-xs font-medium rounded bg-danger-200 dark:bg-danger-900 text-danger-800 dark:text-danger-200">
                                Expired
                            </span>
                        @else
                            <span class="ml-2 px-2 py-1 text-xs font-medium rounded bg-success-200 dark:bg-success-900 text-success-800 dark:text-success-200">
                                Active
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- History Table -->
        <x-admin.data-table>
            <x-slot:head>
                        <x-admin.th>
                            Date
                        </x-admin.th>
                        <x-admin.th>
                            Old Role
                        </x-admin.th>
                        <x-admin.th>
                            New Role
                        </x-admin.th>
                        <x-admin.th>
                            Old Expiry
                        </x-admin.th>
                        <x-admin.th>
                            New Expiry
                        </x-admin.th>
                        <x-admin.th>
                            Effective Date
                        </x-admin.th>
                        <x-admin.th>
                            Stacked
                        </x-admin.th>
                        <x-admin.th>
                            Reason
                        </x-admin.th>
                        <x-admin.th>
                            Changed By
                        </x-admin.th>
            </x-slot:head>

                    @forelse($history as $record)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ formatDateTimeSeconds($record->created_at) }}
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
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                {{ $record->effective_date ? formatDateTime($record->effective_date) : '-' }}
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <i aria-hidden="true" class="fas fa-info-circle mr-2"></i>No role history records found for this user
                            </td>
                        </tr>
                    @endforelse
        </x-admin.data-table>

        <!-- Pagination -->
        <x-admin.pagination :paginator="$history" />
    </div>
</div>
@endsection
