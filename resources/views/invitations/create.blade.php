@extends('layouts.main')

@section('content')
@unless($invite_mode)
<div class="mb-6">
    <nav class="flex" aria-label="breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="{{ $site->home_link }}" class="text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">Home</a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                    <a href="{{ url('/profile') }}" class="text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">Profile</a>
                </div>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                    <a href="{{ url('/invitations') }}" class="text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">My Invitations</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                    <span class="text-gray-500 dark:text-gray-400">Invitations Disabled</span>
                </div>
            </li>
        </ol>
    </nav>
</div>
<div class="max-w-4xl mx-auto px-4 py-3">
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-6 shadow-sm dark:bg-yellow-900 dark:border-yellow-700 dark:text-yellow-300">
        <h5 class="text-lg font-semibold mb-2 flex items-center">
            <i class="fa fa-ban mr-2"></i>Invitations Disabled
        </h5>
        <p class="mb-3">User invitations are currently disabled on this site. You cannot send new invitations at this time.</p>
        <a href="{{ url('/profile') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
            <i class="fa fa-arrow-left mr-1"></i> Back to Profile
        </a>
    </div>
</div>
@else
<div class="mb-6">
    <nav class="flex" aria-label="breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="{{ $site->home_link }}" class="text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">Home</a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                    <a href="{{ url('/profile') }}" class="text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">Profile</a>
                </div>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                    <a href="{{ url('/invitations') }}" class="text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">My Invitations</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                    <span class="text-gray-500 dark:text-gray-400">Send New Invitation</span>
                </div>
            </li>
        </ol>
    </nav>
</div>

<div class="max-w-4xl mx-auto px-4 py-3">
    <div class="bg-white rounded-lg shadow-sm dark:bg-gray-800">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 rounded-t-lg flex justify-between items-center dark:bg-gray-700 dark:border-gray-600">
            <h5 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                <i class="fa fa-paper-plane mr-2"></i>Send New Invitation
            </h5>
            <div class="flex items-center gap-3">
                <div class="px-3 py-1 rounded text-sm font-medium text-white {{ $user_invites_left > 0 ? 'bg-green-600' : 'bg-red-600' }}">
                    <i class="fa fa-envelope mr-1"></i>
                    {{ $user_invites_left }} invites left
                </div>
                <a href="{{ url('/invitations') }}" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Invitations
                </a>
            </div>
        </div>
        <div class="p-6">
            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 dark:bg-red-900 dark:border-red-700 dark:text-red-200" role="alert">
                    <div class="flex items-center">
                        <i class="fa fa-exclamation-triangle mr-2"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 dark:bg-red-900 dark:border-red-700 dark:text-red-200">
                    <ul class="list-disc list-inside mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @unless($can_send_invites)
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-6 text-center dark:bg-yellow-900 dark:border-yellow-700 dark:text-yellow-300">
                    <i class="fa fa-exclamation-triangle text-5xl mb-3"></i>
                    <h5 class="text-lg font-semibold mb-2">No Invitations Available</h5>
                    <p class="mb-4">You have used all of your available invitations. You cannot send new invitations at this time.</p>
                    <div class="flex gap-2 justify-center">
                        <a href="{{ url('/invitations') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fa fa-arrow-left mr-1"></i> Back to My Invitations
                        </a>
                        <a href="{{ url('/contact') }}" class="inline-flex items-center px-4 py-2 border border-blue-300 rounded-md text-sm font-medium text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                            <i class="fa fa-envelope mr-1"></i> Contact Support
                        </a>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ url('/invitations/store') }}">
                            @csrf

                            <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4 dark:bg-blue-900 dark:border-blue-700 dark:text-blue-300">
                                <div class="flex">
                                    <i class="fa fa-info-circle text-lg mr-3 mt-1"></i>
                                    <div>
                                        <strong>Available Invitations:</strong> You have <strong>{{ $user_invites_left }}</strong> invitation{{ $user_invites_left != 1 ? 's' : '' }} remaining.
                                        @if($user_invites_left == 1)
                                            This is your last invitation, so use it wisely!
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="mb-6">
                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fa fa-envelope mr-1"></i>Email Address <span class="text-red-600">*</span>
                                </label>
                                <input type="email"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white @error('email') border-red-500 @enderror"
                                       id="email"
                                       name="email"
                                       value="{{ old('email') }}"
                                       required
                                       placeholder="Enter recipient's email address">
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    <i class="fa fa-info-circle mr-1"></i>The person will receive an invitation email at this address.
                                </p>
                            </div>

                            <div class="mb-6">
                                <label for="expiry_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fa fa-clock-o mr-1"></i>Expiry Period
                                </label>
                                <select class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white @error('expiry_days') border-red-500 @enderror"
                                        id="expiry_days"
                                        name="expiry_days">
                                    <option value="1" @selected(old('expiry_days') == '1')>1 Day</option>
                                    <option value="3" @selected(old('expiry_days') == '3')>3 Days</option>
                                    <option value="7" @selected(old('expiry_days') == '7' || !old('expiry_days'))>1 Week (Default)</option>
                                    <option value="14" @selected(old('expiry_days') == '14')>2 Weeks</option>
                                    <option value="30" @selected(old('expiry_days') == '30')>1 Month</option>
                                </select>
                                @error('expiry_days')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    <i class="fa fa-info-circle mr-1"></i>How long the invitation will remain valid.
                                </p>
                            </div>

                            @if(isset($user_roles) && !empty($user_roles))
                                <div class="mb-6">
                                    <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="fa fa-user-tag mr-1"></i>Default Role <small class="text-gray-500 dark:text-gray-400">(Optional)</small>
                                    </label>
                                    <select class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white @error('role') border-red-500 @enderror"
                                            id="role"
                                            name="role">
                                        <option value="">Use System Default</option>
                                        @foreach($user_roles as $roleId => $roleName)
                                            <option value="{{ $roleId }}" @selected(old('role') == $roleId)>
                                                {{ $roleName }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('role')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        <i class="fa fa-info-circle mr-1"></i>The role to assign to the user when they accept the invitation.
                                    </p>
                                </div>
                            @endif

                            <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4 dark:bg-blue-900 dark:border-blue-700 dark:text-blue-300">
                                <div class="flex">
                                    <i class="fa fa-lightbulb-o text-lg mr-3 mt-1"></i>
                                    <div>
                                        <strong>How it works:</strong>
                                        <ul class="list-disc list-inside mt-2 mb-0 space-y-1">
                                            <li>The recipient will receive an email with a secure invitation link</li>
                                            <li>They can use this link to create their account within the specified time period</li>
                                            <li>Once the time expires, the invitation link becomes invalid</li>
                                            <li>You can track the status of all your invitations from the main invitations page</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-2 justify-end">
                                <a href="{{ url('/invitations') }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500">
                                    <i class="fa fa-times mr-1"></i>Cancel
                                </a>
                                <button type="submit" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-paper-plane mr-1"></i> Send Invitation
                                </button>
                            </div>
                        </form>
                    @endunless
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

