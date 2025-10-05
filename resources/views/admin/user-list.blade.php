@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800">
                    <i class="fa fa-users mr-2"></i>{{ $title }}
                </h1>
                <a href="{{ url('admin/user-edit?action=add') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-plus mr-2"></i>Add New User
                </a>
            </div>
        </div>

        <!-- Search Filters -->
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <form method="get" action="{{ url('admin/user-list') }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text"
                               id="username"
                               name="username"
                               value="{{ $username ?? '' }}"
                               placeholder="Filter by username"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="text"
                               id="email"
                               name="email"
                               value="{{ $email ?? '' }}"
                               placeholder="Filter by email"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="host" class="block text-sm font-medium text-gray-700 mb-1">Host/IP</label>
                        <input type="text"
                               id="host"
                               name="host"
                               value="{{ $host ?? '' }}"
                               placeholder="Filter by host"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select id="role"
                                name="role"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Roles</option>
                            @foreach($role_ids ?? [] as $index => $roleId)
                                <option value="{{ $roleId }}" {{ ($role ?? '') == $roleId ? 'selected' : '' }}>
                                    {{ $role_names[$roleId] ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <i class="fa fa-search mr-2"></i>Filter
                    </button>
                    <a href="{{ url('admin/user-list') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        <i class="fa fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Success/Error Messages -->
        @if(request()->has('deleted') && request()->input('deleted') == 1)
            <div class="mx-6 mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-green-800">
                    <i class="fa fa-check-circle mr-2"></i>
                    User "{{ request()->input('username') }}" has been deleted successfully.
                </p>
            </div>
        @endif

        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-green-800">
                    <i class="fa fa-check-circle mr-2"></i>{{ session('success') }}
                </p>
            </div>
        @endif

        @if(session('error'))
            <div class="mx-6 mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-red-800">
                    <i class="fa fa-exclamation-circle mr-2"></i>{{ session('error') }}
                </p>
            </div>
        @endif

        <!-- User Table -->
        @if(count($userlist) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Host</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Country</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verified</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($userlist as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $user->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $user->username }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user->email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ $user->roles->first()->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user->host ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if(!empty($user->country_code))
                                        <span title="{{ $user->country_name }}">{{ $user->country_code }}</span>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->verified)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fa fa-check mr-1"></i>Yes
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fa fa-times mr-1"></i>No
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $user->created_at ? $user->created_at->format('Y-m-d') : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex gap-2">
                                        <a href="{{ url('admin/user-edit?id=' . $user->id) }}"
                                           class="text-blue-600 hover:text-blue-900"
                                           title="Edit">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        @if(!$user->verified)
                                            <a href="{{ url('admin/verify?id=' . $user->id) }}"
                                               class="text-green-600 hover:text-green-900"
                                               title="Verify User"
                                               onclick="return confirm('Are you sure you want to verify this user?')">
                                                <i class="fa fa-check-circle"></i>
                                            </a>
                                            <a href="{{ url('admin/resendverification?id=' . $user->id) }}"
                                               class="text-yellow-600 hover:text-yellow-900"
                                               title="Resend Verification">
                                                <i class="fa fa-envelope"></i>
                                            </a>
                                        @endif
                                        <a href="{{ url('admin/user-delete?id=' . $user->id) }}"
                                           class="text-red-600 hover:text-red-900"
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete user \'{{ $user->username }}\'?')">
                                            <i class="fa fa-trash"></i>
                                        </a>
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
                <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                <p class="text-gray-500">Try adjusting your search filters or add a new user.</p>
            </div>
        @endif
    </div>
</div>
@endsection

