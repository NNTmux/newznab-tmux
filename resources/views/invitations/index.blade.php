@extends('layouts.main')

@section('content')
<x-page-header :title="$invite_mode ? 'My invitations' : 'Invitations disabled'" eyebrow="Invite and track" :description="$invite_mode ? 'Send invitations and follow their acceptance or expiry status.' : 'User invitations are currently unavailable on this site.'" icon="fas fa-envelope">
    @if($invite_mode)
        <x-slot:stats>
            <span class="workspace-hero__stat"><i class="fas fa-paper-plane" aria-hidden="true"></i>{{ number_format($stats['total'] ?? 0) }} sent</span>
            <span class="workspace-hero__stat"><i class="fas fa-clock" aria-hidden="true"></i>{{ number_format($stats['pending'] ?? 0) }} pending</span>
        </x-slot:stats>
        <x-slot:actions>
            <a href="{{ url('/invitations/create') }}" class="workspace-hero__action"><i class="fas fa-plus" aria-hidden="true"></i>Send invitation</a>
        </x-slot:actions>
    @endif
</x-page-header>

@unless($invite_mode)
<div class="max-w-4xl mx-auto px-4 py-3">
    <div class="bg-warning-50 border border-warning-200 text-warning-800 rounded-lg p-6 shadow-sm dark:bg-warning-900 dark:border-warning-700 dark:text-warning-300">
        <h2 class="text-lg font-semibold mb-2 flex items-center">
            <i aria-hidden="true" class="fa-solid fa-ban mr-2"></i>Invitations Disabled
        </h2>
        <p class="mb-0">User invitations are currently disabled on this site. If you believe this is an error, please contact an administrator.</p>
    </div>
