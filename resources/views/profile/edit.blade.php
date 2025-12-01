@extends('layouts.main')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-gray-800">Edit Profile</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Update your account settings and preferences</p>
        </div>

        <!-- Messages -->
        @if(session('success'))
            <div class="mx-6 mt-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-800 rounded">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif

        @if($success_2fa)
            <div class="mx-6 mt-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-800 rounded">
                <i class="fas fa-check-circle mr-2"></i>{{ $success_2fa }}
            </div>
        @endif

        @if($error || $error_2fa)
            <div class="mx-6 mt-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-800 rounded">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ $error ?: $error_2fa }}
            </div>
        @endif

        <!-- Form -->
        <form method="POST" action="{{ route('profileedit') }}" class="p-6 space-y-6">
            @csrf
            <input type="hidden" name="action" value="submit">

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
                <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 @error('email') border-red-500 dark:border-red-600 @enderror">
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password (leave blank to keep current)</label>
                <div class="relative">
                    <input type="password" name="password" id="password"
                        class="w-full px-4 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 @error('password') border-red-500 dark:border-red-600 @enderror">
                    <button type="button" class="password-toggle-btn absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" data-field-id="password">
                        <i class="fas fa-eye" id="password-eye"></i>
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Must contain at least 8 characters, including uppercase, lowercase, numbers and special characters</p>
                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm Password</label>
                <div class="relative">
                    <input type="password" name="password_confirmation" id="password_confirmation"
                        class="w-full px-4 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    <button type="button" class="password-toggle-btn absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" data-field-id="password_confirmation">
                        <i class="fas fa-eye" id="password_confirmation-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Theme Preference -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                    <i class="fas fa-palette mr-2 text-blue-600 dark:text-blue-400"></i>Theme Preference
                </h3>
                <div class="space-y-3">
                    <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition {{ ($user->theme_preference ?? 'light') === 'light' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700' }}">
                        <input type="radio" name="theme_preference" value="light" {{ ($user->theme_preference ?? 'light') === 'light' ? 'checked' : '' }}
                            class="w-4 h-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                        <div class="ml-3 flex-1">
                            <div class="flex items-center">
                                <i class="fas fa-sun text-yellow-500 text-xl mr-3"></i>
                                <div>
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Light Mode</span>
                                    <span class="block text-xs text-gray-600 dark:text-gray-400">Bright and clean interface</span>
                                </div>
                            </div>
                        </div>
                    </label>
                    <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition {{ ($user->theme_preference ?? 'light') === 'dark' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700' }}">
                        <input type="radio" name="theme_preference" value="dark" {{ ($user->theme_preference ?? 'light') === 'dark' ? 'checked' : '' }}
                            class="w-4 h-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                        <div class="ml-3 flex-1">
                            <div class="flex items-center">
                                <i class="fas fa-moon text-indigo-500 text-xl mr-3"></i>
                                <div>
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Dark Mode</span>
                                    <span class="block text-xs text-gray-600 dark:text-gray-400">Easy on the eyes, especially at night</span>
                                </div>
                            </div>
                        </div>
                    </label>
                    <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition {{ ($user->theme_preference ?? 'light') === 'system' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700' }}">
                        <input type="radio" name="theme_preference" value="system" {{ ($user->theme_preference ?? 'light') === 'system' ? 'checked' : '' }}
                            class="w-4 h-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                        <div class="ml-3 flex-1">
                            <div class="flex items-center">
                                <i class="fas fa-desktop text-blue-500 text-xl mr-3"></i>
                                <div>
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">System (Auto)</span>
                                    <span class="block text-xs text-gray-600 dark:text-gray-400">Match your operating system's theme</span>
                                </div>
                            </div>
                        </div>
                    </label>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>Your theme preference will be applied across all devices and browsers when you're logged in.
                </p>
            </div>

            <!-- Timezone Preference -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                    <i class="fas fa-clock mr-2 text-blue-600 dark:text-blue-400"></i>Timezone Preference
                </h3>
                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Select Your Timezone
                    </label>
                    <select name="timezone" id="timezone"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @php
                            $timezones = getAvailableTimezones();
                            $currentTimezone = old('timezone', $user->timezone ?? 'UTC');
                        @endphp
                        <option value="UTC" {{ $currentTimezone === 'UTC' ? 'selected' : '' }}>UTC (Coordinated Universal Time)</option>
                        @foreach($timezones as $region => $tzList)
                            <optgroup label="{{ $region }}">
                                @foreach($tzList as $tz)
                                    <option value="{{ $tz }}" {{ $currentTimezone === $tz ? 'selected' : '' }}>
                                        {{ str_replace('_', ' ', $tz) }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i>All dates and times will be displayed in your selected timezone. Current server time: {{ now()->format('Y-m-d H:i:s T') }}
                    </p>
                </div>
            </div>

            <!-- View Preferences -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Cover View Preferences</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="movieview" value="1" {{ $user->movieview ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Movie Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="musicview" value="1" {{ $user->musicview ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Music Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="consoleview" value="1" {{ $user->consoleview ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Console Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="gameview" value="1" {{ $user->gameview ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Game Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="bookview" value="1" {{ $user->bookview ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Book Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="xxxview" value="1" {{ $user->xxxview ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">XXX Covers</span>
                    </label>
                </div>
            </div>

            <!-- Category Permissions -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Category Permissions</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="viewmovies" value="1" {{ $user->hasDirectPermission('view movies') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Movies</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewtv" value="1" {{ $user->hasDirectPermission('view tv') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">TV</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewaudio" value="1" {{ $user->hasDirectPermission('view audio') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Audio</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewpc" value="1" {{ $user->hasDirectPermission('view pc') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">PC</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewconsole" value="1" {{ $user->hasDirectPermission('view console') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Console</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewbooks" value="1" {{ $user->hasDirectPermission('view books') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Books</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewadult" value="1" {{ $user->hasDirectPermission('view adult') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Adult</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewother" value="1" {{ $user->hasDirectPermission('view other') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-400 focus:ring-blue-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Other</span>
                    </label>
                </div>
            </div>

            <!-- Actions -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6 flex items-center justify-between">
                <a href="{{ route('profile') }}" class="px-6 py-2 text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </a>
                <div class="flex space-x-2">
                    <a href="{{ route('profileedit', ['action' => 'newapikey']) }}"
                       class="px-6 py-2 text-blue-700 bg-blue-100 rounded-lg hover:bg-blue-200 transition"
                       data-confirm="Are you sure you want to generate a new API key?">
                        <i class="fas fa-key mr-2"></i>New API Key
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </form>

        <!-- 2FA Section (Outside main form) -->
        <div class="p-6 border-t border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-shield-alt mr-2 text-blue-600"></i>Two-Factor Authentication (2FA)
            </h3>

            @if($user->passwordSecurity()->exists() && $user->passwordSecurity->google2fa_enable)
                <!-- 2FA is Enabled -->
                <div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl mr-3 mt-1"></i>
                        <div class="flex-1">
                            <h4 class="text-green-800 dark:text-green-200 font-semibold mb-1">Two-Factor Authentication is Active</h4>
                            <p class="text-green-700 dark:text-green-300 text-sm mb-4">Your account is protected with an additional layer of security. You'll need your authenticator app to log in.</p>

                            <!-- Collapsible Disable Form -->
                            <div class="space-y-3">
                                <button id="toggle-disable-2fa-btn"
                                        type="button"
                                        class="px-4 py-2 bg-red-600 dark:bg-red-700 text-white text-sm rounded-lg hover:bg-red-700 dark:hover:bg-red-800 transition">
                                    <i class="fas fa-times-circle mr-2"></i>Disable 2FA
                                </button>

                                <div id="disable-2fa-form-container"
                                     style="display: none;"
                                     class="bg-white dark:bg-gray-800 border border-red-200 dark:border-red-700 rounded-lg p-4 mt-3">
                                    <div class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                                        <div class="flex items-start">
                                            <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400 mr-2 mt-0.5"></i>
                                            <div class="text-sm text-yellow-800 dark:text-yellow-200">
                                                <strong>Warning:</strong> Disabling 2FA will make your account less secure. Please enter your password to confirm.
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('profileedit.disable2fa') }}" class="space-y-3">
                                        @csrf
                                        <div>
                                            <label for="disable_2fa_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Current Password
                                            </label>
                                            <div class="relative">
                                                <input type="password"
                                                       id="disable_2fa_password"
                                                       name="current-password"
                                                       required
                                                       class="w-full px-4 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
                                                       placeholder="Enter your password">
                                                <button type="button" class="password-toggle-btn absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" data-field-id="disable_2fa_password">
                                                    <i class="fas fa-eye" id="disable_2fa_password-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit"
                                                    class="px-4 py-2 bg-red-600 dark:bg-red-700 text-white text-sm rounded-lg hover:bg-red-700 dark:hover:bg-red-800 transition">
                                                <i class="fas fa-check mr-2"></i>Confirm Disable
                                            </button>
                                            <button id="cancel-disable-2fa-btn"
                                                    type="button"
                                                    class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition">
                                                <i class="fas fa-times mr-2"></i>Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($user->passwordSecurity()->exists() && !$user->passwordSecurity->google2fa_enable)
                <!-- 2FA Setup Started but Not Enabled -->
                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 text-xl mr-3 mt-1"></i>
                        <div class="flex-1">
                            <h4 class="text-blue-800 font-semibold mb-2">Complete Your 2FA Setup</h4>
                            <p class="text-blue-700 text-sm mb-4">Follow these steps to enable two-factor authentication:</p>

                            <ol class="list-decimal list-inside space-y-2 text-sm text-blue-700 mb-4">
                                <li>Install an authenticator app (Google Authenticator, Authy, or similar)</li>
                                <li>Scan the QR code below with your authenticator app</li>
                                <li>Enter the 6-digit code from your app to verify</li>
                            </ol>

                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4 flex justify-center">
                                <img src="{{ $google2fa_url }}" alt="2FA QR Code">
                            </div>

                            <p class="text-xs text-blue-600 dark:text-blue-400 mb-4">
                                <strong>Secret Key (manual entry):</strong>
                                <code class="bg-white dark:bg-gray-800 px-2 py-1 rounded">{{ $user->passwordSecurity->google2fa_secret }}</code>
                            </p>


                            <form method="POST" action="{{ route('profileedit.enable2fa') }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label for="verify-code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Enter Verification Code
                                    </label>
                                    <input type="text"
                                           id="verify-code"
                                           name="verify-code"
                                           placeholder="Enter 6-digit code"
                                           maxlength="6"
                                           required
                                           class="w-full md:w-64 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-center text-lg tracking-widest bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="px-4 py-2 bg-green-600 dark:bg-green-700 text-white text-sm rounded-lg hover:bg-green-700 dark:hover:bg-green-800 transition">
                                        <i class="fas fa-check mr-2"></i>Verify and Enable 2FA
                                    </button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('profileedit.cancel2fa') }}" class="mt-2">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-gray-600 text-white text-sm rounded-lg hover:bg-gray-700 transition">
                                    <i class="fas fa-times mr-2"></i>Cancel Setup
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <!-- 2FA Not Set Up -->
                <div class="bg-yellow-50 border-l-4 border-yellow-500 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mr-3 mt-1"></i>
                        <div class="flex-1">
                            <h4 class="text-yellow-800 font-semibold mb-1">Two-Factor Authentication is Disabled</h4>
                            <p class="text-yellow-700 text-sm mb-4">Add an extra layer of security to your account by enabling two-factor authentication.</p>

                            <form method="POST" action="{{ route('generate2faSecret') }}">
                                @csrf
                                <input type="hidden" name="from_profile" value="1">
                                <button type="submit" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white text-sm rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition">
                                    <i class="fas fa-shield-alt mr-2"></i>Enable Two-Factor Authentication
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                <p class="text-xs text-gray-600">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>What is 2FA?</strong> Two-Factor Authentication adds an extra layer of security by requiring both your password and a code from your phone to log in.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // 2FA disable form toggle
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('toggle-disable-2fa-btn');
        const cancelBtn = document.getElementById('cancel-disable-2fa-btn');
        const formContainer = document.getElementById('disable-2fa-form-container');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                formContainer.style.display = 'block';
                toggleBtn.style.display = 'none';
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                formContainer.style.display = 'none';
                toggleBtn.style.display = 'inline-block';
            });
        }
    });
</script>
@endpush
