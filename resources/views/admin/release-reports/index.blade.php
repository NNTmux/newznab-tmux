@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Flash Messages -->
    @if(session('success'))
        <div class="mb-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-3 text-xl"></i>
                <p class="text-sm text-green-800 dark:text-green-200 font-medium">{{ session('success') }}</p>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mr-3 text-xl"></i>
                <p class="text-sm text-red-800 dark:text-red-200 font-medium">{{ session('error') }}</p>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                        <i class="fas fa-flag mr-2 text-red-500"></i>{{ $title }}
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage release reports submitted by users</p>
                </div>

                <!-- Status Stats -->
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.release-reports', ['status' => 'pending']) }}"
                       class="px-3 py-1.5 rounded-full text-sm font-medium transition {{ $status === 'pending' ? 'bg-yellow-500 text-white' : 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 hover:bg-yellow-200 dark:hover:bg-yellow-800' }}">
                        <i class="fas fa-clock mr-1"></i> Pending ({{ $statusCounts['pending'] }})
                    </a>
                    <a href="{{ route('admin.release-reports', ['status' => 'reviewed']) }}"
                       class="px-3 py-1.5 rounded-full text-sm font-medium transition {{ $status === 'reviewed' ? 'bg-blue-500 text-white' : 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 hover:bg-blue-200 dark:hover:bg-blue-800' }}">
                        <i class="fas fa-eye mr-1"></i> Reviewed ({{ $statusCounts['reviewed'] }})
                    </a>
                    <a href="{{ route('admin.release-reports', ['status' => 'resolved']) }}"
                       class="px-3 py-1.5 rounded-full text-sm font-medium transition {{ $status === 'resolved' ? 'bg-green-500 text-white' : 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 hover:bg-green-200 dark:hover:bg-green-800' }}">
                        <i class="fas fa-check mr-1"></i> Resolved ({{ $statusCounts['resolved'] }})
                    </a>
                    <a href="{{ route('admin.release-reports', ['status' => 'dismissed']) }}"
                       class="px-3 py-1.5 rounded-full text-sm font-medium transition {{ $status === 'dismissed' ? 'bg-gray-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        <i class="fas fa-ban mr-1"></i> Dismissed ({{ $statusCounts['dismissed'] }})
                    </a>
                    <a href="{{ route('admin.release-reports', ['status' => 'all']) }}"
                       class="px-3 py-1.5 rounded-full text-sm font-medium transition {{ $status === 'all' ? 'bg-purple-500 text-white' : 'bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 hover:bg-purple-200 dark:hover:bg-purple-800' }}">
                        <i class="fas fa-list mr-1"></i> All ({{ $statusCounts['total'] }})
                    </a>
                </div>
            </div>
        </div>

        <!-- Reports Table -->
        @if($reportsList->count() > 0)
            <form id="bulk-action-form" method="POST" action="{{ route('admin.release-reports.bulk') }}">
                @csrf
                <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 flex items-center gap-4">
                    <input type="checkbox" id="select-all" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 dark:bg-gray-700">
                    <select name="action" class="text-sm border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-200">
                        <option value="">Bulk Actions</option>
                        <option value="reviewed">Mark as Reviewed</option>
                        <option value="resolve">Mark as Resolved</option>
                        <option value="dismiss">Dismiss Selected</option>
                        <option value="revert">Revert to Reviewed</option>
                        <option value="delete">Delete Releases & Resolve</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition">
                        Apply
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-10"></th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Release</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reporter</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reason</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($reportsList as $report)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="report_ids[]" value="{{ $report->id }}" class="report-checkbox rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 dark:bg-gray-700">
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200 font-mono">
                                        #{{ $report->id }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="max-w-md">
                                            @if($report->release)
                                                <a href="{{ url('/details/' . $report->release->guid) }}"
                                                   target="_blank"
                                                   class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 font-medium text-sm break-all">
                                                    {{ Str::limit($report->release->searchname, 80) }}
                                                </a>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    ID: {{ $report->releases_id }} | Size: {{ number_format($report->release->size / 1073741824, 2) }} GB
                                                </div>
                                            @else
                                                <span class="text-red-500 dark:text-red-400 text-sm">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i> Release Deleted
                                                </span>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    Original ID: {{ $report->releases_id }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-gray-200">
                                            @if($report->user)
                                                {{ $report->user->username }}
                                            @else
                                                <span class="text-gray-500">Deleted User</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200">
                                            {{ $report->reason_label }}
                                        </span>
                                        @if($report->description)
                                            <button type="button"
                                                    class="report-description-btn ml-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                                    data-description="{{ htmlspecialchars($report->description, ENT_QUOTES) }}"
                                                    data-reason="{{ $report->reason_label }}"
                                                    data-reporter="{{ $report->user ? $report->user->username : 'Unknown' }}"
                                                    title="View description">
                                                <i class="fas fa-comment-dots"></i>
                                            </button>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @php
                                            $statusColors = [
                                                'pending' => 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200',
                                                'reviewed' => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
                                                'resolved' => 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200',
                                                'dismissed' => 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200',
                                            ];
                                            $statusIcons = [
                                                'pending' => 'fa-clock',
                                                'reviewed' => 'fa-eye',
                                                'resolved' => 'fa-check',
                                                'dismissed' => 'fa-ban',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$report->status] ?? 'bg-gray-100 text-gray-800' }}">
                                            <i class="fas {{ $statusIcons[$report->status] ?? 'fa-question' }} mr-1"></i>
                                            {{ ucfirst($report->status) }}
                                        </span>
                                        @if($report->reviewer && $report->reviewed_at)
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                by {{ $report->reviewer->username }} at {{ $report->reviewed_at->format('M d, Y H:i') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        {{ $report->created_at->format('M d, Y') }}
                                        <div class="text-xs">{{ $report->created_at->format('H:i') }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            @if($report->release && in_array($report->status, ['pending', 'reviewed']))
                                                <!-- Delete Release Button -->
                                                <form method="POST" action="{{ route('admin.release-reports.delete-release', $report->id) }}" class="inline" onsubmit="return confirm('Are you sure you want to DELETE this release? This action cannot be undone.');">
                                                    @csrf
                                                    <button type="submit"
                                                            class="px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm inline-flex items-center"
                                                            title="Delete Release">
                                                        <i class="fas fa-trash mr-1"></i> Delete
                                                    </button>
                                                </form>
                                            @endif

                                            @if($report->status === 'pending')
                                                <!-- Mark as Reviewed Button -->
                                                <form method="POST" action="{{ route('admin.release-reports.update-status', $report->id) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="status" value="reviewed">
                                                    <button type="submit"
                                                            class="px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm inline-flex items-center"
                                                            title="Mark as Reviewed">
                                                        <i class="fas fa-eye mr-1"></i> Reviewed
                                                    </button>
                                                </form>

                                                <!-- Dismiss Button -->
                                                <form method="POST" action="{{ route('admin.release-reports.dismiss', $report->id) }}" class="inline">
                                                    @csrf
                                                    <button type="submit"
                                                            class="px-3 py-1.5 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition text-sm inline-flex items-center"
                                                            title="Dismiss Report">
                                                        <i class="fas fa-ban mr-1"></i> Dismiss
                                                    </button>
                                                </form>
                                            @elseif($report->status === 'reviewed')
                                                <!-- Mark as Resolved Button -->
                                                <form method="POST" action="{{ route('admin.release-reports.update-status', $report->id) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="status" value="resolved">
                                                    <button type="submit"
                                                            class="px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm inline-flex items-center"
                                                            title="Mark as Resolved">
                                                        <i class="fas fa-check mr-1"></i> Resolve
                                                    </button>
                                                </form>

                                                <!-- Dismiss Button -->
                                                <form method="POST" action="{{ route('admin.release-reports.dismiss', $report->id) }}" class="inline">
                                                    @csrf
                                                    <button type="submit"
                                                            class="px-3 py-1.5 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition text-sm inline-flex items-center"
                                                            title="Dismiss Report">
                                                        <i class="fas fa-ban mr-1"></i> Dismiss
                                                    </button>
                                                </form>
                                            @elseif(!$report->release)
                                                <span class="text-xs text-gray-500 dark:text-gray-400">Release already deleted</span>
                                            @elseif(in_array($report->status, ['resolved', 'dismissed']))
                                                <!-- Revert Button for resolved/dismissed reports -->
                                                <button type="button"
                                                        class="revert-report-btn px-3 py-1.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition text-sm inline-flex items-center"
                                                        data-report-id="{{ $report->id }}"
                                                        data-report-status="{{ $report->status }}"
                                                        data-action-url="{{ route('admin.release-reports.revert', $report->id) }}"
                                                        title="Revert to Reviewed for further action">
                                                    <i class="fas fa-undo mr-1"></i> Revert
                                                </button>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ ucfirst($report->status) }}</span>
                                            @else
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ ucfirst($report->status) }}</span>
                                            @endif

                                            @if($report->release)
                                                <a href="{{ url('/details/' . $report->release->guid) }}"
                                                   target="_blank"
                                                   class="px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm inline-flex items-center"
                                                   title="View Release">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $reportsList->appends(['status' => $status])->links() }}
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fas fa-flag text-gray-300 dark:text-gray-600 text-6xl mb-4"></i>
                <h3 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No Reports Found</h3>
                <p class="text-gray-500 dark:text-gray-400">
                    @if($status !== 'all')
                        No {{ $status }} reports at this time.
                        <a href="{{ route('admin.release-reports', ['status' => 'all']) }}" class="text-blue-600 dark:text-blue-400 hover:underline">View all reports</a>
                    @else
                        No release reports have been submitted yet.
                    @endif
                </p>
            </div>
        @endif
    </div>
