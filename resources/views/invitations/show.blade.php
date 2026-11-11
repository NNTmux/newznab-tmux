@extends('layouts.main')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-3">
    <x-page-header :title="'Invitation to join ' . $site->title" eyebrow="Welcome" description="Review this invitation and create your account securely." icon="fas fa-envelope-open" />

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm dark:bg-gray-800">
        <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700 rounded-t-lg dark:bg-gray-700 dark:border-gray-600">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                <i aria-hidden="true" class="fa-solid fa-envelope-open mr-2"></i>Invitation to Join {{ $site->title }}
            </h2>
        </div>
        <div class="p-6">
            @if($preview)
                <div class="bg-primary-50 border border-primary-200 text-primary-800 rounded-lg p-4 mb-6 dark:bg-primary-900 dark:border-primary-700 dark:text-primary-300">
                    <div class="flex">
                        <i aria-hidden="true" class="fa-solid fa-user-plus text-4xl mr-4 mt-1"></i>
                        <div>
                            <h3 class="font-semibold mb-2">You've been invited!</h3>
                            <p class="mb-0">
                                <strong>{{ $preview['inviter_name'] ?? 'Someone' }}</strong> has invited you to join <strong>{{ $site->title }}</strong>.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:border-gray-600">
                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 dark:border-gray-600">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                                <i aria-hidden="true" class="fa-solid fa-info-circle mr-2"></i>Invitation Details
                            </h3>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-5 gap-3 mb-3">
                                <div class="col-span-2 text-gray-600 dark:text-gray-400">
                                    <i aria-hidden="true" class="fa-solid fa-user mr-1"></i>Invited by:
                                </div>
                                <div class="col-span-3 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $preview['inviter_name'] ?? 'Anonymous' }}
                                </div>
                            </div>
                            <div class="grid grid-cols-5 gap-3 mb-3">
                                <div class="col-span-2 text-gray-600 dark:text-gray-400">
                                    <i aria-hidden="true" class="fa-solid fa-envelope mr-1"></i>Email:
                                </div>
                                <div class="col-span-3 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $preview['email'] ?? 'N/A' }}
                                </div>
                            </div>
                            <div class="grid grid-cols-5 gap-3 mb-3">
                                <div class="col-span-2 text-gray-600 dark:text-gray-400">
                                    <i aria-hidden="true" class="fa-regular fa-clock mr-1"></i>Expires:
                                </div>
                                <div class="col-span-3">
                                    @isset($preview['expires_at'])
                                        <span class="text-gray-900 dark:text-gray-100">{{ date('M j, Y H:i', $preview['expires_at']) }}</span>
                                        @if($preview['expires_at'] < time())
                                            <br><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 mt-1 dark:bg-danger-900 dark:text-danger-200">
                                                <i aria-hidden="true" class="fa-solid fa-times mr-1"></i>Expired
                                            </span>
                                        @elseif($preview['is_used'])
                                            <br><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800 mt-1 dark:bg-success-900 dark:text-success-200">
                                                <i aria-hidden="true" class="fa-solid fa-check mr-1"></i>Already Used
                                            </span>
                                        @else
                                            <br><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800 mt-1 dark:bg-success-900 dark:text-success-200">
                                                <i aria-hidden="true" class="fa-solid fa-check mr-1"></i>Valid
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">N/A</span>
                                    @endisset
                                </div>
                            </div>
                            @if(isset($preview['metadata']['role']) && isset($preview['role_name']))
                                <div class="grid grid-cols-5 gap-3">
                                    <div class="col-span-2 text-gray-600 dark:text-gray-400">
                                        <i aria-hidden="true" class="fa-solid fa-user-tag mr-1"></i>Assigned Role:
                                    </div>
                                    <div class="col-span-3 font-medium">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                            {{ $preview['role_name'] ?? 'Default' }}
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:border-gray-600">
                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 dark:border-gray-600">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                                <i aria-hidden="true" class="fa-solid fa-list-ol mr-2"></i>What's Next?
                            </h3>
                        </div>
                        <div class="p-4">
                            <p class="mb-3 text-gray-700 dark:text-gray-300">To accept this invitation and create your account:</p>
                            <ol class="list-decimal list-inside mb-0 space-y-2 text-gray-700 dark:text-gray-300">
                                <li>Click the "Accept Invitation" button below</li>
                                <li>Fill out the registration form with your details</li>
                                <li>Verify your email address when prompted</li>
                                <li>Start exploring {{ $site->title }}!</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-6">
                    @if($preview['is_used'])
                        <div class="bg-success-50 border border-success-200 text-success-800 rounded-lg p-6 mb-4 dark:bg-success-900 dark:border-success-700 dark:text-success-300">
                            <i aria-hidden="true" class="fas fa-check-circle text-4xl mb-2"></i>
                            <h3 class="font-semibold mb-2">This invitation has already been used</h3>
                            <p class="mb-0">The account has been successfully created using this invitation.</p>
                        </div>
                        <a href="{{ url('/login') }}" class="inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-accent dark:bg-accent-hover hover:bg-accent-hover dark:hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <i aria-hidden="true" class="fas fa-sign-in-alt mr-2"></i> Login to Your Account
                        </a>
                    @elseif($preview['expires_at'] < time())
                        <div class="bg-danger-50 border border-danger-200 text-danger-800 rounded-lg p-6 mb-4 dark:bg-danger-900 dark:border-danger-700 dark:text-danger-300">
                            <i aria-hidden="true" class="fas fa-exclamation-triangle text-4xl mb-2"></i>
                            <h3 class="font-semibold mb-2">This invitation has expired</h3>
                            <p class="mb-0">Please contact the person who invited you for a new invitation.</p>
                        </div>
                        <a href="{{ url('/contact') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                            <i aria-hidden="true" class="fa-solid fa-envelope mr-1"></i> Contact Support
                        </a>
                    @elseif($registrationStatus['is_closed'])
                        <div class="bg-warning-50 border border-warning-200 text-warning-800 rounded-lg p-6 mb-4 dark:bg-warning-900 dark:border-warning-700 dark:text-warning-300">
                            <i aria-hidden="true" class="fas fa-door-closed text-4xl mb-2"></i>
                            <h3 class="font-semibold mb-2">Registrations are currently closed</h3>
                            <p class="mb-0">{{ $registrationStatus['message'] }}</p>
                        </div>
                    @else
                        <a href="{{ route('register', ['token' => $token]) }}" class="inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-lg text-base font-medium text-white bg-accent dark:bg-accent-hover hover:bg-accent-hover dark:hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <i aria-hidden="true" class="fas fa-user-plus mr-2"></i> Accept Invitation & Create Account
                        </a>
                        <div class="mt-3">
                            <small class="text-gray-500 dark:text-gray-400">
                                Already have an account? <a href="{{ url('/login') }}" class="text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 dark:text-primary-400 dark:hover:text-primary-300">Login here</a>
                            </small>
                        </div>
                    @endif
                </div>
            @else
                <div class="bg-danger-50 border border-danger-200 text-danger-800 rounded-lg p-6 text-center dark:bg-danger-900 dark:border-danger-700 dark:text-danger-300">
                    <i aria-hidden="true" class="fas fa-exclamation-triangle text-5xl mb-3 text-danger-600 dark:text-danger-400"></i>
                    <h2 class="text-xl font-semibold text-danger-600 mb-3 dark:text-danger-400">Invalid Invitation</h2>
                    <p class="mb-4">This invitation link is not valid, has expired, or has been removed.</p>
                    <div class="flex flex-col sm:flex-row gap-2 justify-center">
                        <a href="{{ url('/contact') }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                            <i aria-hidden="true" class="fa-solid fa-envelope mr-1"></i> Contact Support
                        </a>
                        @if($registrationStatus['is_open'])
                            <a href="{{ url('/register') }}" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-accent dark:bg-accent-hover hover:bg-accent-hover dark:hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <i aria-hidden="true" class="fa-solid fa-user-plus mr-1"></i> Register Without Invitation
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
