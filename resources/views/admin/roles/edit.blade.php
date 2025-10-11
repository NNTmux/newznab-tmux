@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa fa-user-shield mr-2"></i>{{ $title }}
            </h1>
        </div>

        <!-- Role Edit Form -->
        @if($role)
            <form method="post" action="{{ url('admin/role-edit') }}" class="p-6">
                @csrf
                <input type="hidden" name="action" value="submit">
                <input type="hidden" name="id" value="{{ $role->id }}">

                <div class="space-y-6">
                    <!-- Role Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Role Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ $role->name }}"
                               required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- API Requests -->
                        <div>
                            <label for="apirequests" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                API Requests per Day
                            </label>
                            <input type="number"
                                   id="apirequests"
                                   name="apirequests"
                                   value="{{ $role->apirequests ?? 0 }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>

                        <!-- Download Requests -->
                        <div>
                            <label for="downloadrequests" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Download Requests per Day
                            </label>
                            <input type="number"
                                   id="downloadrequests"
                                   name="downloadrequests"
                                   value="{{ $role->downloadrequests ?? 0 }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>

                        <!-- Default Invites -->
                        <div>
                            <label for="defaultinvites" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Default Invites
                            </label>
                            <input type="number"
                                   id="defaultinvites"
                                   name="defaultinvites"
                                   value="{{ $role->defaultinvites ?? 0 }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>

                        <!-- Rate Limit -->
                        <div>
                            <label for="rate_limit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Rate Limit (requests per minute)
                            </label>
                            <input type="number"
                                   id="rate_limit"
                                   name="rate_limit"
                                   value="{{ $role->rate_limit ?? 60 }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>

                        <!-- Donation -->
                        <div>
                            <label for="donation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Donation Amount
                            </label>
                            <input type="number"
                                   id="donation"
                                   name="donation"
                                   value="{{ $role->donation ?? 0 }}"
                                   step="0.01"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>

                        <!-- Add Years -->
                        <div>
                            <label for="addyears" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Add Years (role duration)
                            </label>
                            <input type="number"
                                   id="addyears"
                                   name="addyears"
                                   value="{{ $role->addyears ?? 0 }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>

                        <!-- Is Default -->
                        <div>
                            <label for="isdefault" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Default Role
                            </label>
                            <select id="isdefault"
                                    name="isdefault"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno_ids as $index => $value)
                                    <option value="{{ $value }}" {{ ($role->isdefault ?? 0) == $value ? 'selected' : '' }}>
                                        {{ $yesno_names[$index] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Permissions -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                            Permissions
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="canpreview"
                                       name="canpreview"
                                       value="1"
                                       {{ $role->hasPermissionTo('preview') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="canpreview" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Can Preview</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="hideads"
                                       name="hideads"
                                       value="1"
                                       {{ $role->hasPermissionTo('hideads') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="hideads" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Hide Ads</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="editrelease"
                                       name="editrelease"
                                       value="1"
                                       {{ $role->hasPermissionTo('edit release') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="editrelease" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Edit Release</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="viewconsole"
                                       name="viewconsole"
                                       value="1"
                                       {{ $role->hasPermissionTo('view console') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="viewconsole" class="ml-2 text-sm text-gray-700 dark:text-gray-300">View Console</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="viewmovies"
                                       name="viewmovies"
                                       value="1"
                                       {{ $role->hasPermissionTo('view movies') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="viewmovies" class="ml-2 text-sm text-gray-700 dark:text-gray-300">View Movies</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="viewaudio"
                                       name="viewaudio"
                                       value="1"
                                       {{ $role->hasPermissionTo('view audio') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="viewaudio" class="ml-2 text-sm text-gray-700 dark:text-gray-300">View Audio</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="viewpc"
                                       name="viewpc"
                                       value="1"
                                       {{ $role->hasPermissionTo('view pc') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="viewpc" class="ml-2 text-sm text-gray-700 dark:text-gray-300">View PC</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="viewtv"
                                       name="viewtv"
                                       value="1"
                                       {{ $role->hasPermissionTo('view tv') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="viewtv" class="ml-2 text-sm text-gray-700 dark:text-gray-300">View TV</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="viewadult"
                                       name="viewadult"
                                       value="1"
                                       {{ $role->hasPermissionTo('view adult') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="viewadult" class="ml-2 text-sm text-gray-700 dark:text-gray-300">View Adult</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="viewbooks"
                                       name="viewbooks"
                                       value="1"
                                       {{ $role->hasPermissionTo('view books') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="viewbooks" class="ml-2 text-sm text-gray-700 dark:text-gray-300">View Books</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="viewother"
                                       name="viewother"
                                       value="1"
                                       {{ $role->hasPermissionTo('view other') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="viewother" class="ml-2 text-sm text-gray-700 dark:text-gray-300">View Other</label>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                            <i class="fa fa-save mr-2"></i>Update Role
                        </button>
                        <a href="{{ url('admin/role-list') }}" class="px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600">
                            <i class="fa fa-times mr-2"></i>Cancel
                        </a>
                    </div>
                </div>
            </form>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fa fa-exclamation-circle text-red-400 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Role not found</h3>
                <p class="text-gray-500 mb-4">The requested role could not be found.</p>
                <a href="{{ url('admin/role-list') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-arrow-left mr-2"></i> Back to Role List
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