</div>

<!-- Report Description Modal -->
<div id="reportDescriptionModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <!-- Backdrop -->
    <div class="report-desc-modal-backdrop fixed inset-0 transition-opacity bg-gray-500/75 dark:bg-gray-900/75"></div>

    <!-- Modal panel container -->
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <!-- Modal Content -->
            <div class="relative w-full max-w-lg p-6 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-gray-800 shadow-xl rounded-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    <i class="fas fa-comment-dots text-blue-500 mr-2"></i>Report Details
                </h3>
                <button type="button" class="report-desc-modal-close text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Reason</label>
                    <p id="reportDescReason" class="text-gray-900 dark:text-gray-100 font-medium"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Reported By</label>
                    <p id="reportDescReporter" class="text-gray-900 dark:text-gray-100"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Additional Details</label>
                    <div id="reportDescContent" class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words max-h-64 overflow-y-auto"></div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" class="report-desc-modal-close px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                    Close
                </button>
            </div>
            </div>
        </div>
    </div>
</div>

<!-- Revert Confirmation Modal -->
<div id="revertConfirmModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <!-- Backdrop -->
    <div class="revert-modal-backdrop fixed inset-0 transition-opacity bg-gray-500/75 dark:bg-gray-900/75"></div>

    <!-- Modal panel container -->
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <!-- Modal Content -->
            <div class="relative w-full max-w-md p-6 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-gray-800 shadow-xl rounded-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    <i class="fas fa-undo text-orange-500 mr-2"></i>Confirm Revert
                </h3>
                <button type="button" class="revert-modal-close text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="mb-6">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    Are you sure you want to revert this <span id="revertReportStatus" class="font-semibold text-orange-600 dark:text-orange-400"></span> report back to <span class="font-semibold text-blue-600 dark:text-blue-400">Reviewed</span> status?
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-500">
                    This will allow further action to be taken on the report if an issue was found with the release.
                </p>
            </div>

            <form id="revertConfirmForm" method="POST" action="">
                @csrf
                <div class="flex justify-end gap-3">
                    <button type="button" class="revert-modal-close px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-orange-600 rounded-lg hover:bg-orange-700 transition">
                        <i class="fas fa-undo mr-1"></i> Revert to Reviewed
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>
</div>
@endsection

