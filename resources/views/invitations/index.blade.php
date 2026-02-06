@extends('layouts.main')

@section('content')
@unless($invite_mode)
<div class="max-w-4xl mx-auto px-4 py-3">
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-6 shadow-sm dark:bg-yellow-900 dark:border-yellow-700 dark:text-yellow-300">
        <h5 class="text-lg font-semibold mb-2 flex items-center">
            <i class="fa fa-ban mr-2"></i>Invitations Disabled
        </h5>
        <p class="mb-0">User invitations are currently disabled on this site. If you believe this is an error, please contact an administrator.</p>
    </div>
</div>
@else
<div class="mb-6">
    <nav class="flex" aria-label="breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="{{ $site['home_link'] }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:text-blue-400 dark:text-gray-400 dark:hover:text-white">Home</a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                    <a href="{{ url('/profile') }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:text-blue-400 dark:text-gray-400 dark:hover:text-white">Profile</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                    <span class="text-gray-500 dark:text-gray-400">My Invitations</span>
                </div>
            </li>
        </ol>
    </nav>
</div>

<div class="px-4 py-3">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm mb-4 dark:bg-gray-800">
        <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700 rounded-t-lg flex justify-between items-center dark:bg-gray-700 dark:border-gray-600">
            <h5 class="text-lg font-semibold text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                <i class="fa fa-envelope mr-2"></i>My Invitations
            </h5>
            <a href="{{ url('/invitations/create') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 dark:bg-blue-700 hover:bg-blue-700 dark:hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-plus mr-1"></i> Send New Invitation
            </a>
        </div>
        <div class="p-6">
            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 dark:bg-green-900 dark:border-green-700 dark:text-green-200" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 dark:bg-red-900 dark:border-red-700 dark:text-red-200" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-600 dark:bg-blue-700 text-white rounded-lg shadow dark:bg-blue-700">
                    <div class="p-4 text-center">
                        <h4 class="text-3xl font-bold mb-1">{{ $stats['total'] ?? 0 }}</h4>
                        <small class="text-blue-100">Total Sent</small>
                    </div>
                </div>
                <div class="bg-green-600 dark:bg-green-700 text-white rounded-lg shadow dark:bg-green-700">
                    <div class="p-4 text-center">
                        <h4 class="text-3xl font-bold mb-1">{{ $stats['used'] ?? 0 }}</h4>
                        <small class="text-green-100">Accepted</small>
                    </div>
                </div>
                <div class="bg-yellow-500 text-white rounded-lg shadow dark:bg-yellow-600">
                    <div class="p-4 text-center">
                        <h4 class="text-3xl font-bold mb-1">{{ $stats['pending'] ?? 0 }}</h4>
                        <small class="text-yellow-100">Pending</small>
                    </div>
                </div>
                <div class="bg-red-600 dark:bg-red-700 text-white rounded-lg shadow dark:bg-red-700">
                    <div class="p-4 text-center">
                        <h4 class="text-3xl font-bold mb-1">{{ $stats['expired'] ?? 0 }}</h4>
                        <small class="text-red-100">Expired</small>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
                <nav class="flex flex-wrap -mb-px" aria-label="Tabs">
                    <a href="{{ url('/invitations') }}" class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium {{ empty($status) ? 'border-blue-500 text-blue-600 dark:text-blue-400 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:border-gray-600 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <i class="fa fa-list mr-1"></i>All
                    </a>
                    <a href="{{ url('/invitations?status=pending') }}" class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium {{ ($status ?? '') == 'pending' ? 'border-blue-500 text-blue-600 dark:text-blue-400 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:border-gray-600 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <i class="fa fa-clock-o mr-1"></i>Pending
                    </a>
                    <a href="{{ url('/invitations?status=used') }}" class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium {{ ($status ?? '') == 'used' ? 'border-blue-500 text-blue-600 dark:text-blue-400 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:border-gray-600 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <i class="fa fa-check mr-1"></i>Accepted
                    </a>
                    <a href="{{ url('/invitations?status=expired') }}" class="inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium {{ ($status ?? '') == 'expired' ? 'border-blue-500 text-blue-600 dark:text-blue-400 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:border-gray-600 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <i class="fa fa-times mr-1"></i>Expired
                    </a>
                </nav>
            </div>

            <!-- Invitations Table -->
            @forelse($invitations as $invitation)
                @if($loop->first)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                    <i class="fa fa-envelope mr-1"></i>Email
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                    <i class="fa fa-info-circle mr-1"></i>Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                    <i class="fa fa-calendar mr-1"></i>Sent Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                    <i class="fa fa-clock-o mr-1"></i>Expires
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                    <i class="fa fa-user mr-1"></i>Used By
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                    <i class="fa fa-check mr-1"></i>Used Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">
                                    <i class="fa fa-cogs mr-1"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                @endif
                            <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <i class="fa fa-envelope text-gray-400 mr-2"></i>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ $invitation['email'] }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($invitation['used_at'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            <i class="fa fa-check mr-1"></i>Accepted
                                        </span>
                                    @elseif($invitation['expires_at'] < time())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            <i class="fa fa-times mr-1"></i>Expired
                                        </span>
                                    @elseif(!$invitation['is_active'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 dark:bg-gray-700 dark:text-gray-300">
                                            <i class="fa fa-ban mr-1"></i>Cancelled
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            <i class="fa fa-clock-o mr-1"></i>Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <i class="fa fa-calendar text-gray-400 mr-2"></i>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ date('M j, Y', $invitation['created_at']) }}</span>
                                        <small class="text-xs text-gray-500 ml-1 dark:text-gray-400">{{ date('H:i', $invitation['created_at']) }}</small>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <i class="fa fa-clock-o text-gray-400 mr-2"></i>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ date('M j, Y', $invitation['expires_at']) }}</span>
                                        <small class="text-xs text-gray-500 ml-1 dark:text-gray-400">{{ date('H:i', $invitation['expires_at']) }}</small>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if(isset($invitation['used_by_user']))
                                        <div class="flex items-center">
                                            <i class="fa fa-user text-gray-400 mr-2"></i>
                                            <span class="text-sm text-gray-900 dark:text-gray-100">{{ $invitation['used_by_user']['username'] ?? $invitation['used_by_user']['email'] }}</span>
                                        </div>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($invitation['used_at'])
                                        <div class="flex items-center">
                                            <i class="fa fa-check text-gray-400 mr-2"></i>
                                            <span class="text-sm text-gray-900 dark:text-gray-100">{{ date('M j, Y', $invitation['used_at']) }}</span>
                                            <small class="text-xs text-gray-500 ml-1 dark:text-gray-400">{{ date('H:i', $invitation['used_at']) }}</small>
                                        </div>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if(!$invitation['used_at'] && $invitation['expires_at'] > time() && $invitation['is_active'])
                                        <div class="flex items-center space-x-2">
                                            <form method="POST" action="{{ url('/invitations/' . $invitation['id'] . '/resend') }}" class="inline">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600" title="Resend Invitation">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ url('/invitations/' . $invitation['id']) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-red-300 rounded text-xs font-medium text-red-700 bg-white dark:bg-gray-800 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-gray-700 dark:text-red-400 dark:border-red-600 dark:hover:bg-gray-600" title="Cancel Invitation"
                                                        data-confirm="Are you sure you want to cancel this invitation?">
                                                    <i class="fas fa-times"></i>
                                                </button>
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

                @if(isset($pagination_links))
                    <div class="mt-4">
                        {!! $pagination_links !!}
                    </div>
                @endif
                @endif
            @empty
                <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-6 text-center dark:bg-blue-900 dark:border-blue-700 dark:text-blue-300">
                    <i class="fa fa-info-circle text-4xl mb-3"></i>
                    <h5 class="text-lg font-semibold mb-2">No Invitations Found</h5>
                    <p class="mb-4">You haven't sent any invitations yet.</p>
                    <a href="{{ url('/invitations/create') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 dark:bg-blue-700 hover:bg-blue-700 dark:hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-plus mr-1"></i> Send Your First Invitation
                    </a>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endunless
@endsection

