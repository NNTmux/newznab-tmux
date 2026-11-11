@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-envelope">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                @if(!$invitation->used_at)
                    <form method="POST" action="{{ url('admin/invitations/' . $invitation->id . '/resend') }}" class="inline">
                        @csrf
                        <x-admin.button type="submit" tone="success" icon="fas fa-paper-plane">Resend</x-admin.button>
                    </form>
                    <form method="POST" action="{{ url('admin/invitations/' . $invitation->id . '/cancel') }}" class="inline"
                          x-data="confirmForm"
                          data-message="Are you sure you want to cancel this invitation?">
                        @csrf
                        <x-admin.button type="button" tone="warning" icon="fas fa-ban" @click="submit()">Cancel</x-admin.button>
                    </form>
                @endif
                <x-admin.button :href="url('admin/invitations')" tone="gray" icon="fas fa-arrow-left">Back to List</x-admin.button>
            </div>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Invitation Details -->
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-lg">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                        <i aria-hidden="true" class="fas fa-info-circle mr-2"></i>Basic Information
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
                                    <x-admin.badge tone="success" icon="fas fa-check-circle">Used</x-admin.badge>
                                @elseif(!$invitation->is_active)
                                    <x-admin.badge tone="warning" icon="fas fa-ban">Cancelled</x-admin.badge>
                                @elseif($invitation->expires_at && $invitation->expires_at->isPast())
                                    <x-admin.badge tone="danger" icon="fas fa-clock">Expired</x-admin.badge>
                                @else
                                    <x-admin.badge tone="primary" icon="fas fa-hourglass-half">Pending</x-admin.badge>
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
                                    <span class="text-success-600 dark:text-success-400"><i aria-hidden="true" class="fas fa-check-circle"></i> Yes</span>
                                @else
                                    <span class="text-danger-600 dark:text-danger-400"><i aria-hidden="true" class="fas fa-times-circle"></i> No</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- User Information -->
                <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-lg">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                        <i aria-hidden="true" class="fas fa-users mr-2"></i>User Information
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
                        <i aria-hidden="true" class="fas fa-clock mr-2"></i>Timestamps
                    </h2>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                                {{ formatDateTimeSeconds($invitation->created_at) }}
                                <span class="text-gray-500 dark:text-gray-400">({{ $invitation->created_at->diffForHumans() }})</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated At</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                                {{ formatDateTimeSeconds($invitation->updated_at) }}
                                <span class="text-gray-500 dark:text-gray-400">({{ $invitation->updated_at->diffForHumans() }})</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expires At</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                                @if($invitation->expires_at)
                                    {{ formatDateTimeSeconds($invitation->expires_at) }}
                                    <span class="text-gray-500 dark:text-gray-400">({{ $invitation->expires_at->diffForHumans() }})</span>
                                    @if($invitation->expires_at->isPast())
                                        <span class="ml-2 text-danger-600 dark:text-danger-400"><i aria-hidden="true" class="fas fa-exclamation-triangle"></i> Expired</span>
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
                                    {{ formatDateTimeSeconds($invitation->used_at) }}
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
                        <i aria-hidden="true" class="fas fa-list mr-2"></i>Additional Information
                    </h2>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Invitation Link</dt>
                            <dd class="mt-1 text-sm">
                                @if(!$invitation->used_at && $invitation->is_active && (!$invitation->expires_at || !$invitation->expires_at->isPast()))
                                    <div class="bg-gray-100 dark:bg-gray-800 p-2 rounded break-all font-mono text-xs">
                                        {{ url('register?invitation=' . $invitation->token) }}
                                    </div>
                                    <button x-data="copyToClipboard()" @click="copy('invitation-link-{{ $invitation->id }}')" class="mt-2 px-3 py-1 bg-accent text-white text-xs rounded hover:bg-accent-hover">
                                        <i aria-hidden="true" class="fas fa-copy mr-1"></i><span x-text="copied ? 'Copied!' : 'Copy Link'"></span>
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
    </x-admin.card>
</div>

@endsection
