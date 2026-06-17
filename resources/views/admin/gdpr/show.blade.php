@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                    <i class="fas fa-shield-alt mr-2"></i>{{ $title }}
                </h1>
                <a href="{{ route('admin.gdpr-requests.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-800 dark:text-green-200">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-800 dark:text-red-200">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-800 dark:text-red-200">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ $errors->first() }}
            </div>
        @endif

        <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Request Details</h2>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Requester</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $gdprRequest->requester_username ?? 'N/A' }} &lt;{{ $gdprRequest->requester_email ?? 'N/A' }}&gt;</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Subject User</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-medium">
                                @if($subject)
                                    {{ $subject->username }} (#{{ $subject->id }}) @if($subject->trashed()) <span class="text-xs text-red-500">deleted</span> @endif
                                @else
                                    Missing/deleted
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Type</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ ucfirst($gdprRequest->type) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ ucfirst($gdprRequest->status) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $gdprRequest->created_at?->format('Y-m-d H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Completed</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $gdprRequest->completed_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                    </dl>

                    @if($gdprRequest->notes)
                        <div class="mt-4">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">User Notes</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ $gdprRequest->notes }}</p>
                        </div>
                    @endif

                    @if($gdprRequest->admin_notes)
                        <div class="mt-4">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Admin Notes</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ $gdprRequest->admin_notes }}</p>
                        </div>
                    @endif
                </div>

                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Audit Trail</h2>
                    <div class="space-y-3">
                        @forelse($gdprRequest->auditLogs as $auditLog)
                            <div class="border-l-4 border-blue-500 pl-3">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $auditLog->event }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $auditLog->description }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-500">{{ $auditLog->created_at?->format('Y-m-d H:i:s') }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No GDPR audit events recorded for this request yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5 bg-gray-50 dark:bg-gray-900">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Actions</h2>

                    @if($gdprRequest->type === \App\Models\GdprRequest::TYPE_EXPORT && in_array($gdprRequest->status, [\App\Models\GdprRequest::STATUS_PENDING, \App\Models\GdprRequest::STATUS_PROCESSING], true))
                        <form method="post" action="{{ route('admin.gdpr-requests.generate-export', $gdprRequest) }}" class="mb-3">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-md hover:bg-blue-700">
                                <i class="fas fa-file-export mr-2"></i>Generate Export
                            </button>
                        </form>
                    @endif

                    @if($gdprRequest->type === \App\Models\GdprRequest::TYPE_ERASURE && in_array($gdprRequest->status, [\App\Models\GdprRequest::STATUS_PENDING, \App\Models\GdprRequest::STATUS_PROCESSING], true))
                        <form method="post" action="{{ route('admin.gdpr-requests.complete-erasure', $gdprRequest) }}" class="mb-3">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 bg-red-600 dark:bg-red-700 text-white rounded-md hover:bg-red-700" data-confirm="Complete GDPR erasure for this account? Retained payment and audit records will be anonymized or minimized where practical.">
                                <i class="fas fa-user-slash mr-2"></i>Complete Erasure
                            </button>
                        </form>
                    @endif

                    @if(in_array($gdprRequest->status, [\App\Models\GdprRequest::STATUS_PENDING, \App\Models\GdprRequest::STATUS_PROCESSING], true))
                        <form method="post" action="{{ route('admin.gdpr-requests.reject', $gdprRequest) }}">
                            @csrf
                            <label for="admin_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rejection reason</label>
                            <textarea id="admin_notes" name="admin_notes" rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md" required></textarea>
                            <button type="submit" class="mt-3 w-full px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-800">
                                <i class="fas fa-ban mr-2"></i>Reject Request
                            </button>
                        </form>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No actions available for completed, cancelled, or rejected requests.</p>
                    @endif
                </div>

                @if($gdprRequest->isDownloadableExport())
                    <div class="border border-green-200 dark:border-green-800 rounded-lg p-5 bg-green-50 dark:bg-green-900/10">
                        <h2 class="text-lg font-semibold text-green-800 dark:text-green-200 mb-2">Export Ready</h2>
                        <p class="text-sm text-green-700 dark:text-green-300">The user can download this export until {{ $gdprRequest->export_expires_at?->format('Y-m-d H:i') }}.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

