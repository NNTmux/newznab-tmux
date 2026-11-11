@extends('layouts.main')

@section('content')
<div class="w-full">
    <x-page-header title="Edit profile" eyebrow="Account settings" description="Update your account, security, downloads, and browsing preferences." icon="fas fa-user-pen">
        <x-slot:actions>
            <a href="{{ route('profile') }}" class="workspace-hero__action"><i class="fas fa-arrow-left" aria-hidden="true"></i>Back to profile</a>
        </x-slot:actions>
    </x-page-header>

    <div class="surface-panel rounded-xl shadow-sm">
        <!-- Messages -->
        @if($success_2fa)
            <div class="mx-6 mt-6 p-4 bg-success-50 dark:bg-success-950/40 border-l-4 border-success-500 text-success-800 dark:text-success-200 rounded">
                <i aria-hidden="true" class="fas fa-check-circle mr-2"></i>{{ $success_2fa }}
            </div>
        @endif

        @if($error || $error_2fa)
            <div class="mx-6 mt-6 p-4 bg-danger-50 dark:bg-danger-950/40 border-l-4 border-danger-500 text-danger-800 dark:text-danger-200 rounded">
                <i aria-hidden="true" class="fas fa-exclamation-circle mr-2"></i>{{ $error ?: $error_2fa }}
            </div>
        @endif

        <nav aria-label="Settings sections" class="flex flex-wrap gap-2 border-b border-line px-6 py-3 text-sm">
            <a href="#profile-update-form" class="rounded-full bg-surface-sunken px-3 py-1 font-medium text-fg transition hover:bg-surface-hover">Account</a>
            <a href="#settings-theme" class="rounded-full bg-surface-sunken px-3 py-1 font-medium text-fg transition hover:bg-surface-hover">Theme</a>
            <a href="#settings-color-scheme" class="rounded-full bg-surface-sunken px-3 py-1 font-medium text-fg transition hover:bg-surface-hover">Color scheme</a>
            <a href="#settings-timezone" class="rounded-full bg-surface-sunken px-3 py-1 font-medium text-fg transition hover:bg-surface-hover">Time zone</a>
            <a href="#settings-cover-views" class="rounded-full bg-surface-sunken px-3 py-1 font-medium text-fg transition hover:bg-surface-hover">Cover views</a>
            <a href="#settings-permissions" class="rounded-full bg-surface-sunken px-3 py-1 font-medium text-fg transition hover:bg-surface-hover">Permissions</a>
            <a href="#settings-exclusions" class="rounded-full bg-surface-sunken px-3 py-1 font-medium text-fg transition hover:bg-surface-hover">Exclusions</a>
            <a href="#settings-security" class="rounded-full bg-surface-sunken px-3 py-1 font-medium text-fg transition hover:bg-surface-hover">Two-factor</a>
        </nav>

        <!-- Form -->
        <form id="profile-update-form" method="POST" action="{{ route('profileedit') }}" class="p-6 space-y-6">
            @csrf
            <input type="hidden" name="action" value="submit">

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
                <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 @error('email') border-danger-500 dark:border-danger-600 @enderror">
                @error('email')
                    <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Current Password -->
            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Current Password (required to change your email or password)</label>
                <div class="relative" x-data="passwordToggle">
                    <input type="password" x-ref="field" name="current_password" id="current_password" autocomplete="current-password"
                        class="w-full px-4 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 @error('current_password') border-danger-500 dark:border-danger-600 @enderror">
                    <button aria-label="Show password" :aria-label="label()" type="button" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="toggle()">
                        <i aria-hidden="true" class="fas" :class="iconClass()"></i>
                    </button>
                </div>
                @error('current_password')
                    <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password (leave blank to keep current)</label>
                <div class="relative" x-data="passwordToggle">
                    <input type="password" x-ref="field" name="password" id="password" autocomplete="new-password"
                        class="w-full px-4 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 @error('password') border-danger-500 dark:border-danger-600 @enderror">
                    <button aria-label="Show password" :aria-label="label()" type="button" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="toggle()">
                        <i aria-hidden="true" class="fas" :class="iconClass()"></i>
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Must contain at least 8 characters, including uppercase, lowercase, numbers and special characters</p>
                @error('password')
                    <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm Password</label>
                <div class="relative" x-data="passwordToggle">
                    <input type="password" x-ref="field" name="password_confirmation" id="password_confirmation" autocomplete="new-password"
                        class="w-full px-4 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    <button aria-label="Show password" :aria-label="label()" type="button" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="toggle()">
                        <i aria-hidden="true" class="fas" :class="iconClass()"></i>
                    </button>
                </div>
            </div>

            <!-- Theme Preference -->
            <div id="settings-theme" class="scroll-mt-4 border-t border-gray-200 dark:border-gray-700 pt-6">
                <fieldset>
                    <legend class="w-full"><h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                    <i aria-hidden="true" class="fas fa-palette mr-2 text-link dark:text-primary-400"></i>Theme Preference
                </h3></legend>
                <div class="space-y-3">
                    <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition {{ ($user->theme_preference ?? 'light') === 'light' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700' }}">
                        <input type="radio" name="theme_preference" value="light" {{ ($user->theme_preference ?? 'light') === 'light' ? 'checked' : '' }}
                            class="w-4 h-4 text-link focus:ring-primary-500 border-gray-300">
                        <div class="ml-3 flex-1">
                            <div class="flex items-center">
                                <i aria-hidden="true" class="fas fa-sun text-amber-500 text-xl mr-3"></i>
                                <div>
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Light Mode</span>
                                    <span class="block text-xs text-gray-600 dark:text-gray-400">Bright and clean interface</span>
                                </div>
                            </div>
                        </div>
                    </label>
                    <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition {{ ($user->theme_preference ?? 'light') === 'dark' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700' }}">
                        <input type="radio" name="theme_preference" value="dark" {{ ($user->theme_preference ?? 'light') === 'dark' ? 'checked' : '' }}
                            class="w-4 h-4 text-link focus:ring-primary-500 border-gray-300">
                        <div class="ml-3 flex-1">
                            <div class="flex items-center">
                                <i aria-hidden="true" class="fas fa-moon text-indigo-500 text-xl mr-3"></i>
                                <div>
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Dark Mode</span>
                                    <span class="block text-xs text-gray-600 dark:text-gray-400">Easy on the eyes, especially at night</span>
                                </div>
                            </div>
                        </div>
                    </label>
                    <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition {{ ($user->theme_preference ?? 'light') === 'system' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700' }}">
                        <input type="radio" name="theme_preference" value="system" {{ ($user->theme_preference ?? 'light') === 'system' ? 'checked' : '' }}
                            class="w-4 h-4 text-link focus:ring-primary-500 border-gray-300">
                        <div class="ml-3 flex-1">
                            <div class="flex items-center">
                                <i aria-hidden="true" class="fas fa-desktop text-primary-500 text-xl mr-3"></i>
                                <div>
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">System (Auto)</span>
                                    <span class="block text-xs text-gray-600 dark:text-gray-400">Match your operating system's theme</span>
                                </div>
                            </div>
                        </div>
                    </label>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>Your theme preference will be applied across all devices and browsers when you're logged in.
                </p>
                </fieldset>
            </div>

            <!-- Color Scheme -->
            <div id="settings-color-scheme" class="scroll-mt-4 border-t border-gray-200 dark:border-gray-700 pt-6">
                <fieldset>
                    <legend class="w-full"><h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                    <i aria-hidden="true" class="fas fa-swatchbook mr-2 text-link"></i>Color Scheme
                </h3></legend>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Choose the main color scheme for the site (sidebar, header, buttons, and accents).</p>
                @php
                    $currentScheme = \App\Enums\ColorScheme::fromPreference($user->color_scheme);
                @endphp
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach(\App\Enums\ColorScheme::cases() as $scheme)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:hover:border-primary-700 dark:hover:bg-primary-950/30">
                            <input type="radio"
                                name="color_scheme"
                                value="{{ $scheme->value }}"
                                {{ $currentScheme === $scheme ? 'checked' : '' }}
                                class="sr-only profile-scheme-radio"
                                data-scheme="{{ $scheme->value }}">
                            <span class="h-10 w-10 shrink-0 rounded-full {{ $scheme->swatchClass() }} ring-2 ring-offset-2 transition {{ $currentScheme === $scheme ? 'ring-primary-500 ring-offset-gray-100 dark:ring-offset-gray-800' : 'ring-transparent ring-offset-gray-100 hover:ring-gray-300 dark:ring-offset-gray-800' }}" aria-hidden="true"></span>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $scheme->label() }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>Save your profile to apply the color scheme. You can also change it from the user menu in the header.
                </p>
                </fieldset>
            </div>

            <!-- Timezone Preference -->
            <div id="settings-timezone" class="scroll-mt-4 border-t border-gray-200 dark:border-gray-700 pt-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                    <i aria-hidden="true" class="fas fa-clock mr-2 text-link dark:text-primary-400"></i>Timezone Preference
                </h3>
                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Select Your Timezone
                    </label>
                    <x-select name="timezone" id="timezone">
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
                    </x-select>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>All dates and times will be displayed in your selected timezone. Current server time: {{ now()->format('Y-m-d H:i:s T') }}
                    </p>
                </div>
            </div>

            <!-- View Preferences -->
            <div id="settings-cover-views" class="scroll-mt-4 border-t border-gray-200 dark:border-gray-700 pt-6">
                <fieldset>
                    <legend class="w-full"><h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Cover View Preferences</h3></legend>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="movieview" value="1" {{ $user->movieview ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Movie Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="musicview" value="1" {{ $user->musicview ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Music Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="consoleview" value="1" {{ $user->consoleview ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Console Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="gameview" value="1" {{ $user->gameview ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Game Covers</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="bookview" value="1" {{ $user->bookview ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Book Covers</span>
                    </label>
                </div>
                </fieldset>
            </div>

            <!-- Category Permissions -->
            <div id="settings-permissions" class="scroll-mt-4 border-t border-gray-200 dark:border-gray-700 pt-6">
                <fieldset>
                    <legend class="w-full"><h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Category Permissions</h3></legend>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="viewmovies" value="1" {{ $user->hasDirectPermission('view movies') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Movies</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewtv" value="1" {{ $user->hasDirectPermission('view tv') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">TV</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewaudio" value="1" {{ $user->hasDirectPermission('view audio') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Audio</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewpc" value="1" {{ $user->hasDirectPermission('view pc') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">PC</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewconsole" value="1" {{ $user->hasDirectPermission('view console') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Console</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewbooks" value="1" {{ $user->hasDirectPermission('view books') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Books</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewadult" value="1" {{ $user->hasDirectPermission('view adult') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Adult</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="viewother" value="1" {{ $user->hasDirectPermission('view other') ? 'checked' : '' }}
                            class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-400 focus:ring-primary-500 mr-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Other</span>
                    </label>
                </div>
                </fieldset>
            </div>

            <!-- Excluded Subcategories -->
            <div id="settings-exclusions" class="scroll-mt-4 border-t border-gray-200 dark:border-gray-700 pt-6">
                <fieldset>
                    <legend class="w-full"><h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">Exclude Subcategories</h3></legend>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Select specific subcategories you don't want to see in browse, search, API, and RSS results.
                    These will be hidden even if you have access to the parent category.
                </p>
                <div class="space-y-4 max-h-96 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    @foreach($categoriesWithSubs as $rootCategory)
                        <div class="mb-4">
                            <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                <i aria-hidden="true" class="fas fa-folder mr-2 text-gray-400"></i>{{ $rootCategory->title }}
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 ml-6">
                                @foreach($rootCategory->categories as $subcategory)
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox"
                                               name="excluded_categories[]"
                                               value="{{ $subcategory->id }}"
                                               {{ in_array($subcategory->id, $userExcludedCategories ?? []) ? 'checked' : '' }}
                                               class="rounded border-gray-300 dark:border-gray-600 text-danger-600 dark:text-danger-400 focus:ring-danger-500 mr-2">
                                        <span class="text-gray-600 dark:text-gray-400">{{ $subcategory->title }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                </fieldset>
            </div>

        </form>

            <!-- Actions -->
            <div class="sticky bottom-0 z-10 mt-6 flex items-center justify-between border-t border-gray-200 bg-surface px-6 py-4 dark:border-gray-700">
                <a href="{{ route('profile') }}" class="px-6 py-2 text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </a>
                <div class="flex space-x-2">
                    <form method="POST" action="{{ route('profileedit') }}">
                        @csrf
                        <input type="hidden" name="action" value="newapikey">
                        <button type="submit"
                            class="px-6 py-2 text-link bg-primary-100 dark:bg-primary-900/40 rounded-lg hover:bg-primary-200 transition"
                            data-confirm="Are you sure you want to generate a new API key?">
                            <i aria-hidden="true" class="fas fa-key mr-2"></i>New API Key
                        </button>
                    </form>
                    <button type="submit" form="profile-update-form" class="px-6 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800 transition">
                        <i aria-hidden="true" class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </div>

        <!-- 2FA Section (Outside main form) -->
        <div id="settings-security" class="scroll-mt-4 p-6 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                <i aria-hidden="true" class="fas fa-shield-alt mr-2 text-link"></i>Two-Factor Authentication (2FA)
            </h3>

            @if($user->passwordSecurity()->exists() && $user->passwordSecurity->google2fa_enable)
                <!-- 2FA is Enabled -->
                <div class="bg-success-50 dark:bg-success-900/20 border-l-4 border-success-500 rounded-lg p-4">
                    <div class="flex items-start">
                        <i aria-hidden="true" class="fas fa-check-circle text-success-600 dark:text-success-400 text-xl mr-3 mt-1"></i>
                        <div class="flex-1">
                            <h4 class="text-success-800 dark:text-success-200 font-semibold mb-1">Two-Factor Authentication is Active</h4>
                            <p class="text-success-700 dark:text-success-300 text-sm mb-4">Your account is protected with an additional layer of security. You'll need your authenticator app to log in.</p>

                            <!-- Collapsible Disable Form -->
                            <div class="space-y-3" x-data="profileEdit">
                                <button x-show="!show2faForm"
                                        @click="toggle2fa()"
                                        type="button"
                                        class="px-4 py-2 bg-danger-600 dark:bg-danger-700 text-white text-sm rounded-lg hover:bg-danger-700 dark:hover:bg-danger-800 transition">
                                    <i aria-hidden="true" class="fas fa-times-circle mr-2"></i>Disable 2FA
                                </button>

                                <div x-show="show2faForm"
                                     x-cloak
                                     class="bg-white dark:bg-gray-800 border border-danger-200 dark:border-danger-700 rounded-lg p-4 mt-3">
                                    <div class="mb-4 p-3 bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-700 rounded-lg">
                                        <div class="flex items-start">
                                            <i aria-hidden="true" class="fas fa-exclamation-triangle text-warning-600 dark:text-warning-400 mr-2 mt-0.5"></i>
                                            <div class="text-sm text-warning-800 dark:text-warning-200">
                                                <strong>Warning:</strong> Disabling 2FA will make your account less secure. Please enter your password and a current authentication code to confirm.
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('profileedit.disable2fa') }}" class="space-y-3">
                                        @csrf
                                        <div>
                                            <label for="disable_2fa_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Current Password
                                            </label>
                                            <div class="relative" x-data="passwordToggle">
                                                <input type="password" x-ref="field"
                                                       id="disable_2fa_password"
                                                       name="current-password"
                                                       required
                                                       class="w-full px-4 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-danger-500 focus:border-danger-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
                                                       placeholder="Enter your password">
                                                <button aria-label="Show password" :aria-label="label()" type="button" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="toggle()">
                        <i aria-hidden="true" class="fas" :class="iconClass()"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div>
                                            <label for="disable_2fa_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Authentication Code
                                            </label>
                                            <x-input type="text"
                                                   id="disable_2fa_code"
                                                   name="one_time_password"
                                                   required
                                                   inputmode="numeric"
                                                   pattern="[0-9]{6}"
                                                   maxlength="6"
                                                   autocomplete="one-time-code"
                                                   
                                                   placeholder="6-digit code from your authenticator app" />
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit"
                                                    class="px-4 py-2 bg-danger-600 dark:bg-danger-700 text-white text-sm rounded-lg hover:bg-danger-700 dark:hover:bg-danger-800 transition">
                                                <i aria-hidden="true" class="fas fa-check mr-2"></i>Confirm Disable
                                            </button>
                                            <button @click="cancel2fa()"
                                                    type="button"
                                                    class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition">
                                                <i aria-hidden="true" class="fas fa-times mr-2"></i>Cancel
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
                <div class="bg-primary-50 dark:bg-primary-900/20 border-l-4 border-primary-500 rounded-lg p-4">
                    <div class="flex items-start">
                        <i aria-hidden="true" class="fas fa-info-circle text-link dark:text-primary-400 text-xl mr-3 mt-1"></i>
                        <div class="flex-1">
                            <h4 class="text-primary-800 dark:text-primary-200 font-semibold mb-2">Complete Your 2FA Setup</h4>
                            <p class="text-link dark:text-primary-300 text-sm mb-4">Follow these steps to enable two-factor authentication:</p>

                            <ol class="list-decimal list-inside space-y-2 text-sm text-link dark:text-primary-300 mb-4">
                                <li>Install an authenticator app (Google Authenticator, Authy, or similar)</li>
                                <li>Scan the QR code below with your authenticator app</li>
                                <li>Enter the 6-digit code from your app to verify</li>
                            </ol>

                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4 flex justify-center">
                                {!! $google2fa_url !!}
                            </div>

                            <p class="text-xs text-link dark:text-primary-400 mb-4">
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
                                           inputmode="numeric"
                                           autocomplete="one-time-code"
                                           maxlength="6"
                                           required
                                           class="w-full md:w-64 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-center text-lg tracking-widest bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="px-4 py-2 bg-success-600 dark:bg-success-700 text-white text-sm rounded-lg hover:bg-success-700 dark:hover:bg-success-800 transition">
                                        <i aria-hidden="true" class="fas fa-check mr-2"></i>Verify and Enable 2FA
                                    </button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('profileedit.cancel2fa') }}" class="mt-2">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-gray-600 text-white text-sm rounded-lg hover:bg-gray-700 transition">
                                    <i aria-hidden="true" class="fas fa-times mr-2"></i>Cancel Setup
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <!-- 2FA Not Set Up -->
                <div class="bg-warning-50 dark:bg-warning-950/40 border-l-4 border-warning-500 rounded-lg p-4">
                    <div class="flex items-start">
                        <i aria-hidden="true" class="fas fa-exclamation-triangle text-warning-600 text-xl mr-3 mt-1"></i>
                        <div class="flex-1">
                            <h4 class="text-warning-800 dark:text-warning-200 font-semibold mb-1">Two-Factor Authentication is Disabled</h4>
                            <p class="text-warning-700 dark:text-warning-300 text-sm mb-4">Add an extra layer of security to your account by enabling two-factor authentication.</p>

                            <form method="POST" action="{{ route('generate2faSecret') }}">
                                @csrf
                                <input type="hidden" name="from_profile" value="1">
                                <button type="submit" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white text-sm rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800 transition">
                                    <i aria-hidden="true" class="fas fa-shield-alt mr-2"></i>Enable Two-Factor Authentication
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                <p class="text-xs text-gray-600">
                    <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>
                    <strong>What is 2FA?</strong> Two-Factor Authentication adds an extra layer of security by requiring both your password and a code from your phone to log in.
                </p>
            </div>
        </div>

        <div class="p-6 border-t border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                <i aria-hidden="true" class="fas fa-fingerprint mr-2 text-link"></i>Passkeys
            </h3>
            <div class="mt-4">
                @include('partials.passkeys-manage')
            </div>
        </div>
    </div>
</div>
@endsection
