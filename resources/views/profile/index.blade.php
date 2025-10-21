@extends('layouts.main')

@section('content')
<!-- Breadcrumb -->
<nav class="mb-4 text-sm" aria-label="breadcrumb">
    <ol class="flex items-center space-x-2">
        <li><a href="{{ url('/') }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800">Home</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="#" class="text-blue-600 dark:text-blue-400 hover:text-blue-800">Profile</a></li>
        <li class="text-gray-400">/</li>
        <li class="text-gray-600">{{ $user->username }}</li>
    </ol>
</nav>

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <!-- Profile Header -->
        <div class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center">
            <h1 class="text-xl font-semibold text-gray-800">
                <i class="fas fa-user mr-2"></i>User Profile
            </h1>
            <div class="flex gap-2">
                @if(($isadmin ?? false) || !$publicview)
                    <a href="{{ route('profileedit') }}" class="px-4 py-2 bg-green-600 dark:bg-green-700 text-white text-sm rounded hover:bg-green-700 dark:hover:bg-green-800 transition">
                        <i class="fa fa-edit mr-1"></i>Edit Profile
                    </a>
                @endif
                @if(!($isadmin ?? false) && !$publicview)
                    <a href="{{ url('profile_delete?id=' . $user->id) }}"
                       class="px-4 py-2 bg-red-600 dark:bg-red-700 text-white text-sm rounded hover:bg-red-700 dark:hover:bg-red-800 transition"
                       onclick="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
                        <i class="fa fa-trash mr-1"></i>Delete Account
                    </a>
                @endif
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Left Sidebar with Avatar and Tabs -->
                <div class="lg:col-span-1">
                    <!-- Avatar -->
                    <div class="flex justify-center mb-6">
                        <img src="{{ Gravatar::get($user->email, ['size' => 120, 'default' => 'mp']) }}"
                             alt="{{ $user->username }}"
                             class="w-30 h-30 rounded-full border-4 border-gray-200 dark:border-gray-700 shadow-lg">
                    </div>

                    <!-- Tab Navigation -->
                    <div class="space-y-1">
                        <a href="#general" class="tab-link flex items-center px-4 py-3 bg-blue-50 text-blue-700 rounded-lg font-medium">
                            <i class="fa fa-info-circle mr-3"></i>General Information
                        </a>
                        <a href="#preferences" class="tab-link flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <i class="fa fa-sliders-h mr-3"></i>UI Preferences
                        </a>
                        <a href="#api" class="tab-link flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <i class="fa fa-key mr-3"></i>API & Downloads
                        </a>
                        @if(($user->id === auth()->id() || ($isadmin ?? false)) && config('nntmux.registerstatus') == 1)
                            <a href="{{ route('invitations.index') }}" class="flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <i class="fa fa-envelope mr-3"></i>My Invitations
                            </a>
                        @endif
                        @if(($isadmin ?? false) && isset($downloadlist) && count($downloadlist) > 0)
                            <a href="#downloads" class="tab-link flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <i class="fa fa-download mr-3"></i>Recent Downloads
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Right Content Area with Tabs -->
                <div class="lg:col-span-3">
                    <!-- General Information Tab -->
                    <div id="general" class="tab-content">
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6 mb-6">
                            <div class="flex items-center mb-4">
                                <i class="fa fa-info-circle text-blue-600 dark:text-blue-400 mr-2"></i>
                                <h2 class="text-lg font-semibold">General Information</h2>
                            </div>
                            <div class="space-y-4">
                                <div class="flex border-b border-gray-200 dark:border-gray-700 pb-3">
                                    <div class="w-1/3 text-gray-600">Username</div>
                                    <div class="w-2/3 font-medium">{{ $user->username }}</div>
                                </div>

                                @if(($isadmin ?? false) || !$publicview)
                                    <div class="flex border-b border-gray-200 dark:border-gray-700 pb-3">
                                        <div class="w-1/3 text-gray-600">Email</div>
                                        <div class="w-2/3 font-medium">{{ $user->email }}</div>
                                    </div>
                                @endif

                                <div class="flex border-b border-gray-200 dark:border-gray-700 pb-3">
                                    <div class="w-1/3 text-gray-600">Registered</div>
                                    <div class="w-2/3">
                                        <i class="fa fa-calendar text-gray-400 mr-2"></i>
                                        {{ \Carbon\Carbon::parse($user->created_at)->format('M d, Y') }}
                                        <span class="ml-2 px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs rounded">{{ \Carbon\Carbon::parse($user->created_at)->diffForHumans() }}</span>
                                    </div>
                                </div>

                                <div class="flex border-b border-gray-200 dark:border-gray-700 pb-3">
                                    <div class="w-1/3 text-gray-600">Role</div>
                                    <div class="w-2/3">
                                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                                            <i class="fa fa-id-badge mr-1"></i>{{ $user->roles->first()->name ?? 'User' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex border-b border-gray-200 dark:border-gray-700 pb-3">
                                    <div class="w-1/3 text-gray-600">Last Login</div>
                                    <div class="w-2/3">{{ \Carbon\Carbon::parse($user->lastlogin)->diffForHumans() }}</div>
                                </div>

                                @if($userinvitedby)
                                    <div class="flex border-b border-gray-200 dark:border-gray-700 pb-3">
                                        <div class="w-1/3 text-gray-600">Invited By</div>
                                        <div class="w-2/3">
                                            <a href="{{ url('/profile?id=' . $userinvitedby->id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800">
                                                {{ $userinvitedby->username }}
                                            </a>
                                        </div>
                                    </div>
                                @endif

                                <div class="flex pb-3">
                                    <div class="w-1/3 text-gray-600">Grabs</div>
                                    <div class="w-2/3 font-semibold text-green-600">{{ number_format($user->grabs ?? 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- UI Preferences Tab -->
                    <div id="preferences" class="tab-content hidden-by-default">
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6 mb-6">
                            <div class="flex items-center mb-4">
                                <i class="fa fa-sliders-h text-blue-600 dark:text-blue-400 mr-2"></i>
                                <h2 class="text-lg font-semibold">UI Preferences</h2>
                            </div>

                            <!-- Theme Preference -->
                            <div class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                    <i class="fa fa-palette mr-2"></i>Theme Mode
                                </h3>
                                <div class="flex items-center">
                                    @php
                                        $themePreference = $user->theme_preference ?? 'light';
                                    @endphp
                                    @if($themePreference === 'dark')
                                        <div class="flex items-center px-4 py-2 bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 rounded-lg">
                                            <i class="fas fa-moon text-indigo-600 dark:text-indigo-400 text-lg mr-2"></i>
                                            <span class="font-medium">Dark Mode</span>
                                        </div>
                                    @elseif($themePreference === 'system')
                                        <div class="flex items-center px-4 py-2 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-lg">
                                            <i class="fas fa-desktop text-blue-600 dark:text-blue-400 text-lg mr-2"></i>
                                            <span class="font-medium">System (Auto)</span>
                                        </div>
                                    @else
                                        <div class="flex items-center px-4 py-2 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded-lg">
                                            <i class="fas fa-sun text-yellow-600 dark:text-yellow-400 text-lg mr-2"></i>
                                            <span class="font-medium">Light Mode</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Cover Preferences -->
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                    <i class="fa fa-images mr-2"></i>Cover Display
                                </h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex items-center">
                                        <i class="fa {{ $user->movieview ? 'fa-check-square text-green-600' : 'fa-square text-gray-400' }} mr-2"></i>
                                        <span>Movie Covers</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fa {{ $user->musicview ? 'fa-check-square text-green-600' : 'fa-square text-gray-400' }} mr-2"></i>
                                        <span>Music Covers</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fa {{ $user->consoleview ? 'fa-check-square text-green-600' : 'fa-square text-gray-400' }} mr-2"></i>
                                        <span>Console Covers</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fa {{ $user->gameview ? 'fa-check-square text-green-600' : 'fa-square text-gray-400' }} mr-2"></i>
                                        <span>Game Covers</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fa {{ $user->bookview ? 'fa-check-square text-green-600' : 'fa-square text-gray-400' }} mr-2"></i>
                                        <span>Book Covers</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fa {{ $user->xxxview ? 'fa-check-square text-green-600' : 'fa-square text-gray-400' }} mr-2"></i>
                                        <span>XXX Covers</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API & Downloads Tab -->
                    <div id="api" class="tab-content hidden-by-default">
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6 mb-6">
                            <div class="flex items-center mb-4">
                                <i class="fa fa-key text-blue-600 dark:text-blue-400 mr-2"></i>
                                <h2 class="text-lg font-semibold">API & Downloads</h2>
                            </div>

                            <!-- Stats -->
                            <div class="grid grid-cols-3 gap-4 mb-6">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 text-center shadow">
                                    <div class="text-3xl font-bold text-blue-600">{{ $user->grabs ?? 0 }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Total Grabs</div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 text-center shadow">
                                    <div class="text-3xl font-bold text-green-600">{{ $grabstoday ?? 0 }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Today</div>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 text-center shadow">
                                    <div class="text-3xl font-bold text-purple-600">{{ $apirequests ?? 0 }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">API Requests</div>
                                </div>
                            </div>

                            @if(($isadmin ?? false) || !$publicview)
                                <!-- API Keys -->
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">API Token</label>
                                        <code class="block text-xs bg-gray-800 text-green-400 p-3 rounded break-all">{{ $user->api_token }}</code>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Recent Downloads Tab -->
                    @if(($isadmin ?? false) && isset($downloadlist) && count($downloadlist) > 0)
                        <div id="downloads" class="tab-content hidden-by-default">
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6">
                                <div class="flex items-center mb-4">
                                    <i class="fa fa-download text-blue-600 dark:text-blue-400 mr-2"></i>
                                    <h2 class="text-lg font-semibold">Recent Downloads</h2>
                                </div>
                                <div class="space-y-2">
                                    @foreach($downloadlist->take(20) as $download)
                                        <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700 last:border-0">
                                            <a href="{{ url('/details/' . $download->guid) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 flex-1 break-words break-all">
                                                {{ $download->searchname }}
                                            </a>
                                            <span class="text-sm text-gray-500 ml-4">{{ \Carbon\Carbon::parse($download->created_at)->diffForHumans() }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
    </div>
</div>
@endsection

