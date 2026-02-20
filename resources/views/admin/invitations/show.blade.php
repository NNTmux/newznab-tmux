@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-envelope mr-2"></i>{{ $title }}
                </h1>
                <div class="space-x-2">
                    @if(!$invitation->used_at)
                        <form method="POST" action="{{ url('admin/invitations/' . $invitation->id . '/resend') }}" class="inline">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                <i class="fas fa-paper-plane mr-2"></i>Resend
                            </button>
                        </form>
                        <form method="POST" action="{{ url('admin/invitations/' . $invitation->id . '/cancel') }}" class="inline"
                              x-data="confirmForm"
                              data-message="Are you sure you want to cancel this invitation?">
                            @csrf
                            <button type="button" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700" x-on:click="submit()">
                                <i class="fas fa-ban mr-2"></i>Cancel
                            </button>
                        </form>
                    @endif
                    <a href="{{ url('admin/invitations') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i>Back to List
                    </a>
                </div>
            </div>
        </div>

        <!-- Invitation Details -->
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-lg">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                        <i class="fas fa-info-circle mr-2"></i>Basic Information
                    </h2>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200 font-mono">{{ $invitation->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="mt-1">
                                @if($invitation->used_at)
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <i class="fas fa-check-circle mr-1"></i>Used
                                    </span>
                                @elseif(!$invitation->is_active)
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        <i class="fas fa-ban mr-1"></i>Cancelled
                                    </span>
                                @elseif($invitation->expires_at && $invitation->expires_at->isPast())
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        <i class="fas fa-clock mr-1"></i>Expired
                                    </span>
                                @else
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        <i class="fas fa-hourglass-half mr-1"></i>Pending
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Token</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200 font-mono break-all bg-gray-100 dark:bg-gray-800 p-2 rounded">
                                {{ $invitation->token }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Active</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                                @if($invitation->is_active)
                                    <span class="text-green-600 dark:text-green-400"><i class="fas fa-check-circle"></i> Yes</span>
                                @else
                                    <span class="text-red-600 dark:text-red-400"><i class="fas fa-times-circle"></i> No</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- User Information -->
                <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-lg">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                        <i class="fas fa-users mr-2"></i>User Information
                    </h2>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Invited By</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                                @if($invitation->invitedBy)
                                    <div class="flex items-center space-x-2">
                                        <span class="font-semibold">{{ $invitation->invitedBy->username }}</span>
                                        <span class="text-gray-500 dark:text-gray-400">({{ $invitation->invitedBy->email }})</span>
                                    </div>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">—</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Used By</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                                @if($invitation->usedBy)
                                    <div class="flex items-center space-x-2">
                                        <span class="font-semibold">{{ $invitation->usedBy->username }}</span>
                                        <span class="text-gray-500 dark:text-gray-400">({{ $invitation->usedBy->email }})</span>
                                    </div>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">—</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Timestamp Information -->
                <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-lg">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                        <i class="fas fa-clock mr-2"></i>Timestamps
                    </h2>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                                {{ $invitation->created_at->format('Y-m-d H:i:s') }}
                                <span class="text-gray-500 dark:text-gray-400">({{ $invitation->created_at->diffForHumans() }})</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated At</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                                {{ $invitation->updated_at->format('Y-m-d H:i:s') }}
                                <span class="text-gray-500 dark:text-gray-400">({{ $invitation->updated_at->diffForHumans() }})</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expires At</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                                @if($invitation->expires_at)
                                    {{ $invitation->expires_at->format('Y-m-d H:i:s') }}
                                    <span class="text-gray-500 dark:text-gray-400">({{ $invitation->expires_at->diffForHumans() }})</span>
                                    @if($invitation->expires_at->isPast())
                                        <span class="ml-2 text-red-600 dark:text-red-400"><i class="fas fa-exclamation-triangle"></i> Expired</span>
                                    @endif
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">Never</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Used At</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                                @if($invitation->used_at)
                                    {{ $invitation->used_at->format('Y-m-d H:i:s') }}
                                    <span class="text-gray-500 dark:text-gray-400">({{ $invitation->used_at->diffForHumans() }})</span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">Not used yet</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Additional Information -->
                <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-lg">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                        <i class="fas fa-list mr-2"></i>Additional Information
                    </h2>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Invitation Link</dt>
                            <dd class="mt-1 text-sm">
                                @if(!$invitation->used_at && $invitation->is_active && (!$invitation->expires_at || !$invitation->expires_at->isPast()))
                                    <div class="bg-gray-100 dark:bg-gray-800 p-2 rounded break-all font-mono text-xs">
                                        {{ url('register?invitation=' . $invitation->token) }}
                                    </div>
                                    <button x-data="copyToClipboard()" x-on:click="copy('invitation-link-{{ $invitation->id }}')" class="mt-2 px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">
                                        <i class="fas fa-copy mr-1"></i><span x-text="copied ? 'Copied!' : 'Copy Link'"></span>
                                    </button>
                                    <input type="hidden" id="invitation-link-{{ $invitation->id }}" value="{{ url('register?invitation=' . $invitation->token) }}" />
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">Invitation not active</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">ID</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200 font-mono">{{ $invitation->id }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Scripts moved to resources/js/csp-safe.js --}}
@endsection