</div>
@else
<div class="px-4 py-3">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm mb-4 dark:bg-gray-800">
        <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700 rounded-t-lg flex justify-between items-center dark:bg-gray-700 dark:border-gray-600">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                <i aria-hidden="true" class="fa-solid fa-envelope mr-2"></i>My Invitations
            </h2>
            <a href="{{ url('/invitations/create') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-accent dark:bg-accent-hover hover:bg-accent-hover dark:hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                <i aria-hidden="true" class="fas fa-plus mr-1"></i> Send New Invitation
            </a>
        </div>
        <div class="p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-accent dark:bg-accent-hover text-white rounded-lg shadow dark:bg-accent-hover">
                    <div class="p-4 text-center">
                        <p class="text-3xl font-bold mb-1">{{ $stats['total'] ?? 0 }}</p>
                        <small class="text-primary-100">Total Sent</small>
                    </div>
                </div>
                <div class="bg-success-600 dark:bg-success-700 text-white rounded-lg shadow dark:bg-success-700">
                    <div class="p-4 text-center">
                        <p class="text-3xl font-bold mb-1">{{ $stats['used'] ?? 0 }}</p>
                        <small class="text-success-100">Accepted</small>
                    </div>
                </div>
                <div class="bg-warning-500 text-white rounded-lg shadow dark:bg-warning-600">
                    <div class="p-4 text-center">
                        <p class="text-3xl font-bold mb-1">{{ $stats['pending'] ?? 0 }}</p>
                        <small class="text-warning-100">Pending</small>
                    </div>
                </div>
                <div class="bg-danger-600 dark:bg-danger-700 text-white rounded-lg shadow dark:bg-danger-700">
                    <div class="p-4 text-center">
                        <p class="text-3xl font-bold mb-1">{{ $stats['expired'] ?? 0 }}</p>
                        <small class="text-danger-100">Expired</small>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
                <nav class="flex flex-wrap -mb-px" aria-label="Tabs">
                    <a href="{{ url('/invitations') }}" class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium {{ empty($status) ? 'border-primary-500 text-link dark:text-primary-400 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:border-gray-600 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <i aria-hidden="true" class="fa-solid fa-list mr-1"></i>All
                    </a>
                    <a href="{{ url('/invitations?status=pending') }}" class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium {{ ($status ?? '') == 'pending' ? 'border-primary-500 text-link dark:text-primary-400 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:border-gray-600 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <i aria-hidden="true" class="fa-regular fa-clock mr-1"></i>Pending
                    </a>
                    <a href="{{ url('/invitations?status=used') }}" class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium {{ ($status ?? '') == 'used' ? 'border-primary-500 text-link dark:text-primary-400 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:border-gray-600 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <i aria-hidden="true" class="fa-solid fa-check mr-1"></i>Accepted
                    </a>
                    <a href="{{ url('/invitations?status=expired') }}" class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium {{ ($status ?? '') == 'expired' ? 'border-primary-500 text-link dark:text-primary-400 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:border-gray-600 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <i aria-hidden="true" class="fa-solid fa-times mr-1"></i>Expired
                    </a>
                </nav>
            </div>

            <!-- Invitations Table / Cards -->
            @forelse($invitations as $invitation)
                @if($loop->first)
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Email</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Sent</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Expires</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Used By</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Used Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @endif
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 dark:text-gray-100"><i aria-hidden="true" class="fa-solid fa-envelope text-gray-400 mr-1"></i>{{ $invitation['email'] }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($invitation['used_at'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200"><i aria-hidden="true" class="fa-solid fa-check mr-1"></i>Accepted</span>
                                    @elseif($invitation['expires_at'] < time())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200"><i aria-hidden="true" class="fa-solid fa-times mr-1"></i>Expired</span>
                                    @elseif(!$invitation['is_active'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"><i aria-hidden="true" class="fa-solid fa-ban mr-1"></i>Cancelled</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200"><i aria-hidden="true" class="fa-regular fa-clock mr-1"></i>Pending</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ date('M j, Y', $invitation['created_at']) }} <small class="text-xs text-gray-500 dark:text-gray-400">{{ date('H:i', $invitation['created_at']) }}</small>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ date('M j, Y', $invitation['expires_at']) }} <small class="text-xs text-gray-500 dark:text-gray-400">{{ date('H:i', $invitation['expires_at']) }}</small>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    @if(isset($invitation['used_by_user']))
                                        <i aria-hidden="true" class="fa-solid fa-user text-gray-400 mr-1"></i>{{ $invitation['used_by_user']['username'] ?? $invitation['used_by_user']['email'] }}
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    @if($invitation['used_at'])
                                        {{ date('M j, Y', $invitation['used_at']) }} <small class="text-xs text-gray-500 dark:text-gray-400">{{ date('H:i', $invitation['used_at']) }}</small>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if(!$invitation['used_at'] && $invitation['expires_at'] > time() && $invitation['is_active'])
                                        <div class="flex items-center space-x-2">
                                            <form method="POST" action="{{ url('/invitations/' . $invitation['id'] . '/resend') }}" class="inline">
                                                @csrf
                                                <button aria-label="Resend" type="submit" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-link bg-white hover:bg-primary-50 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600" title="Resend"><i aria-hidden="true" class="fas fa-paper-plane"></i></button>
                                            </form>
                                            <form method="POST" action="{{ url('/invitations/' . $invitation['id']) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button aria-label="Cancel" type="submit" class="inline-flex items-center px-3 py-1.5 border border-danger-300 rounded text-xs font-medium text-danger-700 bg-white hover:bg-danger-50 dark:bg-gray-700 dark:text-danger-400 dark:border-danger-600 dark:hover:bg-gray-600" title="Cancel" data-confirm="Are you sure you want to cancel this invitation?"><i aria-hidden="true" class="fas fa-times"></i></button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                @if($loop->last)
                        </tbody>
                    </table>
                </div>
                @endif
            @empty
                <x-empty-state
                    icon="fa-solid fa-envelope-open-text"
                    title="No invitations found"
                    message="You haven't sent any invitations yet."
                    :action-url="url('/invitations/create')"
                    action-label="Send Your First Invitation"
                    action-icon="fas fa-plus"
                />
            @endforelse

            @if(count($invitations ?? []) > 0)
            <!-- Mobile Cards -->
            <div class="md:hidden divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($invitations as $invitation)
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                    <i aria-hidden="true" class="fa-solid fa-envelope text-gray-400 mr-1"></i>{{ $invitation['email'] }}
                                </div>
                                <div class="mt-1">
                                    @if($invitation['used_at'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200"><i aria-hidden="true" class="fa-solid fa-check mr-1"></i>Accepted</span>
                                    @elseif($invitation['expires_at'] < time())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200"><i aria-hidden="true" class="fa-solid fa-times mr-1"></i>Expired</span>
                                    @elseif(!$invitation['is_active'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"><i aria-hidden="true" class="fa-solid fa-ban mr-1"></i>Cancelled</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200"><i aria-hidden="true" class="fa-regular fa-clock mr-1"></i>Pending</span>
                                    @endif
                                </div>
                            </div>
                            @if(!$invitation['used_at'] && $invitation['expires_at'] > time() && $invitation['is_active'])
                                <div class="flex gap-1 shrink-0">
                                    <form method="POST" action="{{ url('/invitations/' . $invitation['id'] . '/resend') }}" class="inline">
                                        @csrf
                                        <button aria-label="Resend" type="submit" class="px-2 py-1 border border-primary-300 rounded text-xs text-link bg-white dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600" title="Resend"><i aria-hidden="true" class="fas fa-paper-plane"></i></button>
                                    </form>
                                    <form method="POST" action="{{ url('/invitations/' . $invitation['id']) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button aria-label="Cancel" type="submit" class="px-2 py-1 border border-danger-300 rounded text-xs text-danger-700 bg-white dark:bg-gray-700 dark:text-danger-400 dark:border-danger-600" title="Cancel"><i aria-hidden="true" class="fas fa-times"></i></button>
                                    </form>
                                </div>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 dark:text-gray-400">
                            <div><span class="font-medium">Sent:</span> {{ date('M j, Y', $invitation['created_at']) }}</div>
                            <div><span class="font-medium">Expires:</span> {{ date('M j, Y', $invitation['expires_at']) }}</div>
                            @if(isset($invitation['used_by_user']))
                                <div><span class="font-medium">Used by:</span> {{ $invitation['used_by_user']['username'] ?? $invitation['used_by_user']['email'] }}</div>
                            @endif
                            @if($invitation['used_at'])
                                <div><span class="font-medium">Used:</span> {{ date('M j, Y', $invitation['used_at']) }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @endif

            @if(isset($pagination_links))
                <div class="mt-4">
                    {!! $pagination_links !!}
                </div>
            @endif
        </div>
    </div>
</div>
@endunless
@endsection
