@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa fa-user mr-2"></i>{{ $title }}
            </h1>
        </div>

        <!-- Error Messages -->
        @if(!empty($error))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg">
                <p class="text-red-800 dark:text-red-200">
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
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Username <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="username"
                           name="username"
                           value="{{ is_array($user) ? ($user['username'] ?? '') : ($user->username ?? '') }}"
                           required
                           class="w-full px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500">
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
                           class="w-full px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Password @if(empty($user['id']))<span class="text-red-500">*</span>@endif
                    </label>
                    <input type="password"
                           id="password"
                           name="password"
                           placeholder="{{ !empty($user['id']) ? 'Leave blank to keep current password' : '' }}"
                           @if(empty($user['id'])) required @endif
                           class="w-full px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 placeholder:text-gray-400 dark:placeholder:text-gray-500">
                    @if(!empty($user['id']))
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Leave blank to keep the current password</p>
                    @endif
                </div>

                <!-- Role -->
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Role <span class="text-red-500">*</span>
                    </label>
                    <select id="role"
                            name="role"
                            required
                            class="w-full px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        @foreach($role_ids ?? [] as $index => $roleId)
                            <option value="{{ $roleId }}"
                                {{ (is_array($user) ? ($user['role'] ?? '') : ($user->roles->first()->id ?? '')) == $roleId ? 'selected' : '' }}>
                                {{ $role_names[$roleId] ?? '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Role Expiry Date -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gradient-to-br from-gray-50 to-white dark:from-gray-900 dark:to-gray-800">
                    <div class="flex items-center justify-between mb-3">
                        <label for="rolechangedate" class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center">
                            <i class="fa fa-calendar-alt mr-2 text-blue-600 dark:text-blue-400"></i>
                            Role Expiry Date
                        </label>
                        @if(!empty($user->rolechangedate ?? ''))
                            @php
                                $expiryDate = \Carbon\Carbon::parse($user->rolechangedate);
                                $isExpired = $expiryDate->isPast();
                                $daysUntilExpiry = $expiryDate->diffInDays(now());
                            @endphp
                            @if($isExpired)
                                <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 animate-pulse">
                                    <i class="fa fa-exclamation-triangle mr-1"></i> Expired
                                </span>
                            @elseif($daysUntilExpiry <= 7)
                                <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                    <i class="fa fa-exclamation-circle mr-1"></i> Expiring Soon
                                </span>
                            @else
                                <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                    <i class="fa fa-check-circle mr-1"></i> Active
                                </span>
                            @endif
                        @else
                            <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                                <i class="fa fa-infinity mr-1"></i> No Expiry
                            </span>
                        @endif
                    </div>

                    <!-- Hidden input for form submission -->
                    <input type="hidden" id="rolechangedate" name="rolechangedate" value="{{ is_array($user) ? ($user['rolechangedate'] ?? '') : (isset($user->rolechangedate) ? \Carbon\Carbon::parse($user->rolechangedate)->format('Y-m-d\TH:i:s') : '') }}">

                    <!-- Custom DateTime Picker -->
                    <div class="grid grid-cols-5 gap-3">
                        <!-- Year Selector -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                <i class="fa fa-calendar-alt mr-1"></i>Year
                            </label>
                            <select id="expiry_year"
                                    class="w-full px-2 py-3 text-lg font-semibold bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all shadow-sm hover:shadow-md hover:border-blue-400 dark:hover:border-blue-500">
                                <option value="">--</option>
                                @for($y = date('Y'); $y <= date('Y') + 5; $y++)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>

                        <!-- Month Selector -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                <i class="fa fa-calendar mr-1"></i>Month
                            </label>
                            <select id="expiry_month"
                                    class="w-full px-2 py-3 text-lg font-semibold bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all shadow-sm hover:shadow-md hover:border-blue-400 dark:hover:border-blue-500">
                                <option value="">--</option>
                                <option value="01">Jan</option>
                                <option value="02">Feb</option>
                                <option value="03">Mar</option>
                                <option value="04">Apr</option>
                                <option value="05">May</option>
                                <option value="06">Jun</option>
                                <option value="07">Jul</option>
                                <option value="08">Aug</option>
                                <option value="09">Sep</option>
                                <option value="10">Oct</option>
                                <option value="11">Nov</option>
                                <option value="12">Dec</option>
                            </select>
                        </div>

                        <!-- Day Selector -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                <i class="fa fa-calendar-day mr-1"></i>Day
                            </label>
                            <select id="expiry_day"
                                    class="w-full px-2 py-3 text-lg font-semibold bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all shadow-sm hover:shadow-md hover:border-blue-400 dark:hover:border-blue-500">
                                <option value="">--</option>
                                @for($d = 1; $d <= 31; $d++)
                                    <option value="{{ sprintf('%02d', $d) }}">{{ $d }}</option>
                                @endfor
                            </select>
                        </div>

                        <!-- Hour Selector -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                <i class="fa fa-clock mr-1"></i>Hour
                            </label>
                            <select id="expiry_hour"
                                    class="w-full px-2 py-3 text-lg font-semibold bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all shadow-sm hover:shadow-md hover:border-blue-400 dark:hover:border-blue-500">
                                <option value="">--</option>
                                @for($h = 0; $h <= 23; $h++)
                                    <option value="{{ sprintf('%02d', $h) }}">{{ sprintf('%02d', $h) }}</option>
                                @endfor
                            </select>
                        </div>

                        <!-- Minute Selector -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                <i class="fa fa-hourglass-half mr-1"></i>Min
                            </label>
                            <select id="expiry_minute"
                                    class="w-full px-2 py-3 text-lg font-semibold bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all shadow-sm hover:shadow-md hover:border-blue-400 dark:hover:border-blue-500">
                                <option value="">--</option>
                                @for($m = 0; $m <= 59; $m++)
                                    <option value="{{ sprintf('%02d', $m) }}">{{ sprintf('%02d', $m) }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <!-- Current Selection Display -->
                    <div id="datetime_preview" class="mt-3 p-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg hidden">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                <i class="fa fa-info-circle mr-2"></i>Selected:
                            </span>
                            <span id="datetime_display" class="text-base font-bold text-blue-600 dark:text-blue-400"></span>
                        </div>
                    </div>

                    <!-- Quick Action Buttons -->
                    <div class="mt-3 space-y-2">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-expiry-action="set" data-days="1" data-hours="0" class="px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-md hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-all hover:scale-105">
                                <i class="fa fa-clock mr-1"></i> +1 Day
                            </button>
                            <button type="button" data-expiry-action="set" data-days="7" data-hours="0" class="px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-md hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-all hover:scale-105">
                                <i class="fa fa-calendar-week mr-1"></i> +1 Week
                            </button>
                            <button type="button" data-expiry-action="set" data-days="30" data-hours="0" class="px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-md hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-all hover:scale-105">
                                <i class="fa fa-calendar-alt mr-1"></i> +1 Month
                            </button>
                            <button type="button" data-expiry-action="set" data-days="90" data-hours="0" class="px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-md hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-all hover:scale-105">
                                <i class="fa fa-calendar mr-1"></i> +3 Months
                            </button>
                            <button type="button" data-expiry-action="set" data-days="365" data-hours="0" class="px-3 py-1.5 text-xs font-medium text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-900/30 border border-purple-200 dark:border-purple-800 rounded-md hover:bg-purple-100 dark:hover:bg-purple-900/50 transition-all hover:scale-105">
                                <i class="fa fa-calendar-check mr-1"></i> +1 Year
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-expiry-action="set" data-days="0" data-hours="1" class="px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md hover:bg-green-100 dark:hover:bg-green-900/50 transition-all hover:scale-105">
                                <i class="fa fa-hourglass-start mr-1"></i> +1 Hour
                            </button>
                            <button type="button" data-expiry-action="set" data-days="0" data-hours="6" class="px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md hover:bg-green-100 dark:hover:bg-green-900/50 transition-all hover:scale-105">
                                <i class="fa fa-hourglass-half mr-1"></i> +6 Hours
                            </button>
                            <button type="button" data-expiry-action="set" data-days="0" data-hours="12" class="px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md hover:bg-green-100 dark:hover:bg-green-900/50 transition-all hover:scale-105">
                                <i class="fa fa-hourglass-end mr-1"></i> +12 Hours
                            </button>
                            <button type="button" data-expiry-action="set" data-days="0" data-hours="24" class="px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md hover:bg-green-100 dark:hover:bg-green-900/50 transition-all hover:scale-105">
                                <i class="fa fa-clock mr-1"></i> +24 Hours
                            </button>
                            <button type="button" data-expiry-action="end-of-day" class="px-3 py-1.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 rounded-md hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-all hover:scale-105">
                                <i class="fa fa-moon mr-1"></i> End of Today
                            </button>
                            <button type="button" data-expiry-action="clear" class="px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 transition-all hover:scale-105">
                                <i class="fa fa-times-circle mr-1"></i> Clear
                            </button>
                        </div>
                    </div>

                    <!-- Status Information -->
                    @if(!empty($user->rolechangedate ?? ''))
                        <div class="mt-3 p-3 rounded-lg {{ $isExpired ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' : ($daysUntilExpiry <= 7 ? 'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800' : 'bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800') }}">
                            <div class="flex items-start">
                                <i class="fa {{ $isExpired ? 'fa-exclamation-triangle text-red-600 dark:text-red-400' : ($daysUntilExpiry <= 7 ? 'fa-clock text-yellow-600 dark:text-yellow-400' : 'fa-info-circle text-blue-600 dark:text-blue-400') }} mt-0.5 mr-2"></i>
                                <div class="flex-1">
                                    <p class="text-sm font-medium {{ $isExpired ? 'text-red-800 dark:text-red-200' : ($daysUntilExpiry <= 7 ? 'text-yellow-800 dark:text-yellow-200' : 'text-blue-800 dark:text-blue-200') }}">
                                        @if($isExpired)
                                            Role expired {{ $expiryDate->diffForHumans() }}
                                        @else
                                            Role expires {{ $expiryDate->diffForHumans() }}
                                        @endif
                                    </p>
                                    <p class="text-xs {{ $isExpired ? 'text-red-700 dark:text-red-300' : ($daysUntilExpiry <= 7 ? 'text-yellow-700 dark:text-yellow-300' : 'text-blue-700 dark:text-blue-300') }} mt-1">
                                        <i class="fa fa-calendar-alt mr-1"></i>{{ $expiryDate->format('F j, Y') }}
                                        <span class="mx-2">•</span>
                                        <i class="fa fa-clock mr-1"></i>{{ $expiryDate->format('g:i A') }}
                                    </p>
                                    @if($daysUntilExpiry <= 7 && !$isExpired)
                                        <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">
                                            <i class="fa fa-hourglass-half mr-1"></i>{{ $daysUntilExpiry }} day{{ $daysUntilExpiry != 1 ? 's' : '' }} and {{ $expiryDate->diffInHours(now()) % 24 }} hour{{ ($expiryDate->diffInHours(now()) % 24) != 1 ? 's' : '' }} remaining
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="mt-3 text-xs text-gray-600 dark:text-gray-400 flex items-center">
                            <i class="fa fa-lightbulb mr-1.5 text-yellow-500"></i>
                            <span>Leave empty for permanent role assignment, or use quick actions above to set an expiry date and time.</span>
                        </p>
                    @endif
                </div>

                @if(!empty($user['id']))
                    <!-- Grabs -->
                    <div>
                        <label for="grabs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Grabs
                        </label>
                        <input type="number"
                               id="grabs"
                               name="grabs"
                               value="{{ is_array($user) ? ($user['grabs'] ?? 0) : ($user->grabs ?? 0) }}"
                               disabled
                               class="w-full px-3 py-2 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-md cursor-not-allowed opacity-75">
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <i class="fa fa-info-circle mr-1"></i>Total grabs is read-only and automatically tracked
                        </p>
                    </div>

                    <!-- Daily Activity Stats -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gradient-to-br from-gray-50 to-white dark:from-gray-900 dark:to-gray-800">
                        <div class="flex items-center mb-3">
                            <i class="fa fa-chart-line mr-2 text-purple-600 dark:text-purple-400"></i>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Daily Activity (Last 24 Hours)
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Daily API Requests -->
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-blue-600 dark:text-blue-400 font-medium uppercase tracking-wide">
                                            <i class="fa fa-code mr-1"></i>API Requests
                                        </p>
                                        <p class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-1">
                                            {{ is_array($user) ? ($user['daily_api_count'] ?? 0) : ($user->daily_api_count ?? 0) }}
                                        </p>
                                    </div>
                                    <div class="text-blue-600 dark:text-blue-400">
                                        <i class="fa fa-code text-3xl opacity-20"></i>
                                    </div>
                                </div>
                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">
                                    <i class="fa fa-clock mr-1"></i>In the last 24 hours
                                </p>
                            </div>

                            <!-- Daily Downloads -->
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-green-600 dark:text-green-400 font-medium uppercase tracking-wide">
                                            <i class="fa fa-download mr-1"></i>Downloads
                                        </p>
                                        <p class="text-2xl font-bold text-green-900 dark:text-green-100 mt-1">
                                            {{ is_array($user) ? ($user['daily_download_count'] ?? 0) : ($user->daily_download_count ?? 0) }}
                                        </p>
                                    </div>
                                    <div class="text-green-600 dark:text-green-400">
                                        <i class="fa fa-download text-3xl opacity-20"></i>
                                    </div>
                                </div>
                                <p class="text-xs text-green-600 dark:text-green-400 mt-2">
                                    <i class="fa fa-clock mr-1"></i>In the last 24 hours
                                </p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-gray-600 dark:text-gray-400 flex items-center">
                            <i class="fa fa-info-circle mr-1.5 text-blue-500"></i>
                            <span>These counters show activity from the past 24 hours and are automatically updated.</span>
                        </p>
                    </div>
                @endif

                <!-- Invites -->
                <div>
                    <label for="invites" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Invites
                    </label>
                    <input type="number"
                           id="invites"
                           name="invites"
                           value="{{ is_array($user) ? ($user['invites'] ?? 0) : ($user->invites ?? 0) }}"
                           class="w-full px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Notes
                    </label>
                    <textarea id="notes"
                              name="notes"
                              rows="4"
                              class="w-full px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500">{{ is_array($user) ? ($user['notes'] ?? '') : ($user->notes ?? '') }}</textarea>
                </div>

                @if(!empty($user['id']))
                    <!-- View Preferences -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Category Preferences
                        </label>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="movieview"
                                       name="movieview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['movieview'] ?? 0) : ($user->movieview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="movieview" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Movies</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="musicview"
                                       name="musicview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['musicview'] ?? 0) : ($user->musicview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="musicview" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Music</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="gameview"
                                       name="gameview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['gameview'] ?? 0) : ($user->gameview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="gameview" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Games</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="consoleview"
                                       name="consoleview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['consoleview'] ?? 0) : ($user->consoleview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="consoleview" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Console</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="bookview"
                                       name="bookview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['bookview'] ?? 0) : ($user->bookview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="bookview" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Books</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="xxxview"
                                       name="xxxview"
                                       value="1"
                                       {{ (is_array($user) ? ($user['xxxview'] ?? 0) : ($user->xxxview ?? 0)) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="xxxview" class="ml-2 text-sm text-gray-700 dark:text-gray-300">XXX</label>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="submit" class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                        <i class="fa fa-save mr-2"></i>Save User
                    </button>
                    <a href="{{ url('admin/user-list') }}" class="px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600">
                        <i class="fa fa-times mr-2"></i>Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

