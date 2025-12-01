@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-users mr-2"></i>{{ $title }}
                </h1>
                <a href="{{ url('admin/user-edit?action=add') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                    <i class="fa fa-plus mr-2"></i>Add New User
                </a>
            </div>
        </div>

        <!-- Search Filters -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="get" action="{{ url('admin/user-list') }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                        <input type="text"
                               id="username"
                               name="username"
                               value="{{ $username ?? '' }}"
                               placeholder="Filter by username"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="text"
                               id="email"
                               name="email"
                               value="{{ $email ?? '' }}"
                               placeholder="Filter by email"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                    </div>
                    <div>
                        <label for="host" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Host/IP</label>
                        <input type="text"
                               id="host"
                               name="host"
                               value="{{ $host ?? '' }}"
                               placeholder="Filter by host"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                    </div>
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role</label>
                        <select id="role"
                                name="role"
                                class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                            <option value="">All Roles</option>
                            @foreach($role_ids ?? [] as $index => $roleId)
                                <option value="{{ $roleId }}" {{ ($role ?? '') == $roleId ? 'selected' : '' }}>
                                    {{ $role_names[$roleId] ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="created_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Registered From</label>
                        <input type="date"
                               id="created_from"
                               name="created_from"
                               value="{{ $created_from ?? '' }}"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                    </div>
                    <div>
                        <label for="created_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Registered To</label>
                        <input type="date"
                               id="created_to"
                               name="created_to"
                               value="{{ $created_to ?? '' }}"
                               class="w-full px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-md hover:bg-blue-700 dark:hover:bg-blue-800">
                        <i class="fa fa-search mr-2"></i>Filter
                    </button>
                    <a href="{{ url('admin/user-list') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                        <i class="fa fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Success/Error Messages -->
        @if(request()->has('deleted') && request()->input('deleted') == 1)
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg">
                <p class="text-green-800 dark:text-green-200">
                    <i class="fa fa-check-circle mr-2"></i>
                    User "{{ request()->input('username') }}" has been deleted successfully.
                </p>
            </div>
        @endif

        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg">
                <p class="text-green-800 dark:text-green-200">
                    <i class="fa fa-check-circle mr-2"></i>{{ session('success') }}
                </p>
            </div>
        @endif

        @if(session('error'))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg">
                <p class="text-red-800 dark:text-red-200">
                    <i class="fa fa-exclamation-circle mr-2"></i>{{ session('error') }}
                </p>
            </div>
        @endif

        <!-- User Table -->
        @if(count($userlist) > 0)
            <!-- Top Scrollbar -->
            <div class="overflow-x-auto border-b border-gray-200 dark:border-gray-700" id="topScroll">
                <div style="height: 1px;" id="topScrollContent"></div>
            </div>

            <div class="overflow-x-auto" id="bottomScroll">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <a href="{{ url('admin/user-list?' . http_build_query(array_merge(request()->except('ob'), ['ob' => request('ob') === 'username_asc' ? 'username_desc' : 'username_asc']))) }}" class="group inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200">
                                    Username
                                    @if(request('ob') === 'username_asc')
                                        <i class="fa fa-sort-up ml-1"></i>
                                    @elseif(request('ob') === 'username_desc')
                                        <i class="fa fa-sort-down ml-1"></i>
                                    @else
                                        <i class="fa fa-sort ml-1 opacity-50 group-hover:opacity-100"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <a href="{{ url('admin/user-list?' . http_build_query(array_merge(request()->except('ob'), ['ob' => request('ob') === 'apiaccess_asc' ? 'apiaccess_desc' : 'apiaccess_asc']))) }}" class="group inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200">
                                    Status
                                    @if(request('ob') === 'apiaccess_asc')
                                        <i class="fa fa-sort-up ml-1"></i>
                                    @elseif(request('ob') === 'apiaccess_desc')
                                        <i class="fa fa-sort-down ml-1"></i>
                                    @else
                                        <i class="fa fa-sort ml-1 opacity-50 group-hover:opacity-100"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <i class="fa fa-layer-group mr-1"></i>Pending Role
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <a href="{{ url('admin/user-list?' . http_build_query(array_merge(request()->except('ob'), ['ob' => request('ob') === 'rolechangedate_asc' ? 'rolechangedate_desc' : 'rolechangedate_asc']))) }}" class="group inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200">
                                    Role Expiry
                                    @if(request('ob') === 'rolechangedate_asc')
                                        <i class="fa fa-sort-up ml-1"></i>
                                    @elseif(request('ob') === 'rolechangedate_desc')
                                        <i class="fa fa-sort-down ml-1"></i>
                                    @else
                                        <i class="fa fa-sort ml-1 opacity-50 group-hover:opacity-100"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Host</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Country</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Verified</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <a href="{{ url('admin/user-list?' . http_build_query(array_merge(request()->except('ob'), ['ob' => request('ob') === 'createdat_asc' ? 'createdat_desc' : 'createdat_asc']))) }}" class="group inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200">
                                    Created
                                    @if(request('ob') === 'createdat_asc')
                                        <i class="fa fa-sort-up ml-1"></i>
                                    @elseif(request('ob') === 'createdat_desc')
                                        <i class="fa fa-sort-down ml-1"></i>
                                    @else
                                        <i class="fa fa-sort ml-1 opacity-50 group-hover:opacity-100"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider" title="Daily API requests in last 24 hours">
                                <i class="fa fa-code mr-1"></i>Daily API
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider" title="Daily downloads in last 24 hours">
                                <i class="fa fa-download mr-1"></i>Daily DLs
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($userlist as $user)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $user->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $user->username }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->deleted_at)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200" title="Soft Deleted on {{ \Carbon\Carbon::parse($user->deleted_at)->format('M j, Y g:i A') }}">
                                            <i class="fa fa-trash mr-1"></i>Deleted
                                        </span>
                                    @elseif(isset($user->apiaccess) && $user->apiaccess == 0)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200" title="API Access Disabled">
                                            <i class="fa fa-ban mr-1"></i>Disabled
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                            <i class="fa fa-check-circle mr-1"></i>Active
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                        {{ $user->roles->first()->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if(!empty($user->pending_roles_id) && !empty($user->pending_role_start_date))
                                        @php
                                            $pendingRole = \Spatie\Permission\Models\Role::find($user->pending_roles_id);
                                            $pendingStartDate = \Carbon\Carbon::parse($user->pending_role_start_date);
                                        @endphp
                                        <div class="flex flex-col gap-1">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 w-fit">
                                                <i class="fa fa-layer-group mr-1"></i>{{ $pendingRole->name ?? 'Unknown' }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                <i class="fa fa-clock mr-1"></i>{{ $pendingStartDate->diffForHumans() }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    @if($user->rolechangedate)
                                        @php
                                            $expiryDate = \Carbon\Carbon::parse($user->rolechangedate);
                                            $isExpired = $expiryDate->isPast();
                                            $isExpiringSoon = !$isExpired && $expiryDate->diffInDays(now()) <= 7;
                                        @endphp
                                        <div class="flex flex-col gap-1">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full w-fit
                                                @if($isExpired) bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                                                @elseif($isExpiringSoon) bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200
                                                @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300
                                                @endif">
                                                <i class="fa fa-calendar mr-1"></i>{{ $expiryDate->format('M j, Y') }}
                                                @if($isExpired) <i class="fa fa-exclamation-circle ml-1"></i>@endif
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 flex items-center">
                                                <i class="fa fa-clock mr-1"></i>{{ $expiryDate->format('g:i A') }}
                                                <span class="ml-2 italic">({{ $expiryDate->diffForHumans() }})</span>
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 flex items-center">
                                            <i class="fa fa-infinity mr-1"></i>Never
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $user->host ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    @if(!empty($user->country_code))
                                        <span title="{{ $user->country_name }}">{{ $user->country_code }}</span>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->verified)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                            <i class="fa fa-check mr-1"></i>Yes
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                            <i class="fa fa-times mr-1"></i>No
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $user->created_at ? $user->created_at->format('Y-m-d') : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100" title="API requests in last 24 hours">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ ($user->daily_api_count ?? 0) > 0 ? 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300' }}">
                                        <i class="fa fa-code mr-1"></i>{{ $user->daily_api_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100" title="Downloads in last 24 hours">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ ($user->daily_download_count ?? 0) > 0 ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300' }}">
                                        <i class="fa fa-download mr-1"></i>{{ $user->daily_download_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex gap-2">
                                        @if($user->deleted_at)
                                            <!-- Show restore button for deleted users -->
                                            <button type="button"
                                                    class="restore-user-btn text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300 bg-transparent border-0 p-0 cursor-pointer"
                                                    title="Restore User"
                                                    data-user-id="{{ $user->id }}"
                                                    data-username="{{ $user->username }}">
                                                <i class="fa fa-undo"></i>
                                            </button>
                                        @else
                                            <!-- Show normal actions for active users -->
                                            <a href="{{ url('admin/user-edit?id=' . $user->id) }}"
                                               class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                               title="Edit">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            @if(!$user->verified)
                                                <form method="POST" action="{{ route('admin.verify') }}" class="inline verify-user-form">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $user->id }}">
                                                    <button type="button"
                                                            class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300 border-0 bg-transparent cursor-pointer p-0"
                                                            title="Verify User"
                                                            data-show-verify-modal
                                                            data-form-id="{{ $user->id }}">
                                                        <i class="fa fa-check-circle"></i>
                                                    </button>
                                                </form>
                                                <a href="{{ url('admin/resendverification?id=' . $user->id) }}"
                                                   class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-300"
                                                   title="Resend Verification">
                                                    <i class="fa fa-envelope"></i>
                                                </a>
                                            @endif
                                            <form action="{{ url('admin/user-delete') }}" method="POST" class="inline-form">
                                                @csrf
                                                <input type="hidden" name="id" value="{{ $user->id }}">
                                                <button type="submit"
                                                        class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 bg-transparent border-0 p-0 cursor-pointer"
                                                        title="Delete"
                                                        data-confirm="Are you sure you want to delete user '{{ $user->username }}'?">
                                                    <i class="fa fa-trash"></i>
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
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $userlist->links() }}
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fa fa-users text-gray-400 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No users found</h3>
                <p class="text-gray-500">Try adjusting your search filters or add a new user.</p>
            </div>
        @endif
    </div>
</div>

<!-- Verify User Confirmation Modal -->
<div id="verifyUserModal" class="fixed inset-0 bg-gray-900 dark:bg-black bg-opacity-50 dark:bg-opacity-70 hidden items-center justify-center z-50 transition-opacity">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <i class="fa fa-check-circle text-green-600 dark:text-green-400 mr-2"></i>
                    Verify User
                </h3>
                <button type="button" data-close-verify-modal class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
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
                    data-close-verify-modal
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition font-medium">
                <i class="fas fa-times mr-2"></i>Cancel
            </button>
            <button type="button"
                    data-submit-verify-form
                    class="px-4 py-2 bg-green-600 dark:bg-green-700 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-800 transition font-medium">
                <i class="fa fa-check mr-2"></i>Verify
            </button>
        </div>
    </div>
</div>

<!-- Hidden form for individual actions (restore deleted users) -->
<form id="individualActionForm" method="POST" class="hidden">
    @csrf
</form>
@endsection

{{-- Scripts moved to resources/js/csp-safe.js --}}

