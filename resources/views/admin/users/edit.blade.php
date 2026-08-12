@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-user mr-2"></i>{{ $title }}
                </h1>
                @if(!empty($user['id']))
                    <a href="{{ url('admin/user-role-history/' . $user['id']) }}" class="px-4 py-2 bg-purple-600 dark:bg-purple-700 text-white rounded-lg hover:bg-purple-700 dark:hover:bg-purple-800">
                        <i class="fas fa-history mr-2"></i>View Role History
                    </a>
                @endif
            </div>
        </div>

        <!-- Error Messages -->
        @if(!empty($error))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg">
                <p class="text-red-800 dark:text-red-200">
                    <i class="fas fa-exclamation-circle mr-2"></i>{{ $error }}
                </p>
            </div>
        @endif

        <!-- User Form -->
        <div class="p-6" x-data="adminUserEdit">
        <form id="admin-user-edit-form" method="post" action="{{ url('admin/user-edit') }}">
            @csrf
            <input type="hidden" name="action" value="submit">
            @if(!empty($user['id']))
                <input type="hidden" name="id" value="{{ $user['id'] }}">
            @endif

            <div class="space-y-6">
                <!-- Username -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Username <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="username"
                           name="username"
                           value="{{ is_array($user) ? ($user['username'] ?? '') : ($user->username ?? '') }}"
                           required
                           class="w-full px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500">
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ is_array($user) ? ($user['email'] ?? '') : ($user->email ?? '') }}"
                           required
                           class="w-full px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Password @if(empty($user['id']))<span class="text-red-500">*</span>@endif
                    </label>
                    <div class="relative">
                        <input type="password"
                               id="password"
                               name="password"
                               placeholder="{{ !empty($user['id']) ? 'Leave blank to keep current password' : '' }}"
                               @if(empty($user['id'])) required @endif
                               class="w-full px-3 py-2 pr-12 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 placeholder:text-gray-400 dark:placeholder:text-gray-500">
                        <button type="button" class="password-toggle-btn absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" data-field-id="password">
                            <i class="fas fa-eye" id="password-eye"></i>
                        </button>
                    </div>
                    @if(!empty($user['id']))
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Leave blank to keep the current password</p>
                    @endif
                </div>

                <!-- Role -->
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Role <span class="text-red-500">*</span>
                    </label>
                    @if(!is_array($user) && !empty($user->pending_roles_id))
                        @php
                            $pendingRole = $user->pendingRole;
                        @endphp
                        <div class="mb-2 p-2 bg-primary-100 dark:bg-primary-900/30 border border-primary-300 dark:border-primary-700 rounded text-sm text-primary-800 dark:text-primary-200 flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span><strong>Note:</strong> This user has a pending role change to <strong>{{ $pendingRole->name ?? 'Unknown' }}</strong></span>
                        </div>
                    @endif
                    <select id="role"
                            name="role"
                            required
                            class="w-full px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500">
                        @foreach($role_ids ?? [] as $index => $roleId)
                            <option value="{{ $roleId }}"
                                {{ (is_array($user) ? ($user['role'] ?? '') : ($user->roles->first()->id ?? '')) == $roleId ? 'selected' : '' }}>
                                {{ $role_names[$roleId] ?? '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @include('admin.users.partials.role-expiry')

                @include('admin.users.partials.pending-role')

                <!-- Role Stacking Option -->
                @if(!is_array($user) && !empty($user->rolechangedate) && \Carbon\Carbon::parse($user->rolechangedate)->isFuture())
                    <div class="border border-purple-300 dark:border-purple-600 rounded-lg p-4 bg-linear-to-br from-purple-50 via-purple-50 to-white dark:from-gray-800 dark:via-gray-800 dark:to-gray-700 shadow-sm">
                        <label class="flex items-center cursor-pointer group">
                            <input type="checkbox" name="stack_role" value="1" checked
                                   class="rounded border-gray-300 dark:border-gray-500 text-purple-600 dark:text-purple-500 shadow-sm focus:ring-2 focus:ring-purple-500 dark:focus:ring-purple-400 focus:border-purple-500 dark:focus:border-purple-400 bg-white dark:bg-gray-700">
                            <div class="ml-3 flex-1">
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-purple-700 dark:group-hover:text-purple-300 transition-colors">
                                    <i class="fas fa-layer-group mr-1 text-purple-600 dark:text-purple-400"></i>
                                    Stack role changes
                                </span>
                                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                    When enabled, role changes will be queued to start after the current role expires. Uncheck to apply immediately.
                                </p>
                            </div>
                        </label>
                    </div>
                @endif

                @include('admin.users.partials.activity-stats')

                <!-- Invites -->
                <div>
                    <label for="invites" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Invites
                    </label>
                    <input type="number"
                           id="invites"
                           name="invites"
                           value="{{ is_array($user) ? ($user['invites'] ?? 0) : ($user->invites ?? 0) }}"
                           class="w-full px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500">
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Notes
                    </label>
                    <textarea id="notes"
                              name="notes"
                              rows="4"
                              class="w-full px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500">{{ is_array($user) ? ($user['notes'] ?? '') : ($user->notes ?? '') }}</textarea>
                </div>

                @include('admin.users.partials.view-preferences')

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <x-button type="submit" form="admin-user-edit-form" icon="fas fa-save">Save User</x-button>
                    <x-button-link href="{{ url('admin/user-list') }}" variant="muted" icon="fas fa-times">Cancel</x-button-link>
                    @if(!is_array($user) && $user->exists)
                        <x-button
                            type="submit"
                            form="admin-user-edit-form"
                            variant="danger"
                            icon="fas fa-right-from-bracket"
                            formaction="{{ route('admin.login-sessions.expire-user', $user) }}"
                            formmethod="POST"
                            data-confirm="Expire every web login and trusted device for '{{ $user->username }}'?"
                        >Expire All Logins</x-button>
                    @endif
                </div>
        </div>
    </div>
</x-admin.card>
@endsection
