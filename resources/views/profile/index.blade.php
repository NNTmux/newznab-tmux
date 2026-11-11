@extends('layouts.main')

@section('content')
<x-page-header :title="($publicview ?? false) ? $user->username : 'Your profile'" :current="$user->username" eyebrow="Account overview" description="Review account details, preferences, and download access." icon="fas fa-user-circle">
    @if(($isadmin ?? false) || !$publicview)
        <x-slot:actions>
            <a href="{{ route('profileedit') }}" class="workspace-hero__action"><i class="fas fa-user-pen" aria-hidden="true"></i>Edit profile</a>
            @if(!($isadmin ?? false) && !$publicview)
                <a href="{{ route('privacy-center.index') }}" class="workspace-hero__action"><i class="fas fa-shield-alt" aria-hidden="true"></i>Privacy center</a>
            @endif
        </x-slot:actions>
    @endif
</x-page-header>

<div class="surface-panel rounded-xl shadow-sm overflow-hidden">
        <!-- Profile Header -->
        <div class="surface-panel-alt border-b px-6 py-4 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i aria-hidden="true" class="fas fa-user mr-2"></i>User Profile
            </h2>
            <div class="flex gap-2">
                @if(($isadmin ?? false) || !$publicview)
                    <a href="{{ route('profileedit') }}" class="px-4 py-2 bg-success-600 dark:bg-success-700 text-white text-sm rounded hover:bg-success-700 dark:hover:bg-success-800 transition">
                        <i aria-hidden="true" class="fa-solid fa-edit mr-1"></i>Edit Profile
                    </a>
                @endif
                @if(!($isadmin ?? false) && !$publicview)
                    <a href="{{ route('privacy-center.index') }}" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white text-sm rounded hover:bg-accent-hover dark:hover:bg-primary-800 transition">
                        <i aria-hidden="true" class="fa-solid fa-shield-alt mr-1"></i>Privacy Center
                    </a>
                @endif
                @if(!($isadmin ?? false) && !$publicview)
                    <form method="POST" action="{{ route('profile_delete') }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="id" value="{{ $user->id }}">
                        <button type="submit"
                            class="px-4 py-2 bg-danger-600 dark:bg-danger-700 text-white text-sm rounded hover:bg-danger-700 dark:hover:bg-danger-800 transition"
                            data-confirm="Are you sure you want to delete your account? This action cannot be undone.">
                            <i aria-hidden="true" class="fa-solid fa-trash mr-1"></i>Delete Account
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Left Sidebar with Avatar and Tabs -->
                <div class="lg:col-span-1">
                    <!-- Avatar -->
                    <div class="flex justify-center mb-6">
                        <img width="120" height="120" src="{{ !empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL) ? 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user->email))) . '?s=120&d=mp' : 'https://www.gravatar.com/avatar/?d=mp&s=120' }}"
                             alt="{{ $user->username }}"
                             loading="lazy"
                             class="w-30 h-30 rounded-full border-4 border-gray-200 dark:border-gray-700 shadow-lg">
                    </div>

                    <!-- Tab Navigation -->
                    <div class="space-y-1">
                        <a href="#general" class="tab-link flex items-center px-4 py-3 bg-primary-50 dark:bg-primary-900/20 text-link dark:text-primary-300 rounded-lg font-medium">
                            <i aria-hidden="true" class="fa-solid fa-info-circle mr-3"></i>General Information
                        </a>
                        <a href="#preferences" class="tab-link flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-900 rounded-lg">
                            <i aria-hidden="true" class="fa-solid fa-sliders-h mr-3"></i>UI Preferences
                        </a>
                        <a href="#api" class="tab-link flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-900 rounded-lg">
                            <i aria-hidden="true" class="fa-solid fa-key mr-3"></i>API & Downloads
                        </a>
                        @if(($user->id === auth()->id() || ($isadmin ?? false)) && config('nntmux.registerstatus') == 1)
                            <a href="{{ route('invitations.index') }}" class="flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-900 rounded-lg">
                                <i aria-hidden="true" class="fa-solid fa-envelope mr-3"></i>My Invitations
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Right Content Area with Tabs -->
                <div class="lg:col-span-3">
                    <!-- General Information Tab -->
                    <div id="general" class="tab-content">
                        <div class="surface-panel-alt rounded-lg p-6 mb-6 border">
                            <div class="flex items-center mb-4">
                                <i aria-hidden="true" class="fa-solid fa-info-circle text-link dark:text-primary-400 mr-2"></i>
                                <h2 class="text-lg font-semibold">General Information</h2>
                            </div>
                            <div class="space-y-4">
                                <div class="flex flex-col gap-1 border-b border-gray-200 pb-3 sm:flex-row sm:gap-0 dark:border-gray-700">
                                    <div class="w-full text-gray-600 sm:w-1/3 dark:text-gray-400">Username</div>
                                    <div class="w-full sm:w-2/3 font-medium">{{ $user->username }}</div>
                                </div>

                                @if(($isadmin ?? false) || !$publicview)
                                    <div class="flex flex-col gap-1 border-b border-gray-200 pb-3 sm:flex-row sm:gap-0 dark:border-gray-700">
                                        <div class="w-full text-gray-600 sm:w-1/3 dark:text-gray-400">Email</div>
                                        <div class="w-full sm:w-2/3 font-medium">{{ $user->email }}</div>
                                    </div>
                                @endif

                                <div class="flex flex-col gap-1 border-b border-gray-200 pb-3 sm:flex-row sm:gap-0 dark:border-gray-700">
                                    <div class="w-full text-gray-600 sm:w-1/3 dark:text-gray-400">Registered</div>
                                    <div class="w-full sm:w-2/3">
                                        <i aria-hidden="true" class="fa-solid fa-calendar text-gray-400 mr-2"></i>
                                        {{ userDate($user->created_at, 'M d, Y') }}
                                        <span class="ml-2 px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs rounded">{{ userDateDiffForHumans($user->created_at) }}</span>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1 border-b border-gray-200 pb-3 sm:flex-row sm:gap-0 dark:border-gray-700">
                                    <div class="w-full text-gray-600 sm:w-1/3 dark:text-gray-400">Role</div>
                                    <div class="w-full sm:w-2/3">
                                        <span class="px-3 py-1 bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200 text-sm rounded-full">
                                            <i aria-hidden="true" class="fa-solid fa-id-badge mr-1"></i>{{ $user->roles->first()->name ?? 'User' }}
                                        </span>
                                    </div>
                                </div>

                                @if($user->rolechangedate)
                                    <div class="flex flex-col gap-1 border-b border-gray-200 pb-3 sm:flex-row sm:gap-0 dark:border-gray-700">
                                        <div class="w-full text-gray-600 sm:w-1/3 dark:text-gray-400">Role Expiry</div>
                                        <div class="w-full sm:w-2/3">
                                            @php
                                                $roleExpiryDate = \Carbon\Carbon::parse($user->rolechangedate);
                                                $now = \Carbon\Carbon::now();
                                                $isExpired = $roleExpiryDate->isPast();
                                                $daysUntilExpiry = $now->diffInDays($roleExpiryDate, false);
                                                $expiryDiff = $roleExpiryDate->diffForHumans(['parts' => 2]);
                                            @endphp
                                            <div class="flex items-center">
                                                <i aria-hidden="true" class="fa-solid fa-calendar-times text-gray-400 mr-2"></i>
                                                <span class="font-medium">{{ $roleExpiryDate->format('M d, Y') }}</span>
                                                @if($isExpired)
                                                    <span class="ml-2 px-2 py-1 bg-danger-100 dark:bg-danger-900 text-danger-800 dark:text-danger-200 text-xs rounded">
                                                        <i aria-hidden="true" class="fa-solid fa-exclamation-circle mr-1"></i>Expired {{ $expiryDiff }}
                                                    </span>
                                                @elseif($daysUntilExpiry <= 7)
                                                    <span class="ml-2 px-2 py-1 bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200 text-xs rounded">
                                                        <i aria-hidden="true" class="fa-solid fa-clock mr-1"></i>Expires {{ $expiryDiff }}
                                                    </span>
                                                @elseif($daysUntilExpiry <= 30)
                                                    <span class="ml-2 px-2 py-1 bg-warning-100 dark:bg-warning-900 text-warning-800 dark:text-warning-200 text-xs rounded">
                                                        <i aria-hidden="true" class="fa-solid fa-clock mr-1"></i>Expires {{ $expiryDiff }}
                                                    </span>
                                                @else
                                                    <span class="ml-2 px-2 py-1 bg-success-100 dark:bg-success-900 text-success-800 dark:text-success-200 text-xs rounded">
                                                        <i aria-hidden="true" class="fa-solid fa-check-circle mr-1"></i>Expires {{ $expiryDiff }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @php
                                    $allPendingRoles = $user->getAllPendingStackedRoles();
                                @endphp
                                @if($allPendingRoles->count() > 0)
                                    <div class="flex flex-col gap-1 border-b border-gray-200 pb-3 sm:flex-row sm:gap-0 dark:border-gray-700">
                                        <div class="w-full text-gray-600 sm:w-1/3 dark:text-gray-400">Pending Role{{ $allPendingRoles->count() > 1 ? 's' : '' }}</div>
                                        <div class="w-full sm:w-2/3">
                                            <div class="space-y-3">
                                                @foreach($allPendingRoles as $index => $pendingRoleInfo)
                                                    <div class="@if(!$loop->last) pb-3 border-b border-gray-100 dark:border-gray-700 @endif">
                                                        <div class="flex items-center">
                                                            <span class="flex items-center justify-center w-6 h-6 bg-primary-100 dark:bg-primary-900/50 text-link dark:text-primary-400 text-xs font-bold rounded-full mr-2">
                                                                {{ $index + 1 }}
                                                            </span>
                                                            <i aria-hidden="true" class="fa-solid fa-layer-group text-primary-400 mr-2"></i>
                                                            <span class="font-medium text-link dark:text-primary-400">{{ $pendingRoleInfo['role_name'] }}</span>
                                                            <span class="ml-2 px-2 py-1 bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200 text-xs rounded">
                                                                <i aria-hidden="true" class="fa-solid fa-clock mr-1"></i>Scheduled
                                                            </span>
                                                        </div>
                                                        <div class="ml-8 mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                            <div>
                                                                <i aria-hidden="true" class="fa-solid fa-play-circle text-success-500 mr-1"></i>
                                                                <span class="font-medium">Starts:</span> {{ $pendingRoleInfo['start_date']->format('M d, Y g:i A') }}
                                                                <span class="text-gray-400">({{ $pendingRoleInfo['start_date']->diffForHumans() }})</span>
                                                            </div>
                                                            @if($pendingRoleInfo['end_date'])
                                                                <div class="mt-1">
                                                                    <i aria-hidden="true" class="fa-solid fa-stop-circle text-danger-400 mr-1"></i>
                                                                    <span class="font-medium">Ends:</span> {{ $pendingRoleInfo['end_date']->format('M d, Y g:i A') }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                                <div class="text-xs text-link dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 p-2 rounded mt-2">
                                                    <i aria-hidden="true" class="fa-solid fa-info-circle mr-1"></i>
                                                    These roles will automatically activate in sequence as each previous role expires
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @elseif($user->hasPendingRole())
                                    {{-- Fallback to old display if no history records but pending_roles_id is set --}}
                                    <div class="flex flex-col gap-1 border-b border-gray-200 pb-3 sm:flex-row sm:gap-0 dark:border-gray-700">
                                        <div class="w-full text-gray-600 sm:w-1/3 dark:text-gray-400">Pending Role</div>
                                        <div class="w-full sm:w-2/3">
                                            @php
                                                $pendingRole = $user->getPendingRole();
                                                $pendingStartDate = \Carbon\Carbon::parse($user->pending_role_start_date);
                                            @endphp
                                            <div class="space-y-2">
                                                <div class="flex items-center">
                                                    <i aria-hidden="true" class="fa-solid fa-layer-group text-primary-400 mr-2"></i>
                                                    <span class="font-medium text-link dark:text-primary-400">{{ $pendingRole->name ?? 'Unknown' }}</span>
                                                    <span class="ml-2 px-2 py-1 bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200 text-xs rounded">
                                                        <i aria-hidden="true" class="fa-solid fa-clock mr-1"></i>Scheduled
                                                    </span>
                                                </div>
                                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                                    <i aria-hidden="true" class="fa-solid fa-calendar-alt mr-1"></i>
                                                    Will activate {{ $pendingStartDate->diffForHumans() }} on {{ $pendingStartDate->format('M d, Y g:i A') }}
                                                </div>
                                                <div class="text-xs text-link dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 p-2 rounded">
                                                    <i aria-hidden="true" class="fa-solid fa-info-circle mr-1"></i>
                                                    This role will automatically activate when your current role expires
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="flex flex-col gap-1 border-b border-gray-200 pb-3 sm:flex-row sm:gap-0 dark:border-gray-700">
                                    <div class="w-full text-gray-600 sm:w-1/3 dark:text-gray-400">Last Login</div>
                                    <div class="w-full sm:w-2/3">{{ userDateDiffForHumans($user->lastlogin) }}</div>
                                </div>

                                @if($userinvitedby)
                                    <div class="flex flex-col gap-1 border-b border-gray-200 pb-3 sm:flex-row sm:gap-0 dark:border-gray-700">
                                        <div class="w-full text-gray-600 sm:w-1/3 dark:text-gray-400">Invited By</div>
                                        <div class="w-full sm:w-2/3">
                                            <a href="{{ url('/profile?id=' . $userinvitedby->id) }}" class="text-link dark:text-primary-400 hover:text-primary-800">
                                                {{ $userinvitedby->username }}
                                            </a>
                                        </div>
                                    </div>
                                @endif

                                <div class="flex flex-col gap-1 border-b border-gray-200 pb-3 sm:flex-row sm:gap-0 dark:border-gray-700">
                                    <div class="w-full text-gray-600 sm:w-1/3 dark:text-gray-400">Grabs</div>
                                    <div class="w-full sm:w-2/3 font-semibold text-success-600">{{ number_format($user->grabs ?? 0) }}</div>
                                </div>

                                <div class="flex pb-3">
                                    <div class="w-full text-gray-600 sm:w-1/3 dark:text-gray-400">Timezone</div>
                                    <div class="w-full sm:w-2/3">
                                        @php
                                            $userTimezone = $user->timezone ?? 'UTC';
                                            $timezoneDisplay = str_replace('_', ' ', $userTimezone);

                                            // Get current time in user's timezone
                                            try {
                                                $currentTime = \Carbon\Carbon::now($userTimezone)->format('H:i');
                                                $currentDate = \Carbon\Carbon::now($userTimezone)->format('M d, Y');
                                            } catch (\Exception $e) {
                                                $currentTime = \Carbon\Carbon::now('UTC')->format('H:i');
                                                $currentDate = \Carbon\Carbon::now('UTC')->format('M d, Y');
                                            }
                                        @endphp
                                        <div class="flex items-center">
                                            <i aria-hidden="true" class="fa-solid fa-clock text-link dark:text-primary-400 mr-2"></i>
                                            <span class="font-medium">{{ $timezoneDisplay }}</span>
                                        </div>
                                        <div class="mt-1 text-sm text-gray-500">
                                            <i aria-hidden="true" class="fa-solid fa-calendar-alt text-gray-400 mr-1"></i>
                                            Current time: {{ $currentDate }} {{ $currentTime }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- UI Preferences Tab -->
                    <div id="preferences" class="tab-content hidden-by-default">
                        <div class="surface-panel-alt rounded-lg p-6 mb-6 border">
                            <div class="flex items-center mb-4">
                                <i aria-hidden="true" class="fa-solid fa-sliders-h text-link dark:text-primary-400 mr-2"></i>
                                <h2 class="text-lg font-semibold">UI Preferences</h2>
                            </div>

                            <!-- Theme Preference -->
                            <div class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                    <i aria-hidden="true" class="fa-solid fa-palette mr-2"></i>Theme Mode
                                </h3>
                                <div class="flex items-center">
                                    @php
                                        $themePreference = $user->theme_preference ?? 'light';
                                    @endphp
                                    @if($themePreference === 'dark')
                                        <div class="flex items-center px-4 py-2 bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 rounded-lg">
                                            <i aria-hidden="true" class="fas fa-moon text-indigo-600 dark:text-indigo-400 text-lg mr-2"></i>
                                            <span class="font-medium">Dark Mode</span>
                                        </div>
                                    @elseif($themePreference === 'system')
                                        <div class="flex items-center px-4 py-2 bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200 rounded-lg">
                                            <i aria-hidden="true" class="fas fa-desktop text-link dark:text-primary-400 text-lg mr-2"></i>
                                            <span class="font-medium">System (Auto)</span>
                                        </div>
                                    @else
                                        <div class="flex items-center px-4 py-2 bg-warning-100 dark:bg-warning-900 text-warning-800 dark:text-warning-200 rounded-lg">
                                            <i aria-hidden="true" class="fas fa-sun text-amber-600 dark:text-amber-400 text-lg mr-2"></i>
                                            <span class="font-medium">Light Mode</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Cover Preferences -->
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                    <i aria-hidden="true" class="fa-solid fa-images mr-2"></i>Cover Display
                                </h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex items-center">
                                        <i aria-hidden="true" class="fa {{ $user->movieview ? 'fa-check-square text-success-600' : 'fa-square text-gray-400' }} mr-2"></i>
                                        <span>Movie Covers</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i aria-hidden="true" class="fa {{ $user->musicview ? 'fa-check-square text-success-600' : 'fa-square text-gray-400' }} mr-2"></i>
                                        <span>Music Covers</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i aria-hidden="true" class="fa {{ $user->consoleview ? 'fa-check-square text-success-600' : 'fa-square text-gray-400' }} mr-2"></i>
                                        <span>Console Covers</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i aria-hidden="true" class="fa {{ $user->gameview ? 'fa-check-square text-success-600' : 'fa-square text-gray-400' }} mr-2"></i>
                                        <span>Game Covers</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i aria-hidden="true" class="fa {{ $user->bookview ? 'fa-check-square text-success-600' : 'fa-square text-gray-400' }} mr-2"></i>
                                        <span>Book Covers</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API & Downloads Tab -->
                    <div id="api" class="tab-content hidden-by-default">
                        <div class="surface-panel-alt rounded-lg p-6 mb-6 border">
                            <div class="flex items-center mb-4">
                                <i aria-hidden="true" class="fa-solid fa-key text-link dark:text-primary-400 mr-2"></i>
                                <h2 class="text-lg font-semibold">API & Downloads</h2>
                            </div>

                            <!-- Usage Limits -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6" x-data="profilePage">
                                <!-- Downloads Usage -->
                                <div class="surface-panel rounded-lg p-4 shadow">
                                    <div class="flex justify-between items-center mb-2">
                                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            <i aria-hidden="true" class="fa-solid fa-download text-success-600 mr-2"></i>Downloads (24h)
                                        </h3>
                                        <span class="text-sm font-bold {{ $downloadLimit > 0 && $grabstoday >= $downloadLimit ? 'text-danger-600' : 'text-success-600' }}">
                                            {{ $grabstoday ?? 0 }} / {{ $downloadLimit }}
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                        @php
                                            $downloadPercentage = $downloadLimit > 0 ? min(($grabstoday / $downloadLimit) * 100, 100) : 0;
                                            $downloadColor = $downloadPercentage >= 90 ? 'bg-danger-600' : ($downloadPercentage >= 70 ? 'bg-warning-500' : 'bg-success-600');
                                        @endphp
                                        <div class="{{ $downloadColor }} h-3 rounded-full progress-bar" data-width="{{ $downloadPercentage }}" role="progressbar" aria-label="Downloads used in the last 24 hours" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) round($downloadPercentage) }}"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ number_format($downloadLimit - $grabstoday) }} downloads remaining
                                    </p>
                                </div>

                                <!-- API Requests Usage -->
                                <div class="surface-panel rounded-lg p-4 shadow">
                                    <div class="flex justify-between items-center mb-2">
                                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            <i aria-hidden="true" class="fa-solid fa-server text-purple-600 mr-2"></i>API Requests (24h)
                                        </h3>
                                        <span class="text-sm font-bold {{ $apirequests >= $apiLimit ? 'text-danger-600' : 'text-success-600' }}">
                                            {{ $apirequests ?? 0 }} / {{ $apiLimit }}
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                        @php
                                            $apiPercentage = $apiLimit > 0 ? min(($apirequests / $apiLimit) * 100, 100) : 0;
                                            $apiColor = $apiPercentage >= 90 ? 'bg-danger-600' : ($apiPercentage >= 70 ? 'bg-warning-500' : 'bg-purple-600');
                                        @endphp
                                        <div class="{{ $apiColor }} h-3 rounded-full progress-bar" data-width="{{ $apiPercentage }}" role="progressbar" aria-label="API requests used in the last 24 hours" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) round($apiPercentage) }}"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ number_format($apiLimit - $apirequests) }} requests remaining
                                    </p>
                                </div>
                            </div>

                            <!-- Stats Cards -->
                            <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-3">
                                <div class="surface-panel rounded-lg p-4 text-center shadow">
                                    <div class="text-3xl font-bold text-link dark:text-primary-400">{{ $user->grabs ?? 0 }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Total Grabs</div>
                                </div>
                                <div class="surface-panel rounded-lg p-4 text-center shadow">
                                    <div class="text-3xl font-bold text-success-600">{{ $grabstoday ?? 0 }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Today</div>
                                </div>
                                <div class="surface-panel rounded-lg p-4 text-center shadow">
                                    <div class="text-3xl font-bold text-purple-600">{{ $apirequests ?? 0 }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">API Requests</div>
                                </div>
                            </div>


                            @if(($isadmin ?? false) || !$publicview)
                                <!-- API Token -->
                                <div class="surface-panel rounded-lg p-4 shadow" x-data="copyToClipboard">
                                    <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        <label for="profile-api-token">
                                            <i aria-hidden="true" class="fa-solid fa-key text-link dark:text-primary-400 mr-2"></i>API Token
                                        </label>
                                    </h3>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <input type="text" id="profile-api-token" readonly value="{{ $user->api_token }}"
                                               class="min-h-11 w-full min-w-0 rounded-control border border-line bg-surface-sunken px-3.5 py-2.5 font-mono text-sm text-fg sm:flex-1">
                                        <x-button type="button" variant="secondary" class="shrink-0" @click="copy('profile-api-token')" aria-label="Copy API token" title="Copy API token">
                                            <i aria-hidden="true" class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                                            <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                                        </x-button>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Used for API and RSS requests. Keep it private.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
    </div>
</div>


@endsection
