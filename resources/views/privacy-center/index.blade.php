@extends('layouts.main')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <x-page-header title="Privacy center" eyebrow="Your data and privacy" description="Export your account data and manage GDPR requests from one place." icon="fas fa-shield-alt" />

    <div class="surface-panel rounded-xl shadow-sm overflow-hidden">
        @if($errors->any())
            <div class="mx-6 mt-6 p-4 bg-danger-50 dark:bg-danger-900/20 border-l-4 border-danger-500 text-danger-800 dark:text-danger-200 rounded">
                <i aria-hidden="true" class="fas fa-exclamation-circle mr-2"></i>{{ $errors->first() }}
            </div>
        @endif

        <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5 bg-white dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-3">
                    <i aria-hidden="true" class="fas fa-file-export mr-2 text-link dark:text-primary-400"></i>Download Your Data
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Generate a JSON export containing your account profile, usage records, comments, requests, GDPR history, and retained payment/audit records that relate to your account.
                </p>
                <form method="post" action="{{ route('privacy-center.export') }}">
                    @csrf
                    <label for="export_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Optional notes</label>
                    <textarea id="export_notes" name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md" placeholder="Anything administrators should know?"></textarea>
                    <button type="submit" class="mt-4 px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-md hover:bg-accent-hover">
                        <i aria-hidden="true" class="fas fa-download mr-2"></i>Generate Export
                    </button>
                </form>
            </div>

            <div class="border border-danger-200 dark:border-danger-800 rounded-lg p-5 bg-danger-50 dark:bg-danger-900/10">
                <h2 class="text-lg font-semibold text-danger-800 dark:text-danger-200 mb-3">
                    <i aria-hidden="true" class="fas fa-user-slash mr-2"></i>Request Account Erasure
                </h2>
                <p class="text-sm text-danger-700 dark:text-danger-300 mb-4">
                    Submit a GDPR erasure request for administrator review. Account/profile data and usage records will be removed or anonymized. Payment and audit records are retained only where required and direct account identifiers are anonymized or minimized where practical.
                </p>
                <form method="post" action="{{ route('privacy-center.erasure') }}">
                    @csrf
                    <label for="erasure_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Optional notes</label>
                    <textarea id="erasure_notes" name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md" placeholder="Reason or additional details"></textarea>
                    <label for="confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mt-4 mb-1">Type ERASE to confirm</label>
                    <x-input id="confirmation" name="confirmation" type="text" autocomplete="off" />
                    <button type="submit" class="mt-4 px-4 py-2 bg-danger-600 dark:bg-danger-700 text-white rounded-md hover:bg-danger-700">
                        <i aria-hidden="true" class="fas fa-paper-plane mr-2"></i>Submit Erasure Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="surface-panel rounded-xl shadow-sm overflow-hidden">
        <div class="surface-panel-alt border-b px-6 py-4">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Your GDPR Requests</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Result</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($gdprRequests as $gdprRequest)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">{{ $gdprRequest->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ ucfirst($gdprRequest->type) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">{{ ucfirst($gdprRequest->status) }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                @if($gdprRequest->isDownloadableExport())
                                    <a href="{{ route('privacy-center.export.download', $gdprRequest) }}" class="text-link dark:text-primary-400 hover:underline">
                                        <i aria-hidden="true" class="fas fa-download mr-1"></i>Download export
                                    </a>
                                    <span class="block text-xs text-gray-500 dark:text-gray-500">Expires {{ $gdprRequest->export_expires_at?->diffForHumans() }}</span>
                                @elseif($gdprRequest->admin_notes)
                                    {{ $gdprRequest->admin_notes }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No GDPR requests yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4">
            {{ $gdprRequests->links() }}
        </div>
    </div>

    <div class="surface-panel rounded-xl shadow-sm p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100 mb-3">Essential Cookies & Retention</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">This site uses essential cookies/storage only. Payment and audit records may be retained after erasure where required for legal, accounting, security, dispute, or GDPR accountability purposes, with direct account identifiers anonymized or minimized where practical.</p>
        <ul class="list-disc pl-6 text-sm text-gray-700 dark:text-gray-300 space-y-1">
            @foreach($retentionPolicy['retained_records'] as $record)
                <li><strong>{{ $record['table'] }}:</strong> {{ $record['reason'] }} <span class="text-gray-500">({{ str_replace('_', ' ', $record['erasure_action']) }})</span></li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
