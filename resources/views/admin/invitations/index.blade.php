@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-envelope">
        <x-slot:actions>
            <form method="POST" action="{{ url('admin/invitations/cleanup') }}" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 bg-danger-600 text-white rounded-lg hover:bg-danger-700">
                    <i aria-hidden="true" class="fas fa-trash mr-2"></i>Cleanup Expired
                </button>
            </form>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Statistics Cards -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Invitations</div>
                    <div class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ $stats['total'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Pending</div>
                    <div class="text-2xl font-bold text-link dark:text-primary-400">{{ $stats['pending'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Used</div>
                    <div class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $stats['used'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Expired</div>
                    <div class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $stats['expired'] }}</div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mt-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Today</div>
                    <div class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $stats['today'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-500 dark:text-gray-400">This Week</div>
                    <div class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $stats['this_week'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
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
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="invited_by" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Invited By</label>
                        <input type="text"
                               id="invited_by"
                               name="invited_by"
                               value="{{ $invited_by }}"
                               placeholder="Filter by inviter username"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-primary-500 focus:border-primary-500">
                            @foreach($statusOptions as $key => $label)
                                <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="ob" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Order By</label>
                        <select name="ob" id="ob" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-primary-500 focus:border-primary-500">
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
                    <button type="submit" class="px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-hover">
                        <i aria-hidden="true" class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="{{ url('admin/invitations') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        <i aria-hidden="true" class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Invitations Table -->
        <div class="overflow-x-auto">
            {{-- Standalone bulk form: row actions below are forms too, and forms cannot be nested. --}}
            <form method="POST" action="{{ url('admin/invitations/bulk') }}" id="bulkForm">
                @csrf
            </form>
            <div x-data="adminInvitations">
                <x-admin.data-table>
                    <x-slot:head>
                        <x-admin.th>
                            <input aria-label="Select all invitations" type="checkbox" id="select_all" class="rounded" x-ref="selectAll" @change="toggleAll()">
                        </x-admin.th>
                        <x-admin.th>Email</x-admin.th>
                        <x-admin.th>Invited By</x-admin.th>
                        <x-admin.th>Status</x-admin.th>
                        <x-admin.th>Created</x-admin.th>
                        <x-admin.th>Expires</x-admin.th>
                        <x-admin.th>Actions</x-admin.th>
                    </x-slot:head>

                        @forelse($invitations as $invitation)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <input aria-label="Select invitation for {{ $invitation->email }}" type="checkbox" name="invitation_ids[]" value="{{ $invitation->id }}" form="bulkForm" class="rounded invitation-checkbox" @change="onCheckboxChange()">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    {{ $invitation->email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    {{ optional($invitation->invitedBy)->username ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($invitation->used_at)
                                        <x-admin.badge tone="success" icon="fas fa-check-circle">Used</x-admin.badge>
                                    @elseif(!$invitation->is_active)
                                        <x-admin.badge tone="warning" icon="fas fa-ban">Cancelled</x-admin.badge>
                                    @elseif($invitation->expires_at && $invitation->expires_at->isPast())
                                        <x-admin.badge tone="danger" icon="fas fa-clock">Expired</x-admin.badge>
                                    @else
                                        <x-admin.badge tone="primary" icon="fas fa-hourglass-half">Pending</x-admin.badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDateTime($invitation->created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $invitation->expires_at ? formatDateTime($invitation->expires_at) : 'Never' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a aria-label="View" href="{{ url('admin/invitations/' . $invitation->id) }}" class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300">
                                        <i aria-hidden="true" class="fas fa-eye"></i>
                                    </a>
                                    @if(!$invitation->used_at)
                                        <form method="POST" action="{{ url('admin/invitations/' . $invitation->id . '/resend') }}" class="inline">
                                            @csrf
                                            <button aria-label="Resend" type="submit" class="text-success-600 dark:text-success-400 hover:text-success-900 dark:hover:text-success-300" title="Resend">
                                                <i aria-hidden="true" class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ url('admin/invitations/' . $invitation->id . '/cancel') }}" class="inline"
                                              x-data="confirmForm"
                                              data-message="Are you sure you want to cancel this invitation?"
                                              data-title="Cancel Invitation"
                                              data-type="danger"
                                              data-confirm-text="Cancel Invitation"
                                              @submit.prevent="submit()">
                                            @csrf
                                            <button aria-label="Cancel" type="submit" class="text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300" title="Cancel">
                                                <i aria-hidden="true" class="fas fa-ban"></i>
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
                </x-admin.data-table>

                <!-- Bulk Actions -->
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <select name="bulk_action" id="bulk_action" form="bulkForm" aria-label="Bulk action" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md">
                                <option value="">Bulk Actions</option>
                                <option value="resend">Resend Selected</option>
                                <option value="cancel">Cancel Selected</option>
                                <option value="delete">Delete Selected</option>
                            </select>
                            <button type="submit" form="bulkForm" class="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900" data-confirm="Are you sure you want to perform this bulk action?" data-confirm-type="warning" data-confirm-text="Apply">
                                Apply
                            </button>
                        </div>
                        <div>
                            {{ $invitations->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-admin.card>

    <!-- Top Inviters Section -->
    @if(count($topInviters) > 0)
    <x-admin.card class="mt-8">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i aria-hidden="true" class="fas fa-trophy mr-2"></i>Top Inviters
            </h2>
        </div>
        <div class="p-6">
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th>Rank</x-admin.th>
                    <x-admin.th>Username</x-admin.th>
                    <x-admin.th>Email</x-admin.th>
                    <x-admin.th>Total Invitations</x-admin.th>
                    <x-admin.th>Successful</x-admin.th>
                    <x-admin.th>Success Rate</x-admin.th>
                </x-slot:head>

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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-success-600 dark:text-success-400 font-semibold">
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
                                            <div class="bg-success-600 dark:bg-success-500 h-2 rounded-full" style="width: {{ $rate }}%"></div>
                                        </div>
                                        <span>{{ $rate }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
            </x-admin.data-table>
        </div>
    </x-admin.card>
    @endif
</div>

@endsection

