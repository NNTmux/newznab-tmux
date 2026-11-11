@extends('layouts.admin')

@section('content')
<div class="space-y-6" x-data="adminUserList">
    <x-admin.page-header :title="$title" icon="fas fa-users">
        <x-slot:actions>
            <x-admin.button :href="url('admin/user-edit?action=add')" icon="fas fa-plus">Add New User</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Search Filters -->
        <x-admin.filter-panel>
            <form method="get" action="{{ url('admin/user-list') }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                        <input type="text"
                               id="username"
                               name="username"
                               value="{{ $username ?? '' }}"
                               placeholder="Filter by username"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="text"
                               id="email"
                               name="email"
                               value="{{ $email ?? '' }}"
                               placeholder="Filter by email"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400">
                    </div>
                    <div>
                        <label for="host" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Host/IP</label>
                        <input type="text"
                               id="host"
                               name="host"
                               value="{{ $host ?? '' }}"
                               placeholder="Filter by host"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400">
                    </div>
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role</label>
                        <select id="role"
                                name="role"
                                class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400">
                            <option value="">All Roles</option>
                            @foreach($role_ids ?? [] as $index => $roleId)
                                <option value="{{ $roleId }}" {{ ($role ?? '') == $roleId ? 'selected' : '' }}>
                                    {{ $role_names[$roleId] ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="verified" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Verified</label>
                        <select id="verified"
                                name="verified"
                                class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400">
                            <option value="" {{ ($verified ?? '') === '' ? 'selected' : '' }}>All Users</option>
                            <option value="1" {{ ($verified ?? '') === '1' ? 'selected' : '' }}>Verified</option>
                            <option value="0" {{ ($verified ?? '') === '0' ? 'selected' : '' }}>Not Verified</option>
                        </select>
                    </div>
                    <div>
                        <label for="created_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Registered From</label>
                        <input type="date"
                               id="created_from"
                               name="created_from"
                               value="{{ $created_from ?? '' }}"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400">
                    </div>
                    <div>
                        <label for="created_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Registered To</label>
                        <input type="date"
                               id="created_to"
                               name="created_to"
                               value="{{ $created_to ?? '' }}"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400">
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <x-admin.button type="submit" icon="fas fa-search">Filter</x-admin.button>
                    <x-admin.button :href="url('admin/user-list')" tone="gray" icon="fas fa-times">Clear</x-admin.button>
                </div>
            </form>
        </x-admin.filter-panel>

        <!-- Validation Messages -->
        @if($errors->any())
            <div class="mx-6 mt-4 p-4 bg-danger-50 dark:bg-danger-900 border border-danger-200 dark:border-danger-700 rounded-lg">
                <p class="text-danger-800 dark:text-danger-200">
                    <i aria-hidden="true" class="fas fa-exclamation-circle mr-2"></i>{{ $errors->first() }}
                </p>
            </div>
        @endif

        <div id="validationError" class="mx-6 mt-4 p-4 bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800 rounded-lg hidden">
            <p class="text-warning-800 dark:text-warning-300">
                <i aria-hidden="true" class="fas fa-exclamation-triangle mr-2"></i><span id="validationErrorMessage"></span>
            </p>
        </div>

        <!-- User Table -->
        @if(count($userlist) > 0)
            <form method="post" action="{{ route('admin.user-list.bulk') }}" id="bulkUserActionForm" class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                @csrf
                <div class="flex flex-col md:flex-row md:items-center gap-3">
                    <label for="bulkUserAction" class="text-sm font-medium text-gray-700 dark:text-gray-300">Bulk Actions:</label>
                    <select name="action" id="bulkUserAction" class="w-full md:w-auto px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Select Action</option>
                        <option value="verify">Mark Selected Verified</option>
                        <option value="resend_verification">Resend Verification Email</option>
                        <option value="delete">Soft Delete Selected</option>
                    </select>
                    <x-admin.button type="submit"
                            id="bulkUserActionBtn"
                            class="w-full md:w-auto"
                            icon="fas fa-check">
                        Apply
                    </x-admin.button>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Select non-admin active users below. Deleted users are managed from the Deleted Users page.
                    </p>
                </div>
            </form>

            <!-- Top Scrollbar -->
            <div class="overflow-x-auto border-b border-gray-200 dark:border-gray-700" id="topScroll">
                <div class="h-px" id="topScrollContent"></div>
            </div>

            <div class="overflow-x-auto" id="bottomScroll">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <x-admin.th>
                                <input aria-label="Select all active users on this page" type="checkbox" id="selectAllUserList" class="h-4 w-4 text-link dark:text-primary-400 focus:ring-primary-500 border-gray-300 dark:border-gray-600 rounded" title="Select all active users on this page">
                            </x-admin.th>
                            <x-admin.th class="hidden xl:table-cell">ID</x-admin.th>
                            <x-admin.th :sort="request('ob') === 'username_asc' ? 'asc' : (request('ob') === 'username_desc' ? 'desc' : null)" :href="url('admin/user-list?' . http_build_query(array_merge(request()->except('ob'), ['ob' => request('ob') === 'username_asc' ? 'username_desc' : 'username_asc'])))">Username</x-admin.th>
                            <x-admin.th>Email</x-admin.th>
                            <x-admin.th :sort="request('ob') === 'apiaccess_asc' ? 'asc' : (request('ob') === 'apiaccess_desc' ? 'desc' : null)" :href="url('admin/user-list?' . http_build_query(array_merge(request()->except('ob'), ['ob' => request('ob') === 'apiaccess_asc' ? 'apiaccess_desc' : 'apiaccess_asc'])))">Status</x-admin.th>
                            <x-admin.th>Role</x-admin.th>
                            <x-admin.th class="hidden xl:table-cell">
                                <i aria-hidden="true" class="fas fa-layer-group mr-1"></i>Pending Role
                            </x-admin.th>
                            <x-admin.th class="hidden lg:table-cell" :sort="request('ob') === 'rolechangedate_asc' ? 'asc' : (request('ob') === 'rolechangedate_desc' ? 'desc' : null)" :href="url('admin/user-list?' . http_build_query(array_merge(request()->except('ob'), ['ob' => request('ob') === 'rolechangedate_asc' ? 'rolechangedate_desc' : 'rolechangedate_asc'])))">Role Expiry</x-admin.th>
                            <x-admin.th class="hidden xl:table-cell">Host</x-admin.th>
                            <x-admin.th class="hidden xl:table-cell">Country</x-admin.th>
                            <x-admin.th>Verified</x-admin.th>
                            <x-admin.th class="hidden lg:table-cell" :sort="request('ob') === 'createdat_asc' ? 'asc' : (request('ob') === 'createdat_desc' ? 'desc' : null)" :href="url('admin/user-list?' . http_build_query(array_merge(request()->except('ob'), ['ob' => request('ob') === 'createdat_asc' ? 'createdat_desc' : 'createdat_asc'])))">Created</x-admin.th>
                            <x-admin.th class="hidden xl:table-cell" title="Daily API requests in last 24 hours">
                                <i aria-hidden="true" class="fas fa-code mr-1"></i>Daily API
                            </x-admin.th>
                            <x-admin.th class="hidden xl:table-cell" title="Daily downloads in last 24 hours">
                                <i aria-hidden="true" class="fas fa-download mr-1"></i>Daily DLs
                            </x-admin.th>
                            <x-admin.th>Actions</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($userlist as $user)
                            @php
                                $isAdminUser = (int) $user->roles_id === \App\Enums\UserRole::ADMIN->value
                                    || ($user->rolename ?? null) === \App\Enums\UserRole::ADMIN->label()
                                    || $user->roles->contains('name', \App\Enums\UserRole::ADMIN->label());
                                $isVerified = $user->hasVerifiedEmail();
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if(!$user->deleted_at && !$isAdminUser)
                                        <input aria-label="Select item" type="checkbox" name="user_ids[]" value="{{ $user->id }}" form="bulkUserActionForm" data-is-verified="{{ $isVerified ? '1' : '0' }}" class="user-list-checkbox h-4 w-4 text-link dark:text-primary-400 focus:ring-primary-500 border-gray-300 dark:border-gray-600 rounded">
                                    @elseif($isAdminUser)
                                        <input aria-label="Admin users cannot be selected for bulk actions" type="checkbox" disabled class="h-4 w-4 text-gray-300 dark:text-gray-600 border-gray-300 dark:border-gray-600 rounded cursor-not-allowed" title="Admin users cannot be selected for bulk actions">
                                    @else
                                        <input aria-label="Deleted users cannot be selected here" type="checkbox" disabled class="h-4 w-4 text-gray-300 dark:text-gray-600 border-gray-300 dark:border-gray-600 rounded cursor-not-allowed" title="Deleted users cannot be selected here">
                                    @endif
                                </td>
                                <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $user->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $user->username }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->deleted_at)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-danger-100 dark:bg-danger-900 text-danger-800 dark:text-danger-200" title="Soft Deleted on {{ \Carbon\Carbon::parse($user->deleted_at)->format('M j, Y g:i A') }}">
                                            <i aria-hidden="true" class="fas fa-trash mr-1"></i>Deleted
                                        </span>
                                    @elseif($user->roles_id === \App\Enums\UserRole::DISABLED->value)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-warning-100 dark:bg-warning-900 text-warning-800 dark:text-warning-200" title="User Disabled">
                                            <i aria-hidden="true" class="fas fa-ban mr-1"></i>Disabled
                                        </span>
                                    @else
                                        <x-admin.badge tone="success" icon="fas fa-check-circle">Active</x-admin.badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-admin.badge tone="primary">{{ $user->rolename ?? 'N/A' }}</x-admin.badge>
                                </td>
                                <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap">
                                    @if(!empty($user->pending_roles_id) && !empty($user->pending_role_start_date))
                                        @php
                                            $pendingStartDate = \Carbon\Carbon::parse($user->pending_role_start_date);
                                        @endphp
                                        <div class="flex flex-col gap-1">
                                            <x-admin.badge tone="info" icon="fas fa-layer-group" class="w-fit">{{ $role_names[$user->pending_roles_id] ?? 'Unknown' }}</x-admin.badge>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                <i aria-hidden="true" class="fas fa-clock mr-1"></i>{{ $pendingStartDate->diffForHumans() }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="hidden lg:table-cell px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    @if($user->rolechangedate)
                                        @php
                                            $expiryDate = \Carbon\Carbon::parse($user->rolechangedate);
                                            $isExpired = $expiryDate->isPast();
                                            $isExpiringSoon = !$isExpired && $expiryDate->diffInDays(now()) <= 7;
                                        @endphp
                                        <div class="flex flex-col gap-1">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full w-fit
                                                @if($isExpired) bg-danger-100 dark:bg-danger-900 text-danger-800 dark:text-danger-200
                                                @elseif($isExpiringSoon) bg-warning-100 dark:bg-warning-900 text-warning-800 dark:text-warning-200
                                                @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300
                                                @endif">
                                                <i aria-hidden="true" class="fas fa-calendar mr-1"></i>{{ $expiryDate->format('M j, Y') }}
                                                @if($isExpired) <i aria-hidden="true" class="fas fa-exclamation-circle ml-1"></i>@endif
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 flex items-center">
                                                <i aria-hidden="true" class="fas fa-clock mr-1"></i>{{ $expiryDate->format('g:i A') }}
                                                <span class="ml-2 italic">({{ $expiryDate->diffForHumans(['parts' => 2]) }})</span>
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 flex items-center">
                                            <i aria-hidden="true" class="fas fa-infinity mr-1"></i>Never
                                        </span>
                                    @endif
                                </td>
                                <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $user->host ?? 'N/A' }}</td>
                                <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    @if(!empty($user->country_code))
                                        <span title="{{ $user->country_name }}">{{ $user->country_code }}</span>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($isVerified)
                                        <x-admin.badge tone="success" icon="fas fa-check">Yes</x-admin.badge>
                                    @else
                                        <x-admin.badge tone="danger" icon="fas fa-times">No</x-admin.badge>
                                    @endif
                                </td>
                                <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $user->created_at ? formatDate($user->created_at) : 'N/A' }}
                                </td>
                                <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100" title="API requests in last 24 hours">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ ($user->daily_api_count ?? 0) > 0 ? 'bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300' }}">
                                        <i aria-hidden="true" class="fas fa-code mr-1"></i>{{ $user->daily_api_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100" title="Downloads in last 24 hours">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ ($user->daily_download_count ?? 0) > 0 ? 'bg-success-100 dark:bg-success-900 text-success-800 dark:text-success-200' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300' }}">
                                        <i aria-hidden="true" class="fas fa-download mr-1"></i>{{ $user->daily_download_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex gap-2">
                                        @if($user->deleted_at)
                                            <!-- Show restore button for deleted users -->
                                            <form action="{{ route('admin.deleted.users.restore', $user->id) }}" method="POST" class="inline-form">
                                                @csrf
                                                <button aria-label="Restore User" type="submit"
                                                        class="text-success-600 dark:text-success-400 hover:text-success-900 dark:hover:text-success-300 bg-transparent border-0 p-0 cursor-pointer"
                                                        title="Restore User"
                                                        data-confirm="Are you sure you want to restore user '{{ $user->username }}'?" data-confirm-type="info" data-confirm-text="Restore">
                                                    <i aria-hidden="true" class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                        @else
                                            <!-- Show normal actions for active users -->
                                            <a aria-label="Edit" href="{{ url('admin/user-edit?id=' . $user->id) }}"
                                               class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                               title="Edit">
                                                <i aria-hidden="true" class="fas fa-edit"></i>
                                            </a>
                                            <a aria-label="Role History" href="{{ url('admin/user-role-history/' . $user->id) }}"
                                               class="text-purple-600 dark:text-purple-400 hover:text-purple-900 dark:hover:text-purple-300"
                                               title="Role History">
                                                <i aria-hidden="true" class="fas fa-history"></i>
                                            </a>
                                            @if(!$isVerified)
                                                <form method="POST" action="{{ route('admin.verify') }}" class="inline verify-user-form">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $user->id }}">
                                                    <button aria-label="Verify User" type="button"
                                                            class="text-success-600 dark:text-success-400 hover:text-success-900 dark:hover:text-success-300 border-0 bg-transparent cursor-pointer p-0"
                                                            title="Verify User"
                                                            data-show-verify-modal
                                                            data-form-id="{{ $user->id }}">
                                                        <i aria-hidden="true" class="fas fa-check-circle"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.resend-verification') }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $user->id }}">
                                                    <button aria-label="Resend Verification" type="submit"
                                                            class="text-warning-600 dark:text-warning-400 hover:text-warning-900 dark:hover:text-warning-300 border-0 bg-transparent cursor-pointer p-0"
                                                            title="Resend Verification"
                                                            data-confirm="Resend account verification email to '{{ $user->username }}'?" data-confirm-type="info" data-confirm-text="Resend">
                                                        <i aria-hidden="true" class="fas fa-envelope"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form action="{{ url('admin/user-delete') }}" method="POST" class="inline-form">
                                                @csrf
                                                <input type="hidden" name="id" value="{{ $user->id }}">
                                                <button aria-label="Delete" type="submit"
                                                        class="text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300 bg-transparent border-0 p-0 cursor-pointer"
                                                        title="Delete"
                                                        data-confirm="Are you sure you want to delete user '{{ $user->username }}'?">
                                                    <i aria-hidden="true" class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <x-admin.pagination :paginator="$userlist" />
        @else
            <x-admin.empty-state icon="fas fa-users" title="No users found" message="Try adjusting your search filters or add a new user." />
        @endif
    </x-admin.card>
</div>

<!-- Verify User Confirmation Modal (x-data mounts verifyUser so showVerifyModal/submitVerifyForm exist) -->
<x-modal open="open" close="hide()" labelledby="verify-user-modal-title-0" id="verifyUserModal" x-data="verifyUser" panel-class="">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 id="verify-user-modal-title-0" class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <i aria-hidden="true" class="fas fa-check-circle text-success-600 dark:text-success-400 mr-2"></i>
                    Verify User
                </h3>
                <button type="button" aria-label="Close dialog" @click="hide()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i aria-hidden="true" class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <div class="px-6 py-4">
            <p class="text-gray-700 dark:text-gray-300">
                Are you sure you want to manually verify this user? This will mark their email as verified.
            </p>
        </div>
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
            <button type="button"
                    @click="hide()"
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition font-medium">
                <i aria-hidden="true" class="fas fa-times mr-2"></i>Cancel
            </button>
            <button type="button"
                    @click="submit()"
                    class="px-4 py-2 bg-success-600 dark:bg-success-700 text-white rounded-lg hover:bg-success-700 dark:hover:bg-success-800 transition font-medium">
                <i aria-hidden="true" class="fas fa-check mr-2"></i>Verify
            </button>
        </div>
</x-modal>

@endsection
