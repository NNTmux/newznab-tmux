@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-800">
                <i class="fa fa-user mr-2"></i>{{ $title }}
            </h1>
        </div>

        <!-- Error Messages -->
        @if(!empty($error))
            <div class="mx-6 mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-red-800">
                    <i class="fa fa-exclamation-circle mr-2"></i>{{ $error }}
                </p>
            </div>
        @endif

        <!-- User Form -->
        <form method="post" action="{{ url('admin/user-edit') }}" class="p-6">
            @csrf
            <input type="hidden" name="action" value="submit">
            @if(!empty($user['id']))
                <input type="hidden" name="id" value="{{ $user['id'] }}">
            @endif

            <div class="space-y-6">
                <!-- Username -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                        Username <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="username"
                           name="username"
                           value="{{ is_array($user) ? ($user['username'] ?? '') : ($user->username ?? '') }}"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ is_array($user) ? ($user['email'] ?? '') : ($user->email ?? '') }}"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        Password @if(empty($user['id']))<span class="text-red-500">*</span>@endif
                    </label>
                    <input type="password"
                           id="password"
                           name="password"
                           placeholder="{{ !empty($user['id']) ? 'Leave blank to keep current password' : '' }}"
                           @if(empty($user['id'])) required @endif
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    @if(!empty($user['id']))
                        <p class="mt-1 text-sm text-gray-500">Leave blank to keep the current password</p>
                    @endif
                </div>

                <!-- Role -->
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">
                        Role <span class="text-red-500">*</span>
                    </label>
                    <select id="role"
                            name="role"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        @foreach($role_ids ?? [] as $index => $roleId)
                            <option value="{{ $roleId }}"
                                {{ (is_array($user) ? ($user['role'] ?? '') : ($user->roles->first()->id ?? '')) == $roleId ? 'selected' : '' }}>
                                {{ $role_names[$roleId] ?? '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if(!empty($user['id']))
                    <!-- Grabs -->
                    <div>
                        <label for="grabs" class="block text-sm font-medium text-gray-700 mb-1">
                            Grabs
                        </label>
                        <input type="number"
                               id="grabs"
                               name="grabs"
                               value="{{ is_array($user) ? ($user['grabs'] ?? 0) : ($user->grabs ?? 0) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                @endif

                <!-- Invites -->
                <div>
                    <label for="invites" class="block text-sm font-medium text-gray-700 mb-1">
                        Invites
                    </label>
                    <input type="number"
                           id="invites"
                           name="invites"
                           value="{{ is_array($user) ? ($user['invites'] ?? 0) : ($user->invites ?? 0) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                        Notes
                    </label>
                    <textarea id="notes"
                              name="notes"
                              rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">{{ is_array($user) ? ($user['notes'] ?? '') : ($user->notes ?? '') }}</textarea>
                </div>

                @if(!empty($user['id']))
                    <!-- View Preferences -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Category Preferences
                        </label>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="movieview"
                                       name="movieview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['movieview'] ?? 0) : ($user->movieview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="movieview" class="ml-2 text-sm text-gray-700">Movies</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="musicview"
                                       name="musicview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['musicview'] ?? 0) : ($user->musicview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="musicview" class="ml-2 text-sm text-gray-700">Music</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="gameview"
                                       name="gameview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['gameview'] ?? 0) : ($user->gameview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="gameview" class="ml-2 text-sm text-gray-700">Games</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="consoleview"
                                       name="consoleview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['consoleview'] ?? 0) : ($user->consoleview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="consoleview" class="ml-2 text-sm text-gray-700">Console</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="bookview"
                                       name="bookview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['bookview'] ?? 0) : ($user->bookview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="bookview" class="ml-2 text-sm text-gray-700">Books</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="xxxview"
                                       name="xxxview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['xxxview'] ?? 0) : ($user->xxxview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="xxxview" class="ml-2 text-sm text-gray-700">XXX</label>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fa fa-save mr-2"></i>Save User
                    </button>
                    <a href="{{ url('admin/user-list') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        <i class="fa fa-times mr-2"></i>Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

