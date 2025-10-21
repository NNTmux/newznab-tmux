@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-envelope mr-2"></i>{{ $title }}
                </h1>
                <div class="space-x-2">
                    <form method="POST" action="{{ url('admin/invitations/cleanup') }}" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                            <i class="fas fa-trash mr-2"></i>Cleanup Expired
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Invitations</div>
                    <div class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ $stats['total'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Pending</div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['pending'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Used</div>
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['used'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Expired</div>
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $stats['expired'] }}</div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mt-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Today</div>
                    <div class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $stats['today'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <div class="text-sm text-gray-500 dark:text-gray-400">This Week</div>
                    <div class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $stats['this_week'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                    <div class="text-sm text-gray-500 dark:text-gray-400">This Month</div>
                    <div class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $stats['this_month'] }}</div>
                </div>
            </div>
        </div>

        <!-- Search Filters -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" action="{{ url('admin/invitations') }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="text"
                               id="email"
                               name="email"
                               value="{{ $email }}"
                               placeholder="Filter by email"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="invited_by" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Invited By</label>
                        <input type="text"
                               id="invited_by"
                               name="invited_by"
                               value="{{ $invited_by }}"
                               placeholder="Filter by inviter username"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            @foreach($statusOptions as $key => $label)
                                <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="ob" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Order By</label>
                        <select name="ob" id="ob" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="created_at_desc" {{ $orderBy === 'created_at_desc' ? 'selected' : '' }}>Created (Newest)</option>
                            <option value="created_at_asc" {{ $orderBy === 'created_at_asc' ? 'selected' : '' }}>Created (Oldest)</option>
                            <option value="expires_at_desc" {{ $orderBy === 'expires_at_desc' ? 'selected' : '' }}>Expires (Latest)</option>
                            <option value="expires_at_asc" {{ $orderBy === 'expires_at_asc' ? 'selected' : '' }}>Expires (Soonest)</option>
                            <option value="email_asc" {{ $orderBy === 'email_asc' ? 'selected' : '' }}>Email (A-Z)</option>
                            <option value="email_desc" {{ $orderBy === 'email_desc' ? 'selected' : '' }}>Email (Z-A)</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex space-x-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="{{ url('admin/invitations') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Invitations Table -->
        <div class="overflow-x-auto">
            <form method="POST" action="{{ url('admin/invitations/bulk') }}" id="bulkForm">
                @csrf
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left">
                                <input type="checkbox" id="select_all" class="rounded">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invited By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expires</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($invitations as $invitation)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <input type="checkbox" name="invitation_ids[]" value="{{ $invitation->id }}" class="rounded invitation-checkbox">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    {{ $invitation->email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    {{ optional($invitation->invitedBy)->username ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($invitation->used_at)
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            <i class="fas fa-check-circle mr-1"></i>Used
                                        </span>
                                    @elseif(!$invitation->is_active)
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            <i class="fas fa-ban mr-1"></i>Cancelled
                                        </span>
                                    @elseif($invitation->expires_at && $invitation->expires_at->isPast())
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            <i class="fas fa-clock mr-1"></i>Expired
                                        </span>
                                    @else
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            <i class="fas fa-hourglass-half mr-1"></i>Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $invitation->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $invitation->expires_at ? $invitation->expires_at->format('Y-m-d H:i') : 'Never' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="{{ url('admin/invitations/' . $invitation->id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if(!$invitation->used_at)
                                        <form method="POST" action="{{ url('admin/invitations/' . $invitation->id . '/resend') }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300" title="Resend">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ url('admin/invitations/' . $invitation->id . '/cancel') }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300" title="Cancel" data-confirm="Are you sure you want to cancel this invitation?">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                    No invitations found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <!-- Bulk Actions -->
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <select name="bulk_action" id="bulk_action" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md">
                                <option value="">Bulk Actions</option>
                                <option value="resend">Resend Selected</option>
                                <option value="cancel">Cancel Selected</option>
                                <option value="delete">Delete Selected</option>
                            </select>
                            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900" data-confirm="Are you sure you want to perform this bulk action?">
                                Apply
                            </button>
                        </div>
                        <div>
                            {{ $invitations->links() }}
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Top Inviters Section -->
    @if(count($topInviters) > 0)
    <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fas fa-trophy mr-2"></i>Top Inviters
            </h2>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Invitations</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Successful</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Success Rate</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($topInviters as $index => $user)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-200">
                                    #{{ $index + 1 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    {{ $user['username'] ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $user['email'] ?? '' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    {{ $user['total_invitations'] ?? 0 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400 font-semibold">
                                    {{ $user['successful_invitations'] ?? 0 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    @php
                                        $total = $user['total_invitations'] ?? 0;
                                        $successful = $user['successful_invitations'] ?? 0;
                                        $rate = $total > 0 ? round(($successful / $total) * 100, 1) : 0;
                                    @endphp
                                    <div class="flex items-center">
                                        <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                                            <div class="bg-green-600 dark:bg-green-500 h-2 rounded-full" style="width: {{ $rate }}%"></div>
                                        </div>
                                        <span>{{ $rate }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- Scripts moved to resources/js/csp-safe.js --}}
@endsection

